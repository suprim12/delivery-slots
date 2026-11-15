<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\records;

use craft\db\ActiveRecord;

/**
 * A shipping method's slot schedule.
 *
 * @property int $id
 * @property int $shippingMethodId
 * @property bool $enabled
 * @property array|string|null $settings
 */
class ScheduleRecord extends ActiveRecord
{
    public const TABLE_NAME = '{{%deliveryslots_schedules}}';

    public static function tableName(): string
    {
        return self::TABLE_NAME;
    }
}
