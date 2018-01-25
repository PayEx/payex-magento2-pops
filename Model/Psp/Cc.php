<?php

namespace PayEx\Payments\Model\Psp;

use Magento\Checkout\Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

use PayEx\Payments\Model\Config\Source\CheckoutMethod;

class Cc extends \PayEx\Payments\Model\Psp\AbstractPsp
{
    const METHOD_CODE = 'payex_psp_cc';

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
        $phone = $order->getBillingAddress()->getTelephone();

        try {
            $params = [
                'payment' => [
                    'operation' => 'Purchase',
                    'intent' => 'Authorization', // @todo PreAuthorization|Authorization|AutoCapture
                    'currency' => $currency,
                    'prices' => [
                        [
                            'type' => 'Visa',
                            'amount' => round($amount * 100),
                            'vatAmount' => '0'
                        ],
                        [
                            'type' => 'MasterCard',
                            'amount' => round($amount * 100),
                            'vatAmount' => '0'
                        ]
                    ],
                    'description' => __('Order #%1', $order->getIncrementId()),
                    'payerReference' => $customer_uuid,
                    'generatePaymentToken' => false,
                    'pageStripdown' => false,
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
                        'msisdn' => '+' . ltrim($phone, '+')
                    ],
                    'creditCard' => [
                        'no3DSecure' => false
                    ]
                ]
            ];

            $result = $this->psp->request('POST', '/psp/creditcard/payments', $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        // Save payment ID
        $info->setAdditionalInformation('payex_payment_id', $result['payment']['id']);

        // Get Redirect
        $redirect = $this->psp->get_operation($result['operations'], 'redirect-authorization');

        // Save Redirect URL in Session
        $this->checkoutHelper->getCheckout()->setPayexRedirectUrl($redirect);

        return $this;
    }

    /**
     * Capture
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float                                $amount
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($amount <= 0) {
            throw new LocalizedException(__('Invalid amount for capture.'));
        }

        // Convert amount currency
        $order = $payment->getOrder();
        if ($order->getBaseCurrencyCode() != $order->getOrderCurrencyCode()) {
            $amount = $amount * $order->getBaseToOrderRate();
        }

        $amount = round($amount, 2, PHP_ROUND_HALF_DOWN);

        /** @var Transaction $transaction */
        $transaction = $payment->getAuthorizationTransaction();
        if (!$transaction) {
            throw new LocalizedException(__('Can\'t load last transaction.'));
        }

        $payment_id = $payment->getAdditionalInformation('payex_payment_id');

        try {
            $result = $this->psp->request('GET', $payment_id);
            $capture_href = $this->psp->get_operation($result['operations'], 'create-capture');
            if (empty($capture_href)) {
                throw new LocalizedException(__( 'Capture unavailable.'));
            }

            $params = [
                'transaction' => [
                    'amount' => (int) round($amount * 100),
                    'vatAmount' => 0,
                    'description' => sprintf('Capture for Order #%s', $order->getIncrementId()),
                    'payeeReference' => $this->payexHelper->uuid(uniqid($order->getIncrementId()))
                ]
            ];
            $result = $this->psp->request('POST', $capture_href, $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__( 'Error: %1', $e->getMessage()));
        }

        // Save transaction
        $transaction = $result['capture']['transaction'];
        $this->payexTransaction->import($transaction, $order->getIncrementId());

        switch ($transaction['state']) {
            case 'Completed':
            case 'Initialized':
                $payment->setAmount($amount);

                // Add Capture Transaction
                $payment->setStatus(self::STATUS_APPROVED)
                        ->setTransactionId($transaction['number'])
                        ->setIsTransactionClosed(0)
                        ->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction);

                return $this;
            case 'Failed':
            default:
                $message = isset($transaction['failedReason']) ? __($transaction['failedReason']) : __('Capture failed.');
                throw new LocalizedException($message);
        }
    }

    /**
     * Cancel payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        /** @var Transaction $transaction */
        $transaction = $payment->getAuthorizationTransaction();
        if (!$transaction) {
            throw new LocalizedException(__('Can\'t load last transaction.'));
        }

        $order = $payment->getOrder();
        $payment_id = $payment->getAdditionalInformation('payex_payment_id');

        try {
            $result = $this->psp->request('GET', $payment_id);
            $cancel_href = $this->psp->get_operation($result['operations'], 'create-cancellation');
            if (empty($cancel_href)) {
                throw new LocalizedException(__( 'Cancel unavailable.'));
            }

            $params = [
                'transaction' => [
                    'description' => sprintf('Cancellation for Order #%s', $order->getIncrementId()),
                    'payeeReference' => $this->payexHelper->uuid(uniqid($order->getIncrementId()))
                ]
            ];
            $result = $this->psp->request('POST', $cancel_href, $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__( 'Error: %1', $e->getMessage()));
        }

        // Save transaction
        $transaction = $result['cancellation']['transaction'];
        $this->payexTransaction->import($transaction, $order->getIncrementId());

        switch ($transaction['state']) {
            case 'Completed':
            case 'Initialized':
            case 'AwaitingActivity':
                // Add Capture Transaction
                $payment->setStatus(self::STATUS_DECLINED)
                        ->setTransactionId($transaction['number'])
                        ->setIsTransactionClosed(1)
                        ->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction);

                return $this;
            case 'Failed':
            default:
                $message = isset($transaction['failedReason']) ? __($transaction['failedReason']) : __('Cancel failed.');
                throw new LocalizedException($message);
        }
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float                                $amount
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($amount <= 0) {
            throw new LocalizedException(__('Invalid amount for refund.'));
        }

        // Convert amount currency
        $order = $payment->getOrder();
        if ($order->getBaseCurrencyCode() != $order->getOrderCurrencyCode()) {
            $amount = $amount * $order->getBaseToOrderRate();
        }

        $amount = round($amount, 2, PHP_ROUND_HALF_DOWN);

        if (!$payment->getLastTransId()) {
            throw new LocalizedException(__('Invalid transaction ID.'));
        }

        // Load transaction Data
        $transactionId = $payment->getLastTransId();

        /** @var Transaction $transaction */
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionId,
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        if (!$transaction) {
            throw new LocalizedException(__('Can\'t load last transaction.'));
        }

        // Check transaction is captured
        $transactions = $this->payexTransaction->select([
            'number' => $transactionId,
            'type' => 'Capture'
        ]);
        if (count($transactions) === 0) {
            throw new LocalizedException(__('Refund unavailable.'));
        }

        $payment_id = $payment->getAdditionalInformation('payex_payment_id');

        try {
            $result = $this->psp->request('GET', $payment_id);
            $reversal_href = $this->psp->get_operation($result['operations'], 'create-reversal');
            if (empty($reversal_href)) {
                throw new LocalizedException(__( 'Refund unavailable.'));
            }

            $params = [
                'transaction' => [
                    'amount' => (int) round($amount * 100),
                    'vatAmount' => 0,
                    'description' => sprintf('Refund for Order #%s', $order->getIncrementId()),
                    'payeeReference' => $this->payexHelper->uuid(uniqid($order->getIncrementId()))
                ]
            ];
            $result = $this->psp->request('POST', $reversal_href, $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__( 'Error: %1', $e->getMessage()));
        }

        // Save transaction
        $transaction = $result['reversal']['transaction'];
        $this->payexTransaction->import($transaction, $order->getIncrementId());

        switch ($transaction['state']) {
            case 'Completed':
            case 'Initialized':
                // Add Credit Transaction
                $payment->setAnetTransType(Transaction::TYPE_REFUND);
                $payment->setAmount($amount);

                // Add Capture Transaction
                $payment->setStatus(self::STATUS_APPROVED)
                        ->setTransactionId($transaction['number'])
                        ->setIsTransactionClosed(1)
                        ->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction);

                return $this;
            case 'AwaitingActivity':
                // Pending
                return $this;
            case 'Failed':
            default:
                $message = isset($transaction['failedReason']) ? __($transaction['failedReason']) : __('Refund failed.');
                throw new LocalizedException($message);
        }
    }


}
