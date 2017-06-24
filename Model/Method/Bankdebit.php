<?php

namespace PayEx\Payments\Model\Method;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;

/**
 * Class Bankdebit
 * @package PayEx\Payments\Model\Method
 */
class Bankdebit extends \PayEx\Payments\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'payex_bankdebit';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    //protected $_formBlockType = 'PayEx\Payments\Block\Form\Bankdebit';
    protected $_infoBlockType = 'PayEx\Payments\Block\Info\Bankdebit';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canFetchTransactionInfo = true;

    /**
     * Assign data to info model instance
     *
     * @param DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(DataObject $data)
    {
        if (!$data instanceof DataObject) {
            $data = new DataObject($data);
        }

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        /** @var \Magento\Quote\Model\Quote\Payment $info */
        $info = $this->getInfoInstance();
        $info->setBankId($additionalData->getBankId());

        // Failback
        if (version_compare($this->payexHelper->getMageVersion(), '2.0.2', '<=')) {
            $info->setBankId($data->getBankId());
        }

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validate()
    {
        parent::validate();

        /** @var \Magento\Quote\Model\Quote\Payment $info */
        $info = $this->getInfoInstance();

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $info->getQuote();

        if (!$quote) {
            return $this;
        }

        if (!$info->getBankId()) {
            throw new LocalizedException(__('Please select bank.'));
        }

        // Save Bank Id
        $info->setAdditionalInformation('bank_id', $info->getBankId());

        return $this;
    }

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

        // Get Additional Values
        $additional = '';

        // Responsive Skinning
        if ($this->getConfigData('responsive') === '1') {
            $separator = (!empty($additional) && mb_substr($additional, -1) !== '&') ? '&' : '';
            $additional .= $separator . 'RESPONSIVE=1';
        }

        // Language
        $language = $this->getConfigData('language');
        if (empty($language)) {
            $language = $this->payexHelper->getLanguage();
        }

        // Get Amount
        //$amount = $order->getGrandTotal();
        $items = $this->payexHelper->getOrderItems($order);
        $amount = array_sum(array_column($items, 'price_with_tax'));

        // Get SSN
        $bank_id = $info->getAdditionalInformation('bank_id');

        // Call PxOrder.Initialize8
        $params = [
            'accountNumber' => '',
            'purchaseOperation' => 'SALE',
            'price' => 0,
            'priceArgList' => $bank_id . '=' . round($amount * 100),
            'currency' => $currency_code,
            'vat' => 0,
            'orderID' => $order_id,
            'productNumber' => $order_id,
            'description' => $this->payexHelper->getStore()->getName(),
            'clientIPAddress' => $this->payexHelper->getRemoteAddr(),
            'clientIdentifier' => 'USERAGENT=' . $this->request->getServer('HTTP_USER_AGENT'),
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => $this->urlBuilder->getUrl('payex/cc/success', ['_secure' => $this->request->isSecure()]),
            'view' => 'DIRECTDEBIT',
            'agreementRef' => '',
            'cancelUrl' => $this->urlBuilder->getUrl('payex/cc/cancel', ['_secure' => $this->request->isSecure()]),
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

        // Call PxOrder.PrepareSaleDD2
        $params = [
            'accountNumber' => '',
            'orderRef' => $order_ref,
            'userType' => 0,
            'userRef' => '',
            'bankName' => $bank_id
        ];
        $result = $this->payexHelper->getPx()->PrepareSaleDD2($params);
        $this->payexLogger->info('PxOrder.PrepareSaleDD2', $result);

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);
            throw new LocalizedException(__($message));
        }

        // Set Pending Payment status
        $order->setCanSendNewEmailFlag(false);
        $order->addStatusHistoryComment(
            __('The customer was redirected to PayEx.'),
            Order::STATE_PENDING_PAYMENT
        );
        $order->save();

        // Set state object
        /** @var \Magento\Sales\Model\Order\Status $status */
        $status = $this->payexHelper->getAssignedState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setState($status->getState());
        $stateObject->setStatus($status->getStatus());
        $stateObject->setIsNotified(false);

        // Save Redirect URL in Session
        $this->checkoutHelper->getCheckout()->setPayexRedirectUrl($redirectUrl);

        return $this;
    }
}
