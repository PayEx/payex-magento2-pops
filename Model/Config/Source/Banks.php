<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Banks implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            // Sweden (SEK)
            ['value' => 'NB', 'label' => __('Nordea Bank')],
            ['value' => 'FSPA', 'label' => __('Swedbank')],
            ['value' => 'SEB', 'label' => __('Svenska Enskilda Bank')],
            ['value' => 'SHB', 'label' => __('Handelsbanken')],
            // Denmark (DKK)
            ['value' => 'NB:DK', 'label' => __('Nordea Bank DK')],
            ['value' => 'DDB', 'label' => __('Den Danske Bank')],
            // Norway (NOK)
            ['value' => 'BAX', 'label' => __('BankAxess')],
            // Finland (EUR)
            ['value' => 'SAMPO', 'label' => __('Danske Bank')],
            ['value' => 'AKTIA', 'label' => __('Aktia')],
            ['value' => 'OP', 'label' => __('OP')],
            ['value' => 'OMASP', 'label' => __('Oma Säästöpankki')],
            ['value' => 'NB:FI', 'label' => __('Nordea Finland')],
            ['value' => 'SHB:FI', 'label' => __('Handelsbanken Finland')],
            ['value' => 'SPANKKI', 'label' => __('S-Pankki')],
            ['value' => 'TAPIOLA', 'label' => __('TAPIOLA')],
            ['value' => 'AALAND', 'label' => __('Ålandsbanken')],
            ['value' => 'POP', 'label' => __('POP Pankki')],
            ['value' => 'SP', 'label' => __('Säästöpankki')]
        ];
    }
}
