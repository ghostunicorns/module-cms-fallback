<?php
/*
 * Copyright © Ghost Unicorns snc. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace GhostUnicorns\CmsFallback\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    const PLACEHOLDER_NAME = 1;
    const FALLBACK_TEMPLATE = 2;
    const FALLBACK_TEMPLATE_THEN_PLACEHOLDER_NAME = 3;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::PLACEHOLDER_NAME,
                'label' => __('Placeholder Name')
            ],
            [
                'value' => self::FALLBACK_TEMPLATE,
                'label' => __('Fallback Template')
            ],
            [
                'value' => self::FALLBACK_TEMPLATE_THEN_PLACEHOLDER_NAME,
                'label' => __('Fallback Template then Placeholder Name')
            ]
        ];
    }
}
