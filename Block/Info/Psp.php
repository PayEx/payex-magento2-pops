<?php

namespace PayEx\Payments\Block\Info;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order\Payment\Transaction;

class Psp extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'PayEx_Payments::info/psp_info.phtml';

    /**
     * @var array
     */
    protected $transactionFields = [];

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
    ) {

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
        if ($_info && $transactionId = $_info->getLastTransId()) {
            // Load transaction
            $transaction = $this->transactionRepository->getByTransactionId(
                $transactionId,
                $_info->getOrder()->getPayment()->getId(),
                $_info->getOrder()->getId()
            );

            if ($transaction) {
                $transaction_data = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
                if (!$transaction_data) {
                    $payment = $_info->getOrder()->getPayment();
                    $transaction_data = $payment->getMethodInstance()->fetchTransactionInfo($payment, $transactionId);
                    $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $transaction_data);
                    $transaction->save();
                }

                return array_filter($transaction_data, function ($value, $key) {
                    return in_array($key, ['type', 'state', 'number', 'payeeReference']);
                }, ARRAY_FILTER_USE_BOTH);
            }
        }

        return $this->_prepareSpecificInformation()->getData();
    }
}
