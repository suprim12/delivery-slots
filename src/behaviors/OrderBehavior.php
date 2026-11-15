<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\behaviors;

use craft\commerce\elements\Order;
use suprim\deliveryslots\models\Day;
use suprim\deliveryslots\models\DeliverySlot;
use suprim\deliveryslots\Plugin;
use yii\base\Behavior;

/**
 * Adds `order.deliverySlot` and `order.deliverySlotDays` to Commerce orders.
 *
 * @property Order $owner
 */
class OrderBehavior extends Behavior
{
    /**
     * The slot chosen for the order, or null.
     */
    public function getDeliverySlot(): ?DeliverySlot
    {
        return Plugin::getInstance()->slots->getBooking($this->owner);
    }

    /**
     * The days and slots the order can choose from, for its shipping method or another one.
     *
     * @return Day[]
     */
    public function getDeliverySlotDays(?string $shippingMethodHandle = null): array
    {
        return Plugin::getInstance()->slots->getDays($this->owner, $shippingMethodHandle);
    }

    /**
     * Whether the order's shipping method, or another one, offers slots.
     */
    public function getHasDeliverySlots(?string $shippingMethodHandle = null): bool
    {
        return Plugin::getInstance()->slots->hasSlots($this->owner, $shippingMethodHandle);
    }

    /**
     * Whether the shipping method delivers to zones and the customer hasn’t entered an address yet, so its times can’t
     * be shown. Show a message such as “Enter your address to see delivery times”.
     */
    public function getDeliverySlotsNeedAddress(?string $shippingMethodHandle = null): bool
    {
        return Plugin::getInstance()->slots->needsAddress($this->owner, $shippingMethodHandle);
    }
}
