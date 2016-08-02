<?php

namespace PayEx\Payments\Model\Sales\Total\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

class Fee extends AbstractTotal
{

    /**
     * Collect
     * @param Creditmemo $creditmemo
     * @return $this
     */
    public function collect(Creditmemo $creditmemo)
    {
        // Init totals
        $creditmemo->setBasePayexPaymentFee(0);
        $creditmemo->setBasePayexPaymentFeeTax(0);
        $creditmemo->setPayexPaymentFee(0);
        $creditmemo->setPayexPaymentFeeTax(0);

        $order = $creditmemo->getOrder();
        $fee = $order->getPayexPaymentFeeInvoiced() - $order->getPayexPaymentFeeRefunded();
        $baseFee = $order->getBasePayexPaymentFeeInvoiced() - $order->getBasePayexPaymentFeeRefunded();
        $feeTax = $order->getPayexPaymentFeeTaxInvoiced() - $order->getPayexPaymentFeeTaxRefunded();
        $baseFeeTax = $order->getBasePayexPaymentFeeTaxInvoiced() - $order->getBasePayexPaymentFeeTaxRefunded();

        if ($fee > 0) {
            $creditmemo->setPayexPaymentFee($fee);
            $creditmemo->setBasePayexPaymentFee($baseFee);
            $creditmemo->setPayexPaymentFeeTax($feeTax);
            $creditmemo->setBasePayexPaymentFeeTax($baseFeeTax);

            //$creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $feeTax);
            //$creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $baseFeeTax);

            $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $fee);
            $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseFee);

            $order->setPayexPaymentFeeRefunded($fee)
                ->setBasePayexPaymentFeeRefunded($baseFee)
                ->setPayexPaymentFeeTaxRefunded($feeTax)
                ->setBasePayexPaymentFeeTaxRefunded($baseFeeTax);
        }

        return $this;
    }
}
