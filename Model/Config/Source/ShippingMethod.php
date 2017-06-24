<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Shipping\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ShippingMethod implements ArrayInterface
{
    /**
     * @var \Magento\Shipping\Model\Config
     */
    private $shippingConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Constructor
     * @param Config $shippingConfig
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Config $shippingConfig,
        ScopeConfigInterface $scopeConfig
    ) {
    
        $this->shippingConfig = $shippingConfig;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $methods = [
            [
                'value' => '',
                'label' => __('--Please Select--')
            ]
        ];

        $activeCarriers = $this->shippingConfig->getActiveCarriers();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $options = [];

            $carrierTitle = sprintf('Carrier "%s"', $carrierCode);
            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode . '_' . $methodCode;
                    $options[] = ['value' => $code, 'label' => $method];
                }
                $carrierTitle = $this->scopeConfig->getValue(
                    'carriers/' . $carrierCode . '/title',
                    ScopeInterface::SCOPE_STORE
                );
            }

            $methods[] = ['value' => $options, 'label' => $carrierTitle];
        }

        return $methods;
    }
}
