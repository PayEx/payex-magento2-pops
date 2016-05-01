<?php

namespace PayEx\Payments\Block\Info;

use Magento\Framework\View\Element\Template;

class Financing extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'PayEx_Payments::info/financing.phtml';

    /**
     * @var \PayEx\Payments\Helper\Data
     */
    protected $payexHelper;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * Constructor
     *
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        \PayEx\Payments\Helper\Data $payexHelper,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->payexHelper = $payexHelper;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Get some specific information in format of array($label => $value)
     *
     * @return array
     */
    public function getSpecificInformation()
    {
        // Get Payment Info
        /** @var \Magento\Payment\Model\Info $_info */
        $_info = $this->getInfo();

        // Transaction Fields
        $fields = array(
            'PayEx Payment Method' => array('paymentMethod', 'cardProduct'),
            //'Masked Number' => array('maskedNumber', 'maskedCard'),
            //'Bank Hash' => array('BankHash', 'csId', 'panId'),
            //'Bank Reference' => array('bankReference'),
            //'Authenticated Status' => array('AuthenticatedStatus', 'authenticatedStatus'),
            'Transaction Ref' => array('transactionRef'),
            'PayEx Transaction Number' => array('transactionNumber'),
            'PayEx Transaction Status' => array('transactionStatus'),
            'Transaction Error Code' => array('transactionErrorCode'),
            'Transaction Error Description' => array('transactionErrorDescription'),
            'Transaction ThirdParty Error' => array('transactionThirdPartyError')
        );

        if ($_info) {
            $transactionId = $_info->getLastTransId();

            if ($transactionId) {
                // Load transaction
                $transaction = $this->transactionRepository->getByTransactionId(
                    $transactionId,
                    $_info->getOrder()->getPayment()->getId(),
                    $_info->getOrder()->getId()
                );

                if ($transaction) {
                    $transaction_data = $transaction->getAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS);
                    if (!$transaction_data) {
                        $payment = $_info->getOrder()->getPayment();
                        $transaction_data = $payment->getMethodInstance()->fetchTransactionInfo($payment, $transactionId);
                        $transaction->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $transaction_data);
                        $transaction->save();
                    }

                    // Filter empty values
                    $transaction_data = array_filter($transaction_data, 'strlen');

                    $result = array();
                    foreach ($fields as $description => $list) {
                        foreach ($list as $key => $value) {
                            if (isset($transaction_data[$value])) {
                                $result[$description] = $transaction_data[$value];
                            }
                        }
                    }

                    return $result;
                }
            }
        }

        return $this->_prepareSpecificInformation()->getData();
    }

    /**
     * Render as PDF
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('PayEx_Payments::info/pdf/financing.phtml');
        return $this->toHtml();
    }
}
