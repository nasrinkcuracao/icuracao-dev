<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-report
 * @version   1.3.30
 * @copyright Copyright (C) 2018 Mirasvit (https://mirasvit.com/)
 */


namespace Mirasvit\Report\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Mirasvit\Report\Api\Data\EmailInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD)
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $connection = $installer->getConnection();

        if (version_compare($context->getVersion(), '1.0.1') < 0) {
            $connection->dropTable($installer->getTable('mst_report_email'));

            $table = $installer->getConnection()->newTable(
                $installer->getTable('mst_report_email')
            )->addColumn(
                EmailInterface::ID,
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Email Id'
            )->addColumn(
                EmailInterface::TITLE,
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Title'
            )->addColumn(
                EmailInterface::IS_ACTIVE,
                Table::TYPE_INTEGER,
                1,
                ['nullable' => false, 'default' => 0],
                'Is Active'
            )->addColumn(
                EmailInterface::SUBJECT,
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Subject'
            )->addColumn(
                EmailInterface::RECIPIENT,
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Recipient'
            )->addColumn(
                EmailInterface::SCHEDULE,
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Schedule'
            )->addColumn(
                EmailInterface::BLOCKS_SERIALIZED,
                Table::TYPE_TEXT,
                '64k',
                ['nullable' => false],
                'Content'
            )->setComment(
                'Report Email'
            );

            $installer->getConnection()->createTable($table);
        }
    }
}
