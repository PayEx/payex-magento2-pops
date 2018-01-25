<?php

namespace PayEx\Payments\Model;

class PayexTransaction extends \Magento\Framework\Model\AbstractModel
{
    protected function _construct()
    {
        $this->_init('PayEx\Payments\Model\ResourceModel\PayexTransaction');
    }

    public function import_transactions($transactions, $order_id)
    {
        $result = [];
        foreach ($transactions as $transaction) {
            $result[] = $this->import($transaction, $order_id);
        }

        return $result;
    }

    /**
     * Import Transaction
     *
     * @param $data
     * @param $order_id
     *
     * @return bool|int|mixed
     */
    public function import($data, $order_id)
    {
        $id    = $data['id'];
        $saved = $this->getByField('id', $id);
        if (!$saved) {
            $data = $this->prepare($data, $order_id);

            return $this->add($data);
        } else {
            // Data is should be updated
            $data = $this->prepare($data, $order_id);
            $this->update($saved['transaction_id'], $data);
        }

        return $saved['transaction_id'];
    }

    /**
     * Prepare data
     *
     * @param $data
     * @param $order_id
     *
     * @return array
     */
    public function prepare($data, $order_id)
    {
        $data['transaction_data'] = json_encode($data, true);
        $data['order_id']         = $order_id;
        $data['created']          = gmdate('Y-m-d H:i:s', strtotime($data['created']));
        $data['updated']          = gmdate('Y-m-d H:i:s', strtotime($data['updated']));

        return $data;
    }

    /**
     * Get By Field
     *
     * @param      $field
     * @param      $value
     * @param bool $single
     *
     * @return array
     */
    public function getByField($field, $value, $single = true)
    {
        if ($single) {
            $data = $this->getCollection()->addFieldToFilter($field, $value)
                         ->getFirstItem();
        } else {
            $data = $this->getCollection()->addFieldToFilter($field, $value)
                         ->load();
        }

        return $data->toArray();
    }

    /**
     * Add Transaction
     *
     * @param $fields
     *
     * @return PayexTransaction
     */
    public function add($fields)
    {
        // Get Object Manager Instance
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var PayexTransaction $instance */
        $instance = $objectManager->create('PayEx\Payments\Model\PayexTransaction');
        $instance->setData($fields);

        return $instance->save();
    }

    /**
     * Update Transaction
     *
     * @param $transaction_id
     * @param $fields
     *
     * @return mixed
     */
    public function update($transaction_id, $fields)
    {
        $instance = $this->getCollection()->addFieldToFilter('transaction_id', $transaction_id)
                         ->getFirstItem();

        $instance->addData($fields);

        return $instance->save();
    }

    /**
     * Get Transactions by Conditionals
     *
     * @param array $conditionals
     *
     * @return array
     */
    public function select(array $conditionals)
    {
        // Get Object Manager Instance
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var PayexTransaction $instance */
        $instance = $objectManager->create('PayEx\Payments\Model\PayexTransaction');

        $collection = $instance->getCollection()
                               ->addFieldToSelect('*');
        foreach ($conditionals as $key => $value) {
            $collection = $collection->addFieldToFilter($key, $value);
        }

        $result = $collection->load()->toArray();

        return isset($result['items']) ? $result['items'] : [];
    }
}
