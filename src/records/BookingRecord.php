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
 * An order's slot: held while the customer checks out, booked once the order is complete.
 *
 * @property int $id
 * @property int $orderId
 * @property int $shippingMethodId
 * @property string $date Y-m-d, in the site's time zone
 * @property string $start H:i
 * @property string $end H:i
 * @property int|null $zoneId
 * @property string|null $zoneKey
 * @property string|null $zoneName
 * @property bool $asap Whether the customer asked for as soon as possible
 * @property float|string $fee
 * @property string $status
 * @property string|null $expiresAt
 */
class BookingRecord extends ActiveRecord
{
    public const TABLE_NAME = '{{%deliveryslots_bookings}}';
    public const STATUS_HELD = 'held';
    public const STATUS_BOOKED = 'booked';

    public static function tableName(): string
    {
        return self::TABLE_NAME;
    }
}
