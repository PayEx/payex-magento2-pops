<?php

namespace PayEx\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session;
use PayEx\Payments\Model\Psp\Vipps;

class PspConfigProvider implements ConfigProviderInterface
{

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
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param PaymentHelper $paymentHelper
     * @param UrlInterface $urlBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Session $session
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        PaymentHelper $paymentHelper,
        UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Session $session
    ) {
        $this->appState = $context->getAppState();
        $this->checkoutHelper = $checkoutHelper;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                Vipps::METHOD_CODE => []
            ],
        ];

        /** @var Vipps $method */
        $method = $this->paymentHelper->getMethodInstance(Vipps::METHOD_CODE);
        if ($method->isAvailable()) {
            $config['payment'][Vipps::METHOD_CODE]['checkout_method'] = $method->getConfigData(
                'checkout_method',
                $this->storeManager->getStore()->getId()
            );
        }

        return $config;
    }
}
