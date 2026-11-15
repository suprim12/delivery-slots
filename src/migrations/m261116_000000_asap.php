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

/**
 * Bookings remember when the customer asked for as soon as possible, rather than for a time.
 */
class m261116_000000_asap extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(BookingRecord::TABLE_NAME, 'asap')) {
            $this->addColumn(BookingRecord::TABLE_NAME, 'asap', $this->boolean()->notNull()->defaultValue(false)->after('zoneName'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261116_000000_asap can’t be reverted.\n";
        return false;
    }
}
