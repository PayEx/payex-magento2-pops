<?php

namespace PayEx\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Magento\Sales\Model\Order\Payment\Transaction;

class CheckoutIpn extends Action
{
    /**
     * @var \PayEx\Payments\Helper\Data
     */
    private $payexHelper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \PayEx\Payments\Helper\Psp
     */
    private $psp;

    /**
     * @var \PayEx\Payments\Model\PayexTransaction
     */
    private $payexTransaction;

    /**
     * @var Transaction\Repository
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
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    private $rawResultFactory;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    private $order;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    private $iofile;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    private $quoteManagement;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * CheckoutIpn constructor.
     *
     * @param \Magento\Framework\App\Action\Context               $context
     * @param \Magento\Framework\Controller\Result\RawFactory     $rawResultFactory
     * @param \Psr\Log\LoggerInterface                            $logger
     * @param \PayEx\Payments\Helper\Data                         $payexHelper
     * @param \PayEx\Payments\Helper\Psp                          $psp
     * @param \PayEx\Payments\Model\PayexTransaction              $payexTransaction
     * @param \Magento\Sales\Api\TransactionRepositoryInterface   $transactionRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Sales\Model\OrderFactory                   $orderFactory
     * @param \Magento\Framework\Filesystem\Io\File               $iofile
     * @param \Magento\Quote\Model\QuoteFactory                   $quoteFactory
     * @param \Magento\Quote\Api\CartManagementInterface          $quoteManagement
     * @param \Magento\Sales\Api\Data\OrderInterface              $order
     * @param \Magento\Checkout\Helper\Data                       $checkoutHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $rawResultFactory,
        \Psr\Log\LoggerInterface $logger,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Helper\Psp $psp,
        \PayEx\Payments\Model\PayexTransaction $payexTransaction,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Filesystem\Io\File $iofile,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Checkout\Helper\Data $checkoutHelper
    ) {
    
        parent::__construct($context);
        $this->rawResultFactory = $rawResultFactory;
        $this->logger = $logger;
        $this->payexHelper = $payexHelper;
        $this->psp = $psp;
        $this->payexTransaction = $payexTransaction;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
        $this->orderFactory = $orderFactory;
        $this->iofile = $iofile;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->order = $order;
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     * @SuppressWarnings(Generic.Metrics.CyclomaticComplexity.MaxExceeded)
     * @SuppressWarnings(MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation)
     * @SuppressWarnings(MEQP2.Classes.ObjectManager.ObjectManagerFound)
     * @SuppressWarnings(Generic.Files.LineLength.TooLong)
     */
    public function execute()
    {
        // Init Logger
        $writer = new Stream(BP . '/var/log/payex_psp_checkout.log');
        $logger = new Logger();
        $logger->addWriter($writer);

        // @todo Filter by ip 82.115.146.1
        $remote_addr = $this->payexHelper->getRemoteAddr();
        $logger->info('IPN: Initialized', [$remote_addr, $_SERVER['REQUEST_URI']]);

        $raw_body = $this->iofile->read('php://input');
        $data = json_decode($raw_body,true);
        $logger->info('IPN: Body', [$raw_body]);

        $reference = $this->getRequest()->getParam('reference');
        if (strpos($reference, 'quote_') === 0) {
            $reference = str_replace('quote_', '', $reference);
        }

        try {
            // Load quote
            $quote = $this->quoteFactory->create()->load($reference);
            if (!$quote->getId()) {
                throw new \Exception(sprintf('Error: Failed to load quote #%s', $reference));
            }

            /** @var \PayEx\Payments\Model\Method\Checkout $method */
            $methodInstance = $quote->getPayment()->getMethodInstance();

            // Init Api helper
            // @todo Define Store Id
            $this->psp->setMerchantToken($methodInstance->getConfigData('merchant_token'));
            $this->psp->setBackendApiUrl($methodInstance->getConfigData('debug') ?
                \PayEx\Payments\Model\Psp\AbstractPsp::BACKEND_API_URL_TEST
                : \PayEx\Payments\Model\Psp\AbstractPsp::BACKEND_API_URL_PROD);

            $payment_session_id = $quote->getPayment()->getAdditionalInformation('payex_payment_session_id');
            if (empty($payment_session_id)) {
                throw new \Exception('Error: No payment session ID');
            }

            // Get Payment Id
            $result = $this->psp->request('GET', $payment_session_id);
            if (!isset($result['payment'])) {
                throw new \Exception('Invalid payment response');
            }

            $payment_id = $result['payment'];

            // Fetch transactions list
            $result = $this->psp->request('GET', $payment_id . '/transactions');
            $transactions = $result['transactions']['transactionList'];

            // Import transactions
            //$this->payexTransaction->import_transactions($transactions, null);

            // Extract transaction from list
            $transaction = $this->psp->filter($transactions, ['number' => $data['transaction']['number']]);
            $logger->info(sprintf('IPN: Debug: Transaction: %s', var_export($transaction, true)));
            if (!is_array($transaction) || count($transaction) === 0) {
                throw new \Exception(sprintf('Error: Failed to fetch transaction number #%s', $data['transaction']['number']));
            }

            // Check transaction state
            if ($transaction['state'] !== 'Completed') {
                $reason = isset($transaction['failedReason']) ? $transaction['failedReason'] : __('Transaction failed.');
                throw new \Exception(sprintf('Error: Transaction state %s. Reason: %s', $data['transaction']['state'], $reason));
            }

            // Load order by quote_id
            $order = $this->order->loadByAttribute('quote_id', $quote->getId());
            if (!$order->getId()) {
                // Place Order
                $order_id = $quote->getReservedOrderId();
                if (!$order_id) {
                    // Reserve Order
                    try {
                        // Set Payment Method
                        //$quote->setCheckoutMethod('payex_psp_checkout');

                        // Update totals
                        $quote->collectTotals();

                        // Create an Order ID for the customer's quote
                        $quote->reserveOrderId()->save();
                    } catch (\Exception $e) {
                        $logger->crit('Failed to reserve order', [$quote->getId(), $e->getMessage()]);
                        throw $e;
                    }

                    $order_id = $quote->getReservedOrderId();
                    $logger->info('Reserved order', [$quote->getId(), $order_id]);
                }

                // Load order
                /** @var \Magento\Sales\Model\Order $order */
                $order = $this->order->loadByIncrementIdAndStoreId($order_id, $quote->getStore()->getStoreId());
                if (!$order->getId()) {
                    try {
                        $order = $this->quoteManagement->submit($quote);
                        $order->getPayment()->place();
                    } catch (\Exception $e) {
                        $logger->crit('Failed to place order', [$order_id, $e->getMessage()]);
                        throw $e;
                    }
                }

                // Save payment ID
                $order->getPayment()->setAdditionalInformation('payex_payment_id', $payment_id);
            }

            // Get Increment Id
            $order_id = $order->getIncrementId();

            // Import transactions
            $this->payexTransaction->import_transactions($transactions, $order->getIncrementId());

            // Check Transaction is already registered
            $trans = $this->transactionRepository->getByTransactionId(
                $transaction['number'],
                $order->getPayment()->getId(),
                $order->getId()
            );
            if ($trans) {
                throw new \Exception(sprintf('Action of Transaction #%s already performed', $data['transaction']['number']));
            }

            // Apply action
            switch ($transaction['type']) {
                case 'Initialization':
                    // Register Transaction
                    $order->getPayment()->setTransactionId($transaction['number']);
                    $trans = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
                    $trans->setIsClosed(0);
                    $trans->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction);
                    $trans->save();

                    $logger->info(sprintf('IPN: Order #%s initialized', $order_id));
                    break;
                case 'Authorization':
                    // Payment authorized
                    // Register Transaction
                    $order->getPayment()->setTransactionId($transaction['number']);
                    $trans = $order->getPayment()->addTransaction(Transaction::TYPE_AUTH, null, true);
                    $trans->setIsClosed(0);
                    $trans->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction);
                    $trans->save();

                    // Set Last Transaction ID
                    $order->getPayment()->setLastTransId($transaction['number'])->save();

                    // Change order status
                    $new_status = $method->getConfigData('order_status_authorize');

                    /** @var \Magento\Sales\Model\Order\Status $status */
                    $status = $this->payexHelper->getAssignedState($new_status);
                    $order->setData('state', $status->getState());
                    $order->setStatus($status->getStatus());
                    $order->save();

                    $order->addStatusHistoryComment(__('Payment has been authorized'));

                    // Send order notification
                    try {
                        $this->orderSender->send($order);
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }

                    $logger->info(sprintf('IPN: Order #%s marked as authorized', $order_id));
                    break;
                case 'Capture':
                    // Payment captured
                    // Register Transaction
                    $order->getPayment()->setTransactionId($transaction['number']);
                    $trans = $order->getPayment()->addTransaction(Transaction::TYPE_CAPTURE, null, true);
                    $trans->setIsClosed(0);
                    $trans->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction);
                    $trans->save();

                    // Set Last Transaction ID
                    $order->getPayment()->setLastTransId($transaction['number'])->save();

                    // Change order status
                    $new_status = $method->getConfigData('order_status_capture');

                    /** @var \Magento\Sales\Model\Order\Status $status */
                    $status = $this->payexHelper->getAssignedState($new_status);
                    $order->setData('state', $status->getState());
                    $order->setStatus($status->getStatus());
                    $order->save();

                    $order->addStatusHistoryComment(__('Payment has been captured'));

                    // Send order notification
                    try {
                        $this->orderSender->send($order);
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }

                    // Create Invoice
                    $invoice = $this->payexHelper->makeInvoice(
                        $order,
                        [],
                        false
                    );
                    $invoice->setTransactionId($transaction['number']);
                    $invoice->save();

                    $logger->info(sprintf('IPN: Order #%s marked as captured', $order_id));
                    break;
                case 'Cancellation':
                    // Register Transaction
                    $order->getPayment()->setTransactionId($transaction['number']);
                    $trans = $order->getPayment()->addTransaction(Transaction::TYPE_VOID, null, true);
                    $trans->setIsClosed(1);
                    $trans->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction);
                    $trans->save();

                    // Set Last Transaction ID
                    $order->getPayment()->setLastTransId($transaction['number'])->save();

                    if (!$order->isCanceled() && !$order->hasInvoices()) {
                        $order->cancel();
                        $order->addStatusHistoryComment(__('Order canceled by IPN'));
                        $order->save();
                        //$order->sendOrderUpdateEmail(true, $message);

                        $logger->info(sprintf('IPN: Order #%s marked as cancelled', $order_id));
                    }
                    break;
                case 'Reversal':
                    // @todo Implement Refunds creation
                    throw new \Exception('Error: Reversal transaction don\'t implemented yet.');
                default:
                    throw new \Exception(sprintf('Error: Unknown type %s', $transaction['type']));
            }
        } catch (\Exception $e) {
            $logger->crit(sprintf('IPN: %s', $e->getMessage()));

            /** @var \Magento\Framework\Controller\Result\Raw $result */
            $result = $this->rawResultFactory->create();
            $result->setStatusHeader('400', '1.1', sprintf('IPN: %s', $e->getMessage()));
            $result->setContents(sprintf('IPN: %s', $e->getMessage()));

            return $result;
        }

        /** @var \Magento\Framework\Controller\Result\Raw $result */
        $result = $this->rawResultFactory->create();
        $result->setStatusHeader('200', '1.1', 'OK');
        $result->setContents('OK');

        return $result;
    }
}
