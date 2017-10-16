<?php

namespace PayEx\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Checkout\Model\Session;

class SsnConfigProvider implements ConfigProviderInterface
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
     * @var \Magento\Checkout\Model\Session
     */
    private $session;

    /**
     * Constructor
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Session $session
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Session $session
    ) {
    
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return [
            'payexSSN' => [
                'isEnabled' => (bool)$this->scopeConfig->getValue(
                    'payment/payex_financing/checkout_field',
                    ScopeInterface::SCOPE_STORE,
                    $this->storeManager->getStore()->getCode()
                ),
                'appliedSSN' => $this->session->getPayexSSN()
            ]
        ];
    }
}
