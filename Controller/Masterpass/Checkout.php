<?php

namespace PayEx\Payments\Controller\Masterpass;

use Magento\Sales\Model\Order\Payment\Transaction;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\Exception\LocalizedException;

class Checkout extends \Magento\Framework\App\Action\Action
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
    ) {
    
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
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->session->getQuote();
        try {
            if (!$quote->hasItems()) {
                throw new LocalizedException(__('You don\'t have any items in your cart'));
            }
            if (!$quote->getGrandTotal()) {
                throw new LocalizedException(__('Order total is too small'));
            }

            // Set Payment Method
            //$quote->setCheckoutMethod('payex_masterpass');

            // Update totals
            $quote->collectTotals();

            // Create an Order ID for the customer's quote
            $quote->reserveOrderId()->save();
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $this->_redirect('checkout/cart');
            return;
        }

        $order_id = $quote->getReservedOrderId();

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $this->_objectManager->get('PayEx\Payments\Model\Method\MasterPass');

        // Get Currency code
        $currency_code = $quote->getQuoteCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = $method->getConfigData('transactiontype');

        // Get Additional Values
        $additional = 'USEMASTERPASS=1&RESPONSIVE=1&SHOPPINGCARTXML=' .
            urlencode($this->payexHelper->getQuteShoppingCartXML($quote));

        // Language
        $language = $method->getConfigData('language');
        if (empty($language)) {
            $language = $this->payexHelper->getLanguage();
        }

        // Get Amount
        $amount = $quote->getGrandTotal();

        // Init PayEx Environment
        $accountnumber = $method->getConfigData('accountnumber');
        $encryptionkey = $method->getConfigData('encryptionkey');
        $debug = (bool)$method->getConfigData('debug');
        $this->payexHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);

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
            'clientIdentifier' => 'USERAGENT=' . $this->_request->getServer('HTTP_USER_AGENT'),
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => $this->urlBuilder->getUrl('payex/masterpass/order', [
                '_secure' => $this->getRequest()->isSecure()
            ]),
            'view' => 'CREDITCARD',
            'agreementRef' => '',
            'cancelUrl' => $this->urlBuilder->getUrl('payex/cc/cancel', ['_secure' => $this->getRequest()->isSecure()]),
            'clientLanguage' => $language
        ];
        $result = $this->payexHelper->getPx()->Initialize8($params);
        $this->payexLogger->info('PxOrder.Initialize8', $result);

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);

            // Restore the quote
            $this->session->restoreQuote();
            $this->messageManager->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        //$order_ref = $result['orderRef'];
        $redirectUrl = $result['redirectUrl'];

        // Redirect to PayEx
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($redirectUrl);
        return $resultRedirect;
    }

    /**
     * Get order object
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        $incrementId = $this->getCheckout()->getLastRealOrderId();
        $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
        return $orderFactory->create()->loadByIncrementId($incrementId);
    }

    /**
     * Get Checkout Session
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }
}
