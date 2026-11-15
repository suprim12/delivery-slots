<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\services;

use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin as Commerce;
use craft\elements\Address;
use craft\elements\Entry;
use suprim\deliveryslots\models\DeliverySlot;
use suprim\deliveryslots\models\Times;
use suprim\deliveryslots\Plugin;

/**
 * Booked orders for the run sheet, packing list and exports.
 */
class RunSheets extends Component
{
    /**
     * Shipping methods that have a schedule, by ID.
     *
     * @return array<int, ShippingMethod>
     */
    public function getMethods(): array
    {
        $methods = [];
        foreach (Commerce::getInstance()->getStores()->getAllStores() as $store) {
            foreach (Commerce::getInstance()->getShippingMethods()->getAllShippingMethods($store->id) as $method) {
                if ($method->id && Plugin::getInstance()->schedules->getByShippingMethodId($method->id)) {
                    $methods[$method->id] = $method;
                }
            }
        }

        return $methods;
    }

    /**
     * One row per booked order between two dates (Y-m-d, both included), by date, slot, zone and suburb.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRows(string $from, string $to, ?int $shippingMethodId = null): array
    {
        $bookings = Plugin::getInstance()->slots->getBookedBetween($from, $to, $shippingMethodId);
        if (!$bookings) {
            return [];
        }

        $orders = [];
        foreach (Order::find()->id(array_map(fn(DeliverySlot $b) => $b->orderId, $bookings))->status(null)->all() as $order) {
            $orders[$order->id] = $order;
        }
        $zoneNames = [];
        $zoneIds = array_values(array_filter(array_unique(array_map(fn(DeliverySlot $b) => $b->zoneId, $bookings))));
        if ($zoneIds) {
            foreach (Entry::find()->id($zoneIds)->status(null)->site('*')->unique()->all() as $zone) {
                $zoneNames[$zone->id] = (string)$zone->title;
            }
        }
        $methods = $this->getMethods();
        $noteField = Plugin::getInstance()->getSettings()->noteField;

        $rows = [];
        foreach ($bookings as $booking) {
            $order = $orders[$booking->orderId] ?? null;
            if ($order) {
                $rows[] = $this->row($order, $booking, $zoneNames, $methods, $noteField);
            }
        }
        usort($rows, fn($a, $b) => [$a['date'], $a['start'], $a['zone'], $a['locality'], $a['address']] <=> [$b['date'], $b['start'], $b['zone'], $b['locality'], $b['address']]);

        return $rows;
    }

    /**
     * Rows grouped by slot, for one day.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{start: string, end: string, label: string, rows: array<int, array<string, mixed>>}>
     */
    public function groupBySlot(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['date'] . ' ' . $row['start'] . '-' . $row['end'];
            $groups[$key] ??= ['start' => $row['start'], 'end' => $row['end'], 'label' => $row['slot'], 'rows' => []];
            $groups[$key]['rows'][] = $row;
        }
        ksort($groups);

        return array_values($groups);
    }

    /**
     * How full each window is on a day, for one shipping method.
     *
     * @return array<int, array{label: string, count: int, capacity: int}>
     */
    public function getSummary(string $date, int $shippingMethodId): array
    {
        $schedule = Plugin::getInstance()->schedules->getByShippingMethodId($shippingMethodId);
        if (!$schedule) {
            return [];
        }
        $counts = [];
        foreach (Plugin::getInstance()->slots->getBookedOn($date, $shippingMethodId) as $booking) {
            $counts[$booking->start] = ($counts[$booking->start] ?? 0) + 1;
        }

        return array_map(fn($w) => [
            'label' => Times::range($w['start'], $w['end']),
            'count' => $counts[$w['start']] ?? 0,
            'capacity' => $w['capacity'],
        ], $schedule->windowsFor($date));
    }

    /**
     * What to pack: each item's total quantity, by slot.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array{slots: array<string, string>, items: array<int, array{description: string, sku: string, bySlot: array<string, int|float>, total: float}>}
     */
    public function getPackingList(array $rows): array
    {
        $slots = [];
        $items = [];
        foreach ($rows as $row) {
            $slotKey = (string)$row['start'];
            $slots[$slotKey] = (string)$row['slot'];
            /** @var Order $order */
            $order = $row['order'];
            foreach ($order->getLineItems() as $lineItem) {
                $key = $lineItem->purchasableId ? 'p' . $lineItem->purchasableId : 's' . $lineItem->getSku();
                $items[$key] ??= [
                    'description' => (string)($lineItem->getPurchasable()->title ?? $lineItem->getDescription()),
                    'sku' => (string)$lineItem->getSku(),
                    'bySlot' => [],
                    'total' => 0.0,
                ];
                $items[$key]['bySlot'][$slotKey] = ($items[$key]['bySlot'][$slotKey] ?? 0) + $lineItem->qty;
                $items[$key]['total'] += $lineItem->qty;
            }
        }
        ksort($slots);
        uasort($items, fn($a, $b) => strcasecmp($a['description'], $b['description']));

        return ['slots' => $slots, 'items' => array_values($items)];
    }

    /**
     * The rows as CSV.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function toCsv(array $rows): string
    {
        $noteField = Plugin::getInstance()->getSettings()->noteField;
        $header = ['Date', 'Slot', 'Order', 'Customer', 'Email', 'Phone', 'Address', 'Suburb', 'Postcode', 'Zone', 'Items', 'Total', 'Shipping method'];
        if ($noteField) {
            $header[] = 'Note';
        }
        $lines = [$header];
        foreach ($rows as $row) {
            $line = [$row['date'], $row['slot'], $row['reference'], $row['name'], $row['email'], $row['phone'], $row['address'], $row['locality'], $row['postcode'], $row['zone'], $row['items'], number_format((float)$row['total'], 2, '.', ''), $row['method']];
            if ($noteField) {
                $line[] = $row['note'];
            }
            $lines[] = $line;
        }

        return $this->csv($lines);
    }

    /**
     * The packing list as CSV.
     *
     * @param array{slots: array<string, string>, items: array<int, array<string, mixed>>} $packing
     */
    public function packingToCsv(array $packing): string
    {
        $lines = [array_merge(['Item', 'SKU'], array_values($packing['slots']), ['Total'])];
        foreach ($packing['items'] as $item) {
            $line = [$item['description'], $item['sku']];
            foreach (array_keys($packing['slots']) as $slot) {
                $line[] = $item['bySlot'][$slot] ?? 0;
            }
            $line[] = $item['total'];
            $lines[] = $line;
        }

        return $this->csv($lines);
    }

    /**
     * @param array<int, array<int, mixed>> $lines
     */
    private function csv(array $lines): string
    {
        $out = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($out, array_map([$this, 'cell'], $line), ',', '"', '');
        }
        rewind($out);
        $csv = (string)stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * A CSV cell that spreadsheets won't run as a formula. Customers type their own names and addresses, so a
     * value such as `=HYPERLINK(…)` is prefixed with an apostrophe. Numbers are left as they are.
     */
    private function cell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($value)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @param array<int, string> $zoneNames
     * @param array<int, ShippingMethod> $methods
     * @return array<string, mixed>
     */
    private function row(Order $order, DeliverySlot $booking, array $zoneNames, array $methods, ?string $noteField): array
    {
        $address = $order->getShippingAddress();
        $note = '';
        if ($noteField && $order->getFieldLayout()->getFieldByHandle($noteField)) {
            $note = trim((string)$order->getFieldValue($noteField));
        }

        return [
            'order' => $order,
            'booking' => $booking,
            'date' => $booking->date,
            'start' => $booking->start,
            'end' => $booking->end,
            'slot' => $booking->getTimeLabel(),
            'reference' => $order->reference ?: $order->getShortNumber(),
            'name' => $address?->fullName ?: (string)$order->getCustomer()?->getName(),
            'email' => (string)$order->getEmail(),
            'phone' => $this->phone($address),
            'address' => $address ? implode(', ', array_filter([$address->addressLine1, $address->addressLine2])) : '',
            'locality' => (string)($address->locality ?? ''),
            'postcode' => (string)($address->postalCode ?? ''),
            'zone' => $booking->zoneName ?: ($booking->zoneId ? ($zoneNames[$booking->zoneId] ?? '') : ''),
            'items' => $order->getTotalQty(),
            'total' => $order->getTotalPrice(),
            'method' => isset($methods[$booking->shippingMethodId]) ? $methods[$booking->shippingMethodId]->getName() : '',
            'note' => $note,
            'status' => $order->getOrderStatus(),
            'delivered' => $order->getOrderStatus()?->handle === Plugin::getInstance()->getSettings()->deliveredStatus,
        ];
    }

    /**
     * The address's phone number, when its field layout has a Phone field.
     */
    private function phone(?Address $address): string
    {
        if (!$address) {
            return '';
        }
        foreach (['phone', 'phoneNumber', 'telephone'] as $handle) {
            if ($address->getFieldLayout()?->getFieldByHandle($handle)) {
                return trim((string)$address->getFieldValue($handle));
            }
        }

        return '';
    }
}
