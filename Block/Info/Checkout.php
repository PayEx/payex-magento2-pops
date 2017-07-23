<?php

namespace PayEx\Payments\Block\Info;

class Checkout extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'PayEx_Payments::info/checkout.phtml';

    /**
     * Render as PDF
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('PayEx_Payments::info/pdf/checkout.phtml');
        return $this->toHtml();
    }
}
