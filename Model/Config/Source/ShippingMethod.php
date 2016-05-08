<?php

namespace PayEx\Payments\Model\Config\Source;

class ShippingMethod implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Magento\Shipping\Model\Config
     */
    protected $shippingConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Constructor
     * @param \Magento\Shipping\Model\Config $shippingConfig
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Shipping\Model\Config $shippingConfig,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
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
                $carrierTitle = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/title', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            }

            $methods[] = ['value' => $options, 'label' => $carrierTitle];
        }

        return $methods;
    }
}

