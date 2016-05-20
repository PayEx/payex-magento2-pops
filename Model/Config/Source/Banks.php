<?php

namespace PayEx\Payments\Model\Config\Source;

class Banks implements \Magento\Framework\Option\ArrayInterface
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
            ['value' => 'SAMPO', 'label' => __('Sampo')],
            ['value' => 'AKTIA', 'label' => __('Aktia, Säästöpankki')],
            ['value' => 'OP', 'label' => __('Osuuspanki, Pohjola, Oko')],
            ['value' => 'NB:FI', 'label' => __('Nordea Bank Finland')],
            ['value' => 'SHB:FI', 'label' => __('SHB:FI')],
            ['value' => 'SPANKKI', 'label' => __('SPANKKI')],
            ['value' => 'TAPIOLA', 'label' => __('TAPIOLA')],
            ['value' => 'AALAND', 'label' => __('Ålandsbanken')]
        ];
    }
}
