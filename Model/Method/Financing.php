<?php

namespace PayEx\Payments\Model\Method;

use Magento\Framework\DataObject;
use \Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class Financing
 * @package PayEx\Payments\Model\Method
 */
class Financing extends \PayEx\Payments\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'payex_financing';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'PayEx\Payments\Block\Form\Financing';
    protected $_infoBlockType = 'PayEx\Payments\Block\Info\Financing';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canFetchTransactionInfo = true;

    /**
     * Constructor
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Locale\ResolverInterface $resolver
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Checkout\Model\Session $session
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \PayEx\Payments\Helper\Data $payexHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Checkout\Model\Session $session,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $request,
            $urlBuilder,
            $payexHelper,
            $storeManager,
            $resolver,
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $session,
            $payexLogger,
            $resource,
            $resourceCollection,
            $data
        );

        // Init PayEx Environment
        $accountnumber = $this->getConfigData('accountnumber');
        $encryptionkey = $this->getConfigData('encryptionkey');
        $debug = (bool)$this->getConfigData('debug');
        $this->payexHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);
    }

    /**
     * Assign data to info model instance
     *
     * @param DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(DataObject $data)
    {
        if (!$data instanceof DataObject) {
            $data = new DataObject($data);
        }

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        /** @var \Magento\Quote\Model\Quote\Payment $info */
        $info = $this->getInfoInstance();
        $info->setSocialSecurityNumber($additionalData->getSocialSecurityNumber());
        $info->setTos($additionalData->getTos());

        // Failback
        if (version_compare($this->payexHelper->getMageVersion(), '2.0.2', '<=')) {
            $info->setSocialSecurityNumber($data->getSocialSecurityNumber());
            $info->setTos($data->getTos());
        }

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validate()
    {
        parent::validate();

        /** @var \Magento\Quote\Model\Quote\Payment $info */
        $info = $this->getInfoInstance();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $info->getQuote();

        if (!$quote) {
            return $this;
        }

        if (!$info->getTos()) {
            throw new LocalizedException(__('Please accept the Terms of service.'));
        }

        // Check SSN is saved in session
        $ssn = $this->session->getPayexSSN();
        if (!empty($ssn)) {
            $info->setAdditionalInformation('social_security_number', $ssn);
            return $this;
        }

        $ssn = preg_replace('/[\-\s]+/', '', $info->getSocialSecurityNumber());
        $country_code = $quote->getBillingAddress()->getCountry();
        $postcode = str_replace(' ', '', $quote->getBillingAddress()->getPostcode());

        // Validate fields
        if (empty($ssn)) {
            throw new LocalizedException(__('Please enter Social Security Number.'));
        }

        if (!is_numeric($ssn)) {
            throw new LocalizedException(__('Social Security number have wrong format.'));
        }

        if (empty($country_code)) {
            throw new LocalizedException(__('Please select country.'));
        }

        if (empty($postcode)) {
            throw new LocalizedException(__('Please enter postcode.'));
        }

        // Validate Product names
        if (!$this->getConfigData('replace_illegal')) {
            $items = $quote->getAllVisibleItems();
            /** @var \Magento\Quote\Model\Quote\Item $item */
            foreach ($items as $item) {
                $product_name = $item->getName();
                if (!preg_match('/^[a-zA-Z0-9_:!#=?\\@{}´ %-À-ÖØ-öø-ú]*$/u', $product_name)) {
                    throw new LocalizedException(__('Product name "%1" contains invalid characters.', $product_name));
                }
            }
        }

        // Save SSN
        $info->setAdditionalInformation('social_security_number', $ssn);

        return $this;
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     *
     * @return $this
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @api
     */
    public function initialize($paymentAction, $stateObject)
    {
        $this->payexLogger->info('initialize');

        /** @var \Magento\Quote\Model\Quote\Payment $info */
        $info = $this->getInfoInstance();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $info->getOrder();

        $order_id = $order->getIncrementId();

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = $this->getConfigData('transactiontype');

        // Get Additional Values
        $additional = '';
        if ($operation === 'SALE') {
            $xml = $this->payexHelper->getInvoiceExtraPrintBlocksXML($order);
            $additional = 'FINANCINGINVOICE_ORDERLINES=' . urlencode($xml);
        }

        // Language
        $language = $this->getConfigData('language');
        if (empty($language)) {
            $language = $this->payexHelper->getLanguage();
        }

        // Get Amount
        $amount = $order->getGrandTotal();

        // Get SSN
        $ssn = $info->getAdditionalInformation('social_security_number');

        // Call PxOrder.Initialize8
        $params = [
            'accountNumber' => '',
            'purchaseOperation' => $operation,
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency_code,
            'vat' => 0,
            'orderID' => $order_id,
            'productNumber' => $order_id,
            'description' => $this->payexHelper->getStore()->getName(),
            'clientIPAddress' => $this->payexHelper->getRemoteAddr(),
            'clientIdentifier' => '',
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => 'http://localhost.no/return',
            'view' => 'FINANCING',
            'agreementRef' => '',
            'cancelUrl' => 'http://localhost.no/cancel',
            'clientLanguage' => $language
        ];
        $result = $this->payexHelper->getPx()->Initialize8($params);
        $this->payexLogger->info('PxOrder.Initialize8', $result);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);
            throw new LocalizedException(__($message));
        }
        $order_ref = $result['orderRef'];

        // Call PxOrder.PurchaseFinancingInvoice
        $params = [
            'accountNumber' => '',
            'orderRef' => $order_ref,
            'socialSecurityNumber' => $ssn,
            'legalName' => $order->getBillingAddress()->getName(),
            'streetAddress' => trim(implode(' ', $order->getBillingAddress()->getStreet())),
            'coAddress' => '',
            'zipCode' => str_replace(' ', '', $order->getBillingAddress()->getPostcode()),
            'city' => $order->getBillingAddress()->getCity(),
            'countryCode' => $order->getBillingAddress()->getCountryId(),
            'paymentMethod' => 'PXFINANCINGINVOICE' . $order->getBillingAddress()->getCountryId(),
            'email' => $order->getBillingAddress()->getEmail(),
            'msisdn' => (mb_substr($order->getBillingAddress()->getTelephone(), 0, 1) === '+') ? $order->getBillingAddress()->getTelephone() : '+' . $order->getBillingAddress()->getTelephone(),
            'ipAddress' => $this->payexHelper->getRemoteAddr()
        ];
        $result = $this->payexHelper->getPx()->PurchaseFinancingInvoice($params);
        $this->payexLogger->info('PxOrder.PurchaseFinancingInvoice', $result);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);
            throw new LocalizedException(__($message));
        }

        // Check Transaction fields
        if (empty($result['transactionNumber'])) {
            throw new LocalizedException(__('Error: Transaction failed'));
        }

        if (!isset($result['transactionStatus'])) {
            throw new LocalizedException(__('Error: No transactionsStatus in response'));
        }

        // Save Order
        $order->save();

        // Register Transaction
        $transaction_id = isset($result['transactionNumber']) ? $result['transactionNumber'] : null;
        $transaction_status = isset($result['transactionStatus']) ? (int)$result['transactionStatus'] : null;

        $order->getPayment()->setTransactionId($transaction_id);
        $transaction = $this->payexHelper->addPaymentTransaction($order, $result);

        // Set Last Transaction ID
        $order->getPayment()->setLastTransId($transaction_id)->save();

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ($transaction_status) {
            case 1:
            case 3:
                // Payment authorized
                $message = __('Payment has been authorized');

                // Change order status
                $new_status = $this->getConfigData('order_status_authorize');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();

                $order->addStatusHistoryComment($message);

                // Set state object
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                // Unset SSN
                $this->session->unsPayexSSN();
                break;
            case 0:
            case 6:
                // Payment captured
                $message = __('Payment has been captured');

                // Change order status
                $new_status = $this->getConfigData('order_status_capture');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();
                $order->addStatusHistoryComment($message);

                // Set state object
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                // Create Invoice for Sale Transaction
                $invoice = $this->payexHelper->makeInvoice($order, [], false, $message);
                $invoice->setTransactionId($result['transactionNumber']);
                $invoice->save();

                // Unset SSN
                $this->session->unsPayexSSN();
                break;
            case 2:
            case 4:
            case 5:
                if ($transaction_status === 2) {
                    $message = __('Detected an abnormal payment process (Transaction Status: %1).', $transaction_status);
                } elseif ($transaction_status === 4) {
                    $message = __('Order automatically canceled.');
                } else {
                    $message = $this->payexHelper->getVerboseErrorMessage($result);
                }

                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Set state object
                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState(\Magento\Sales\Model\Order::STATE_CANCELED);
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                throw new LocalizedException(__($message));
                // no break;
            default:
                // Invalid transaction status
                $message = __('Invalid transaction status.');

                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Set state object
                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState(\Magento\Sales\Model\Order::STATE_CANCELED);
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                throw new LocalizedException(__($message));
        }

        return $this;
    }

    /**
     * Capture
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for capture.'));
        }

        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $payment->getAuthorizationTransaction();
        if (!$transaction) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Can\'t load last transaction.'));
        }

        $payment->setAmount($amount);

        // Load transaction Data
        $transactionId = $transaction->getTxnId();
        $details = $transaction->getAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS);

        // Get Transaction Details
        if (!is_array($details) || count($details) === 0) {
            $details = $this->fetchTransactionInfo($payment, $transactionId);
            $transaction->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $details);
            $transaction->save();
        }

        // Not to execute for Sale transactions
        if ((int)$details['transactionStatus'] !== 3) {
            throw new LocalizedException(__('Can\'t capture captured order.'));
        }

        $transactionNumber = $details['transactionNumber'];
        $order_id = $details['orderId'];
        if (!$order_id) {
            $order_id = $payment->getOrder()->getIncrementId();
        }

        // Get Additional Values
        $xml = $this->payexHelper->getInvoiceExtraPrintBlocksXML($payment->getOrder());
        $additional = 'FINANCINGINVOICE_ORDERLINES=' . urlencode($xml);

        // Call PxOrder.Capture5
        $params = [
            'accountNumber' => '',
            'transactionNumber' => $transactionNumber,
            'amount' => round(100 * $amount),
            'orderId' => $order_id,
            'vatAmount' => 0,
            'additionalValues' => $additional
        ];
        $result = $this->payexHelper->getPx()->Capture5($params);
        if ($result['code'] === 'OK' && $result['errorCode'] === 'OK' && $result['description'] === 'OK') {
            // Note: Order Status will be changed in Observer

            // Add Capture Transaction
            $payment->setStatus(self::STATUS_APPROVED)
                ->setTransactionId($result['transactionNumber'])
                ->setIsTransactionClosed(0)
                ->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $result);

            return $this;
        }

        // Show Error
        throw new LocalizedException(__($this->payexHelper->getVerboseErrorMessage($result)));
    }
}
