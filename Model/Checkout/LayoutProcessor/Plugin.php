<?php

namespace PayEx\Payments\Model\Checkout\LayoutProcessor;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Plugin
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Constructor
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
    
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Process js Layout of block
     * @param \Magento\Checkout\Block\Checkout\LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */

    public function beforeProcess(
        \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        $jsLayout
    ) {
    
        $configuration = &$jsLayout['components']['checkout']['children']['steps']['children']['billing-step']
            ['children']['payment']['children']['renders']['children'];
        if (!isset($configuration)) {
            return [$jsLayout];
        }

        foreach ($configuration as $paymentGroup => &$groupConfig) {
            foreach ($groupConfig['methods'] as $paymentCode => &$paymentComponent) {
                if (empty($paymentComponent['isBillingAddressRequired'])) {
                    continue;
                }

                if (strpos($paymentCode, 'payex') !== false) {
                    // Check is billing address component enabled
                    $is_required = (bool)$this->scopeConfig->getValue(
                        'payment/' . $paymentCode . '/billing_address_required',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                        $this->storeManager->getStore()->getCode()
                    );

                    if (!$is_required) {
                        unset($paymentComponent['isBillingAddressRequired']);
                    }
                }
            }
        }
        return [$jsLayout];
    }
}
