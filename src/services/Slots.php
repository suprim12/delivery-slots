<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingMethod;
use craft\db\Query;
use craft\helpers\Db;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use suprim\deliveryslots\models\Day;
use suprim\deliveryslots\models\DeliverySlot;
use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\models\Slot;
use suprim\deliveryslots\Plugin;
use suprim\deliveryslots\records\BookingRecord;

/**
 * Works out which slots an order can choose, and holds and books them.
 */
class Slots extends Component
{
    /** @var array<int, DeliverySlot|null> Bookings by order ID, for this request. */
    private array $_bookings = [];

    /** For tests: the time to treat as now. */
    public ?DateTimeImmutable $now = null;

    /**
     * The days in the order's slot picker, from today or tomorrow up to the schedule's days ahead.
     * Empty when the shipping method has no slots, or the address is outside all of its zones.
     *
     * @param ShippingMethod|string|null $method The shipping method, or its handle. Default: the order's.
     * @return Day[]
     */
    public function getDays(Order $order, ShippingMethod|string|null $method = null): array
    {
        $method = $this->method($order, $method);
        $schedule = $method ? Plugin::getInstance()->schedules->getEnabled($method->id) : null;
        if (!$method || !$schedule) {
            return [];
        }

        $zone = null;
        $zones = Plugin::getInstance()->zones;
        if ($schedule->zones && $zones->isAvailable()) {
            $zone = $zones->match($schedule, $order);
            if ($zone === null) {
                return [];
            }
        }

        $now = $this->now();
        $today = $now->setTime(0, 0);
        $earliest = $now->add(new DateInterval('PT' . $schedule->leadHours . 'H'));
        $cutoffPassed = $schedule->cutoff !== null && $now->format('H:i') >= $schedule->cutoff;

        $dates = [];
        for ($i = $schedule->sameDay ? 0 : 1; $i <= $schedule->daysAhead; $i++) {
            $dates[] = $today->add(new DateInterval("P{$i}D"));
        }
        [$taken, $takenInZone] = $this->taken($method->id, $dates, $order->id, $zone['zoneId'] ?? null);

        $days = [];
        foreach ($dates as $i => $date) {
            $ymd = $date->format('Y-m-d');
            $weekday = (int)$date->format('N');
            $day = new Day(['date' => $date]);
            $days[] = $day;

            if (($closed = $schedule->closedLabel($ymd)) !== null) {
                $day->unavailableReason = $closed;
                continue;
            }
            $windows = $schedule->windowsOn($weekday);
            if (!$windows) {
                $day->unavailableReason = Craft::t('delivery-slots', 'Closed');
                continue;
            }
            if ($zone !== null && $zone['days'] && !in_array($weekday, $zone['days'], true)) {
                $day->unavailableReason = Craft::t('delivery-slots', 'Not in your area');
                continue;
            }
            if ($cutoffPassed && $date == $today->add(new DateInterval('P1D'))) {
                $day->unavailableReason = Craft::t('delivery-slots', 'Ordering has closed');
                continue;
            }

            foreach ($windows as $window) {
                [$h, $m] = array_map('intval', explode(':', $window['start']));
                $start = $date->setTime($h, $m);
                if ($start < $earliest) {
                    continue;
                }
                $left = $window['capacity'] - ($taken[$ymd][$window['start']] ?? 0);
                if ($zone !== null && $zone['capacity'] !== null) {
                    $left = min($left, $zone['capacity'] - ($takenInZone[$ymd][$window['start']] ?? 0));
                }
                $day->slots[] = new Slot([
                    'date' => $start,
                    'start' => $window['start'],
                    'end' => $window['end'],
                    'capacity' => $window['capacity'],
                    'left' => max(0, $left),
                    'fee' => $zone['fee'] ?? 0.0,
                ]);
            }
            if (!$day->slots) {
                $day->unavailableReason = Craft::t('delivery-slots', 'Ordering has closed');
            }
        }

        return $days;
    }

    /**
     * Whether the order's shipping method offers slots.
     */
    public function hasSlots(Order $order, ShippingMethod|string|null $method = null): bool
    {
        $method = $this->method($order, $method);

        return $method && Plugin::getInstance()->schedules->getEnabled($method->id) !== null;
    }

    /**
     * Holds a slot for the order while the customer checks out.
     *
     * @param string $value A slot's value, such as `2026-11-17@08:00`, or an empty string to clear it.
     * @return string|null An error message, or null when the slot is held.
     */
    public function choose(Order $order, string $value): ?string
    {
        if (!$order->id) {
            return Craft::t('delivery-slots', 'Add something to your cart first.');
        }
        if (trim($value) === '') {
            $this->release($order);
            return null;
        }
        $method = $this->method($order, null);
        if (!$method || !$this->hasSlots($order)) {
            return Craft::t('delivery-slots', 'Choose a shipping method with delivery times first.');
        }
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})@(\d{2}:\d{2})$/', trim($value), $m)) {
            return Craft::t('delivery-slots', 'That delivery time isn’t available. Choose another.');
        }
        [, $date, $start] = $m;

        $mutex = Craft::$app->getMutex();
        $lock = "delivery-slots:$method->id:$date:$start";
        if (!$mutex->acquire($lock, 5)) {
            return Craft::t('delivery-slots', 'That delivery time is busy. Try again.');
        }

        try {
            $slot = $this->find($order, $date, $start);
            if (!$slot || $slot->getIsFull()) {
                return Craft::t('delivery-slots', 'That delivery time is no longer available. Choose another.');
            }

            $zone = Plugin::getInstance()->zones->match(Plugin::getInstance()->schedules->getEnabled($method->id), $order);
            $record = BookingRecord::findOne(['orderId' => $order->id]) ?? new BookingRecord(['orderId' => $order->id]);
            $record->shippingMethodId = $method->id;
            $record->date = $date;
            $record->start = $slot->start;
            $record->end = $slot->end;
            $record->zoneId = $zone['zoneId'] ?? null;
            $record->fee = $slot->fee;
            $record->status = BookingRecord::STATUS_HELD;
            $record->expiresAt = Db::prepareDateForDb($this->holdUntil());
            $record->save(false);
            $this->_bookings[$order->id] = DeliverySlot::fromRecord($record);
        } finally {
            $mutex->release($lock);
        }

        return null;
    }

    /**
     * Checks the order's slot before payment, holding it again if the hold ran out and it's still free.
     *
     * @return string|null An error message, or null when the order can be paid for.
     */
    public function checkBeforePayment(Order $order): ?string
    {
        if (!$this->hasSlots($order)) {
            return null;
        }
        $booking = $this->getBooking($order);
        if (!$booking) {
            return Craft::t('delivery-slots', 'Choose a delivery time.');
        }
        if ($booking->getIsBooked()) {
            return null;
        }

        return $this->choose($order, $booking->getValue());
    }

    /**
     * Books the order's slot once the order is complete.
     */
    public function book(Order $order): void
    {
        $record = $order->id ? BookingRecord::findOne(['orderId' => $order->id]) : null;
        if (!$record) {
            return;
        }
        if ($record->shippingMethodId !== $order->getShippingMethod()?->id) {
            // The customer changed to a method without this slot.
            $record->delete();
            unset($this->_bookings[$order->id]);
            return;
        }
        $record->status = BookingRecord::STATUS_BOOKED;
        $record->expiresAt = null;
        $record->save(false);
        $this->_bookings[$order->id] = DeliverySlot::fromRecord($record);
    }

    /**
     * Removes the order's slot.
     */
    public function release(Order $order): void
    {
        if ($order->id) {
            BookingRecord::deleteAll(['orderId' => $order->id]);
            $this->_bookings[$order->id] = null;
        }
    }

    /**
     * The order's slot, if it has one for its current shipping method.
     */
    public function getBooking(Order $order): ?DeliverySlot
    {
        if (!$order->id) {
            return null;
        }
        if (!array_key_exists($order->id, $this->_bookings)) {
            $record = BookingRecord::findOne(['orderId' => $order->id]);
            $this->_bookings[$order->id] = $record ? DeliverySlot::fromRecord($record) : null;
        }
        $booking = $this->_bookings[$order->id];
        if (!$booking) {
            return null;
        }
        if ($booking->shippingMethodId !== $order->getShippingMethod()?->id) {
            return null;
        }

        return $booking;
    }

    /**
     * Bookings for a day, for the run sheet, earliest first.
     *
     * @return DeliverySlot[]
     */
    public function getBookedOn(string $date, ?int $shippingMethodId = null): array
    {
        $query = BookingRecord::find()
            ->where(['date' => $date, 'status' => BookingRecord::STATUS_BOOKED])
            ->orderBy(['start' => SORT_ASC, 'id' => SORT_ASC]);
        if ($shippingMethodId) {
            $query->andWhere(['shippingMethodId' => $shippingMethodId]);
        }

        return array_map(fn(BookingRecord $r) => DeliverySlot::fromRecord($r), $query->all());
    }

    public function now(): DateTimeImmutable
    {
        return $this->now ?? new DateTimeImmutable('now', new DateTimeZone(Craft::$app->getTimeZone()));
    }

    private function holdUntil(): DateTime
    {
        $minutes = max(1, Plugin::getInstance()->getSettings()->holdMinutes);

        return new DateTime('@' . ($this->now()->getTimestamp() + $minutes * 60));
    }

    private function find(Order $order, string $date, string $start): ?Slot
    {
        foreach ($this->getDays($order) as $day) {
            if ($day->date->format('Y-m-d') !== $date) {
                continue;
            }
            foreach ($day->slots as $slot) {
                if ($slot->start === $start) {
                    return $slot;
                }
            }
        }

        return null;
    }

    private function method(Order $order, ShippingMethod|string|null $method): ?ShippingMethod
    {
        if ($method instanceof ShippingMethod) {
            return $method;
        }
        if (is_string($method)) {
            foreach (\craft\commerce\Plugin::getInstance()->getShippingMethods()->getAllShippingMethods($order->storeId) as $m) {
                if ($m->getHandle() === $method) {
                    return $m;
                }
            }
            return null;
        }
        $current = $order->getShippingMethod();

        return $current instanceof ShippingMethod ? $current : null;
    }

    /**
     * Slots taken by other orders: booked, or held and not yet expired.
     *
     * @param DateTimeImmutable[] $dates
     * @return array{0: array<string, array<string, int>>, 1: array<string, array<string, int>>}
     */
    private function taken(int $methodId, array $dates, ?int $orderId, ?int $zoneId): array
    {
        $rows = (new Query())
            ->select(['date', 'start', 'zoneId', 'count' => 'COUNT(*)'])
            ->from(BookingRecord::TABLE_NAME)
            ->where([
                'shippingMethodId' => $methodId,
                'date' => array_map(fn($d) => $d->format('Y-m-d'), $dates),
            ])
            ->andWhere(['or',
                ['status' => BookingRecord::STATUS_BOOKED],
                ['>', 'expiresAt', Db::prepareDateForDb(new DateTime('@' . $this->now()->getTimestamp()))],
            ])
            ->groupBy(['date', 'start', 'zoneId']);
        if ($orderId) {
            $rows->andWhere(['not', ['orderId' => $orderId]]);
        }
        $rows = $rows->all();

        $all = [];
        $inZone = [];
        foreach ($rows as $row) {
            $date = substr((string)$row['date'], 0, 10);
            $all[$date][$row['start']] = ($all[$date][$row['start']] ?? 0) + (int)$row['count'];
            if ($zoneId !== null && (int)$row['zoneId'] === $zoneId) {
                $inZone[$date][$row['start']] = ($inZone[$date][$row['start']] ?? 0) + (int)$row['count'];
            }
        }

        return [$all, $inZone];
    }
}
