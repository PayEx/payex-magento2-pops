<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Language implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('Detect automatically')],
            ['value' => 'en-US', 'label' => __('English')],
            ['value' => 'sv-SE', 'label' => __('Swedish')],
            ['value' => 'nb-NO', 'label' => __('Norway')],
            ['value' => 'da-DK', 'label' => __('Danish')],
            ['value' => 'es-ES', 'label' => __('Spanish')],
            ['value' => 'de-DE', 'label' => __('German')],
            ['value' => 'fi-FI', 'label' => __('Finnish')],
            ['value' => 'fr-FR', 'label' => __('French')],
            ['value' => 'pl-PL', 'label' => __('Polish')],
            ['value' => 'cs-CZ', 'label' => __('Czech')],
            ['value' => 'hu-HU', 'label' => __('Hungarian')]
        ];
    }
}
