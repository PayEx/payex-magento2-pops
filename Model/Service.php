<?php

namespace PayEx\Payments\Model;

use Magento\Framework\Exception\LocalizedException;
use PayEx\Payments\Api\ServiceInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Exception\CouldNotSaveException;

class Service implements ServiceInterface
{
    const XML_PATH_MODULE_DEBUG = 'payment/payex_financing/debug';
    const XML_PATH_MODULE_ACCOUNTNUMBER = 'payment/payex_financing/accountnumber';
    const XML_PATH_MODULE_ENCRYPTIONKEY = 'payment/payex_financing/encryptionkey';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    private $cart;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var \Magento\Framework\View\Layout
     */
    private $layout;

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    private $payexHelper;

    /**
     * @var \PayEx\Payments\Logger\Logger
     */
    private $payexLogger;

    /**
     * Service constructor.
     *
     * @param ScopeConfigInterface           $scopeConfig
     * @param StoreManagerInterface          $storeManager
     * @param \Magento\Checkout\Model\Cart   $cart
     * @param \Magento\Checkout\Helper\Data  $checkoutHelper
     * @param \Magento\Framework\View\Layout $layout
     * @param \PayEx\Payments\Helper\Data    $payexHelper
     * @param \PayEx\Payments\Logger\Logger  $payexLogger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Framework\View\Layout $layout,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->cart = $cart;
        $this->checkoutHelper = $checkoutHelper;
        $this->layout = $layout;
        $this->payexHelper = $payexHelper;
        $this->payexLogger = $payexLogger;
    }

    /**
     * Return URL
     * @return string
     * @throws CouldNotSaveException
     */
    public function redirect_url()
    {
        $redirectUrl = $this->checkoutHelper->getCheckout()->getPayexRedirectUrl();
        if (empty($redirectUrl)) {
            throw new CouldNotSaveException(__('Failed to get redirect url'));
        }

        return $redirectUrl;
    }

    /**
     * Apply Payment Method
     * @param $payment_method
     *
     * @api
     * @param string $payment_method
     * @return void
     * @throws CouldNotSaveException
     */
    public function apply_payment_method($payment_method)
    {
        try {
            $quote = $this->cart->getQuote();
            $quote->getPayment()->setMethod($payment_method);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $quote->save();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }

    /**
     * Get Terms of Service
     *
     * @api
     * @param string $payment_method
     * @return string
     * @throws CouldNotSaveException
     */
    public function get_service_terms($payment_method)
    {
        // Get content
        $content = $this->scopeConfig->getValue(
            'payment/' . $payment_method .'/content_tos',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()
        );

        try {
            $block = $this->layout->createBlock('Magento\Framework\View\Element\Template');
            $block->setData('area', 'frontend')
                ->assign('method', $payment_method)
                ->assign('content', $content)
                ->setTemplate('PayEx_Payments::checkout/terms_of_service.phtml');

            return $block->toHtml();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }

    /**
     * Get Address by SSN
     * @param string $country_code
     * @param string $postcode
     * @param string $ssn
     * @return string
     * @throws CouldNotSaveException
     */
    public function get_address($country_code, $postcode, $ssn) {
        // Strip whitespaces from postcode to pass validation
        $postcode = preg_replace('/\s+/', '', $postcode);

        try {
            if (empty($country_code)) {
                throw new LocalizedException(__('Country is empty'));
            }

            if (empty($postcode)) {
                throw new LocalizedException(__('Postcode is empty'));
            }

            if (empty($ssn)) {
                throw new LocalizedException(__('Social security number is empty'));
            }

            if (!in_array($country_code, ['SE', 'NO', 'FI'])) {
                throw new LocalizedException(__('This country is not supported by the payment system'));
            }

            $store = $this->storeManager->getStore();

            // Init PayEx Environment
            $accountnumber = $this->scopeConfig->getValue(
                self::XML_PATH_MODULE_ACCOUNTNUMBER,
                ScopeInterface::SCOPE_STORE,
                $store
            );

            $encryptionkey = $this->scopeConfig->getValue(
                self::XML_PATH_MODULE_ENCRYPTIONKEY,
                ScopeInterface::SCOPE_STORE,
                $store
            );

            $debug = $this->scopeConfig->getValue(
                self::XML_PATH_MODULE_DEBUG,
                ScopeInterface::SCOPE_STORE,
                $store
            );

            $this->payexHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);

            // Call PxOrder.GetAddressByPaymentMethod
            $params = [
                'accountNumber' => '',
                'paymentMethod' => 'PXFINANCINGINVOICE' . $country_code,
                'ssn' => $ssn,
                'zipcode' => $postcode,
                'countryCode' => $country_code,
                'ipAddress' => $this->payexHelper->getRemoteAddr()
            ];
            $result = $this->payexHelper->getPx()->GetAddressByPaymentMethod($params);
            if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                $this->payexLogger->error('PxOrder.GetAddressByPaymentMethod', $result);

                throw new \Exception($this->payexHelper->getVerboseErrorMessage($result));
            }

            // Parse name field
            $name = $this->payexHelper->getNameParser()->parse_name($result['name']);

            $data = [
                'success' => true,
                'first_name' => trim($name['fname']),
                'last_name' => trim($name['lname']),
                'address_1' => $result['streetAddress'],
                'address_2' => !empty($result['coAddress']) ? 'c/o ' . trim($result['coAddress']) : '',
                'postcode' => trim($result['zipCode']),
                'city' => trim($result['city']),
                'country' => trim($result['countryCode'])
            ];

            // Save data in Session
            $this->checkoutHelper->getCheckout()->setPayexSSN($ssn);
            $this->checkoutHelper->getCheckout()->setPayexPostalCode($postcode);
            $this->checkoutHelper->getCheckout()->setPayexCountryCode($country_code);
            $this->checkoutHelper->getCheckout()->setPayexSSNData($data);

            return json_encode($data, true);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }
}
