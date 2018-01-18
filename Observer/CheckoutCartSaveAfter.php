<?php

namespace PayEx\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use PayEx\Payments\Model\Psp\Checkout as PxCheckout;

class CheckoutCartSaveAfter implements ObserverInterface
{
    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;

    /**
     * @var \PayEx\Payments\Helper\Psp
     */
    protected $psp;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * Constructor
     *
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \PayEx\Payments\Helper\Checkout $pxCheckoutHelper
     * @param PaymentHelper $paymentHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     */
    public function __construct(
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \PayEx\Payments\Helper\Psp $psp,
        PaymentHelper $paymentHelper,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->checkoutHelper = $checkoutHelper;
        $this->psp = $psp;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Checkout\Model\Cart $cart */
        $cart = $observer->getEvent()->getCart();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $cart->getQuote();

        try {
            /** @var \PayEx\Payments\Model\Method\Checkout $method */
            $methodInstance = $this->paymentHelper->getMethodInstance(PxCheckout::METHOD_CODE);
            if ($methodInstance->isAvailable()) {
                // @todo Define Store Id
                // Init Api helper
                $this->psp->setMerchantToken($methodInstance->getConfigData('merchant_token'));
                $this->psp->setBackendApiUrl($methodInstance->getConfigData('debug') ?
                    \PayEx\Payments\Model\Psp\AbstractPsp::BACKEND_API_URL_TEST
                    : \PayEx\Payments\Model\Psp\AbstractPsp::BACKEND_API_URL_PROD);

                $amount = $quote->getGrandTotal();
                if ($amount > 0) {
                    $reference = 'quote_' . $quote->getId();
                    $currency = $quote->getQuoteCurrencyCode();

                    // Init Payment Session
                    $payment_session_url = $this->psp->init_payment_session();

                    // Init Payment
                    $result = $this->psp->request('POST', $payment_session_url,
                        [
                            'amount'      => round($amount, 2),
                            'vatAmount'   => 0,
                            'currency'    => $currency,
                            'callbackUrl' => $this->urlBuilder->getUrl('payex/checkout/checkoutipn', ['_query' => ['reference' => $reference]]),
                            'reference'   => $reference,
                            'culture'     => $methodInstance->getConfigData('culture'),
                            'acquire'     => [
                                'email', 'mobilePhoneNumber', 'shippingAddress'
                            ]
                        ]
                    );

                    $quote->getPayment()->setAdditionalInformation('payex_payment_session', $payment_session_url);
                    $quote->getPayment()->setAdditionalInformation('payex_payment_session_id', isset($result['id']) ? $result['id'] : null);
                    $quote->getPayment()->save();
                }
            }
        } catch (\Exception $e) {
            // @todo Log Exception
        }

        return $this;
    }
}
