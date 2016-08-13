<?php

namespace PayEx\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session;

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
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

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
    )
    {
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
                    'payex/ssn/enable',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $this->storeManager->getStore()->getCode()
                ),
                'appliedSSN' => $this->session->getPayexSSN()
            ]
        ];
    }
}
