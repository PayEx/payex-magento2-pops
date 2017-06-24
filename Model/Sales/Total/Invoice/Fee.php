<?php

namespace PayEx\Payments\Model\Sales\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class Fee extends AbstractTotal
{
    /**
     * Collect
     * @param Invoice $invoice
     * @return $this
     */
    public function collect(Invoice $invoice)
    {
        // Init totals
        $invoice->setBasePayexPaymentFee(0);
        $invoice->setBasePayexPaymentFeeTax(0);
        $invoice->setPayexPaymentFee(0);
        $invoice->setPayexPaymentFeeTax(0);

        $order = $invoice->getOrder();
        if ($order->getBasePayexPaymentFee()) {
            // Invoice values
            $invoice->setBasePayexPaymentFee($order->getBasePayexPaymentFee());
            $invoice->setBasePayexPaymentFeeTax($order->getBasePayexPaymentFeeTax());
            $invoice->setPayexPaymentFee($order->getPayexPaymentFee());
            $invoice->setPayexPaymentFeeTax($order->getPayexPaymentFeeTax());

            // Order Values
            $order->setBasePayexPaymentFeeInvoiced($order->getBasePayexPaymentFee());
            $order->setBasePayexPaymentFeeTaxInvoiced($order->getBasePayexPaymentFeeTax());
            $order->setPayexPaymentFeeInvoiced($order->getPayexPaymentFee());
            $order->setPayexPaymentFeeTaxInvoiced($order->getPayexPaymentFeeTax());

            // Update totals
            //$invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $order->getBasePayexPaymentFee());
            //$invoice->setGrandTotal($invoice->getGrandTotal() + $order->getPayexPaymentFee());
            $invoice->setBaseGrandTotal($order->getBaseGrandTotal());
            $invoice->setGrandTotal($order->getGrandTotal());
            $invoice->setTaxAmount($order->getTaxAmount());
            $invoice->setBaseTaxAmount($order->getBaseTaxAmount());
        }

        return $this;
    }
}
