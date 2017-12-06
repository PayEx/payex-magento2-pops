<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Culture implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'en-US', 'label' => __('English')],
            ['value' => 'sv-SE', 'label' => __('Swedish')],
            ['value' => 'nb-NO', 'label' => __('Norway')],
        ];
    }
}
