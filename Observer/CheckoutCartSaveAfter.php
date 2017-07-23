<?php

namespace PayEx\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use PayEx\Payments\Model\Method\Checkout as PxCheckout;

class CheckoutCartSaveAfter implements ObserverInterface
{
    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;

    /**
     * @var \PayEx\Payments\Helper\Checkout
     */
    protected $pxCheckoutHelper;

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
        \PayEx\Payments\Helper\Checkout $pxCheckoutHelper,
        PaymentHelper $paymentHelper,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->checkoutHelper = $checkoutHelper;
        $this->pxCheckoutHelper = $pxCheckoutHelper;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Checkout\Model\Cart $cart */
        $cart = $observer->getEvent()->getCart();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $cart->getQuote();

        /** @var \PayEx\Payments\Model\Method\Checkout $method */
        $methodInstance = $this->paymentHelper->getMethodInstance(PxCheckout::METHOD_CODE);
        if ($methodInstance->isAvailable()) {
            // @todo Define Store Id
            $this->pxCheckoutHelper->setMerchantToken($methodInstance->getConfigData('merchant_token'));
            if ($methodInstance->getConfigData('debug')) {
                $this->pxCheckoutHelper->setBackendApiUrl('https://api.externalintegration.payex.com');
            } else {
                $this->pxCheckoutHelper->setBackendApiUrl('https://api.payex.com');
            }

            $amount = $quote->getGrandTotal();
            if ($amount > 0) {
                $reference = 'quote_' . $quote->getId();
                $currency = $quote->getQuoteCurrencyCode();

                try {
                    $payment_session_url = $this->pxCheckoutHelper->init_payment_session();
                    $result = $this->pxCheckoutHelper->init_payment($payment_session_url,
                        [
                            'amount'      => round($amount, 2),
                            'vatAmount'   => 0,
                            'currency'    => $currency,
                            'callbackUrl' => $this->urlBuilder->getUrl('payex/checkout/ipn', ['_query' => ['reference' => $reference]]),
                            'reference'   => $reference,
                            'culture'     => $methodInstance->getConfigData('culture'),
                            'acquire'     => [
                                "email", "mobilePhoneNumber", "shippingAddress"
                            ]
                        ]
                    );

                    $quote->setPayexPaymentSession($payment_session_url);
                    $quote->setPayexPaymentId(isset($result['id']) ? $result['id'] : null);
                    $quote->getResource()->save($quote);
                } catch (\Exception $e) {
                    // @todo Log Exception
                }
            }
        }

        return $this;
    }
}
