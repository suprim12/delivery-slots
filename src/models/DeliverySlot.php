<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\models;

use Craft;
use craft\base\Model;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use suprim\deliveryslots\records\BookingRecord;

/**
 * The slot chosen for an order: `order.deliverySlot`.
 */
class DeliverySlot extends Model
{
    public ?int $id = null;
    public ?int $orderId = null;
    public ?int $shippingMethodId = null;

    /** Y-m-d, in the site's time zone. */
    public string $date = '';
    public string $start = '';
    public string $end = '';
    public ?int $zoneId = null;
    public float $fee = 0.0;
    public string $status = BookingRecord::STATUS_HELD;
    public ?DateTime $expiresAt = null;

    public static function fromRecord(BookingRecord $record): self
    {
        return new self([
            'id' => $record->id,
            'orderId' => $record->orderId,
            'shippingMethodId' => $record->shippingMethodId,
            'date' => (string)$record->date,
            'start' => $record->start,
            'end' => $record->end,
            'zoneId' => $record->zoneId,
            'fee' => (float)$record->fee,
            'status' => $record->status,
            'expiresAt' => $record->expiresAt ? DateTimeHelper::toDateTime($record->expiresAt) ?: null : null,
        ]);
    }

    /** When the slot starts, in the site's time zone. */
    public function getStartDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->date . ' ' . $this->start, new DateTimeZone(Craft::$app->getTimeZone()));
    }

    /** When the slot ends, in the site's time zone. */
    public function getEndDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->date . ' ' . $this->end, new DateTimeZone(Craft::$app->getTimeZone()));
    }

    /** The value posted as `deliverySlot`: `2026-11-17@08:00`. */
    public function getValue(): string
    {
        return $this->date . '@' . $this->start;
    }

    /** The times, such as “8 – 10am”. */
    public function getTimeLabel(): string
    {
        return Times::range($this->start, $this->end);
    }

    /** The day and times, such as “Monday 17 November, 8 – 10am”. */
    public function getLabel(): string
    {
        $day = Craft::$app->getFormatter()->asDate(DateTime::createFromImmutable($this->getStartDate()), 'php:l j F');

        return $day . ', ' . $this->getTimeLabel();
    }

    public function getIsBooked(): bool
    {
        return $this->status === BookingRecord::STATUS_BOOKED;
    }

    /** The Geo Maps zone entry the address is in, when the schedule has zones. */
    public function getZone(): ?Entry
    {
        return $this->zoneId ? Entry::find()->id($this->zoneId)->status(null)->one() : null;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
