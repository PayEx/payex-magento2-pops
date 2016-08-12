<?php

namespace PayEx\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class SsnConfigProvider implements ConfigProviderInterface
{

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor
     * @param ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return [
            'payexSSN' => [
                'isEnabled' => (bool)$this->scopeConfig->getValue(
                    'payex/ssn/enable',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $this->storeManager->getStore()->getCode()
                ),
            ]
        ];
    }
}
