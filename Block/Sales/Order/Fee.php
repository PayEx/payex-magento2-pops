<?php

namespace PayEx\Payments\Block\Sales\Order;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PayEx\Payments\Model\Fee\Config;
use Magento\Framework\DataObject;
use PayEx\Payments\Model\Method\Financing;
use PayEx\Payments\Model\Method\PartPayment;

class Fee extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Config
     */
    private $feeConfig;

    /**
     * Fee constructor.
     * @param Template\Context $context
     * @param array $data
     * @param Config $feeConfig
     */
    public function __construct(
        Template\Context $context,
        array $data,
        Config $feeConfig
    ) {
    
        parent::__construct($context, $data);

        $this->scopeConfig = $context->getScopeConfig();
        $this->feeConfig = $feeConfig;
    }

    /**
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $source = $parent->getSource();
        $order = $parent->getOrder();

        // Check is fee allowed for payment method
        if (!in_array($order->getPayment()->getMethod(), [
            Financing::METHOD_CODE,
            PartPayment::METHOD_CODE
        ])) {
            return $this;
        }

        if ($source->getBasePayexPaymentFee() > 0) {
            if ($this->displaySalesFeeBoth()) {
                $parent->addTotal(
                    new DataObject([
                        'code' => 'payex_payment_fee_with_tax',
                        'strong' => false,
                        'value' => $order->getPayexPaymentFee() + $order->getPayexPaymentFeeTax(),
                        'label' => __('Payment Fee') . ' ' . __('(Incl.Tax)'),
                    ]),
                    'grand_total'
                );

                $parent->addTotal(
                    new DataObject([
                        'code' => 'payex_payment_fee',
                        'strong' => false,
                        'value' => $order->getPayexPaymentFee(),
                        'label' => __('Payment Fee') . ' ' . __('(Excl.Tax)'),
                    ]),
                    'payex_payment_fee_with_tax'
                );
            } elseif ($this->displaySalesFeeInclTax()) {
                $parent->addTotal(
                    new DataObject([
                        'code' => 'payex_payment_fee_with_tax',
                        'strong' => false,
                        'value' => $order->getPayexPaymentFee() + $order->getPayexPaymentFeeTax(),
                        'label' => __('Payment Fee') . ' ' . __('(Incl.Tax)'),
                    ]),
                    'grand_total'
                );
            } else {
                $parent->addTotal(
                    new DataObject([
                        'code' => 'payex_payment_fee',
                        'strong' => false,
                        'value' => $order->getPayexPaymentFee(),
                        'label' => __('Payment Fee') . ' ' . __('(Excl.Tax)'),
                    ]),
                    'grand_total'
                );
            }
        }

        return $this;
    }

    /**
     * Check if display sales prices fee included and excluded tax
     * @return mixed
     */
    public function displaySalesFeeBoth()
    {
        return $this->feeConfig->displaySalesFeeBoth($this->getParentBlock()->getOrder()->getStore());
    }

    /**
     * Check if display sales prices fee included tax
     * @return mixed
     */
    public function displaySalesFeeInclTax()
    {
        return $this->feeConfig->displaySalesFeeInclTax($this->getParentBlock()->getOrder()->getStore());
    }
}
