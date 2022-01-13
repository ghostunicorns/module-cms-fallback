<?php
/*
 * Copyright © Ghost Unicorns snc. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace GhostUnicorns\CmsFallback\Plugin\View\Element;

use Exception;
use GhostUnicorns\CmsFallback\Model\Config;
use Magento\Cms\Block\Block\Interceptor;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class AbstractBlockPlugin extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var FilterProvider
     */
    private $filterProvider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Template\Context $context
     * @param Config $config
     * @param FilterProvider $filterProvider
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Template\Context      $context,
        Config                $config,
        FilterProvider        $filterProvider,
        StoreManagerInterface $storeManager,
        array                 $data = []
    ) {
        $this->config = $config;
        $this->filterProvider = $filterProvider;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function afterToHtml(AbstractBlock $subject, $result): string
    {
        if (!$this->config->isEnabled() || $result) {
            return (string)$result;
        }

        if ($this->config->isPlaceholderNameMode()) {
            return $this->getPlaceholderResult($subject);
        }

        if ($this->config->isFallbackTemplateMode()) {
            try {
                return $this->getTemplateResult($subject);
            } catch (Exception $e) {
                $this->_logger->warning($e->getMessage());
            }
        }

        if ($this->config->isFallbackTemplateThenPlaceholderMode()) {
            try {
                return $this->getTemplateResult($subject);
            } catch (Exception $e) {
                if ($subject->getData('type') === Interceptor::class) {
                    $result = $this->getPlaceholderResult($subject);
                }
            }
        }
        return (string)$result;
    }

    /**
     * @param AbstractBlock $subject
     * @return string
     */
    private function getPlaceholderResult(AbstractBlock $subject): string
    {
        $result = '';
        try {
            $storeName = $this->storeManager->getStore()->getName();
            $result = '<div class="cms-fallback"> ' .
                'CMS Block missing: <code>' . $subject->getNameInLayout() . '</code> ' .
                'Storeview: <code>' . $storeName . '</code> ' .
                '</div>';
        } catch (Exception $e) {
            $this->_logger->warning($e->getMessage());
        }
        return $result;
    }

    /**
     * @param AbstractBlock $subject
     * @return string
     * @throws Exception
     */
    private function getTemplateResult(AbstractBlock $subject): string
    {
        $result = '';
        $templateFile = $this->getTemplateFile($subject->getData('fallback_template'));
        if ($templateFile) {
            try {
                $content = $this->fetchView($templateFile);
                $result = $this->filterProvider->getBlockFilter()->filter($content);
            } catch (Exception $e) {
                $this->_logger->warning($e->getMessage());
            }
        } else {
            throw new LocalizedException(__("Template not found"));
        }
        return $result;
    }
}
