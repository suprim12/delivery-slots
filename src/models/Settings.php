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

    /** Slots with this many places or fewer count as nearly full (`slot.isLow`). */
    public int $fewLeft = 2;

    /** An order field to show as the delivery note on the run sheet, such as `deliveryInstructions`. */
    public ?string $noteField = null;
}
