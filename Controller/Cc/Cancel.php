<?php

namespace PayEx\Payments\Controller\Cc;

class Cancel extends \Magento\Framework\App\Action\Action
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
     * Cancel constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Checkout\Model\Session $session
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $session,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger
    ) {
        parent::__construct($context);

        $this->urlBuilder = $urlBuilder;
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
        $message = __('Order canceled by user');
        $order = $this->getOrder();
        if ($order->getId()) {
            $order->cancel();
            $order->addStatusHistoryComment($message);
            $order->save();
        }

        // Restore the quote
        $this->session->restoreQuote();
        $this->messageManager->addError($message);
        $this->_redirect('checkout/cart');
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