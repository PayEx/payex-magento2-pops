<?php

namespace PayEx\Payments\Controller\Psp;

use Magento\Framework\App\Action\Action;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Magento\Sales\Model\Order\Payment\Transaction;

class Callback extends Action
{
    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    private $rawResultFactory;

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
     * @var \PayEx\Payments\Helper\Psp
     */
    protected $psp;

    /**
     * @var \PayEx\Payments\Model\PayexTransaction
     */
    protected $payexTransaction;

    /**
     * Callback constructor.
     *
     * @param \Magento\Framework\App\Action\Context             $context
     * @param \Magento\Framework\Controller\Result\RawFactory   $rawResultFactory
     * @param \Psr\Log\LoggerInterface                          $logger
     * @param \Magento\Checkout\Helper\Data                     $checkoutHelper
     * @param \PayEx\Payments\Helper\Data                       $payexHelper
     * @param \PayEx\Payments\Logger\Logger                     $payexLogger
     * @param \PayEx\Payments\Helper\Psp                        $psp
     * @param \PayEx\Payments\Model\PayexTransaction            $payexTransaction
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\OrderFactory                 $orderFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $rawResultFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \PayEx\Payments\Helper\Psp $psp,
        \PayEx\Payments\Model\PayexTransaction $payexTransaction,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\OrderFactory $orderFactory
    ) {

        parent::__construct($context);

        $this->rawResultFactory      = $rawResultFactory;
        $this->logger                = $logger;
        $this->urlBuilder            = $context->getUrl();
        $this->checkoutHelper        = $checkoutHelper;
        $this->payexHelper           = $payexHelper;
        $this->payexLogger           = $payexLogger;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender           = $orderSender;
        $this->orderFactory          = $orderFactory;

        $this->psp              = $psp;
        $this->payexTransaction = $payexTransaction;

    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        // Init Logger
        $writer = new Stream(BP . '/var/log/payex_psp_ipn.log');
        $logger = new Logger();
        $logger->addWriter($writer);

        $raw_body = file_get_contents('php://input');

        // @todo Important note: Add security check

        // Log requested params for Debug
        $logger->info(sprintf('IPN: Initialized %s from %s', $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR']));
        $logger->info(sprintf('Incoming Callback. Post data: %s', var_export($raw_body, true)));

        /** @var \Magento\Framework\Controller\Result\Raw $result */
        $result = $this->rawResultFactory->create();

        $order_id = $this->getRequest()->getParam('order_id');
        if (empty($order_id)) {
            $result->setStatusHeader('400', '1.1', 'Error');
            $result->setContents('Order Id required');

            return $result;
        }

        // Decode raw body
        $data = @json_decode($raw_body, true);

        try {
            if (!isset($data['payment']) || !isset($data['payment']['id'])) {
                throw new \Exception('Error: Invalid payment value');
            }

            if (!isset($data['transaction']) || !isset($data['transaction']['number'])) {
                throw new \Exception('Error: Invalid transaction number');
            }

            // Load Order ID
            $order = $this->orderFactory->create()->loadByIncrementId($order_id);
            if (!$order->getId()) {
                throw new \Exception(sprintf('Error: Failed to get order by ID %s', $order_id));
            }

            /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
            $method = $order->getPayment()->getMethodInstance();

            // Override PSP helper
            $this->psp = $method->getPsp();

            // Get Order by Payment Id
            $order_id = $order->getIncrementId();

            $payment_id = $order->getPayment()->getAdditionalInformation('payex_payment_id');
            if (empty($payment_id)) {
                throw new \Exception('Error: No payment ID');
            }

            // Fetch transactions list
            $result = $this->psp->request('GET', $payment_id . '/transactions');
            $transactions = $result['transactions']['transactionList'];

            // Import transactions
            $this->payexTransaction->import_transactions($transactions, $order_id);

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

            $result->setStatusHeader('400', '1.1', sprintf('IPN: %s', $e->getMessage()));
            $result->setContents(sprintf('IPN: %s', $e->getMessage()));

            return $result;
        }

        $result->setStatusHeader('200', '1.1', 'OK');
        $result->setContents('OK');

        return $result;
    }
}
