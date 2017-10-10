<?php

namespace PayEx\Payments\Controller\Cc;

use Magento\Sales\Model\Order\Payment\Transaction;

class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    private $payexHelper;

    /**
     * @var \PayEx\Payments\Logger\Logger
     */
    private $payexLogger;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $orderSender;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * Success constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {
    
        parent::__construct($context);

        $this->logger = $logger;
        $this->urlBuilder = $context->getUrl();
        $this->checkoutHelper = $checkoutHelper;
        $this->payexHelper = $payexHelper;
        $this->payexLogger = $payexLogger;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        // Check OrderRef
        $orderRef = $this->getRequest()->getParam('orderRef');
        if (empty($orderRef)) {
            $this->checkoutHelper->getCheckout()->restoreQuote();
            $this->messageManager->addError(__('Order reference is empty'));
            $this->_redirect('checkout/cart');
            return;
        }

        // Load Order
        $order = $this->getOrder();
        if (!$order->getId()) {
            $this->checkoutHelper->getCheckout()->restoreQuote();
            $this->messageManager->addError(__('No order for processing found'));
            $this->_redirect('checkout/cart');
            return;
        }

        // Remove Redirect Url from Session
        $this->checkoutHelper->getCheckout()->unsPayexRedirectUrl();

        $order_id = $order->getIncrementId();

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        // Init PayEx Environment
        $accountnumber = $method->getConfigData('accountnumber');
        $encryptionkey = $method->getConfigData('encryptionkey');
        $debug = (bool)$method->getConfigData('debug');
        $this->payexHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);

        // Call PxOrder.Complete
        $params = [
            'accountNumber' => '',
            'orderRef' => $orderRef
        ];
        $details = $this->payexHelper->getPx()->Complete($params);
        $this->payexLogger->info('PxOrder.Complete', $details);
        if ($details['errorCodeSimple'] !== 'OK') {
            // Cancel order
            $this->payexHelper->cancelOrder($order, __('Order automatically canceled. Failed to complete payment.'));
            $order->save();

            // Restore the quote
            $this->checkoutHelper->getCheckout()->restoreQuote();

            $message = $this->payexHelper->getVerboseErrorMessage($details);
            $this->messageManager->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        $transaction_id = isset($details['transactionNumber']) ? $details['transactionNumber'] : null;
        $transaction_status = isset($details['transactionStatus']) ? (int)$details['transactionStatus'] : null;

        // Check Transaction is already registered
        $transaction = $this->transactionRepository->getByTransactionId(
            $transaction_id,
            $order->getPayment()->getId(),
            $order->getId()
        );

        if ($transaction) {
            $raw_details_info = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
            if (is_array($raw_details_info) && in_array($transaction_status, [0, 3, 6])) {
                // Redirect to Success Page
                $this->payexLogger->info('Transaction already paid: Redirect to success page', [$order_id]);
                $this->checkoutHelper->getCheckout()->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                return;
            }

            // Restore the quote
            $this->checkoutHelper->getCheckout()->restoreQuote();

            $message = __('Payment failed');
            if (is_array($raw_details_info) && isset($raw_details_info['code'])) {
                $message = $this->payexHelper->getVerboseErrorMessage($raw_details_info);
            }

            $this->messageManager->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        // Register Transaction
        $order->getPayment()->setTransactionId($transaction_id);
        $transaction = $this->payexHelper->addPaymentTransaction($order, $details);

        // Set Last Transaction ID
        $order->getPayment()->setLastTransId($transaction_id)->save();

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        $transaction_status = isset($details['transactionStatus']) ? (int)$details['transactionStatus'] : null;
        switch ($transaction_status) {
            case 1:
            case 3:
                // Payment authorized
                $message = __('Payment has been authorized');

                // Change order status
                $new_status = $method->getConfigData('order_status_authorize');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();

                // @todo Fixme: No comment
                $order->addStatusHistoryComment($message);

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->logger->critical($e);
                }

                // Redirect to Success page
                $this->checkoutHelper->getCheckout()->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                break;
            case 0:
            case 6:
                // Payment captured
                $message = __('Payment has been captured');

                // Change order status
                $new_status = $method->getConfigData('order_status_capture');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();
                $order->addStatusHistoryComment($message);

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->logger->critical($e);
                }

                // Create Invoice for Sale Transaction
                $invoice = $this->payexHelper->makeInvoice(
                    $order,
                    [],
                    false,
                    $message
                );
                $invoice->setTransactionId($details['transactionNumber']);
                $invoice->save();

                // Redirect to Success page
                $this->checkoutHelper->getCheckout()->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                break;
            case 2:
            case 4:
            case 5:
                if ($transaction_status === 2) {
                    $message = __(
                        'Detected an abnormal payment process (Transaction Status: %1).',
                        $transaction_status
                    );
                } elseif ($transaction_status === 4) {
                    $message = __('Order automatically canceled.');
                } else {
                    $message = $this->payexHelper->getVerboseErrorMessage($details);
                }

                $this->payexHelper->cancelOrder($order, $message);
                $order->addStatusHistoryComment($message);
                $order->save();

                // Restore the quote
                $this->checkoutHelper->getCheckout()->restoreQuote();

                $this->messageManager->addError($message);
                $this->_redirect('checkout/cart');
                break;
            default:
                // Invalid transaction status
                $message = __('Invalid transaction status.');

                // Restore the quote
                $this->checkoutHelper->getCheckout()->restoreQuote();

                $this->messageManager->addError($message);
                $this->_redirect('checkout/cart');
                return;
        }
    }

    /**
     * Get order object
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        $incrementId = $this->checkoutHelper->getCheckout()->getLastRealOrderId();
        return $this->orderFactory->create()->loadByIncrementId($incrementId);
    }
}
