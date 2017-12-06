<?php

namespace PayEx\Payments\Model\Psp;

use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;

abstract class AbstractPsp extends \Magento\Payment\Model\Method\AbstractMethod implements PaymentMethodInterface
{
    const BACKEND_API_URL_PROD = 'https://api.payex.com';
    const BACKEND_API_URL_TEST = 'https://api.externalintegration.payex.com';

    protected $_formBlockType = 'PayEx\Payments\Block\Form\Psp';
    protected $_infoBlockType = 'PayEx\Payments\Block\Info\Psp';

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
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var Transaction\Repository
     */
    protected $transactionRepository;

    /**
     * @var \PayEx\Payments\Helper\Psp
     */
    protected $psp;

    /**
     * @var \PayEx\Payments\Model\PayexTransaction
     */
    protected $payexTransaction;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Customer\Model\Session $customerSession ,
     * @param Transaction\Repository $transactionRepository
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     * @param \PayEx\Payments\Helper\Psp $psp
     * @param \PayEx\Payments\Model\PayexTransaction $payexTransaction
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \PayEx\Payments\Helper\Data $payexHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Checkout\Model\Session $session,
        \Magento\Customer\Model\Session $customerSession,
        Transaction\Repository $transactionRepository,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \PayEx\Payments\Helper\Psp $psp,
        \PayEx\Payments\Model\PayexTransaction $payexTransaction,
        $resource = null,
        $resourceCollection = null,
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

        $this->urlBuilder            = $urlBuilder;
        $this->payexHelper           = $payexHelper;
        $this->storeManager          = $storeManager;
        $this->payexLogger           = $payexLogger;
        $this->logger                = $logger;
        $this->request               = $request;
        $this->checkoutHelper        = $checkoutHelper;
        $this->session               = $session;
        $this->customerSession       = $customerSession;
        $this->transactionRepository = $transactionRepository;

        $this->psp              = $psp;
        $this->payexTransaction = $payexTransaction;

        // Init Api helper
        $this->psp->setMerchantToken($this->getConfigData('merchant_token'));
        $this->psp->setBackendApiUrl($this->getConfigData('debug') ? self::BACKEND_API_URL_TEST
            : self::BACKEND_API_URL_PROD);
    }

    /**
     * @return \PayEx\Payments\Helper\Psp
     */
    public function getPsp()
    {
        return $this->psp;
    }

    /**
     * Fetch transaction info
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string                               $transactionId
     *
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        $payment_id = $payment->getAdditionalInformation('payex_payment_id');
        if (empty($payment_id)) {
            throw new LocalizedException(__('Failed to fetch transaction info'));
        }

        // Get Order by Payment Id
        $order_id = $payment->getOrder()->getIncrementId();

        // Fetch transactions list
        try {
            $result = $this->psp->request('GET', $payment_id . '/transactions');
        } catch (\Exception $e) {
            throw new LocalizedException(__('Error: %1', $e->getMessage()));
        }

        $transactions = $result['transactions']['transactionList'];

        // Import transactions
        $this->payexTransaction->import_transactions($transactions, $order_id);

        // Get saved transaction
        $transactions = $this->payexTransaction->select([
            'number' => $transactionId,
        ]);
        if (count($transactions) > 0) {
            return array_shift($transactions);
        }

        return [];
    }

    /**
     * Capture
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float                                $amount
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::capture($payment, $amount);

        return $this;
    }

    /**
     * Cancel payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float                                $amount
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return $this;
    }
}
