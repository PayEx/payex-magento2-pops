<?php

namespace PayEx\Payments\Model\ResourceModel\PayexTransaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'transaction_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('PayEx\Payments\Model\PayexTransaction', 'PayEx\Payments\Model\ResourceModel\PayexTransaction');
    }
}
