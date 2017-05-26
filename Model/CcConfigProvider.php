<?php

namespace PayEx\Payments\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Directory\Helper\Data;
use Magento\Framework\UrlInterface;

class CcConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    protected $_config;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param PaymentHelper $paymentHelper
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        PaymentHelper $paymentHelper,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        UrlInterface $urlBuilder
    ) {
        $this->_appState = $context->getAppState();
        $this->_session = $session;
        $this->_storeManager = $storeManager;
        $this->_paymentHelper = $paymentHelper;
        $this->_localeResolver = $localeResolver;
        $this->_config = $config;
        $this->urlBuilder = $urlBuilder;
    }


    public function getConfig()
    {
        $config = [
            'payment' => [
                \PayEx\Payments\Model\Method\Bankdebit::METHOD_CODE => [],
                \PayEx\Payments\Model\Method\MasterPass::METHOD_CODE => []
            ],
            'payex' => [
                'payment_url' => $this->urlBuilder->getUrl('payex/checkout/getPaymentUrl'),
                'address_url' => $this->urlBuilder->getUrl('payex/checkout/getAddress')
            ]
        ];

        /** @var \PayEx\Payments\Model\Method\Bankdebit $method */
        $method = $this->_paymentHelper->getMethodInstance(\PayEx\Payments\Model\Method\Bankdebit::METHOD_CODE);
        if ($method->isAvailable()) {
            $banks = \Magento\Framework\App\ObjectManager::getInstance()->get('PayEx\Payments\Block\Bankdebit\Banks')->getAvailableBanks();
            $config['payment'] [\PayEx\Payments\Model\Method\Bankdebit::METHOD_CODE]['banks'] = $banks;
        }

        /** @var \PayEx\Payments\Model\Method\MasterPass $method */
        $method = $this->_paymentHelper->getMethodInstance(\PayEx\Payments\Model\Method\MasterPass::METHOD_CODE);
        if ($method->isAvailable()) {
            $config['payment'] [\PayEx\Payments\Model\Method\MasterPass::METHOD_CODE]['redirectUrl'] = $method->getCheckoutRedirectUrl();
        }

        return $config;
    }
}