<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Operation implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'AUTHORIZATION', 'label' => __('Authorize')],
            ['value' => 'SALE', 'label' => __('Sale')]
        ];
    }
}
