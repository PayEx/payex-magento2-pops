<?php

namespace PayEx\Payments\Model\Method;

use Magento\Framework\DataObject;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Payment\Model\Method\Online\GatewayInterface;
use \Magento\Framework\Exception\LocalizedException;

abstract class AbstractMethod extends \Magento\Payment\Model\Method\AbstractMethod implements GatewayInterface
{
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
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

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
        $this->session = $session;

        // Init PayEx Environment
        $accountnumber = $this->getConfigData('accountnumber');
        $encryptionkey = $this->getConfigData('encryptionkey');
        $debug = (bool)$this->getConfigData('debug');
        $this->payexHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);
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
        throw new LocalizedException(__($this->payexHelper->getVerboseErrorMessage($details)));
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
            throw new LocalizedException(__('Invalid amount for capture.'));
        }

        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $payment->getAuthorizationTransaction();
        if (!$transaction) {
            throw new LocalizedException(__('Can\'t load last transaction.'));
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

        // Call PxOrder.Capture5
        $params = [
            'accountNumber' => '',
            'transactionNumber' => $transactionNumber,
            'amount' => round(100 * $amount),
            'orderId' => $order_id,
            'vatAmount' => 0,
            'additionalValues' => ''
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
            throw new LocalizedException(__('Can\'t load last transaction.'));
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
            throw new LocalizedException(__('Unable to execute cancel.'));
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
        throw new LocalizedException(__($this->payexHelper->getVerboseErrorMessage($result)));
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
            throw new LocalizedException(__('Invalid amount for refund.'));
        }

        if (!$payment->getLastTransId()) {
            throw new LocalizedException(__('Invalid transaction ID.'));
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
            throw new LocalizedException(__('Can\'t load last transaction.'));
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
            throw new LocalizedException(__('This payment has not yet captured.'));
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
        throw new LocalizedException(__($this->payexHelper->getVerboseErrorMessage($result)));
    }
}
