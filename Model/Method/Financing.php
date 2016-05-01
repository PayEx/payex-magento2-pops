<?php

namespace PayEx\Payments\Model\Method;

use Magento\Framework\DataObject;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Payment\Model\Method\Online\GatewayInterface;
use \Magento\Framework\Exception\LocalizedException;

/**
 * Class Financing
 * @package PayEx\Payments\Model\Method
 */
class Financing extends \Magento\Payment\Model\Method\AbstractMethod implements GatewayInterface
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Payment\Model\Method\Logger
     */
    protected $logger;

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    protected $payexHelper;

    /**
     * @var \PayEx\Payments\Logger\Logger
     */
    protected $payexLogger;

    /**
     * Constructor.
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
        \PayEx\Payments\Logger\Logger $payexLogger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->payexHelper = $payexHelper;
        $this->storeManager = $storeManager;
        $this->payexLogger = $payexLogger;
        $this->logger = $logger;
        $this->request = $request;

        // Init PayEx Environment
        $accountnumber = $this->getConfigData('accountnumber');
        $encryptionkey = $this->getConfigData('encryptionkey');
        $debug = (bool)$this->getConfigData('debug');
        $this->payexHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        if (!$data instanceof \Magento\Framework\DataObject) {
            $data = new \Magento\Framework\DataObject($data);
        }

        /** @var \Magento\Quote\Model\Quote\Payment $info */
        $info = $this->getInfoInstance();
        $info->setSocialSecurityNumber($data->getSocialSecurityNumber());
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

        // Validate SSN using PayEx
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
            throw new LocalizedException(__($this->payexHelper->getVerboseErrorMessage($result)));
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

        if ($result['transactionStatus']) {
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
     * Fetch transaction info
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $transactionId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        // Get Transaction Details
        $params = [
            'accountNumber' => '',
            'transactionNumber' => $transactionId,
        ];
        $details = $this->payexHelper->getPx()->GetTransactionDetails2($params);
        if ($details['code'] === 'OK' && $details['errorCode'] === 'OK' && $details['description'] === 'OK') {
            // Filter details
            $details = array_filter($details, 'strlen');
            return $details;
        }

        // Show Error
        throw new \Magento\Framework\Exception\LocalizedException(__($this->payexHelper->getVerboseErrorMessage($details)));
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
        parent::capture($payment, $amount);

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
            throw new \Magento\Framework\Exception\LocalizedException(__('Can\'t capture captured order.'));
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
        throw new \Magento\Framework\Exception\LocalizedException(__($this->payexHelper->getVerboseErrorMessage($result)));
    }

    /**
     * Cancel payment
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $payment->getAuthorizationTransaction();
        if (!$transaction) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Can\'t load last transaction.'));
        }

        // Load transaction Data
        $transactionId = $transaction->getTxnId();
        $details = $transaction->getAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS);

        // Get Transaction Details
        if (!is_array($details) || count($details) === 0) {
            $details = $this->fetchTransactionInfo($payment, $transactionId);
            $transaction->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $details);
            $transaction->save();
        }

        // Not to execute for Non-Authorized transactions
        if ((int)$details['transactionStatus'] !== 3) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Unable to execute cancel.'));
        }

        // Call PxOrder.Cancel2
        $params = [
            'accountNumber' => '',
            'transactionNumber' => $details['transactionNumber']
        ];
        $result = $this->payexHelper->getPx()->Cancel2($params);
        if ($result['code'] === 'OK' && $result['errorCode'] === 'OK' && $result['description'] === 'OK') {
            // Add Cancel Transaction
            $payment->setStatus(self::STATUS_DECLINED)
                ->setTransactionId($result['transactionNumber'])
                ->setIsTransactionClosed(1); // Closed

            // Add Transaction fields
            $payment->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $result);
            return $this;
        }

        // Show Error
        throw new \Magento\Framework\Exception\LocalizedException(__($this->payexHelper->getVerboseErrorMessage($result)));
    }

    /**
     * Refund specified amount for payment
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for refund.'));
        }

        if (!$payment->getLastTransId()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid transaction ID.'));
        }

        // Load transaction Data
        $transactionId = $payment->getLastTransId();
        $transactionRepository = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Sales\Model\Order\Payment\Transaction\Repository');

        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $transactionRepository->getByTransactionId(
            $transactionId,
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        if (!$transaction) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Can\'t load last transaction.'));
        }

        $details = $transaction->getAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS);

        // Get Transaction Details
        if (!is_array($details) || count($details) === 0) {
            $details = $this->fetchTransactionInfo($payment, $transactionId);
            $transaction->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $details);
            $transaction->save();
        }

        // Check for Capture and Authorize transaction only
        if (!in_array((int)$details['transactionStatus'], [0, 6])) {
            throw new \Magento\Framework\Exception\LocalizedException(__('This payment has not yet captured.'));
        }

        // Call PxOrder.Credit5
        $params = [
            'accountNumber' => '',
            'transactionNumber' => $details['transactionNumber'],
            'amount' => round(100 * $amount),
            'orderId' => $details['orderId'],
            'vatAmount' => 0,
            'additionalValues' => ''
        ];
        $result = $this->payexHelper->getPx()->Credit5($params);
        if ($result['code'] === 'OK' && $result['errorCode'] === 'OK' && $result['description'] === 'OK') {
            // Add Credit Transaction
            $payment->setAnetTransType(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND);
            $payment->setAmount($amount);

            $payment->setStatus(self::STATUS_APPROVED)
                ->setTransactionId($result['transactionNumber'])
                ->setIsTransactionClosed(0);

            // Add Transaction fields
            $payment->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $result);
            return $this;
        }

        // Show Error
        throw new \Magento\Framework\Exception\LocalizedException(__($this->payexHelper->getVerboseErrorMessage($result)));
    }


    /**
     * Post request to gateway and return response
     *
     * @param DataObject $request
     * @param ConfigInterface $config
     *
     * @return DataObject
     *
     * @throws \Exception
     */
    public function postRequest(DataObject $request, ConfigInterface $config)
    {
        // Implement postRequest() method.
    }
}
