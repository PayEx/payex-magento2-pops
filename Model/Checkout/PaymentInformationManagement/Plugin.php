<?php

namespace PayEx\Payments\Model\Checkout\PaymentInformationManagement;

class Plugin
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        \Magento\Checkout\Model\Session $session
    )
    {
        $this->session = $session;
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
    )
    {
        if ($paymentMethod->getMethod() === \PayEx\Payments\Model\Method\Bankdebit::METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->session->setBankId(isset($additionalData['bank_id']) ? $additionalData['bank_id'] : null);
        }

        if ($paymentMethod->getMethod() === \PayEx\Payments\Model\Method\PartPayment::METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->session->setPayexSSN(isset($additionalData['social_security_number']) ? $additionalData['social_security_number'] : null);
        }
    }
}
