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
use craft\web\Controller;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use suprim\deliveryslots\models\Times;
use suprim\deliveryslots\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Lets staff move an order to another slot, or set one, from the control panel.
 */
class OrdersController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->requireCpRequest();
        $this->requirePermission('commerce-editOrders');

        return true;
    }

    public function actionEdit(int $orderId): Response
    {
        $order = $this->order($orderId);
        $method = $order->getShippingMethod();
        $schedule = $method instanceof ShippingMethod && $method->id ? Plugin::getInstance()->schedules->getByShippingMethodId($method->id) : null;
        $current = Plugin::getInstance()->slots->getBooking($order);

        $tz = new DateTimeZone(Craft::$app->getTimeZone());
        $dateParam = $this->request->getQueryParam('date');
        $date = is_string($dateParam) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)
            ? new DateTimeImmutable($dateParam, $tz)
            : ($current ? new DateTimeImmutable($current->date, $tz) : Plugin::getInstance()->slots->now()->setTime(0, 0));
        $ymd = $date->format('Y-m-d');

        // Every window that runs on the day, including ones that are full or closed for the date: staff can override.
        $windows = [];
        if ($schedule) {
            $taken = Plugin::getInstance()->slots->getTakenOn($method->id, $ymd, $order->id);
            $open = [];
            foreach ($schedule->windowsFor($ymd) as $w) {
                $open[$w['start']] = $w['capacity'];
            }
            foreach ($schedule->windowsOn((int)$date->format('N')) as $w) {
                $windows[] = [
                    'start' => $w['start'],
                    'label' => Times::range($w['start'], $w['end']),
                    'taken' => $taken[$w['start']] ?? 0,
                    'capacity' => $open[$w['start']] ?? 0,
                    'closed' => !isset($open[$w['start']]),
                    'current' => $current && $current->date === $ymd && $current->start === $w['start'],
                ];
            }
        }

        return $this->renderTemplate('delivery-slots/orders/_edit', [
            'order' => $order,
            'method' => $method,
            'schedule' => $schedule,
            'current' => $current,
            'date' => $date,
            'prev' => $date->sub(new DateInterval('P1D'))->format('Y-m-d'),
            'next' => $date->add(new DateInterval('P1D'))->format('Y-m-d'),
            'closedLabel' => $schedule?->closedLabel($ymd),
            'windows' => $windows,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $order = $this->order((int)$this->request->getRequiredBodyParam('orderId'));
        $slots = Plugin::getInstance()->slots;

        if ($this->request->getBodyParam('remove')) {
            $slots->release($order);
            return $this->asSuccess(Craft::t('delivery-slots', 'Delivery time removed.'));
        }

        $previous = $slots->getBooking($order);
        $error = $slots->assign($order, (string)$this->request->getRequiredBodyParam('date'), (string)$this->request->getRequiredBodyParam('start'));
        if ($error !== null) {
            return $this->asFailure($error);
        }
        $slot = $slots->getBooking($order);
        $message = Craft::t('delivery-slots', 'Delivery time changed to {slot}.', ['slot' => (string)$slot]);

        // Completed orders only: a customer still checking out sees the change in their cart.
        if ($slot && $order->isCompleted && $this->request->getBodyParam('notify')) {
            $message .= ' ' . (Plugin::getInstance()->notifications->sendSlotChanged($order, $slot, $previous)
                ? Craft::t('delivery-slots', 'The customer was emailed.')
                : Craft::t('delivery-slots', 'The customer couldn’t be emailed.'));
        }

        return $this->asSuccess($message);
    }

    private function order(int $id): Order
    {
        $order = Order::find()->id($id)->status(null)->one();
        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        return $order;
    }
}
