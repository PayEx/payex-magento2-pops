<?php

namespace PayEx\Payments\Controller\Masterpass;

use Magento\Sales\Model\Order\Payment\Transaction;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\Exception\LocalizedException;

class Order extends \Magento\Framework\App\Action\Action
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
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $quoteManagement;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Success constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \PayEx\Payments\Helper\Data $payexHelper
     * @param \PayEx\Payments\Logger\Logger $payexLogger
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Quote\Api\CartManagementInterface $quoteManagement
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \PayEx\Payments\Helper\Data $payexHelper,
        \PayEx\Payments\Logger\Logger $payexLogger,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Customer\Model\Session $customerSession
    ) {
    
        parent::__construct($context);

        $this->urlBuilder = $context->getUrl();
        $this->session = $session;
        $this->payexHelper = $payexHelper;
        $this->payexLogger = $payexLogger;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
        $this->quoteManagement = $quoteManagement;
        $this->customerSession = $customerSession;
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

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->session->getQuote();
        if (!$quote->getGrandTotal()) {
            $this->messageManager->addError(__('Order total is too small'));
            $this->_redirect('checkout/cart');
            return;
        }

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $this->_objectManager->get('PayEx\Payments\Model\Method\MasterPass');

        // Init PayEx Environment
        $accountnumber = $method->getConfigData('accountnumber');
        $encryptionkey = $method->getConfigData('encryptionkey');
        $debug = (bool)$method->getConfigData('debug');
        $this->payexHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);

        // Call PxOrder.GetApprovedDeliveryAddress
        $params = [
            'accountNumber' => '',
            'orderRef' => $orderRef
        ];
        $result = $this->payexHelper->getPx()->GetApprovedDeliveryAddress($params);
        $this->payexLogger->info('PxOrder.GetApprovedDeliveryAddress', $result);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);

            // Restore the quote
            $this->session->restoreQuote();
            $this->messageManager->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        // Billing Address
        $billingAddress = [
            'firstname' => $result['firstName'],
            'lastname' => $result['lastName'],
            'company' => '',
            'email' => $result['eMail'],
            'street' => [
                $result['address1'],
                trim($result['address2'] . ' ' . $result['address3'])
            ],
            'city' => ucfirst($result['city']),
            'region_id' => '',
            'region' => '',
            'postcode' => str_replace(' ', '', $result['postalCode']),
            'country_id' => $result['country'],
            'telephone' => $result['phone'],
            'fax' => '',
            'customer_password' => '',
            'confirm_password' => '',
            'save_in_address_book' => '0',
            'use_for_shipping' => '1',
        ];

        // Set Billing Address
        $quote->getBillingAddress()
            ->addData($billingAddress);

        // Set Shipping Address
        $shipping = $quote->getShippingAddress()
            ->addData($billingAddress);

        // Set Shipping Method
        if (!$quote->isVirtual()) {
            // @todo Improve this method: allow customer select shipping method
            $shipping_method = $method->getConfigData('shipping_method');
            $shipping->setCollectShippingRates(true)->collectShippingRates()
                ->setShippingMethod($shipping_method);

            //$quote->getShippingAddress()->setShippingMethod($shipping_method);
        }

        // Update totals
        $quote->collectTotals();

        // Use Guest Checkout Method
        $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST)
            ->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);

        // Set Payment Method
        $quote->getPayment()->importData(['method' => \PayEx\Payments\Model\Method\MasterPass::METHOD_CODE]);

        // Save Order
        try {
            $quote->save();
            $order = $this->quoteManagement->submit($quote);
        } catch (Exception $e) {
            // Restore the quote
            $this->session->restoreQuote();
            $this->messageManager->addError($e->getMessage());
            $this->_redirect('checkout/cart');
            return;
        }

        $this->session
            ->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();

        if ($order) {
            $this->_eventManager->dispatch(
                'checkout_type_onepage_save_order_after',
                ['order' => $order, 'quote' => $quote]
            );

            // add order information to the session
            $this->session
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastOrderStatus($order->getStatus());
        }

        $this->_eventManager->dispatch(
            'checkout_submit_all_after',
            [
                'order' => $order,
                'quote' => $quote
            ]
        );

        // MasterPass Success page
        $redirectUrl = $this->urlBuilder->getUrl('payex/masterpass/success', [
            '_query' => [
                'orderRef' => $orderRef
            ],
            '_secure' => $this->getRequest()->isSecure()
        ]);

        // Redirect to Success
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
