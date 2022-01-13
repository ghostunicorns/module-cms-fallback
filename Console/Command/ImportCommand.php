<?php
/*
 * Copyright © Ghost Unicorns snc. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace GhostUnicorns\CmsFallback\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use GhostUnicorns\CmsFallback\Model\Import;
use \Magento\Framework\App\State;

class ImportCommand extends Command
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Import
     */
    private $import;

    public function __construct(
        State $appState,
        StoreManagerInterface $storeManager,
        Import $import
    ) {
        $this->appState = $appState;
        $this->storeManager = $storeManager;
        $this->import = $import;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('cms-fallback:import')
            ->setDescription('Import all missing cms block from filesystem to cms')
            ->addOption(
                'storeview',
                's',
                InputOption::VALUE_REQUIRED,
                'Specify storeview code'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Force to delete block if exists',
                false
            );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$storeViewCode = $input->getOption('storeview')) {
            $output->writeln('<error>The "--storeview" option is mandatory.</error>');
            return Cli::RETURN_FAILURE;
        }
        $force = !($input->getOption('force') === false);

        // For store exist verification
        $this->storeManager->getStore($storeViewCode);

        $this->appState->setAreaCode('frontend');

        /** @var array $fallbackTemplates */
        try {
            $fallbackTemplates = $this->import->getFallbackTemplates($storeViewCode);
        } catch (\Exception $e) {
            $output->writeln('<error>Error in layout</error>');
            $output->writeln($e->getMessage());
            return Cli::RETURN_FAILURE;
        }

        $elementCount = count($fallbackTemplates);

        if ($elementCount > 0) {
            $output->writeln('<info>Importing CMS Blocks...</info>');

            $progressBar = new ProgressBar($output, $elementCount);
            $progressBar->start();

            /** @var array $template */
            foreach ($fallbackTemplates as $fallbackTemplate) {
                $output->writeln('');
                try {
                    $this->import->importFallbackTemplate($storeViewCode, $fallbackTemplate, $force);
                    $output->writeln('<info>Successfully imported.</info>');
                    $output->writeln('<info>Storeview ' . $storeViewCode . '</info>');
                    $output->writeln('<info>Identifier ' . $fallbackTemplate['identifier'] . '</info>');
                    $output->writeln('<info>Template ' . $fallbackTemplate['template'] . '</info>');
                } catch (\Exception $e) {
                    $output->writeln('<comment>Storeview: ' . $storeViewCode . '</comment>');
                    $output->writeln('<comment>Identifier: ' . trim($fallbackTemplate['identifier']) . '</comment>');
                    $output->writeln('<comment>Template: ' . trim($fallbackTemplate['template']) . '</comment>');
                    $output->writeln('<comment>Skipped: ' . $e->getMessage() . '</comment>');
                }

                $progressBar->advance();
            }

            $progressBar->finish();

            $output->writeln('');
            $output->writeln('<info>CMS Block creation completed</info>');
        } else {
            $output->writeln('<info>No CMS Blocks to import</info>');
        }

        return 0;
    }
}
