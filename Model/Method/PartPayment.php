<?php

namespace PayEx\Payments\Model\Method;

use Magento\Framework\DataObject;
use \Magento\Framework\Exception\LocalizedException;

/**
 * Class PartPayment
 * @package PayEx\Payments\Model\Method
 */
class PartPayment extends \PayEx\Payments\Model\Method\Financing
{

    const METHOD_CODE = 'payex_partpayment';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'PayEx\Payments\Block\Form\PartPayment';
    protected $_infoBlockType = 'PayEx\Payments\Block\Info\PartPayment';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;
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
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
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

        // Language
        $language = $this->getConfigData('language');
        if (empty($language)) {
            $language = $this->payexHelper->getLanguage();
        }

        // Get Amount
        $amount = $order->getGrandTotal();

        // Get SSN
        $ssn = $info->getAdditionalInformation('social_security_number');

        // Call PxOrder.Initialize8
        $params = [
            'accountNumber' => '',
            'purchaseOperation' => 'AUTHORIZATION',
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency_code,
            'vat' => 0,
            'orderID' => $order_id,
            'productNumber' => $order_id,
            'description' => $this->payexHelper->getStore()->getName(),
            'clientIPAddress' => $this->payexHelper->getRemoteAddr(),
            'clientIdentifier' => '',
            'additionalValues' => '',
            'externalID' => '',
            'returnUrl' => 'http://localhost.no/return',
            'view' => 'FINANCING',
            'agreementRef' => '',
            'cancelUrl' => 'http://localhost.no/cancel',
            'clientLanguage' => $language
        ];
        $result = $this->payexHelper->getPx()->Initialize8($params);
        $this->payexLogger->info('PxOrder.Initialize8', $result);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);
            throw new LocalizedException(__($message));
        }
        $order_ref = $result['orderRef'];

        // Call PxOrder.PurchaseCreditAccount
        $params = [
            'accountNumber' => '',
            'orderRef' => $order_ref,
            'socialSecurityNumber' => $ssn,
            'legalName' => $order->getBillingAddress()->getName(),
            'streetAddress' => trim(implode(' ', $order->getBillingAddress()->getStreet())),
            'coAddress' => '',
            'zipCode' => $order->getBillingAddress()->getPostcode(),
            'city' => $order->getBillingAddress()->getCity(),
            'countryCode' => $order->getBillingAddress()->getCountryId(),
            'paymentMethod' => 'PXCREDITACCOUNT' . $order->getBillingAddress()->getCountryId(),
            'email' => $order->getBillingAddress()->getEmail(),
            'msisdn' => (mb_substr($order->getBillingAddress()->getTelephone(), 0, 1) === '+') ? $order->getBillingAddress()->getTelephone() : '+' . $order->getBillingAddress()->getTelephone(),
            'ipAddress' => $this->payexHelper->getRemoteAddr()
        ];
        $result = $this->payexHelper->getPx()->PurchaseCreditAccount($params);
        $this->payexLogger->info('PxOrder.PurchaseCreditAccount', $result);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK') {
            $message = $this->payexHelper->getVerboseErrorMessage($result);
            throw new LocalizedException(__($message));
        }

        // Check Transaction fields
        if (empty($result['transactionNumber'])) {
            throw new LocalizedException(__('Error: Transaction failed'));
        }

        if ($result['transactionStatus']) {
            throw new LocalizedException(__('Error: No transactionsStatus in response'));
        }

        // Save Order
        $order->save();
        
        // Register Transaction
        $transaction_id = isset($result['transactionNumber']) ? $result['transactionNumber'] : null;
        $transaction_status = isset($result['transactionStatus']) ? (int)$result['transactionStatus'] : null;

        $order->getPayment()->setTransactionId($transaction_id);
        $transaction = $this->payexHelper->addPaymentTransaction($order, $result);

        // Set Last Transaction ID
        $order->getPayment()->setLastTransId($transaction_id)->save();

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ($transaction_status) {
            case 1:
            case 3:
                // Payment authorized
                $message = __('Payment has been authorized');

                // Change order status
                $new_status = $this->getConfigData('order_status_authorize');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();

                $order->addStatusHistoryComment($message);

                // Set state object
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);
                break;
            case 0:
            case 6:
                // Payment captured
                $message = __('Payment has been captured');

                // Change order status
                $new_status = $this->getConfigData('order_status_capture');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();
                $order->addStatusHistoryComment($message);

                // Set state object
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                // Create Invoice for Sale Transaction
                $invoice = $this->payexHelper->makeInvoice($order, [], false, $message);
                $invoice->setTransactionId($result['transactionNumber']);
                $invoice->save();
                break;
            case 2:
            case 4:
            case 5:
                if ($transaction_status === 2) {
                    $message = __('Detected an abnormal payment process (Transaction Status: %1).', $transaction_status);
                } elseif ($transaction_status === 4) {
                    $message = __('Order automatically canceled.');
                } else {
                    $message = $this->payexHelper->getVerboseErrorMessage($result);
                }

                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Set state object
                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState(\Magento\Sales\Model\Order::STATE_CANCELED);
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                throw new LocalizedException(__($message));
                // no break;
            default:
                // Invalid transaction status
                $message = __('Invalid transaction status.');

                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Set state object
                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payexHelper->getAssignedState(\Magento\Sales\Model\Order::STATE_CANCELED);
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                throw new LocalizedException(__($message));
        }

        return $this;
    }
}
