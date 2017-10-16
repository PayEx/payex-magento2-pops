<?php

namespace PayEx\Payments\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;

class UpgradeData implements \Magento\Framework\Setup\UpgradeDataInterface
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
     * @var ConfigInterface
     */
    private $configInterface;

    /**
     * Constructor
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ConfigInterface $configInterface
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ConfigInterface $configInterface
    ) {

        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->configInterface = $configInterface;
    }

    /**
     * Do Upgrade Data
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface   $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.13', '<')) {
            $this->importSSNConfig($setup, $context);
        }

        $setup->endSetup();
    }

    /**
     * Import SSN config
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface   $context
     */
    private function importSSNConfig(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $is_enabled = (bool)$this->scopeConfig->getValue(
            'payex/ssn/enable',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getCode()
        );

        if ($is_enabled) {
            $this->configInterface->saveConfig(
                'payment/payex_financing/checkout_field',
                1,
                'default',
                $this->storeManager->getStore()->getCode()
            );
        }
    }
}
