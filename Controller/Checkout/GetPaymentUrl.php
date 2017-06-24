<?php

namespace PayEx\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;

class GetPaymentUrl extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * Constructor
     * @param \Magento\Framework\App\Action\Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        JsonFactory $resultJsonFactory,
        \Magento\Checkout\Helper\Data $checkoutHelper
    ) {
    
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutHelper = $checkoutHelper;

        parent::__construct($context);
    }

    /**
     * View CMS page action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $redirectUrl = $this->checkoutHelper->getCheckout()->getPayexRedirectUrl();
        if (!empty($redirectUrl)) {
            $data = [
                'redirect_url' => $redirectUrl
            ];
        } else {
            $data = [
                'error' => __('Failed to get redirect url')
            ];
        }

        /** @var \Magento\Framework\Controller\Result\Json $json */
        $json = $this->resultJsonFactory->create();
        return $json->setData($data);
    }
}
