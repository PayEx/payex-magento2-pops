<?php

namespace PayEx\Payments\Model\Psp;

use Magento\Checkout\Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

class Invoice extends \PayEx\Payments\Model\Psp\AbstractPsp
{
    const METHOD_CODE = 'payex_psp_invoice';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'PayEx\Payments\Block\Form\Psp\Invoice';
    protected $_infoBlockType = 'PayEx\Payments\Block\Info\Psp';

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
    protected $_canRefundInvoicePartial = false;
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canVoid = false;
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
        $info->setSocialSecurityNumber($additionalData->getSocialSecurityNumber());

        // Failback
        if (version_compare($this->payexHelper->getMageVersion(), '2.0.2', '<=')) {
            $info->setSocialSecurityNumber($data->getSocialSecurityNumber());
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

        $ssn = trim($info->getSocialSecurityNumber());
        $country_code = $quote->getBillingAddress()->getCountry();
        $postcode = str_replace(' ', '', $quote->getBillingAddress()->getPostcode());

        // Validate fields
        if (empty($ssn)) {
            throw new LocalizedException(__('Please enter Social Security Number.'));
        }

        if (empty($country_code)) {
            throw new LocalizedException(__('Please select country.'));
        }

        if (empty($postcode)) {
            throw new LocalizedException(__('Please enter postcode.'));
        }

        $info->setAdditionalInformation('social_security_number', $ssn);

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
        //$phone = $order->getBillingAddress()->getTelephone();
        $country = $order->getBillingAddress()->getCountryId();

        // Get SSN
        $ssn = $info->getAdditionalInformation('social_security_number');

        try {
            $params = [
                'payment' => [
                    'operation' => 'FinancingConsumer',
                    'intent' => 'Authorization',
                    'currency' => $currency,
                    'prices' => [
                        [
                            'type' => 'Invoice',
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
                        'payeeReference' => str_replace('-', '', $order->getPayexOrderUuid()),
                        'payeeName' => 'Merchant1',
                        'productCategory' => 'PC1234'
                    ],
                ],
                'invoice' => [
                    'invoiceType' => 'PayExFinancing' . ucfirst(strtolower($country))
                ]
            ];

            $result = $this->psp->request('POST', '/psp/invoice/payments', $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        // Save payment ID
        $info->setAdditionalInformation('payex_payment_id', $result['payment']['id']);

        // Authorization
        $create_authorize_href = $this->psp->get_operation($result['operations'], 'create-authorization');

        // Approved Legal Address
        $legal_address_href = $this->psp->get_operation($result['operations'], 'create-approved-legal-address');

        // Get Approved Legal Address
        try {
            $params = [
                'addressee' => [
                    'socialSecurityNumber' => $ssn,
                    'zipCode' => str_replace(' ', '', $order->getBillingAddress()->getPostcode())
                ]
            ];

            $result = $this->psp->request('POST', $legal_address_href, $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        // @todo Save legal address
        $legal_address = $result['approvedLegalAddress'];

        // Transaction Activity: FinancingConsumer
        try {
            $params = [
                'transaction' => [
                    'activity' => 'FinancingConsumer'
                ],
                'consumer' => [
                    'socialSecurityNumber' => $ssn,
                    'customerNumber' => $customer_uuid,
                    'email' => $order->getBillingAddress()->getEmail(),
                    'msisdn' => '+' . ltrim($order->getBillingAddress()->getTelephone(), '+'),
                    'ip' => $this->payexHelper->getRemoteAddr()
                ],
                'legalAddress' => [
                    'addressee' => $legal_address['addressee'],
                    'coAddress' => $legal_address['coAddress'],
                    'streetAddress' => $legal_address['streetAddress'],
                    'zipCode' => $legal_address['zipCode'],
                    'city' => $legal_address['city'],
                    'countryCode' => $legal_address['countryCode']
                ]
            ];

            $result = $this->psp->request('POST', $create_authorize_href, $params);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

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

        // Get Items
        $descriptions = [];
        $items = $this->payexHelper->getOrderItems($order, $order->getOrderCurrencyCode());
        foreach ($items as $item) {
            $unit_price     = sprintf("%.2f", $item['price_without_tax'] / $item['qty']);
            $descriptions[] = [
                'product' => $item['name'],
                'quantity' => $item['qty'],
                'unitPrice' => (int) round($unit_price * 100),
                'amount' => (int) round( $item['price_with_tax'] * 100),
                'vatAmount' => (int) round( $item['tax_price'] * 100),
                'vatPercent' => sprintf( "%.2f", $item['tax_percent'] ),
            ];
        }

        try {
            $result = $this->psp->request('GET', $payment_id);
            $capture_href = $this->psp->get_operation($result['operations'], 'create-capture');
            if (empty($capture_href)) {
                throw new LocalizedException(__( 'Capture unavailable.'));
            }

            $params = [
                'transaction' => [
                    'activity' => 'FinancingConsumer',
                    'amount' => (int) round($amount * 100),
                    'vatAmount' => 0,
                    'description' => sprintf('Capture for Order #%s', $order->getIncrementId()),
                    'payeeReference' => str_replace('-', '', $this->payexHelper->uuid(uniqid($order->getIncrementId())))
                ],
                'itemDescriptions' => $descriptions
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
                    'activity' => 'FinancingConsumer',
                    'description' => sprintf('Cancellation for Order #%s', $order->getIncrementId()),
                    'payeeReference' => str_replace('-', '', $this->payexHelper->uuid(uniqid($order->getIncrementId())))
                ],
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
                    'activity' => 'FinancingConsumer',
                    'amount' => (int) round($amount * 100),
                    'vatAmount' => 0,
                    'description' => sprintf('Refund for Order #%s', $order->getIncrementId()),
                    'payeeReference' => str_replace('-', '', $this->payexHelper->uuid(uniqid($order->getIncrementId())))
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
