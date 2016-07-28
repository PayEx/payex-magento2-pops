<?php

namespace PayEx\Payments\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        // Table quote
        $columns = [
            'payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee',
            ],
            'payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee Tax',
            ],
            'base_payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee',
            ],
            'base_payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payment Fee Tax',
            ]
        ];

        $sales_order = $installer->getTable('quote');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $column) {
            $connection->addColumn($sales_order, $name, $column);
        }

        // Table quote_address
        $columns = [
            'payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee',
            ],
            'payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee Tax',
            ],
            'base_payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee',
            ],
            'base_payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payment Fee Tax',
            ]
        ];

        $sales_order = $installer->getTable('quote_address');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $column) {
            $connection->addColumn($sales_order, $name, $column);
        }

        // Table sales_order
        $columns = [
            'payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee',
            ],
            'payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee Tax',
            ],
            'base_payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee',
            ],
            'base_payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payment Fee Tax',
            ],
            'payex_payment_fee_invoiced' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee (Invoiced)',
            ],
            'payex_payment_fee_tax_invoiced' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee Tax (Invoiced)',
            ],
            'base_payex_payment_fee_invoiced' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee (Invoiced)',
            ],
            'base_payex_payment_fee_tax_invoiced' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee Tax (Invoiced)',
            ],
            'payex_payment_fee_refunded' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee (Refunded)',
            ],
            'payex_payment_fee_tax_refunded' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee Tax (Refunded)',
            ],
            'base_payex_payment_fee_refunded' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee (Refunded)',
            ],
            'base_payex_payment_fee_tax_refunded' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee Tax (Refunded)',
            ],
        ];

        $sales_order = $installer->getTable('sales_order');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $definition) {
            $connection->addColumn($sales_order, $name, $definition);
        }

        // Table sales_invoice
        $columns = [
            'payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee',
            ],
            'payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee Tax',
            ],
            'base_payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee',
            ],
            'base_payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx PaymentFee Tax',
            ],
        ];

        $sales_order = $installer->getTable('sales_invoice');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $definition) {
            $connection->addColumn($sales_order, $name, $definition);
        }

        // Table sales_creditmemo
        $columns = [
            'payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee',
            ],
            'payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayEx Payments Fee Tax',
            ],
            'base_payex_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payments Fee',
            ],
            'base_payex_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayEx Payment Fee Tax',
            ],
        ];

        $sales_order = $installer->getTable('sales_creditmemo');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $definition) {
            $connection->addColumn($sales_order, $name, $definition);
        }

        $installer->endSetup();
    }
}