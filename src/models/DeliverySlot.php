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
use craft\commerce\elements\Order;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
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

    /** The zone’s key: `entry:12` for a drawn zone, `shippingZone:3` for a Commerce shipping zone. */
    public ?string $zoneKey = null;

    /** The zone’s name when the slot was chosen. */
    public ?string $zoneName = null;
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
            'zoneKey' => $record->zoneKey,
            'zoneName' => $record->zoneName,
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

    /**
     * A link to download the slot as a calendar event (.ics), for the order confirmation page and emails.
     */
    public function getCalendarUrl(): ?string
    {
        $number = $this->orderId ? Order::find()->id($this->orderId)->status(null)->select(['commerce_orders.number'])->scalar() : null;

        return $number ? UrlHelper::siteUrl(UrlHelper::actionUrl('delivery-slots/calendar', ['number' => $number], null, false)) : null;
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

    /** The zone’s name, for a drawn or a shipping zone, or null when the slot has no zone. */
    public function getZoneName(): ?string
    {
        if ($this->zoneName !== null && $this->zoneName !== '') {
            return $this->zoneName;
        }
        $zone = $this->getZone();

        return $zone ? (string)$zone->title : null;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
