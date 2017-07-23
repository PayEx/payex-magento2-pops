<?php

namespace PayEx\Payments\Model\Method;

use Magento\Checkout\Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Class Checkout
 * @see https://developer.payex.com/xwiki/wiki/external/view/ecommerce/
 * @package PayEx\Payments\Model\Method
 */
class Checkout extends \PayEx\Payments\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'payex_checkout';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    //protected $_formBlockType = 'PayEx\Payments\Block\Form\Checkout';
    protected $_infoBlockType = 'PayEx\Payments\Block\Info\Checkout';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canCaptureOnce = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canFetchTransactionInfo = false;

    protected $merchant_token = '';
    protected $backend_api_url = '';

    /**
     * @var \PayEx\Payments\Helper\Checkout
     */
    public $pxCheckoutHelper;

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
        Transaction\Repository $transactionRepository,
        \PayEx\Payments\Logger\Logger $payexLogger,
        $resource = null,
        $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($request, $urlBuilder, $payexHelper, $storeManager,
            $context, $registry, $extensionFactory, $customAttributeFactory,
            $paymentData, $scopeConfig, $logger, $checkoutHelper,
            $transactionRepository, $payexLogger, $resource,
            $resourceCollection, $data);

        // Init Api helper
        $this->pxCheckoutHelper = $this->getPxCheckoutHelper();
        $this->pxCheckoutHelper->setMerchantToken($this->getConfigData('merchant_token'));

        if ($this->getConfigData('debug')) {
            $this->pxCheckoutHelper->setBackendApiUrl('https://api.externalintegration.payex.com');
        } else {
            $this->pxCheckoutHelper->setBackendApiUrl('https://api.payex.com');
        }
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
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

        // Set state object
        /** @var \Magento\Sales\Model\Order\Status $status */
        $status = $this->payexHelper->getAssignedState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setState($status->getState());
        $stateObject->setStatus($status->getStatus());
        $stateObject->setIsNotified(false);

        if ($this->checkoutHelper->getQuote()->getId()) {
            $payment_session_url = $this->checkoutHelper->getQuote()->getPayexPaymentSession();
            $payment_id          = $this->checkoutHelper->getQuote()->getPayexPaymentId();
        } else {
            $quote = $this->getQuoteById($info->getOrder()->getQuoteId());
            $payment_session_url = $quote->getPayexPaymentSession();
            $payment_id          = $quote->getPayexPaymentId();

        }

        try {
            // Get Payment Session
            $result = $this->pxCheckoutHelper->request('GET', $payment_session_url);

            // Get Address Url
            if (!empty($result['addressee'])) {
                $address_url = $result['addressee'];
                $result      = $this->request('GET', $address_url);
                if ( ! isset($result['payment'])) {
                    throw new \Exception('Invalid payment response');
                }

                // @todo Parse name
                // @todo Update address fields
            }

            // Get Payment Url
            $result = $this->pxCheckoutHelper->request('GET', $payment_id);
            if ( ! isset($result['payment'])) {
                throw new \Exception('Invalid payment response');
            }
            // Get Payment Status
            $payment_url = $result['payment'];
            $result      = $this->pxCheckoutHelper->request('GET', $payment_url);
            if ( ! isset($result['payment'])) {
                throw new \Exception('Invalid payment response');
            }

            if (!in_array($result['payment']['state'], ['Ready', 'Pending'])) {
                throw new \Exception('Payment failed');
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        $transaction_id = $result['payment']['number'];

        // Transaction Details
        $details = array_filter($result['payment'], function($value, $key) {
            return !is_array($value);
        }, ARRAY_FILTER_USE_BOTH);

        // Save response
        $order->setPayexCheckout(json_encode($result));

        // Save Order
        $order->save();

        // Register Transaction
        $order->getPayment()
              ->setTransactionId($transaction_id)
              ->setLastTransId($transaction_id)
              ->save()
              ->addTransaction(Transaction::TYPE_AUTH, null, false)
              ->setIsClosed(false)
              ->setAdditionalInformation(Transaction::RAW_DETAILS, $details)
              ->save();

        /** @var \Magento\Sales\Model\Order\Status $status */
        $status = $this->payexHelper->getAssignedState($this->getConfigData('order_status_authorize'));

        // Set state object
        $stateObject->setState($status->getState());
        $stateObject->setStatus($status->getStatus());
        $stateObject->setIsNotified(true);

        return $this;
    }

    /**
     * Fetch transaction info
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $transactionId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {
        return [];
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
            throw new LocalizedException(__('Invalid amount for capture.'));
        }

        // Convert amount currency
        $order = $payment->getOrder();
        if ($order->getBaseCurrencyCode() != $order->getOrderCurrencyCode()) {
            $amount = $amount * $order->getBaseToOrderRate();
        }

        /** @var Transaction $transaction */
        $transaction = $payment->getAuthorizationTransaction();
        if (!$transaction) {
            throw new LocalizedException(__('Can\'t load last transaction.'));
        }

        $data = json_decode($payment->getOrder()->getPayexCheckout(), true);
        $operations = array_filter($data['operations'], function($value, $key) {
            return $value['rel'] === 'create-checkout-capture';
        }, ARRAY_FILTER_USE_BOTH);
        $operation = array_shift($operations);

        $vatAmount    = 0;
        $descriptions = [];
        $items = $this->payexHelper->getOrderItems($payment->getOrder());
        foreach ( $items as $item ) {
            $vatAmount      += $item['tax_price'];
            $descriptions[] = [
                'amount'      => $item['price_with_tax'],
                'vatAmount'   => $item['tax_price'],
                'itemAmount'  => sprintf( "%.2f", $item['price_with_tax'] / $item['qty'] ),
                'quantity'    => $item['qty'],
                'description' => $item['name']
            ];
        }

        try {
            $params = [
                'transaction' => [
                    'amount'      => round($amount, 2),
                    'vatAmount'   => round($vatAmount, 2),
                    'description' => sprintf('Capture for Order #%s', $payment->getOrder()->getIncrementId())
                ],

                'itemDescriptions' => $descriptions
            ];
            $result = $this->pxCheckoutHelper->request('POST', $operation['href'], $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        $details = $result['capture']['transaction'];

        $payment->setAmount($amount);

        // Add Capture Transaction
        $payment->setStatus(self::STATUS_APPROVED)
                ->setTransactionId($details['number'])
                ->setIsTransactionClosed(0)
                ->setAdditionalInformation(Transaction::RAW_DETAILS, $details);

        return $this;
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
        if ($amount <= 0) {
            throw new LocalizedException(__('Invalid amount for refund.'));
        }

        if (!$payment->getLastTransId()) {
            throw new LocalizedException(__('Invalid transaction ID.'));
        }

        // Load transaction Data
        $transactionId = $payment->getLastTransId();

        /** @var Transaction $transaction */
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionId,
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        if (!$transaction) {
            throw new LocalizedException(__('Can\'t load last transaction.'));
        }

        $data = json_decode($payment->getOrder()->getPayexCheckout(), true);
        $operations = array_filter($data['operations'], function($value, $key) {
            return $value['rel'] === 'create-checkout-reversal';
        }, ARRAY_FILTER_USE_BOTH);
        $operation = array_shift($operations);

        try {
            $params = [
                'transaction' => [
                    'amount'      => round($amount, 2),
                    'vatAmount'   => 0,
                    'description' => sprintf('Refund for Order #%s', $payment->getOrder()->getIncrementId())
                ],
            ];
            $result = $this->pxCheckoutHelper->request('POST', $operation['href'], $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        $details = $result['reversal']['transaction'];
        switch ( $details['state'] ) {
            case 'Completed':
                // Add Credit Transaction
                $payment->setAnetTransType(Transaction::TYPE_REFUND);
                $payment->setAmount($amount);

                $payment->setStatus(self::STATUS_APPROVED)
                        ->setTransactionId($details['number'])
                        ->setIsTransactionClosed(0);

                // Add Transaction fields
                $payment->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                return $this;
            default:
                $message = isset($result['reversal']['transaction']['failedReason']) ? $result['reversal']['transaction']['failedReason'] : 'Refund failed';
                throw new LocalizedException(__($message));
        }
    }

    /**
     * Get PayEx Checkout Helper
     *
     * @return \PayEx\Payments\Helper\Checkout
     */
    protected function getPxCheckoutHelper()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        return $om->get('PayEx\Payments\Helper\Checkout');
    }

    /**
     * @param $quote_id
     *
     * @return mixed
     */
    public function getQuoteById($quote_id) {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $quoteFactory = $om->get('Magento\Quote\Model\QuoteFactory');
        return $quoteFactory->create()->load($quote_id);
    }
}
