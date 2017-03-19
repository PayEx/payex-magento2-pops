<?php

namespace PayEx\Payments\Model\Method;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class MasterPass
 * @package PayEx\Payments\Model\Method
 */
class MobilePay extends \PayEx\Payments\Model\Method\Cc
{

    const METHOD_CODE = 'payex_mobilepay';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * Payment method blocks
     * @var string
     */
    protected $_infoBlockType = 'PayEx\Payments\Block\Info\MobilePay';
    protected $_formBlockType = 'PayEx\Payments\Block\Form\MobilePay';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = true;
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

        $order_id = $order->getIncrementId();

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = $this->getConfigData('transactiontype');

        // Get Additional Values
        $additional = 'RESPONSIVE=1&USEMOBILEPAY=True';

        // Language
        $language = $this->getConfigData('language');
        if (empty($language)) {
            $language = $this->payexHelper->getLanguage();
        }

        // Get Amount
        $amount = $order->getGrandTotal();

        // Call PxOrder.Initialize8
        $params = [
            'accountNumber' => '',
            'purchaseOperation' => $operation,
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency_code,
            'vat' => 0,
            'orderID' => $order_id,
            'productNumber' => $order_id,
            'description' => $this->payexHelper->getStore()->getName(),
            'clientIPAddress' => $this->payexHelper->getRemoteAddr(),
            'clientIdentifier' => 'USERAGENT=' . $this->request->getServer('HTTP_USER_AGENT'),
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => $this->urlBuilder->getUrl('payex/cc/success', [
                '_secure' => $this->request->isSecure()
            ]),
            'view' => 'CREDITCARD',
            'agreementRef' => '',
            'cancelUrl' => $this->urlBuilder->getUrl('payex/cc/cancel', [
                '_secure' => $this->request->isSecure()
            ]),
            'clientLanguage' => $language
        ];
        $result = $this->payexHelper->getPx()->Initialize8($params);
        $this->payexLogger->info('PxOrder.Initialize8', $result);

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);
            throw new LocalizedException(__($message));
        }

        $order_ref = $result['orderRef'];
        $redirectUrl = $result['redirectUrl'];

        // Add Order Info
        if ($this->getConfigData('checkoutinfo')) {
            // Add Order Items
            $items = $this->payexHelper->getOrderItems($order);
            foreach ($items as $index => $item) {
                // Call PxOrder.AddSingleOrderLine2
                $params = [
                    'accountNumber' => '',
                    'orderRef' => $order_ref,
                    'itemNumber' => ($index + 1),
                    'itemDescription1' => $item['name'],
                    'itemDescription2' => '',
                    'itemDescription3' => '',
                    'itemDescription4' => '',
                    'itemDescription5' => '',
                    'quantity' => $item['qty'],
                    'amount' => (int)(100 * $item['price_with_tax']), //must include tax
                    'vatPrice' => (int)(100 * $item['tax_price']),
                    'vatPercent' => (int)(100 * $item['tax_percent'])
                ];

                $result = $this->payexHelper->getPx()->AddSingleOrderLine2($params);
                $this->payexLogger->info('PxOrder.AddSingleOrderLine2', $result);
            }

            // Add Order Address Info
            $params = array_merge([
                'accountNumber' => '',
                'orderRef' => $order_ref
            ], $this->payexHelper->getAddressInfo($order));

            $result = $this->payexHelper->getPx()->AddOrderAddress2($params);
            $this->payexLogger->info('PxOrder.AddOrderAddress2', $result);
        }

        // Set Pending Payment status
        $order->addStatusHistoryComment(__('The customer was redirected to PayEx.'), \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $order->save();

        // Save Redirect URL in Session
        $this->session->setPayexRedirectUrl($redirectUrl);

        return $this;
    }
}
