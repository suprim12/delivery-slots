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
use suprim\deliveryslots\Plugin;

/**
 * A time window on one day that a customer can choose.
 */
class Slot extends Model
{
    /** When the slot starts, in the site's time zone. */
    public DateTimeImmutable $date;
    public string $start = '';
    public string $end = '';
    public int $capacity = 0;
    public int $left = 0;

    /** Extra charge for this slot, such as a zone's fee. */
    public float $fee = 0.0;

    /** The value to post as `deliverySlot`: `2026-11-17@08:00`. */
    public function getValue(): string
    {
        return $this->date->format('Y-m-d') . '@' . $this->start;
    }

    public function getIsFull(): bool
    {
        return $this->left <= 0;
    }

    /** The times, such as “8 – 10am”. */
    public function getLabel(): string
    {
        return Times::range($this->start, $this->end);
    }

    /** Whether few places are left, for a “2 left” note. */
    public function getIsLow(): bool
    {
        return $this->left > 0 && $this->left <= Plugin::getInstance()->getSettings()->fewLeft;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
