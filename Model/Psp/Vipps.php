<?php

namespace PayEx\Payments\Model\Psp;

use Magento\Checkout\Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

use PayEx\Payments\Model\Config\Source\CheckoutMethod;

class Vipps extends \PayEx\Payments\Model\Psp\Cc
{
    const METHOD_CODE = 'payex_psp_vipps';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    //protected $_formBlockType = 'PayEx\Payments\Block\Form\Psp';
    //protected $_infoBlockType = 'PayEx\Payments\Block\Info\Psp';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canCaptureOnce = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canFetchTransactionInfo = true;

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @api
     */
    public function initialize($paymentAction, $stateObject)
    {
        $this->payexLogger->info('initialize');

        /** @var \Magento\Quote\Model\Quote\Payment $info */
        $info = $this->getInfoInstance();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $info->getOrder();

        // Set state object
        /** @var \Magento\Sales\Model\Order\Status $status */
        $status = $this->payexHelper->getAssignedState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setState($status->getState());
        $stateObject->setStatus($status->getStatus());
        $stateObject->setIsNotified(false);

        // Get Customer UUID
        if ($this->customerSession->isLoggedIn()) {
            $customer_uuid = $this->payexHelper->uuid($this->customerSession->getCustomer()->getEmail());
        } else {
            $customer_uuid = $this->payexHelper->uuid(uniqid());
        }

        // Get Order UUID
        $order->setPayexOrderUuid($this->payexHelper->uuid($order->getIncrementId()));

        $currency = $order->getOrderCurrencyCode();
        $amount = $order->getGrandTotal();

        // Get msisdn
	    $phone = $order->getBillingAddress()->getTelephone();
        $countryCode = $order->getBillingAddress()->getCountryId();
	    $msisdn = $this->payexHelper->getMsisdn($phone, $countryCode);

        try {
            $params = [
                'payment' => [
                    'operation' => 'Purchase',
                    'intent' => 'Authorization',
                    'currency' => $currency,
                    'prices' => [
                        [
                            'type' => 'Vipps',
                            'amount' => round($amount * 100),
                            'vatAmount' => '0'
                        ]
                    ],
                    'description' => __('Order #%1', $order->getIncrementId()),
                    'payerReference' => $customer_uuid,
                    'userAgent' => $this->request->getServer('HTTP_USER_AGENT'),
                    'language' => $this->getConfigData('culture'),
                    'urls' => [
                        'completeUrl' => $this->urlBuilder->getUrl('payex/psp/success', [
                            '_secure' => $this->request->isSecure()
                        ]),
                        'cancelUrl'   => $this->urlBuilder->getUrl('payex/psp/cancel', [
                            '_secure' => $this->request->isSecure()
                        ]),
                        'callbackUrl' => $this->urlBuilder->getUrl('payex/psp/callback', [
                            '_secure' => $this->request->isSecure(),
                            '_query' => 'order_id=' . $order->getIncrementId()
                        ])
                    ],
                    'payeeInfo' => [
                        'payeeId' => $this->getConfigData('payee_id'),
                        'payeeReference' => $order->getPayexOrderUuid(),
                    ],
                    'prefillInfo' => [
                        'msisdn' => $msisdn
                    ]
                ]
            ];

            $result = $this->psp->request('POST', '/psp/vipps/payments', $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        // Save payment ID
        $info->setAdditionalInformation('payex_payment_id', $result['payment']['id']);

        switch ($this->getConfigData('checkout_method')) {
            case CheckoutMethod::METHOD_REDIRECT:
                // Get Redirect
                $redirect = $this->psp->get_operation($result['operations'], 'redirect-authorization');

                // Save Redirect URL in Session
                $this->checkoutHelper->getCheckout()->setPayexRedirectUrl($redirect);

                break;
            case CheckoutMethod::METHOD_DIRECT:
                // Authorize payment
                $authorization = $this->psp->get_operation($result['operations'], 'create-authorization');

                try {
                    $params = [
                        'transaction' => [
                            'msisdn' => '+' . ltrim($phone, '+')
                        ]
                    ];

                    $result = $this->psp->request('POST', $authorization, $params);
                } catch ( \Exception $e ) {
                    throw new LocalizedException(__($e->getMessage()));
                }

                $redirect = $this->urlBuilder->getUrl('payex/psp/success', [
                    '_secure' => $this->request->isSecure()
                ]);

                // Save Redirect URL in Session
                $this->checkoutHelper->getCheckout()->setPayexRedirectUrl($redirect);

                break;
            default:
                //
        }

        return $this;
    }
}
