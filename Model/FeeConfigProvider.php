<?php

namespace PayEx\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class FeeConfigProvider implements ConfigProviderInterface
{
    const CONFIG_XML_PATH_CART_FEE_DISPLAY_MODE = 'tax/cart_display/payex_fee';

    const CONFIG_XML_PATH_SALES_FEE_DISPLAY_MODE = 'tax/sales_display/payex_fee';

    const DISPLAY_TYPE_EXCLUDING_TAX = 1;

    const DISPLAY_TYPE_INCLUDING_TAX = 2;

    const DISPLAY_TYPE_BOTH = 3;

    /**
     * Payment Method Codes
     *
     * @var array
     */
    private static $allowed_methods = [
        'payex_financing',
        'payex_partpayment'
    ];

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param TaxHelper $taxHelper
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        TaxHelper $taxHelper,
        ScopeConfigInterface $scopeConfig
    ) {
    
        $this->taxHelper = $taxHelper;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return [
            'payexPaymentFee' => [
                'isEnabled' => $this->isEnabled(),
                'isDisplayPriceExclTax' => $this->isDisplayPriceExclTax(),
                'isDisplayBothPrices' => $this->isDisplayBothPrices(),
                'cartDisplayMode' => $this->getCartFeeDisplayMode(),
                'salesDisplayMode' => $this->getSalesFeeDisplayMode(),
            ]
        ];
    }

    /**
     * Check Fee is enabled
     * @param null|string|bool|int|Store $store
     * @return bool
     */
    public function isEnabled($store = null)
    {
        $enabled = false;
        foreach (self::$allowed_methods as $method) {
            $active = $this->scopeConfig->getValue(
                'payment/' . $method . '/active',
                ScopeInterface::SCOPE_STORE,
                $store
            );

            $price = $this->scopeConfig->getValue(
                'payment/' . $method . '/paymentfee',
                ScopeInterface::SCOPE_STORE,
                $store
            );

            if ($active && $price > 0) {
                $enabled = true;
                break;
            }
        }

        return $enabled;
    }

    /**
     * Cart Fee mode: 'both', 'including', 'excluding'
     * @return string
     */
    public function getCartFeeDisplayMode()
    {
        if ($this->displayCartFeeBoth()) {
            return 'both';
        }

        if ($this->displayCartFeeExclTax()) {
            return 'excluding';
        }

        return 'including';
    }

    /**
     * Cart Fee mode: 'both', 'including', 'excluding'
     * @return string
     */
    public function getSalesFeeDisplayMode()
    {
        if ($this->displaySalesFeeBoth()) {
            return 'both';
        }

        if ($this->displaySalesFeeExclTax()) {
            return 'excluding';
        }

        return 'including';
    }

    /**
     * Check if display cart prices fee included tax
     * @param null|string|bool|int|Store $store
     * @return bool
     */
    public function displayCartFeeInclTax($store = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_PATH_CART_FEE_DISPLAY_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check if display cart prices fee excluded tax
     * @param null|string|bool|int|Store $store
     * @return bool
     */
    public function displayCartFeeExclTax($store = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_PATH_CART_FEE_DISPLAY_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check if display cart prices fee included and excluded tax
     * @param null|string|bool|int|Store $store
     * @return bool
     */
    public function displayCartFeeBoth($store = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_PATH_CART_FEE_DISPLAY_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_BOTH;
    }

    /**
     * Check if display sales prices fee included tax
     * @param null|string|bool|int|Store $store
     * @return bool
     */
    public function displaySalesFeeInclTax($store = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_PATH_SALES_FEE_DISPLAY_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check if display sales prices fee excluded tax
     * @param null|string|bool|int|Store $store
     * @return bool
     */
    public function displaySalesFeeExclTax($store = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_PATH_SALES_FEE_DISPLAY_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check if display sales prices fee included and excluded tax
     * @param null|string|bool|int|Store $store
     * @return bool
     */
    public function displaySalesFeeBoth($store = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_PATH_SALES_FEE_DISPLAY_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) == self::DISPLAY_TYPE_BOTH;
    }

    /**
     * Return flag whether to display price excluding tax
     *
     * @return bool
     */
    public function isDisplayPriceInclTax()
    {
        return $this->taxHelper->displayPriceIncludingTax();
    }

    /**
     * Return flag whether to display price excluding tax
     *
     * @return bool
     */
    public function isDisplayPriceExclTax()
    {
        return $this->taxHelper->displayPriceExcludingTax();
    }

    /**
     * Return flag whether to display price including and excluding tax
     *
     * @return bool
     */
    public function isDisplayBothPrices()
    {
        return $this->taxHelper->displayBothPrices();
    }
}
