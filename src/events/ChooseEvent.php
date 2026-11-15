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
use craft\events\CancelableEvent;
use suprim\deliveryslots\models\Slot;

/**
 * Before a slot is held for an order. Set `isValid` to false to refuse it, with `error` as the message for the customer.
 */
class ChooseEvent extends CancelableEvent
{
    public Order $order;
    public Slot $slot;

    /** The message shown when the slot is refused. */
    public ?string $error = null;
}
