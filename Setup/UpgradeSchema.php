<?php

namespace PayEx\Payments\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.12', '<')) {
            // PayEx Checkout Session
            $table = $setup->getTable('quote');
            $setup->getConnection()->addColumn(
                $table,
                'payex_payment_session',
                [
                    'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length'   => '64k',
                    'unsigned' => true,
                    'nullable' => true,
                    'comment'  => 'PayEx Checkout Session'
                ]
            );

            // PayEx Checkout Payment Id
            $setup->getConnection()->addColumn(
                $table,
                'payex_payment_id',
                [
                    'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length'   => '64k',
                    'unsigned' => true,
                    'nullable' => true,
                    'comment'  => 'PayEx Checkout Payment Id'
                ]
            );

            // PayEx Checkout Operations
            $table = $setup->getTable('sales_order');
            $setup->getConnection()->addColumn(
                $table,
                'payex_checkout',
                [
                    'type'     => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length'   => '64k',
                    'unsigned' => true,
                    'nullable' => true,
                    'comment'  => 'PayEx Checkout Response'
                ]
            );
        }

        $setup->endSetup();
    }
}
