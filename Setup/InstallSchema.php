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

        // Install PayEx Transactions Table
        $table = $installer->getConnection()
            ->newTable($installer->getTable('payex_transactions'))
            ->addColumn(
                'transaction_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Table Key ID'
            )
            ->addColumn(
                'transaction_data',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' =>  true],
                'Transaction Data'
            )
            ->addColumn(
                'order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' =>  true],
                'Increment Order Id'
            )
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Transaction ID'
            )
            ->addColumn(
                'created',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                null,
                ['nullable' =>  true],
                'Created At'
            )
            ->addColumn(
                'updated',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                null,
                ['nullable' =>  true],
                'Updated At'
            )
            ->addColumn(
                'type',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Transaction Type'
            )
            ->addColumn(
                'state',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Transaction State'
            )
            ->addColumn(
                'number',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Transaction Number'
            )
            ->addColumn(
                'amount',
                \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
                20,
                ['nullable' => true],
                'Amount'
            )
            ->addColumn(
                'vatAmount',
                \Magento\Framework\DB\Ddl\Table::TYPE_BIGINT,
                20,
                ['nullable' => true],
                'Amount'
            )
            ->addColumn(
                'description',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Description'
            )
            ->addColumn(
                'payeeReference',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true],
                'Payee Reference'
            )
            ->addIndex(
                $installer->getIdxName('payex_transactions', ['order_id', 'id']),
                ['order_id', 'id']
            )
            ->addIndex(
                $installer->getIdxName(
                    'payex_transactions',
                    ['number'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                'number',
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->setComment('PayEx PSP Transactions');
        $installer->getConnection()->createTable($table);

        // Order UUID
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'payex_order_uuid',
            [
                'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length'   => '255',
                'unsigned' => true,
                'nullable' => true,
                'comment'  => 'Order UUID'
            ]
        );

        $installer->endSetup();
    }
}
