<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\events;

use craft\commerce\elements\Order;
use craft\commerce\models\ShippingMethod;
use suprim\deliveryslots\models\Day;
use suprim\deliveryslots\models\Schedule;
use yii\base\Event;

/**
 * The days and slots an order can choose from. Change `days` to remove slots, change their fees or limits, or close
 * a day with `unavailableReason`. The same days are used to check a slot when it's chosen.
 */
class DaysEvent extends Event
{
    public Order $order;
    public ShippingMethod $method;
    public Schedule $schedule;

    /** @var array{zoneId: int|null, key: string, name: string, days: int[], capacity: int|null, fee: float}|null The address's zone, if the schedule has zones. */
    public ?array $zone = null;

    /** @var Day[] */
    public array $days = [];
}
