<?php

namespace PayEx\Payments\Model\Config\Source;

class Type implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'PX', 'label' => __('Payment Menu')],
            ['value' => 'CREDITCARD', 'label' => __('Credit Card')],
            ['value' => 'INVOICE', 'label' => __('Invoice (Ledger Service)')],
            ['value' => 'DIRECTDEBIT', 'label' => __('Direct Debit')],
            ['value' => 'PAYPAL', 'label' => __('PayPal')]

        ];
    }
}
