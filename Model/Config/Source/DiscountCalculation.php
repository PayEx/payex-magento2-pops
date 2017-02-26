<?php

namespace PayEx\Payments\Model\Config\Source;

class DiscountCalculation implements \Magento\Framework\Option\ArrayInterface
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
