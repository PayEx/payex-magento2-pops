<?php

namespace PayEx\Payments\Block\Bankdebit;

use Magento\Framework\View\Element\Template;

class Banks extends \Magento\Framework\View\Element\AbstractBlock implements \Magento\Widget\Block\BlockInterface
{
    /**
     * Options
     *
     * @var array
     */
    protected $_options = [];

    /**
     * Render block HTML
     * @return string
     */
    protected function _toHtml()
    {
        $options = $this->getOptions();

        /** @var \Magento\Framework\View\Element\Html\Select $select */
        $select = $this->getLayout()->createBlock(
            'Magento\Framework\View\Element\Html\Select',
            'payexbank'
        );

        $select->setName('payexbank')
            ->setId('payexbank')
            ->setValue(null)
            ->setExtraParams(null)
            ->setOptions($options);

        $html = $select->getHtml();
        return $html;
    }

    /**
     * Set Options
     * @param $options
     */
    public function setOptions($options)
    {
        $this->_options = $options;
    }

    /**
     * Get Options
     * @return array|null
     */
    public function getOptions()
    {
        if (count($this->_options) === 0) {
            return $this->getAvailableBanks();
        }
        return $this->_options;
    }

    /**
     * Get Available Banks
     * @return array
     */
    public function getAvailableBanks()
    {
        $selected_banks = $this->_scopeConfig->getValue(
            'payment/payex_bankdebit/banks',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $selected_banks = explode(',', $selected_banks);

        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \PayEx\Payments\Model\Config\Source\Banks $banks_source */
        $banks_source = $om->get('PayEx\Payments\Model\Config\Source\Banks');

        // Get Banks
        $banks = $banks_source->toOptionArray();

        $result = array();
        foreach ($banks as $current) {
            if (in_array($current['value'], $selected_banks)) {
                $result[] = $current;
            }
        }
        return $result;
    }
}
