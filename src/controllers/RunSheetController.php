<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin as Commerce;
use craft\elements\Address;
use craft\elements\Entry;
use craft\web\Controller;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use suprim\deliveryslots\models\DeliverySlot;
use suprim\deliveryslots\models\Times;
use suprim\deliveryslots\Plugin;
use yii\web\Response;

/**
 * The run sheet: a day's booked orders, by slot.
 */
class RunSheetController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->requirePermission(Plugin::PERMISSION_RUN_SHEET);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('delivery-slots/run-sheet/_index', $this->sheet());
    }

    public function actionPrint(): Response
    {
        return $this->renderTemplate('delivery-slots/run-sheet/_print', $this->sheet());
    }

    public function actionExport(): Response
    {
        $sheet = $this->sheet();
        $noteField = Plugin::getInstance()->getSettings()->noteField;

        $out = fopen('php://temp', 'r+');
        $header = ['Date', 'Slot', 'Order', 'Customer', 'Email', 'Phone', 'Address', 'Suburb', 'Postcode', 'Zone', 'Items', 'Shipping method'];
        if ($noteField) {
            $header[] = 'Note';
        }
        fputcsv($out, $header);
        foreach ($sheet['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                $line = [$sheet['date']->format('Y-m-d'), $group['label'], $row['reference'], $row['name'], $row['email'], $row['phone'], $row['address'], $row['locality'], $row['postcode'], $row['zone'], $row['items'], $row['method']];
                if ($noteField) {
                    $line[] = $row['note'];
                }
                fputcsv($out, $line);
            }
        }
        rewind($out);
        $csv = (string)stream_get_contents($out);
        fclose($out);

        return $this->response->sendContentAsFile($csv, 'run-sheet-' . $sheet['date']->format('Y-m-d') . '.csv', ['mimeType' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sheet(): array
    {
        $request = Craft::$app->getRequest();
        $tz = new DateTimeZone(Craft::$app->getTimeZone());
        $dateParam = $request->getQueryParam('date');
        $date = is_string($dateParam) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)
            ? new DateTimeImmutable($dateParam, $tz)
            : Plugin::getInstance()->slots->now()->setTime(0, 0);

        // Shipping methods with schedules, for the filter.
        $methods = [];
        foreach (Commerce::getInstance()->getStores()->getAllStores() as $store) {
            foreach (Commerce::getInstance()->getShippingMethods()->getAllShippingMethods($store->id) as $method) {
                if ($method->id && Plugin::getInstance()->schedules->getByShippingMethodId($method->id)) {
                    $methods[$method->id] = $method;
                }
            }
        }
        $methodId = (int)$request->getQueryParam('method') ?: null;
        if ($methodId && !isset($methods[$methodId])) {
            $methodId = null;
        }

        $bookings = Plugin::getInstance()->slots->getBookedOn($date->format('Y-m-d'), $methodId);
        $orders = [];
        if ($bookings) {
            foreach (Order::find()->id(array_map(fn(DeliverySlot $b) => $b->orderId, $bookings))->status(null)->all() as $order) {
                $orders[$order->id] = $order;
            }
        }
        $zoneNames = [];
        $zoneIds = array_values(array_filter(array_unique(array_map(fn(DeliverySlot $b) => $b->zoneId, $bookings))));
        if ($zoneIds) {
            foreach (Entry::find()->id($zoneIds)->status(null)->site('*')->unique()->all() as $zone) {
                $zoneNames[$zone->id] = (string)$zone->title;
            }
        }

        $noteField = Plugin::getInstance()->getSettings()->noteField;
        $groups = [];
        foreach ($bookings as $booking) {
            $order = $orders[$booking->orderId] ?? null;
            if (!$order) {
                continue;
            }
            $key = $booking->start . '-' . $booking->end;
            $groups[$key] ??= ['start' => $booking->start, 'end' => $booking->end, 'label' => Times::range($booking->start, $booking->end), 'rows' => []];
            $groups[$key]['rows'][] = $this->row($order, $booking, $zoneNames, $methods, $noteField);
        }
        foreach ($groups as &$group) {
            usort($group['rows'], fn($a, $b) => [$a['zone'], $a['locality'], $a['address']] <=> [$b['zone'], $b['locality'], $b['address']]);
        }
        unset($group);
        ksort($groups);

        // How full each window is, when one shipping method is shown.
        $summary = [];
        $schedule = $methodId ? Plugin::getInstance()->schedules->getByShippingMethodId($methodId) : null;
        if ($schedule) {
            foreach ($schedule->windowsOn((int)$date->format('N')) as $window) {
                $key = $window['start'] . '-' . $window['end'];
                $summary[] = [
                    'label' => Times::range($window['start'], $window['end']),
                    'count' => count($groups[$key]['rows'] ?? []),
                    'capacity' => $window['capacity'],
                ];
            }
        }

        return [
            'date' => $date,
            'prev' => $date->sub(new DateInterval('P1D'))->format('Y-m-d'),
            'next' => $date->add(new DateInterval('P1D'))->format('Y-m-d'),
            'methods' => $methods,
            'methodId' => $methodId,
            'groups' => array_values($groups),
            'summary' => $summary,
            'total' => array_sum(array_map(fn($g) => count($g['rows']), $groups)),
            'showNote' => (bool)$noteField,
        ];
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
            'reference' => $order->reference ?: $order->getShortNumber(),
            'name' => $address?->fullName ?: (string)$order->getCustomer()?->getName(),
            'email' => (string)$order->getEmail(),
            'phone' => $this->phone($address),
            'address' => $address ? implode(', ', array_filter([$address->addressLine1, $address->addressLine2])) : '',
            'locality' => (string)($address->locality ?? ''),
            'postcode' => (string)($address->postalCode ?? ''),
            'zone' => $booking->zoneId ? ($zoneNames[$booking->zoneId] ?? '') : '',
            'items' => $order->getTotalQty(),
            'method' => isset($methods[$booking->shippingMethodId]) ? $methods[$booking->shippingMethodId]->getName() : '',
            'note' => $note,
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
