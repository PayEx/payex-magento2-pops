<?php

namespace PayEx\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Tax\Model\TaxClass\Source\Product;

class TaxClass implements ArrayInterface
{
    /**
     * @var Product
     */
    private $tax_class;

    /**
     * Constructor
     * @param Product $tax_class
     */
    public function __construct(
        Product $tax_class
    ) {
        $this->tax_class = $tax_class;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return $this->tax_class->getAllOptions(true);
    }
}
