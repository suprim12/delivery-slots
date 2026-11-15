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
use suprim\deliveryslots\models\DeliverySlot;
use yii\base\Event;

/**
 * After a completed order's slot is booked, such as to send it to a routing or delivery service.
 */
class BookEvent extends Event
{
    public Order $order;
    public DeliverySlot $booking;
}
