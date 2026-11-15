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
use craft\web\Controller;
use DateTimeImmutable;
use DateTimeZone;
use suprim\deliveryslots\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * An order's slot as a calendar event: `actions/delivery-slots/calendar?number=…`.
 *
 * The order number is long and random, so only people who have it (the customer, from their confirmation) can download it.
 */
class CalendarController extends Controller
{
    protected array|int|bool $allowAnonymous = ['index'];

    public function actionIndex(string $number): Response
    {
        // Order numbers are 32 hex characters. Checking that first means query syntax such as `*` is never passed on.
        if (!preg_match('/^[a-f0-9]{32}$/i', $number)) {
            throw new NotFoundHttpException('Delivery time not found');
        }
        // Only completed orders: a cart's address isn't given out.
        $order = Order::find()->number($number)->isCompleted(true)->status(null)->one();
        $slot = $order ? Plugin::getInstance()->slots->getBooking($order) : null;
        if (!$order || !$slot) {
            throw new NotFoundHttpException('Delivery time not found');
        }

        $utc = new DateTimeZone('UTC');
        $format = fn(DateTimeImmutable $d) => $d->setTimezone($utc)->format('Ymd\THis\Z');
        $siteName = Craft::$app->getSites()->getCurrentSite()->getName();
        $method = $order->getShippingMethod()?->getName() ?? Craft::t('delivery-slots', 'Delivery');
        $address = $order->getShippingAddress();
        $location = $address ? implode(', ', array_filter([$address->addressLine1, $address->addressLine2, $address->locality, $address->postalCode])) : '';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//suprim//Delivery Slots//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $order->number . '@' . (parse_url((string)Craft::$app->getSites()->getCurrentSite()->getBaseUrl(), PHP_URL_HOST) ?: 'delivery-slots'),
            'DTSTAMP:' . $format(new DateTimeImmutable('now')),
            'DTSTART:' . $format($slot->getStartDate()),
            'DTEND:' . $format($slot->getEndDate()),
            'SUMMARY:' . $this->escape("$method · $siteName"),
            'DESCRIPTION:' . $this->escape(Craft::t('delivery-slots', 'Order {reference}', ['reference' => $order->reference ?: $order->getShortNumber()])),
        ];
        if ($location !== '') {
            $lines[] = 'LOCATION:' . $this->escape($location);
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return $this->response->sendContentAsFile(implode("\r\n", $lines) . "\r\n", 'delivery-' . $slot->date . '.ics', [
            'mimeType' => 'text/calendar',
            'inline' => false,
        ]);
    }

    private function escape(string $text): string
    {
        return str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }
}
