<?php

namespace PayEx\Payments\Model\Fee;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Config extends \Magento\Tax\Model\Config
{
    /**
     * Shopping cart display settings
     */
    const XML_PATH_DISPLAY_CART_PAYEX_FEE = 'tax/cart_display/payex_fee';

    /**
     * Shopping cart display settings
     */
    const XML_PATH_DISPLAY_SALES_PAYEX_FEE = 'tax/sales_display/payex_fee';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
    
        parent::__construct($scopeConfig);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if display cart prices fee included tax
     * @param mixed $store
     * @return bool
     */
    public function displayCartFeeInclTax($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DISPLAY_CART_PAYEX_FEE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check if display cart prices fee excluded tax
     * @param mixed $store
     * @return bool
     */
    public function displayCartFeeExclTax($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DISPLAY_CART_PAYEX_FEE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check if display cart prices fee included and excluded tax
     * @param mixed $store
     * @return bool
     */
    public function displayCartFeeBoth($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DISPLAY_CART_PAYEX_FEE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_BOTH;
    }

    /**
     * Check if display sales prices fee included tax
     * @param mixed $store
     * @return bool
     */
    public function displaySalesFeeInclTax($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DISPLAY_SALES_PAYEX_FEE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check if display sales prices fee excluded tax
     * @param mixed $store
     * @return bool
     */
    public function displaySalesFeeExclTax($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DISPLAY_SALES_PAYEX_FEE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check if display sales prices fee included and excluded tax
     * @param mixed $store
     * @return bool
     */
    public function displaySalesFeeBoth($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_DISPLAY_SALES_PAYEX_FEE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_BOTH;
    }
}
