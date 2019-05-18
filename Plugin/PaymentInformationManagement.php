<?php

namespace PayEx\Payments\Plugin;

use Magento\Framework\Exception\CouldNotSaveException;

class PaymentInformationManagement
{
    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    private $payexHelper;

    /**
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param \Psr\Log\LoggerInterface $logger
     * @param \PayEx\Payments\Helper\Data $payexHelper
     */
    public function __construct(
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Psr\Log\LoggerInterface $logger,
        \PayEx\Payments\Helper\Data $payexHelper
    ) {

        $this->checkoutHelper = $checkoutHelper;
        $this->cartManagement = $cartManagement;
        $this->logger = $logger;
        $this->payexHelper = $payexHelper;
    }

    /**
     * Save Bank Id from payment additional data to session
     * @param \Magento\Checkout\Model\PaymentInformationManagement $subject
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface $billingAddress
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress
    ) {
    
        if ($paymentMethod->getMethod() === \PayEx\Payments\Model\Method\PartPayment::METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->checkoutHelper->getCheckout()->setPayexSSN(
                isset($additionalData['social_security_number']) ? $additionalData['social_security_number'] : null
            );
        }
    }

    /**
     * Set payment information and place order for a specified cart.
     *
     * Override this method to get correct exceptions instead
     * "An error occurred on the server. Please try to place the order again."
     *
     * @param \Magento\Checkout\Model\PaymentInformationManagement $subject
     * @param \Closure $proceed
     * @param $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @return int Order ID.
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
        \Closure $proceed,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress
    ) {

        /** @see \Magento\Checkout\Model\PaymentInformationManagement::savePaymentInformationAndPlaceOrder() */
        $subject->savePaymentInformation($cartId, $paymentMethod, $billingAddress);
        try {
            $orderId = $this->cartManagement->placeOrder($cartId);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new CouldNotSaveException(
                __($e->getMessage()),
                $e
            );
        } catch (\Exception $e) {
            if (version_compare($this->payexHelper->getMageVersion(), '2.3', '<')) {
                throw new CouldNotSaveException(
                    __($e->getMessage()),
                    $e
                );
            } else {
                $this->logger->critical($e);
                throw new CouldNotSaveException(
                    __('A server error stopped your order from being placed. Please try to place your order again.'),
                    $e
                );
            }
        }

        return $orderId;
    }
}
