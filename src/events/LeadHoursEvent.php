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
use suprim\deliveryslots\models\Schedule;
use yii\base\Event;

/**
 * How much notice an order needs: change `leadHours` for products or customers of your own.
 */
class LeadHoursEvent extends Event
{
    public Order $order;
    public Schedule $schedule;

    /** Hours before a slot starts that it can still be booked, with any product notice applied. */
    public int $leadHours = 0;
}
