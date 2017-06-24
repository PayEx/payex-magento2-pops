<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class DiscountCalculation implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'classic', 'label' => __('Classic')],
            ['value' => 'advanced', 'label' => __('Advanced (experimental)')]
        ];
    }
}
