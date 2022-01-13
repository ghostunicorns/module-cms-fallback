<?php
/*
 * Copyright © Ghost Unicorns snc. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace GhostUnicorns\CmsFallback\Model;

use GhostUnicorns\CmsFallback\Model\Config\Source\Mode;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    /** @var string */
    const XML_PATH_GENERAL_ENABLED = 'ghostunicorns_cmsfallback/general/enabled';

    /** @var string */
    const XML_PATH_GENERAL_MODE = 'ghostunicorns_cmsfallback/general/mode';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->scopeConfig = $scopeConfigInterface;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_GENERAL_ENABLED);
    }

    /**
     * @return integer
     */
    public function getMode(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_GENERAL_MODE);
    }

    /**
     * @return bool
     */
    public function isPlaceholderNameMode(): bool
    {
        return $this->getMode() === Mode::PLACEHOLDER_NAME;
    }

    /**
     * @return bool
     */
    public function isFallbackTemplateMode(): bool
    {
        return $this->getMode() === Mode::FALLBACK_TEMPLATE;
    }

    /**
     * @return bool
     */
    public function isFallbackTemplateThenPlaceholderMode(): bool
    {
        return $this->getMode() === Mode::FALLBACK_TEMPLATE_THEN_PLACEHOLDER_NAME;
    }
}
