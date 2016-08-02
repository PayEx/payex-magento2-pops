<?php

namespace PayEx\Payments\Model\Config\Source;

class TaxClass implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Tax\Model\TaxClass\Source\Product $ra */
        $tax_class = $om->get('Magento\Tax\Model\TaxClass\Source\Product');

        return $tax_class->getAllOptions(true);
    }
}
