<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\migrations;

use craft\db\Migration;
use suprim\deliveryslots\records\BookingRecord;
use suprim\deliveryslots\records\ScheduleRecord;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(ScheduleRecord::TABLE_NAME)) {
            $this->createTable(ScheduleRecord::TABLE_NAME, [
                'id' => $this->primaryKey(),
                'shippingMethodId' => $this->integer()->notNull(),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'settings' => $this->json(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, ScheduleRecord::TABLE_NAME, ['shippingMethodId'], true);
            $this->addForeignKey(null, ScheduleRecord::TABLE_NAME, ['shippingMethodId'], '{{%commerce_shippingmethods}}', ['id'], 'CASCADE');
        }

        if (!$this->db->tableExists(BookingRecord::TABLE_NAME)) {
            $this->createTable(BookingRecord::TABLE_NAME, [
                'id' => $this->primaryKey(),
                'orderId' => $this->integer()->notNull(),
                'shippingMethodId' => $this->integer()->notNull(),
                'date' => $this->date()->notNull(),
                'start' => $this->string(5)->notNull(),
                'end' => $this->string(5)->notNull(),
                'zoneId' => $this->integer(),
                'fee' => $this->decimal(14, 4)->notNull()->defaultValue(0),
                'status' => $this->string(10)->notNull()->defaultValue(BookingRecord::STATUS_HELD),
                'expiresAt' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, BookingRecord::TABLE_NAME, ['orderId'], true);
            $this->createIndex(null, BookingRecord::TABLE_NAME, ['shippingMethodId', 'date', 'start']);
            $this->addForeignKey(null, BookingRecord::TABLE_NAME, ['orderId'], '{{%commerce_orders}}', ['id'], 'CASCADE');
            $this->addForeignKey(null, BookingRecord::TABLE_NAME, ['shippingMethodId'], '{{%commerce_shippingmethods}}', ['id'], 'CASCADE');
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(BookingRecord::TABLE_NAME);
        $this->dropTableIfExists(ScheduleRecord::TABLE_NAME);

        return true;
    }
}
