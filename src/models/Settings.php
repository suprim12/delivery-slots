<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\models;

use craft\base\Model;

/**
 * Options set in `config/delivery-slots.php`. Schedules are set per shipping method in the control panel.
 */
class Settings extends Model
{
    /** How long a chosen slot is held for a customer who hasn't paid yet, in minutes. */
    public int $holdMinutes = 15;

    /** How long a slot is held once the customer starts paying, so time on a payment page doesn’t lose it. */
    public int $paymentHoldMinutes = 60;

    /** Slots with this many places or fewer count as nearly full (`slot.isLow`). */
    public int $fewLeft = 2;

    /** An order field to show as the delivery note on the run sheet, such as `deliveryInstructions`. */
    public ?string $noteField = null;

    /**
     * The handle of the Commerce order status that the run sheet’s **Delivered** button sets. The button shows
     * when the store has a status with this handle.
     */
    public string $deliveredStatus = 'delivered';

    /**
     * A site template for the email customers get when staff change their slot, such as `_emails/slot-changed`.
     * It gets `order`, `slot` and `previousSlot`. Leave it null for the built-in email.
     */
    public ?string $slotChangedTemplate = null;

    /**
     * The subject of that email. `{site}`, `{reference}` and `{slot}` are replaced with the site name, the order
     * reference and the new time. Leave it null for “{site}: your new delivery time for order {reference}”.
     */
    public ?string $slotChangedSubject = null;
}
