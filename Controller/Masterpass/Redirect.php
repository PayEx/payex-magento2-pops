<?php

namespace PayEx\Payments\Controller\Masterpass;

class Redirect extends \Magento\Framework\App\Action\Action
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
     * Redirect constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger
    ) {
    
        parent::__construct($context);

        $this->urlBuilder = $context->getUrl();
        $this->session = $session;
        $this->payexHelper = $payexHelper;
        $this->payexLogger = $payexLogger;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        // Load Order
        $order = $this->getOrder();
        if (!$order->getId()) {
            $this->session->restoreQuote();
            $this->messageManager->addError(__('No order for processing found'));
            $this->_redirect('checkout/cart');
            return;
        }

        $order_id = $order->getIncrementId();

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = $method->getConfigData('transactiontype');

        // Get Additional Values
        $additional = 'USEMASTERPASS=1&RESPONSIVE=1&SHOPPINGCARTXML=' . urlencode($this->payexHelper->getOrderShoppingCartXML($order));

        // Language
        $language = $method->getConfigData('language');
        if (empty($language)) {
            $language = $this->payexHelper->getLanguage();
        }

        // Get Amount
        $amount = $order->getGrandTotal();

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
            'returnUrl' => $this->urlBuilder->getUrl('payex/masterpass/success', [
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

            // Cancel order
            $order->cancel();
            $order->addStatusHistoryComment($message, \Magento\Sales\Model\Order::STATE_CANCELED);
            $order->save();

            // Restore the quote
            $this->session->restoreQuote();
            $this->messageManager->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        //$order_ref = $result['orderRef'];
        $redirectUrl = $result['redirectUrl'];

        // Set Pending Payment status
        $order->addStatusHistoryComment(
            __('The customer was redirected to PayEx.'),
            \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT
        );
        $order->save();

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
