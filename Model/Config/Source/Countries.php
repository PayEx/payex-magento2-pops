<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Countries implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'SE', 'label' => __('Sweden')],
            ['value' => 'NO', 'label' => __('Norway')],
            ['value' => 'FI', 'label' => __('Finland')],
        ];
    }
}
