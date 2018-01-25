<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class CheckoutMethod implements ArrayInterface
{
    const METHOD_REDIRECT = 'redirect';
    const METHOD_DIRECT = 'direct';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::METHOD_REDIRECT, 'label' => __('Redirect')],
            ['value' => self::METHOD_DIRECT, 'label' => __('Direct')],
        ];
    }
}
