<?php
/*
 * Copyright © Ghost Unicorns snc. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace GhostUnicorns\CmsFallback\Model;

use Exception;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\BlockRepository;
use Magento\Cms\Model\ResourceModel\Block\Collection;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\View\Design\Theme\ThemeList;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\Template\File\Resolver;
use Magento\Framework\View\Layout\File\Collector\Aggregated;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\Theme;

class Import
{
    /**
     * @var Aggregated
     */
    private $aggregated;

    /**
     * @var ThemeList
     */
    private $themeList;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ThemeProviderInterface
     */
    private $themeProvider;

    /**
     * @var Resolver
     */
    private $resolver;

    /**
     * @var BlockCollectionFactory
     */
    private $blockCollectionFactory;

    /**
     * @var BlockRepository
     */
    private $blockRepository;

    /**
     * @var BlockFactory
     */
    private $blockFactory;

    /**
     * @var File
     */
    private $ioFile;

    /**
     * @param ThemeList $themeList
     * @param Aggregated $aggregated
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ThemeProviderInterface $themeProvider
     * @param BlockFactory $blockFactory
     * @param BlockCollectionFactory $blockCollectionFactory
     * @param Resolver $resolver
     * @param BlockRepository $blockRepository
     * @param File $ioFile
     */
    public function __construct(
        ThemeList              $themeList,
        Aggregated             $aggregated,
        StoreManagerInterface  $storeManager,
        ScopeConfigInterface   $scopeConfig,
        ThemeProviderInterface $themeProvider,
        BlockFactory           $blockFactory,
        BlockCollectionFactory $blockCollectionFactory,
        Resolver               $resolver,
        BlockRepository        $blockRepository,
        File                   $ioFile
    ) {
        $this->themeList = $themeList;
        $this->aggregated = $aggregated;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->blockFactory = $blockFactory;
        $this->themeProvider = $themeProvider;
        $this->blockCollectionFactory = $blockCollectionFactory;
        $this->resolver = $resolver;
        $this->blockRepository = $blockRepository;
        $this->ioFile = $ioFile;
    }

    /**
     * @param string $storeViewCode
     * @return array
     * @throws LocalizedException
     */
    public function getFallbackTemplates(string $storeViewCode): array
    {
        $themes = $this->getThemes($storeViewCode);
        $cmsBlocks = [];

        foreach ($themes as $theme) {
            $layouts = $this->aggregated->getFiles($theme, '*.xml');
            foreach ($layouts as $layout) {
                foreach ($this->getCmsBlockFromFallbackXml($layout->getFilename()) as $value) {
                    $cmsBlocks[] = $value;
                }
            }
        }
        return $cmsBlocks;
    }

    /**
     * @param string $storeViewCode
     * @return Theme[]
     */
    private function getThemes(string $storeViewCode): array
    {
        $theme = $this->getTheme($storeViewCode);

        $this->themeList->addConstraint(
            ThemeList::CONSTRAINT_AREA,
            'frontend'
        );
        $this->themeList->addConstraint(
            ThemeList::CONSTRAINT_VENDOR,
            strstr($theme->getCode(), '/', true)
        );
        $this->themeList->addConstraint(
            ThemeList::CONSTRAINT_THEME_NAME,
            substr(strstr($theme->getCode(), '/', false), 1)
        );

        return $this->themeList->getItems();
    }

    /**
     * @param string $storeViewCode
     * @return ThemeInterface
     */
    private function getTheme(string $storeViewCode): ThemeInterface
    {
        $themeId = $this->scopeConfig->getValue(
            DesignInterface::XML_PATH_THEME_ID,
            ScopeInterface::SCOPE_STORE,
            $storeViewCode
        );

        return $this->themeProvider->getThemeById($themeId);
    }

    /**
     * @param $filename
     * @return array
     * @throws LocalizedException
     */
    private function getCmsBlockFromFallbackXml($filename): array
    {
        $cmsBlocks = [];
        $xml = simplexml_load_file($filename);
        $blocks = $xml->xpath('//argument[@name="fallback_template"]') ?: [];

        if (count($blocks)) {
            $identifiers = $blocks[0]
                ->xpath("parent::*")[0]
                ->xpath('//argument[@name="block_id"]') ?: [];

            foreach ($blocks as $key => $el) {
                try {
                    $template = (string)$el[0];
                    $identifier = (string)$identifiers[$key][0];

                    if ($template && $identifier) {
                        $cmsBlocks[] = [
                            "template" => $template,
                            "identifier" => $identifier
                        ];
                    } else {
                        throw new LocalizedException(__('Invalid block key: %1 val: %2', $key, $el));
                    }
                } catch (Exception $e) {
                    throw new LocalizedException(__('Template: %1', $template));
                }
            }
        }
        return $cmsBlocks;
    }

    /**
     * @param string $storeViewCode
     * @param array $fallbackTemplate
     * @param bool $force
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function importFallbackTemplate(string $storeViewCode, array $fallbackTemplate, bool $force)
    {
        $templateFileName = trim($fallbackTemplate['template']);
        $templateIdentifier = trim($fallbackTemplate['identifier']);

        $content = $this->getFallbackTemplateContent($storeViewCode, $templateFileName);

        if ($content) {
            $this->createBlockForSingleStore(
                $storeViewCode,
                $content,
                $templateIdentifier,
                $templateIdentifier,
                $force
            );
        }
    }

    /**
     * @param string $storeViewCode
     * @param string $templateFileName
     * @return string
     * @throws LocalizedException
     */
    private function getFallbackTemplateContent(string $storeViewCode, string $templateFileName): string
    {
        $theme = $this->getTheme($storeViewCode);

        $file = $this->resolver->getTemplateFileName($templateFileName, [
            'module' => '',
            'area' => 'frontend',
            'theme' => $theme->getCode()
        ]);

        if (!$this->ioFile->fileExists($file)) {
            throw new LocalizedException(__('Template file not found: %1', $templateFileName));
        }

        return $this->ioFile->read($file);
    }

    /**
     * @param string $storeViewCode
     * @param string $content
     * @param string $title
     * @param string $identifier
     * @param bool $force
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createBlockForSingleStore(
        string $storeViewCode,
        string $content,
        string $title,
        string $identifier,
        bool $force
    ): void {
        $this->storeManager->setCurrentStore($storeViewCode);

        $store = $this->storeManager->getStore();

        $blockCollection = $this->blockCollectionFactory->create();
        $blockCollection
            ->addStoreFilter($storeViewCode)
            ->addFieldToSelect('*')
            ->addFieldToFilter('identifier', $identifier);

        $block = [
            'title' => $title,
            'identifier' => $identifier,
            'content' => $content,
            'is_active' => true,
            'stores' => [$store->getId()]
        ];

        if ($force) {
            try {
                $this->blockRepository->deleteById($identifier);
            } catch (CouldNotDeleteException $e) {
                unset($e);
            }
        }
        $this->blockRepository->save(
            $this->blockFactory->create()->setData($block)
        );
    }
}
