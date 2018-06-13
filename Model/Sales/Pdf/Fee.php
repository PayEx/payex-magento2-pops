<?php

namespace PayEx\Payments\Model\Sales\Pdf;

use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use PayEx\Payments\Model\Fee\Config;
use PayEx\Payments\Model\Method\Financing;
use PayEx\Payments\Model\Method\PartPayment;

class Fee extends DefaultTotal
{
    /**
     * @var Config
     */
    private $feeConfig;

    /**
     * @param \Magento\Tax\Helper\Data $taxHelper
     * @param \Magento\Tax\Model\Calculation $taxCalculation
     * @param \Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory $ordersFactory
     * @param Config $feeConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Tax\Model\Calculation $taxCalculation,
        \Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory $ordersFactory,
        Config $feeConfig,
        array $data = []
    ) {
        $this->feeConfig = $feeConfig;
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    /**
     * Get array of arrays with totals information for display in PDF
     * array(
     *  $index => array(
     *      'amount'   => $amount,
     *      'label'    => $label,
     *      'font_size'=> $font_size
     *  )
     * )
     * @return array
     */
    public function getTotalsForDisplay()
    {
        $order = $this->getOrder();

        // Check is fee allowed for payment method
        if (!in_array($order->getPayment()->getMethod(), [
            Financing::METHOD_CODE,
            PartPayment::METHOD_CODE,
            \PayEx\Payments\Model\Psp\Invoice::METHOD_CODE
        ])) {
            return [];
        }

        $fee = $order->getPayexPaymentFee();
        $feeInclTax = $order->getPayexPaymentFee() + $order->getPayexPaymentFeeTax();

        if ($fee <= 0) {
            return [];
        }

        $fontSize = $this->getFontSize() ? $this->getFontSize() : 7;
        if ($this->displaySalesFeeBoth()) {
            $totals = [
                [
                    'amount' => $this->getAmountPrefix() . $order->formatPriceTxt($fee),
                    'label' => __('Payment Fee') . ' ' . __('(Excl.Tax)') . ':',
                    'font_size' => $fontSize,
                ],
                [
                    'amount' => $this->getAmountPrefix() . $order->formatPriceTxt($feeInclTax),
                    'label' => __('Payment Fee') . ' ' . __('(Incl.Tax)') . ':',
                    'font_size' => $fontSize
                ],
            ];
        } elseif ($this->displaySalesFeeInclTax()) {
            $totals = [
                [
                    'amount' => $this->getAmountPrefix() . $order->formatPriceTxt($feeInclTax),
                    'label' => __('Payment Fee') . ':',
                    'font_size' => $fontSize,
                ],
            ];
        } else {
            $totals = [
                [
                    'amount' => $this->getAmountPrefix() . $order->formatPriceTxt($fee),
                    'label' => __('Payment Fee') . ':',
                    'font_size' => $fontSize,
                ],
            ];
        }

        return $totals;
    }

    /**
     * Check if display sales prices fee included and excluded tax
     * @return mixed
     */
    public function displaySalesFeeBoth()
    {
        return $this->feeConfig->displaySalesFeeBoth($this->getOrder()->getStore());
    }

    /**
     * Check if display sales prices fee included tax
     * @return mixed
     */
    public function displaySalesFeeInclTax()
    {
        return $this->feeConfig->displaySalesFeeInclTax($this->getOrder()->getStore());
    }
}
