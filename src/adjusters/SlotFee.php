<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\adjusters;

use Craft;
use craft\base\Component;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use suprim\deliveryslots\Plugin;

/**
 * Adds the slot's fee, such as a zone's fee, to the order's shipping.
 */
class SlotFee extends Component implements AdjusterInterface
{
    public const ADJUSTMENT_TYPE = 'shipping';

    public function adjust(Order $order): array
    {
        $booking = Plugin::getInstance()->slots->getBooking($order);
        if (!$booking || $booking->fee <= 0) {
            return [];
        }

        $adjustment = new OrderAdjustment();
        $adjustment->type = self::ADJUSTMENT_TYPE;
        $adjustment->name = Craft::t('delivery-slots', 'Delivery time');
        $adjustment->description = $booking->getLabel();
        $adjustment->amount = $booking->fee;
        $adjustment->setOrder($order);
        $adjustment->sourceSnapshot = ['deliverySlot' => $booking->getValue(), 'zoneId' => $booking->zoneId];

        return [$adjustment];
    }
}
