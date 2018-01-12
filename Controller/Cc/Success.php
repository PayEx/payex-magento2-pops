<?php

namespace PayEx\Payments\Controller\Cc;

use Magento\Sales\Model\Order\Payment\Transaction;

class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    protected $payexHelper;

    /**
     * @var \PayEx\Payments\Logger\Logger
     */
    protected $payexLogger;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * Success constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    )
    {
        parent::__construct($context);

        $this->urlBuilder = $context->getUrl();
        $this->session = $session;
        $this->payexHelper = $payexHelper;
        $this->payexLogger = $payexLogger;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
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
            $this->session->restoreQuote();
            $this->messageManager->addError(__('Order reference is empty'));
            $this->_redirect('checkout/cart');
            return;
        }

        // Load Order
        $order = $this->getOrder($orderRef);
        if (!$order->getId()) {
            $this->session->restoreQuote();
            $this->messageManager->addError(__('No order for processing found'));
            $this->_redirect('checkout/cart');
            return;
        }

        // Remove Redirect Url from Session
        $this->session->unsPayexRedirectUrl();

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
            $order->cancel();
            $order->addStatusHistoryComment(__('Order automatically canceled. Failed to complete payment.'));
            $order->save();

            // Restore the quote
            $this->session->restoreQuote();

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
                $this->session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                return;
            }

            // Restore the quote
            $this->session->restoreQuote();

            $this->messageManager->addError(__('Payment failed'));
            $this->_redirect('checkout/cart');
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
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                // Redirect to Success page
                $this->session->getQuote()->setIsActive(false)->save();
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
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                // Create Invoice for Sale Transaction
                $invoice = $this->payexHelper->makeInvoice($order, [], false, $message);
                $invoice->setTransactionId($details['transactionNumber']);
                $invoice->save();

                // Redirect to Success page
                $this->session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                break;
            case 2:
            case 4:
            case 5:
                if ($transaction_status === 2) {
                    $message = __('Detected an abnormal payment process (Transaction Status: %1).', $transaction_status);
                } elseif ($transaction_status === 4) {
                    $message = __('Order automatically canceled.');
                } else {
                    $message = $this->payexHelper->getVerboseErrorMessage($details);
                }

                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Restore the quote
                $this->session->restoreQuote();

                $this->messageManager->addError($message);
                $this->_redirect('checkout/cart');
                break;
            default:
                // Invalid transaction status
                $message = __('Invalid transaction status.');

                // Restore the quote
                $this->session->restoreQuote();

                $this->messageManager->addError($message);
                $this->_redirect('checkout/cart');
                return;
        }
    }

    /**
     * Get order object by external reference saved in payment
     * @param string $orderRef
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder($orderRef)
    {
        $searchCriteriaBuilder = $this->_objectManager->create('Magento\Framework\Api\SearchCriteriaBuilder');
        $searchCriteria = $searchCriteriaBuilder->addFilter(
            'po_number',
            $orderRef,
            'eq'
        )->create();
        $payment = $this->_objectManager->get('Magento\Sales\Model\Order\Payment\Repository')->getList($searchCriteria)->getFirstItem();
        return $payment->getOrder();
    }
}
