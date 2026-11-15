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
use DateTimeImmutable;

/**
 * A day in the slot picker, with its slots or the reason there are none.
 */
class Day extends Model
{
    public DateTimeImmutable $date;

    /** @var Slot[] */
    public array $slots = [];

    /** Why there are no slots, such as “Closed”, or null when the day has slots. */
    public ?string $unavailableReason = null;

    public function getIsAvailable(): bool
    {
        if ($this->unavailableReason !== null) {
            return false;
        }
        foreach ($this->slots as $slot) {
            if (!$slot->getIsFull()) {
                return true;
            }
        }

        return false;
    }

    /** Slots that aren't full. */
    public function getOpenSlots(): array
    {
        return array_values(array_filter($this->slots, fn(Slot $s) => !$s->getIsFull()));
    }
}
