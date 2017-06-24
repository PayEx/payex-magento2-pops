<?php

namespace PayEx\Payments\Helper;

use PayEx\Px;
use FullNameParser;
use DOMDocument;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Data extends AbstractHelper
{
    const MODULE_NAME = 'PayEx_Payments';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * @var \Magento\Payment\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    private $moduleList;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $resolver;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    private $orderConfig;

    /**
     * @var \PayEx\Px
     */
    private $px;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory
     */
    private $orderStatusCollectionFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    private $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private $transaction;

    /**
     * @var \Magento\Tax\Helper\Data
     */
    private $taxHelper;

    /**
     * @var \Magento\Framework\App\ProductMetadata
     */
    private $productMetadata;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    private $remoteAddress;

    /**
     * @var FullNameParser
     */
    private $nameParser;

    /**
     * @var \PayEx\Payments\Model\Config\Source\Language
     */
    private $language;

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    private $imageHelper;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var \Magento\Tax\Model\Calculation
     */
    private $calculationTool;

    /**
     * @var \Magento\Directory\Model\Country
     */
    private $country;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Payment\Model\Config $config
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Locale\Resolver $resolver
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Tax\Helper\Data $taxHelper
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
     * @param Px $px
     * @param FullNameParser $nameParser
     * @param \PayEx\Payments\Model\Config\Source\Language $language
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \Magento\Tax\Model\Calculation $calculationTool
     * @param \Magento\Directory\Model\Country $country
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Payment\Model\Config $config,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Locale\Resolver $resolver,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        Px $px,
        FullNameParser $nameParser,
        \PayEx\Payments\Model\Config\Source\Language $language,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Tax\Model\Calculation $calculationTool,
        \Magento\Directory\Model\Country $country
    ) {

        parent::__construct($context);
        $this->logger = $logger;
        $this->encryptor = $encryptor;
        $this->config = $config;
        $this->moduleList = $moduleList;
        $this->resolver = $resolver;
        $this->orderConfig = $orderConfig;

        $this->orderStatusCollectionFactory = $orderStatusCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->transaction = $transaction;

        $this->taxHelper = $taxHelper;
        $this->productMetadata = $productMetadata;
        $this->storeManager = $storeManager;
        $this->remoteAddress = $remoteAddress;
        $this->px = $px;
        $this->nameParser = $nameParser;
        $this->language = $language;
        $this->imageHelper = $imageHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->calculationTool = $calculationTool;
        $this->country = $country;
    }

    /**
     * Get Module Version
     * @return string
     */
    public function getVersion()
    {
        return $this->moduleList
            ->getOne(self::MODULE_NAME)['setup_version'];
    }

    /**
     * Retrieve information from payment configuration
     * @param $field
     * @param $paymentMethodCode
     * @param $storeId
     * @param bool|false $flag
     * @return bool|mixed
     */
    public function getConfigData($field, $paymentMethodCode, $storeId, $flag = false)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    /**
     * Get Store
     * @param int|string|null|bool|\Magento\Store\Api\Data\StoreInterface $id [optional]
     * @return \Magento\Store\Api\Data\StoreInterface
     */
    public function getStore($id = null)
    {
        return $this->storeManager->getStore($id);
    }

    /**
     * Get Visitor IP address
     * @return string
     */
    public function getRemoteAddr()
    {
        return $this->remoteAddress->getRemoteAddress();
    }

    /**
     * Get PayEx Api Handler
     * @return \PayEx\Px
     */
    public function getPx()
    {
        // Set User Agent
        $this->px->setUserAgent(sprintf(
            "PayEx.Ecommerce.Php/%s PHP/%s Magento/%s PayEx.Magento2/%s",
            Px::VERSION,
            phpversion(),
            $this->productMetadata->getVersion(),
            $this->getVersion()
        ));

        return $this->px;
    }

    /**
     * Get verbose error message by Error Code
     * @param $errorCode
     * @return string | false
     */
    public function getErrorMessageByCode($errorCode)
    {
        // @codingStandardsIgnoreStart
        $errorMessages = [
            'REJECTED_BY_ACQUIRER' =>
                __('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            '3DSecureDirectoryServerError' =>
                __('A problem with Visa or MasterCards directory server, that communicates transactions for 3D-Secure verification'),
            'AcquirerComunicationError' =>
                __('Communication error with the acquiring bank'),
            'AmountNotEqualOrderLinesTotal' =>
                __('The sum of your order lines is not equal to the price set in initialize'),
            'CardNotEligible' =>
                __('Your customers card is not eligible for this kind of purchase, your customer can contact their bank for more information'),
            'CreditCard_Error' =>
                __('Some problem occurred with the credit card, your customer can contact their bank for more information'),
            'PaymentRefusedByFinancialInstitution' =>
                __('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Merchant_InvalidAccountNumber' =>
                __('The merchant account number sent in on request is invalid'),
            'Merchant_InvalidIpAddress' =>
                __('The IP address the request comes from is not registered in PayEx, you can set it up in PayEx Admin under Merchant profile'),
            'Access_MissingAccessProperties' =>
                __('The merchant does not have access to requested functionality'),
            'Access_DuplicateRequest' =>
                __('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Admin_AccountTerminated' =>
                __('The merchant account is not active'),
            'Admin_AccountDisabled' =>
                __('The merchant account is not active'),
            'ValidationError_AccountLockedOut' =>
                __('The merchant account is locked out'),
            'ValidationError_Generic' =>
                __('Generic validation error'),
            'ValidationError_HashNotValid' =>
                __('The hash on request is not valid, this might be due to the encryption key being incorrect'),
            'OperationCancelledbyCustomer' =>
                __('The operation was cancelled by the client'),
            'PaymentDeclinedDoToUnspecifiedErr' =>
                __('Unexpecter error at 3rd party'),
            'InvalidAmount' =>
                __('The amount is not valid for this operation'),
            'NoRecordFound' =>
                __('No data found'),
            'OperationNotAllowed' =>
                __('The operation is not allowed, transaction is in invalid state'),
            'ACQUIRER_HOST_OFFLINE' =>
                __('Could not get in touch with the card issuer'),
            'ARCOT_MERCHANT_PLUGIN_ERROR' =>
                __('The card could not be verified'),
            'REJECTED_BY_ACQUIRER_CARD_BLACKLISTED' =>
                __('There is a problem with this card'),
            'REJECTED_BY_ACQUIRER_CARD_EXPIRED' =>
                __('The card expired'),
            'REJECTED_BY_ACQUIRER_INSUFFICIENT_FUNDS' =>
                __('Insufficient funds'),
            'REJECTED_BY_ACQUIRER_INVALID_AMOUNT' =>
                __('Incorrect amount'),
            'USER_CANCELED' =>
                __('Payment cancelled'),
            'CardNotAcceptedForThisPurchase' =>
                __('Your Credit Card not accepted for this purchase'),
            'CreditCheckNotApproved' =>
                __('Credit check was declined, please try another payment option'),
            'NotSupportedPaymentMethod' =>
                __('Not supported paymentmethod')
        ];
        // @codingStandardsIgnoreEnd
        $errorMessages = array_change_key_case($errorMessages, CASE_UPPER);

        $errorCode = mb_strtoupper($errorCode);
        return isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : false;
    }

    /**
     * Get Verbose Error Message
     * @param array $details
     * @return string
     */
    public function getVerboseErrorMessage(array $details)
    {
        $errorCode = isset($details['transactionErrorCode']) ? $details['transactionErrorCode'] : $details['errorCode'];
        $errorMessage = $this->getErrorMessageByCode($errorCode);
        if ($errorMessage) {
            return $errorMessage;
        }

        $errorCode = isset($details['transactionErrorCode']) ? $details['transactionErrorCode'] : '';
        $errorDescription = isset($details['transactionThirdPartyError']) ? $details['transactionThirdPartyError'] : '';
        if (empty($errorCode) && empty($errorDescription)) {
            $errorCode = $details['code'];
            $errorDescription = $details['description'];
        }

        return __('PayEx error: %1 (%2)', $errorCode, $errorDescription);
    }

    /**
     * Get Order Items
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getOrderItems(\Magento\Sales\Model\Order $order)
    {
        $lines = [];
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
            /** @var \Magento\Sales\Model\Order\Item $item */
            // Skip configurable product which should be invisible
            if ($item->getProductType() === \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE &&
                $item->getParentItem()
            ) {
                continue;
            }

            $itemQty = (int)$item->getQtyOrdered();
            $priceWithTax = $item->getRowTotalInclTax();
            $priceWithoutTax = $item->getRowTotal();
            $taxPercent = $priceWithoutTax > 0 ? (($priceWithTax / $priceWithoutTax) - 1) * 100 : 0;
            $taxPrice = $priceWithTax - $priceWithoutTax;

            $lines[] = [
                'type' => 'product',
                'name' => $item->getName(),
                'qty' => $itemQty,
                'price_with_tax' => sprintf("%.2f", $priceWithTax),
                'price_without_tax' => sprintf("%.2f", $priceWithoutTax),
                'tax_price' => sprintf("%.2f", $taxPrice),
                'tax_percent' => sprintf("%.2f", $taxPercent)
            ];
        }

        // add Shipping
        if (!$order->getIsVirtual()) {
            $shippingExclTax = $order->getShippingAmount();
            $shippingIncTax = $order->getShippingInclTax();
            $shippingTax = $shippingIncTax - $shippingExclTax;

            // find out tax-rate for the shipping
            if ((float)$shippingIncTax && (float)$shippingExclTax) {
                $shippingTaxRate = (($shippingIncTax / $shippingExclTax) - 1) * 100;
            } else {
                $shippingTaxRate = 0;
            }

            $lines[] = [
                'type' => 'shipping',
                'name' => $order->getShippingDescription(),
                'qty' => 1,
                'price_with_tax' => sprintf("%.2f", $shippingIncTax),
                'price_without_tax' => sprintf("%.2f", $shippingExclTax),
                'tax_price' => sprintf("%.2f", $shippingTax),
                'tax_percent' => sprintf("%.2f", $shippingTaxRate)
            ];
        }

        // add Discount
        if (abs($order->getDiscountAmount()) > 0) {
            $discountData = $this->getOrderDiscountData($order);
            $discountInclTax = $discountData->getDiscountInclTax();
            $discountExclTax = $discountData->getDiscountExclTax();
            $discountVatAmount = $discountInclTax - $discountExclTax;
            $discountVatPercent = $discountExclTax > 0 ? (($discountInclTax / $discountExclTax) - 1) * 100 : 0;

            $lines[] = [
                'type' => 'discount',
                'name' => __('Discount (%1)', $order->getDiscountDescription()),
                'qty' => 1,
                'price_with_tax' => sprintf("%.2f", -1 * $discountInclTax),
                'price_without_tax' => sprintf("%.2f", -1 * $discountExclTax),
                'tax_price' => sprintf("%.2f", -1 * $discountVatAmount),
                'tax_percent' => sprintf("%.2f", $discountVatPercent)
            ];
        }

        // add Payment Fee
        if ($order->getPayexPaymentFee() > 0 &&
            in_array($order->getPayment()->getMethod(), [
                \PayEx\Payments\Model\Method\Financing::METHOD_CODE,
                \PayEx\Payments\Model\Method\PartPayment::METHOD_CODE
            ])
        ) {
            $feeExclTax = $order->getPayexPaymentFee();
            $feeTax = $order->getPayexPaymentFeeTax();
            $feeIncTax = $feeExclTax + $feeTax;
            $feeTaxRate = $feeExclTax > 0 ? (($feeIncTax / $feeExclTax) - 1) * 100 : 0;

            $lines[] = [
                'type' => 'fee',
                'name' => __('Payment Fee'),
                'qty' => 1,
                'price_with_tax' => sprintf("%.2f", $feeIncTax),
                'price_without_tax' => sprintf("%.2f", $feeExclTax),
                'tax_price' => sprintf("%.2f", $feeTax),
                'tax_percent' => sprintf("%.2f", $feeTaxRate)
            ];
        }

        return $lines;
    }

    /**
     * Prepare Address Info
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getAddressInfo(\Magento\Sales\Model\Order $order)
    {
        $billingAddress = $order->getBillingAddress()->getStreet();
        $billingCountryId = $order->getBillingAddress()->getCountryId();
        $billingCountry = $this->country->loadByCode($billingCountryId)->getName();

        $params = [
            'billingFirstName' => $order->getBillingAddress()->getFirstname(),
            'billingLastName' => $order->getBillingAddress()->getLastname(),
            'billingAddress1' => $billingAddress[0],
            'billingAddress2' => (isset($billingAddress[1])) ? $billingAddress[1] : '',
            'billingAddress3' => '',
            'billingPostNumber' => (string)$order->getBillingAddress()->getPostcode(),
            'billingCity' => (string)$order->getBillingAddress()->getCity(),
            'billingState' => (string)$order->getBillingAddress()->getRegion(),
            'billingCountry' => $billingCountry,
            'billingCountryCode' => $billingCountryId,
            'billingEmail' => (string)$order->getBillingAddress()->getEmail(),
            'billingPhone' => (string)$order->getBillingAddress()->getTelephone(),
            'billingGsm' => '',
            'deliveryFirstName' => '',
            'deliveryLastName' => '',
            'deliveryAddress1' => '',
            'deliveryAddress2' => '',
            'deliveryAddress3' => '',
            'deliveryPostNumber' => '',
            'deliveryCity' => '',
            'deliveryState' => '',
            'deliveryCountry' => '',
            'deliveryCountryCode' => '',
            'deliveryEmail' => '',
            'deliveryPhone' => '',
            'deliveryGsm' => '',
        ];

        // add Shipping
        if (!$order->getIsVirtual()) {
            $deliveryAddress = $order->getShippingAddress()->getStreet();
            $deliveryCountryId = $order->getShippingAddress()->getCountryId();
            $deliveryCountry = $this->country->loadByCode($billingCountryId)->getName();

            $params = array_merge($params, [
                'deliveryFirstName' => $order->getShippingAddress()->getFirstname(),
                'deliveryLastName' => $order->getShippingAddress()->getLastname(),
                'deliveryAddress1' => $deliveryAddress[0],
                'deliveryAddress2' => (isset($deliveryAddress[1])) ? $deliveryAddress[1] : '',
                'deliveryAddress3' => '',
                'deliveryPostNumber' => (string)$order->getShippingAddress()->getPostcode(),
                'deliveryCity' => (string)$order->getShippingAddress()->getCity(),
                'deliveryState' => (string)$order->getShippingAddress()->getRegion(),
                'deliveryCountry' => $deliveryCountry,
                'deliveryCountryCode' => $deliveryCountryId,
                'deliveryEmail' => (string)$order->getShippingAddress()->getEmail(),
                'deliveryPhone' => (string)$order->getShippingAddress()->getTelephone(),
                'deliveryGsm' => '',
            ]);
        }

        return $params;
    }

    /**
     * Get Assigned State
     * @param $status
     * @return \Magento\Framework\DataObject
     */
    public function getAssignedState($status)
    {
        $collection = $this->orderStatusCollectionFactory->create()->joinStates();
        $status = $collection->addAttributeToFilter('main_table.status', $status)
            ->getFirstItem();
        return $status;
    }

    /**
     * Create Invoice
     * @param \Magento\Sales\Model\Order $order
     * @param array $qtys
     * @param bool $online
     * @param string $comment
     * @return \Magento\Sales\Model\Order\Invoice
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function makeInvoice(\Magento\Sales\Model\Order $order, array $qtys = [], $online = false, $comment = '')
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $this->invoiceService->prepareInvoice($order, $qtys);
        $invoice->setRequestedCaptureCase($online ?
            \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE : \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);

        // Add Comment
        if (!empty($comment)) {
            $invoice->addComment(
                $comment,
                true,
                true
            );

            $invoice->setCustomerNote($comment);
            $invoice->setCustomerNoteNotify(true);
        }

        $invoice->register();
        $invoice->getOrder()->setIsInProcess(true);

        /** @var \Magento\Framework\DB\Transaction $transactionSave */
        $transactionSave = $this->transaction
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();

        // send invoice emails
        try {
            $this->invoiceSender->send($invoice);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        $invoice->setIsPaid(true);

        // Assign Last Transaction Id with Invoice
        $transactionId = $invoice->getOrder()->getPayment()->getLastTransId();
        if ($transactionId) {
            $invoice->setTransactionId($transactionId);
            $invoice->save();
        }

        return $invoice;
    }

    /**
     * Gets the total discount from Order
     * inkl. and excl. tax
     * Data is returned as a Varien_Object with these data-keys set:
     *   - discount_incl_tax
     *   - discount_excl_tax
     * @param \Magento\Sales\Model\Order $order
     * @return \Magento\Framework\DataObject
     */
    public function getOrderDiscountData(\Magento\Sales\Model\Order $order)
    {
        $discountIncl = 0;
        $discountExcl = 0;

        // find discount on the items
        foreach ($order->getItemsCollection() as $item) {
            /** @var \Magento\Sales\Model\Order\Item $item */
            if (!$this->taxHelper->priceIncludesTax()) {
                $discountExcl += $item->getDiscountAmount();
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
                $discountIncl += $item->getDiscountAmount();
            }
        }

        // find out tax-rate for the shipping
        if ((float)$order->getShippingInclTax() && (float)$order->getShippingAmount()) {
            $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // get discount amount for shipping
        $shippingDiscount = (float)$order->getShippingDiscountAmount();

        // apply/remove tax to shipping-discount
        if (!$this->taxHelper->priceIncludesTax()) {
            $discountIncl += $shippingDiscount * $shippingTaxRate;
            $discountExcl += $shippingDiscount;
        } else {
            $discountIncl += $shippingDiscount;
            $discountExcl += $shippingDiscount / $shippingTaxRate;
        }

        // @codingStandardsIgnoreStart
        $return = new DataObject;
        // @codingStandardsIgnoreEnd
        return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }

    /**
     * Add Payment Transaction
     * @param \Magento\Sales\Model\Order $order
     * @param array $details
     * @return Transaction
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addPaymentTransaction(\Magento\Sales\Model\Order $order, array $details = [])
    {
        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = null;

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        $transaction_status = !empty($details['transactionStatus']) ? (int)$details['transactionStatus'] : 'undefined';
        switch ($transaction_status) {
            case 1:
                // @todo Use $details['pendingReason']
                if ($details['pending'] === 'true') {
                    $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_AUTH, null, true);
                    $transaction->setIsClosed(0);
                    $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                    $transaction->save();
                    break;
                }

                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 3:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 0:
            case 6:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_CAPTURE, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 2:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_REFUND, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 4:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_VOID, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 5:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            default:
                // Invalid transaction status
        }

        return $transaction;
    }

    /**
     * Detect Client Language
     * @return string
     */
    public function getLanguage()
    {
        $locale = $this->resolver->getLocale();
        $languages = $this->language->toOptionArray();
        foreach ($languages as $key => $value) {
            if (str_replace('_', '-', $locale) === $value['value']) {
                return $value['value'];
            }
        }

        // Use en-US as default language
        return 'en-US';
    }

    /**
     * Generate Invoice Print XML for Financing Invoice/PartPayment
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getInvoiceExtraPrintBlocksXML(\Magento\Sales\Model\Order $order)
    {
        $lines = $this->getOrderItems($order);

        // Replace illegal characters of product names
        $replace_illegal = $order->getPayment()->getMethodInstance()
            ->getConfigData('replace_illegal', $order->getStoreId());
        if ($replace_illegal) {
            $replacement_char = $order->getPayment()->getMethodInstance()
                ->getConfigData('replacement_char', $order->getStoreId());
            if (empty($replacement_char)) {
                $replacement_char = '-';
            }

            $lines = array_map(function ($value) use ($replacement_char) {
                if (isset($value['name'])) {
                    mb_regex_encoding('utf-8');
                    $value['name'] = mb_ereg_replace(
                        '[^a-zA-Z0-9_:!#=?\[\]@{}´ %-\/À-ÖØ-öø-ú]',
                        $replacement_char,
                        $value['name']
                    );
                }
                return $value;
            }, $lines);
        }

        // @codingStandardsIgnoreStart
        $dom = new DOMDocument('1.0', 'utf-8');
        // @codingStandardsIgnoreEnd
        $OnlineInvoice = $dom->createElement('OnlineInvoice');
        $dom->appendChild($OnlineInvoice);
        $OnlineInvoice->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $OnlineInvoice->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'xsd',
            'http://www.w3.org/2001/XMLSchema'
        );

        $OrderLines = $dom->createElement('OrderLines');
        $OnlineInvoice->appendChild($OrderLines);

        // Add Order Lines
        foreach ($lines as $line) {
            $unit_price = $line['qty'] > 0 ? $line['price_without_tax'] / $line['qty'] : 0;
            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', $line['name']));
            $OrderLine->appendChild($dom->createElement('Qty', $line['qty']));
            $OrderLine->appendChild($dom->createElement('UnitPrice', sprintf("%.2f", $unit_price)));
            $OrderLine->appendChild($dom->createElement('VatRate', sprintf("%.2f", $line['tax_percent'])));
            $OrderLine->appendChild($dom->createElement('VatAmount', sprintf("%.2f", $line['tax_price'])));
            $OrderLine->appendChild($dom->createElement('Amount', sprintf("%.2f", $line['price_with_tax'])));
            $OrderLines->appendChild($OrderLine);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Get Shopping Cart XML for MasterPass
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getOrderShoppingCartXML(\Magento\Sales\Model\Order $order)
    {
        // @codingStandardsIgnoreStart
        $dom = new DOMDocument('1.0', 'utf-8');
        // @codingStandardsIgnoreEnd
        $ShoppingCart = $dom->createElement('ShoppingCart');
        $dom->appendChild($ShoppingCart);

        $currency = $order->getOrderCurrencyCode();

        $ShoppingCart->appendChild($dom->createElement('CurrencyCode', $currency));
        $ShoppingCart->appendChild($dom->createElement('Subtotal', (int)(100 * $order->getGrandTotal())));

        // Add Order Lines
        $items = $order->getAllVisibleItems();
        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            $product = $item->getProduct();
            $qty = $item->getQtyOrdered();
            $image = $this->imageHelper->init($product, 'category_page_list')->getUrl();

            $ShoppingCartItem = $dom->createElement('ShoppingCartItem');
            $ShoppingCartItem->appendChild($dom->createElement('Description', $item->getName()));
            $ShoppingCartItem->appendChild($dom->createElement('Quantity', (float)$qty));
            $ShoppingCartItem->appendChild($dom->createElement('Value', (int)bcmul($product->getFinalPrice(), 100)));
            $ShoppingCartItem->appendChild($dom->createElement('ImageURL', $image));
            $ShoppingCart->appendChild($ShoppingCartItem);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Get Shopping Cart XML for MasterPass
     * @param \Magento\Quote\Model\Quote $quote
     * @return mixed
     */
    public function getQuteShoppingCartXML(\Magento\Quote\Model\Quote $quote)
    {
        // @codingStandardsIgnoreStart
        $dom = new DOMDocument('1.0', 'utf-8');
        // @codingStandardsIgnoreEnd
        $ShoppingCart = $dom->createElement('ShoppingCart');
        $dom->appendChild($ShoppingCart);

        $currency = $quote->getQuoteCurrencyCode();

        $ShoppingCart->appendChild($dom->createElement('CurrencyCode', $currency));
        $ShoppingCart->appendChild($dom->createElement('Subtotal', (int)(100 * $quote->getGrandTotal())));

        // Add Order Lines
        $items = $quote->getAllVisibleItems();
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($items as $item) {
            $product = $item->getProduct();
            $qty = $item->getQty();
            $image = $this->imageHelper->init($product, 'category_page_list')->getUrl();

            $ShoppingCartItem = $dom->createElement('ShoppingCartItem');
            $ShoppingCartItem->appendChild($dom->createElement('Description', $item->getName()));
            $ShoppingCartItem->appendChild($dom->createElement('Quantity', (float)$qty));
            $ShoppingCartItem->appendChild($dom->createElement('Value', (int)bcmul($product->getFinalPrice(), 100)));
            $ShoppingCartItem->appendChild($dom->createElement('ImageURL', $image));
            $ShoppingCart->appendChild($ShoppingCartItem);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Calculate Payment Fee Price
     * @param float $fee
     * @param int $tax_class
     * @return \Magento\Framework\DataObject
     */
    public function getPaymentFeePrice($fee, $tax_class)
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->checkoutHelper->getQuote();

        // Get Tax Rate
        /** @var \Magento\Framework\DataObject $request */
        $request = $this->calculationTool->getRateRequest(
            $quote->getShippingAddress(),
            $quote->getBillingAddress(),
            $quote->getCustomerTaxClassId(),
            $quote->getStore()
        );

        $taxRate = $this->calculationTool->getRate($request->setProductClassId($tax_class));
        $priceIncludeTax = $this->taxHelper->priceIncludesTax($quote->getStore());
        $taxAmount = $this->calculationTool->calcTaxAmount($fee, $taxRate, $priceIncludeTax, true);
        if ($priceIncludeTax) {
            $fee -= $taxAmount;
        }

        // @codingStandardsIgnoreStart
        $result = new DataObject;
        $result->setPaymentFeeExclTax($fee)
            ->setPaymentFeeInclTax($fee + $taxAmount)
            ->setPaymentFeeTax($taxAmount)
            ->setRateRequest($request);
        // @codingStandardsIgnoreEnd

        return $result;
    }

    /**
     * Get Name Parser Instance
     * @see https://github.com/joshfraser/PHP-Name-Parser
     * @return \FullNameParser
     */
    public function getNameParser()
    {
        return $this->nameParser;
    }

    /**
     * Get Magento Version
     * @return string
     */
    public function getMageVersion()
    {
        return $this->productMetadata->getVersion();
    }
}
