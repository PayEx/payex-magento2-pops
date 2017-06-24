<?php

namespace PayEx\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\CouldNotSaveException;

class PlaceOrder extends Action
{
    /**
     * @var CartManagementInterface
     */
    private $quoteManagement;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var ServiceInputProcessor
     */
    private $inputProcessor;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * Constructor
     * @param \Magento\Framework\App\Action\Context $context
     * @param CartManagementInterface $quoteManagement
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param ServiceInputProcessor $inputProcessor
     * @param OrderFactory $orderFactory
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        CartManagementInterface $quoteManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        ServiceInputProcessor $inputProcessor,
        OrderFactory $orderFactory,
        JsonFactory $resultJsonFactory
    ) {
    
        $this->quoteManagement = $quoteManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->inputProcessor = $inputProcessor;
        $this->orderFactory = $orderFactory;
        $this->resultJsonFactory = $resultJsonFactory;

        parent::__construct($context);
    }

    /**
     * View CMS page action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $payload = file_get_contents('php://input');
        $payload = json_decode($payload, true);
        $cartId = $payload['cartId'];
        $email = $payload['email'];

        // Check is order already placed
        $incrementId = $this->checkoutSession->getLastRealOrderId();
        if ($incrementId) {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if ($order->getId()) {
                // add order information to the session
                $this->checkoutSession
                    ->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());

                $data = [
                    'success' => true,
                    'order_id' => $order->getId(),
                    'increment_id' => $order->getIncrementId()
                ];

                /** @var \Magento\Framework\Controller\Result\Json $json */
                $json = $this->resultJsonFactory->create();
                return $json->setData($data);
            }
        }

        /** @var \Magento\Quote\Api\Data\PaymentInterface $pm */
        $pm = $this->inputProcessor->convertValue($payload['paymentMethod'], 'Magento\Quote\Api\Data\PaymentInterface');

        /** @var \Magento\Quote\Api\Data\AddressInterface $ba */
        $ba = $this->inputProcessor->convertValue($payload['billingAddress'], 'Magento\Quote\Api\Data\AddressInterface');

        try {
            if (!$this->customerSession->isLoggedIn()) {
                /** @var \Magento\Checkout\Model\GuestPaymentInformationManagement $info */
                $info = $this->_objectManager->create('Magento\Checkout\Model\GuestPaymentInformationManagement');
                $orderId = $info->savePaymentInformationAndPlaceOrder($cartId, $email, $pm, $ba);
            } else {
                /** @var \Magento\Checkout\Model\PaymentInformationManagement $info */
                $info = $this->_objectManager->create('Magento\Checkout\Model\PaymentInformationManagement');
                $orderId = $info->savePaymentInformationAndPlaceOrder($cartId, $pm, $ba);
            }
        } catch (CouldNotSaveException $e) {
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
                'responseText' => json_encode(['message' => $e->getMessage()])
            ];

            /** @var \Magento\Framework\Controller\Result\Json $json */
            $json = $this->resultJsonFactory->create();
            return $json->setData($data);
        }

        //$quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        //$cartId = $quoteIdMask->getQuoteId();

        // prepare session to success or cancellation page
        //$this->_checkoutSession->clearHelperData();

        // "last successful quote"
        //$this->_checkoutSession->getQuote();
        //$quoteId = $this->_checkoutSession->getQuote()->getId();
        //$this->_checkoutSession->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->load($orderId);

        // add order information to the session
        $this->checkoutSession
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());

        $data = [
            'success' => true,
            'order_id' => $order->getId(),
            'increment_id' => $order->getIncrementId()
        ];

        /** @var \Magento\Framework\Controller\Result\Json $json */
        $json = $this->resultJsonFactory->create();
        return $json->setData($data);
    }
}
