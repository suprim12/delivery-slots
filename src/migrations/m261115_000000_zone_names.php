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
 * Bookings remember their zone by key and name, so shipping zones, which aren’t entries, can be counted and shown.
 */
class m261115_000000_zone_names extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(BookingRecord::TABLE_NAME, 'zoneKey')) {
            $this->addColumn(BookingRecord::TABLE_NAME, 'zoneKey', $this->string(64)->after('zoneId'));
        }
        if (!$this->db->columnExists(BookingRecord::TABLE_NAME, 'zoneName')) {
            $this->addColumn(BookingRecord::TABLE_NAME, 'zoneName', $this->string()->after('zoneKey'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261115_000000_zone_names can’t be reverted.\n";
        return false;
    }
}
