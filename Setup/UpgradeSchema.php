<?php

namespace PayEx\Payments\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Do Upgrade Schema
     *
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            // Install PayEx Transactions Table
            $this->installTransactionsTable($setup, $context);
        }

        $setup->endSetup();
    }

    /**
     * Install PayEx Transactions Table
     *
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     */
    private function installTransactionsTable(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        // Install PayEx Transactions Table
        $table = $setup
            ->getConnection()
            ->newTable($setup->getTable('payex_transactions'))
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
                ['nullable' => true],
                'Transaction Data'
            )
            ->addColumn(
                'order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true],
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
                ['nullable' => true],
                'Created At'
            )
            ->addColumn(
                'updated',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                null,
                ['nullable' => true],
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
                $setup->getIdxName('payex_transactions', ['order_id', 'id']),
                ['order_id', 'id']
            )
            ->addIndex(
                $setup->getIdxName(
                    'payex_transactions',
                    ['number'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                'number',
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )
            ->setComment('PayEx PSP Transactions');

        $setup->getConnection()->createTable($table);

        // Order UUID
        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'payex_order_uuid',
            [
                'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length'   => '255',
                'unsigned' => true,
                'nullable' => true,
                'comment'  => 'Order UUID'
            ]
        );
    }

}