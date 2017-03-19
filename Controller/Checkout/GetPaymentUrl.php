<?php

namespace PayEx\Payments\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;

class GetPaymentUrl extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * Constructor
     * @param \Magento\Framework\App\Action\Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        JsonFactory $resultJsonFactory,
        \Magento\Checkout\Model\Session $session
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->session = $session;

        parent::__construct($context);
    }

    /**
     * View CMS page action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $redirectUrl = $this->session->getPayexRedirectUrl();
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
