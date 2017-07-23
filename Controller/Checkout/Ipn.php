<?php

namespace PayEx\Payments\Controller\Checkout;

use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order\Payment\Transaction;

class Ipn extends Action
{
    /**
     * @var \PayEx\Payments\Helper\Data
     */
    private $payexHelper;

    /**
     * @var \PayEx\Payments\Helper\Checkout
     */
    private $pxCheckoutHelper;

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
    protected $quoteManagement;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;

    /**
     * @var Transaction\Repository
     */
    protected $transactionRepository;

    /**
     * Constructor
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\RawFactory $rawResultFactory
     * @param \PayEx\Payments\Helper\Data                     $payexHelper
     * @param \PayEx\Payments\Helper\Checkout                 $pxCheckoutHelper
     * @param \Magento\Framework\Filesystem\Io\File           $iofile
     * @param \Magento\Quote\Model\QuoteFactory               $quoteFactory
     * @param \Magento\Quote\Api\CartManagementInterface      $quoteManagement
     * @param \Magento\Sales\Api\Data\OrderInterface          $order
     * @param \Magento\Checkout\Helper\Data                   $checkoutHelper
     * @param Transaction\Repository                          $transactionRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $rawResultFactory,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Helper\Checkout $pxCheckoutHelper,
        \Magento\Framework\Filesystem\Io\File $iofile,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        Transaction\Repository $transactionRepository
    ) {
    
        parent::__construct($context);
        $this->rawResultFactory = $rawResultFactory;
        $this->payexHelper = $payexHelper;
        $this->pxCheckoutHelper = $pxCheckoutHelper;
        $this->iofile = $iofile;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->order = $order;
        $this->checkoutHelper = $checkoutHelper;
        $this->transactionRepository = $transactionRepository;
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
        /** @var \Magento\Framework\Controller\Result\Raw $result */
        $result = $this->rawResultFactory->create();

        // Init Logger
        $writer = new Stream(BP . '/var/log/payex_checkout.log');
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

        // Load quote
        $quote = $this->quoteFactory->create()->load($reference);
        if (!$quote->getId()) {
            $logger->crit('Failed to load quote', [$reference]);
            $result->setStatusHeader('200', '1.1', 'FAILED');
            $result->setContents('FAILED');
            return $result;
        }

        /** @var \PayEx\Payments\Model\Method\Checkout $method */
        $methodInstance = $quote->getPayment()->getMethodInstance();

        $this->pxCheckoutHelper->setMerchantToken($methodInstance->getConfigData('merchant_token'));
        $this->pxCheckoutHelper->setBackendApiUrl('https://api.externalintegration.payex.com');

        $url = $this->pxCheckoutHelper->getBackendApiUrl() . $data['transaction']['id'];
        try {
            $result = $this->pxCheckoutHelper->request('GET', $url);
        } catch (\Exception $e) {
            $logger->crit('Failed to load transaction data', [$url, $e->getMessage()]);
            $result->setStatusHeader('200', '1.1', 'FAILED');
            $result->setContents('FAILED');
            return $result;
        }

        // Get Action
        $action = '';
        if ( isset( $result['authorization'] ) ) {
            $action = 'authorization';
        } elseif ( isset( $result['capture'] ) ) {
            $action = 'capture';
        } elseif ( isset( $result['reversal'] ) ) {
            $action = 'reversal';
        }

        // Check transaction state
        if ($result[$action]['transaction']['state'] !== 'Completed') {
            $message = isset( $result[$action]['transaction']['failedReason'] ) ? $result[$action]['transaction']['failedReason'] : __( 'Transaction failed');
            $logger->crit('IPN: Error: Transaction failed', [$message]);
            $result->setStatusHeader('200', '1.1', 'FAILED');
            $result->setContents('FAILED');
            return $result;
        }

        $action = 'authorization';

        switch ($action) {
            case 'authorization':
                $logger->info('IPN: Action: Authorization', [$reference]);

                // Get Order Id
                $order_id = $quote->getReservedOrderId();
                if (!$order_id) {
                    // Reserve Order
                    try {
                        // Set Payment Method
                        //$quote->setCheckoutMethod('payex_checkout');

                        // Update totals
                        $quote->collectTotals();

                        // Create an Order ID for the customer's quote
                        $quote->reserveOrderId()->save();
                    } catch (\Exception $e) {
                        $logger->crit('Failed to reserve order', [$quote->getId(), $e->getMessage()]);
                        $result->setStatusHeader('200', '1.1', 'FAILED');
                        $result->setContents('FAILED');
                        return $result;
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
                        $result->setStatusHeader('200', '1.1', 'FAILED');
                        $result->setContents('FAILED');
                        return $result;
                    }
                }

                $logger->info('IPN: Authorization: success', [$reference]);
                break;
            case 'capture':
                $logger->info('IPN: Action: Capture', [$reference]);

                // Load order
                /** @var \Magento\Sales\Model\Order $order */
                $order = $this->order->loadByAttribute('quote_id', $quote->getId());
                if (!$order->getId()) {
                    $logger->crit('Failed to get order by quote', [$quote->getId()]);
                    $result->setStatusHeader('200', '1.1', 'FAILED');
                    $result->setContents('FAILED');
                    return $result;
                }

                if (!$order->hasInvoices()) {
                    $methodInstance = $order->getPayment()->getMethodInstance();
                    $methodInstance->capture($order->getPayment(), $order->getGrandTotal());
                    $logger->info('IPN: Capture: success', [$reference]);
                }
                break;
            case 'reversal':
                $logger->info('IPN: Action: Reversal', [$reference]);

                // Load order
                /** @var \Magento\Sales\Model\Order $order */
                $order = $this->order->loadByAttribute('quote_id', $quote->getId());
                if (!$order->getId()) {
                    $logger->crit('Failed to get order by quote', [$quote->getId()]);
                    $result->setStatusHeader('200', '1.1', 'FAILED');
                    $result->setContents('FAILED');
                    return $result;
                }

                // @todo
                break;
        }

        $result->setContents('OK');
        return $result;
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
}
