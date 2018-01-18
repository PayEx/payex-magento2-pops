<?php

namespace PayEx\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\ObjectManager;
use PayEx\Payments\Model\Method\Bankdebit;
use PayEx\Payments\Model\Method\MasterPass;
use PayEx\Payments\Model\Psp\Checkout as PxCheckout;

class CcConfigProvider implements ConfigProviderInterface
{
    const FRONTEND_URL_PROD = 'https://checkout.payex.com/js/payex-checkout.min.js';
    const FRONTEND_URL_TEST = 'https://checkout.externalintegration.payex.com/js/payex-checkout.min';

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param PaymentHelper $paymentHelper
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        PaymentHelper $paymentHelper,
        UrlInterface $urlBuilder
    ) {
        $this->appState = $context->getAppState();
        $this->checkoutHelper = $checkoutHelper;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                Bankdebit::METHOD_CODE => [],
                MasterPass::METHOD_CODE => []
            ],
        ];

        /** @var Bankdebit $method */
        $method = $this->paymentHelper->getMethodInstance(Bankdebit::METHOD_CODE);
        if ($method->isAvailable()) {
            $banks = ObjectManager::getInstance()->get('PayEx\Payments\Block\Bankdebit\Banks')->getAvailableBanks();
            $config['payment'][Bankdebit::METHOD_CODE]['banks'] = $banks;
        }

        /** @var \PayEx\Payments\Model\Method\MasterPass $method */
        $method = $this->paymentHelper->getMethodInstance(MasterPass::METHOD_CODE);
        if ($method->isAvailable()) {
            $config['payment'][MasterPass::METHOD_CODE]['redirectUrl'] = $method->getCheckoutRedirectUrl();
        }

        /** @var \PayEx\Payments\Model\Method\Checkout $method */
        $method = $this->paymentHelper->getMethodInstance(PxCheckout::METHOD_CODE);
        if ($method->isAvailable()) {
            // @todo Add Store Id
            $script = $method->getConfigData('debug') ? self::FRONTEND_URL_TEST : self::FRONTEND_URL_PROD;
            $config['payment'][PxCheckout::METHOD_CODE]['frontend_script'] = $script;

            try {
                $payment_id = $this->checkoutHelper->getQuote()->getPayment()->getAdditionalInformation('payex_payment_session_id');
                $config['payment'][PxCheckout::METHOD_CODE]['payment_id'] = $payment_id;
            } catch (\Exception $e) {
                //
            }
        }

        return $config;
    }
}
