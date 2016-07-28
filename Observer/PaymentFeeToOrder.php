<?php

namespace PayEx\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;

class PaymentFeeToOrder implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        // Copy Payment Fee from Quote to Order
        $order->setBasePayexPaymentFee($quote->getBasePayexPaymentFee());
        $order->setBasePayexPaymentFeeTax($quote->getBasePayexPaymentFeeTax());
        $order->setPayexPaymentFee($quote->getPayexPaymentFee());
        $order->setPayexPaymentFeeTax($quote->getPayexPaymentFeeTax());

        return $this;
    }
}
