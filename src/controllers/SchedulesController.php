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
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use DateTime;
use DateTimeZone;
use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Slot schedules for shipping methods, in the control panel.
 */
class SchedulesController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->requireCpRequest();
        $this->requirePermission(Plugin::PERMISSION_SCHEDULES);

        return true;
    }

    public function actionIndex(): Response
    {
        $commerce = Commerce::getInstance();
        $stores = [];
        foreach ($commerce->getStores()->getAllStores() as $store) {
            $methods = [];
            foreach ($commerce->getShippingMethods()->getAllShippingMethods($store->id) as $method) {
                if (!$method->id) {
                    continue;
                }
                $methods[] = ['method' => $method, 'schedule' => Plugin::getInstance()->schedules->getByShippingMethodId($method->id)];
            }
            $stores[] = ['store' => $store, 'methods' => $methods];
        }

        return $this->renderTemplate('delivery-slots/schedules/_index', [
            'stores' => $stores,
            'multiStore' => count($stores) > 1,
        ]);
    }

    public function actionEdit(int $shippingMethodId, ?Schedule $schedule = null): Response
    {
        $method = $this->method($shippingMethodId);
        $schedule ??= Plugin::getInstance()->schedules->getByShippingMethodId($shippingMethodId) ?? $this->defaults($shippingMethodId);
        $zones = Plugin::getInstance()->zones;

        return $this->renderTemplate('delivery-slots/schedules/_edit', [
            'method' => $method,
            'schedule' => $schedule,
            'windowRows' => array_map(fn($w) => [
                'start' => $this->time($w['start']),
                'end' => $this->time($w['end']),
                'capacity' => $w['capacity'],
            ] + $this->dayCells($w['days']), $schedule->windows),
            'closedRows' => array_map(fn($c) => [
                'date' => new DateTime($c['date'], new DateTimeZone(Craft::$app->getTimeZone())),
                'label' => $c['label'],
            ], $schedule->closedDates),
            'overrideRows' => array_map(fn($o) => [
                'date' => new DateTime($o['date'], new DateTimeZone(Craft::$app->getTimeZone())),
                'start' => $this->time($o['start']),
                'capacity' => $o['capacity'],
            ], $schedule->overrides),
            'zoneRows' => array_map(fn($z) => [
                'zoneId' => $z['zoneId'],
                'capacity' => $z['capacity'],
                'fee' => $z['fee'] ?: '',
            ] + $this->dayCells($z['days']), $schedule->zones),
            'zonesAvailable' => $zones->isAvailable(),
            'zoneOptions' => $zones->options(),
            'cutoff' => $schedule->cutoff ? $this->time($schedule->cutoff) : null,
            'weekdays' => $this->weekdays(),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $shippingMethodId = (int)$request->getRequiredBodyParam('shippingMethodId');
        $this->method($shippingMethodId);

        $schedule = Plugin::getInstance()->schedules->getByShippingMethodId($shippingMethodId) ?? new Schedule(['shippingMethodId' => $shippingMethodId]);
        $schedule = clone $schedule;
        $schedule->enabled = (bool)$request->getBodyParam('enabled');
        $schedule->setSettings([
            'windows' => $request->getBodyParam('windows') ?: [],
            'sameDay' => $request->getBodyParam('sameDay'),
            'leadHours' => $request->getBodyParam('leadHours'),
            'cutoff' => $request->getBodyParam('cutoff'),
            'daysAhead' => $request->getBodyParam('daysAhead'),
            'closedDates' => $request->getBodyParam('closedDates') ?: [],
            'overrides' => $request->getBodyParam('overrides') ?: [],
            // Keep saved zones if Geo Maps has been removed, so they come back when it's reinstalled.
            'zones' => Plugin::getInstance()->zones->isAvailable() ? ($request->getBodyParam('zones') ?: []) : $schedule->zones,
        ]);

        if (!Plugin::getInstance()->schedules->save($schedule)) {
            $this->setFailFlash(Craft::t('delivery-slots', 'Couldn’t save the schedule.'));
            Craft::$app->getUrlManager()->setRouteParams(['schedule' => $schedule]);
            return null;
        }

        return $this->asSuccess(Craft::t('delivery-slots', 'Schedule saved.'));
    }

    private function method(int $id): ShippingMethod
    {
        foreach (Commerce::getInstance()->getStores()->getAllStores() as $store) {
            $method = Commerce::getInstance()->getShippingMethods()->getShippingMethodById($id, $store->id);
            if ($method) {
                return $method;
            }
        }
        throw new NotFoundHttpException('Shipping method not found');
    }

    /**
     * A new schedule: two-hour windows from 8am to 6pm, Monday to Saturday.
     */
    private function defaults(int $shippingMethodId): Schedule
    {
        $schedule = new Schedule(['shippingMethodId' => $shippingMethodId, 'enabled' => false]);
        $windows = [];
        foreach (['08:00', '10:00', '12:00', '14:00', '16:00'] as $start) {
            $windows[] = ['start' => $start, 'end' => sprintf('%02d:00', (int)$start + 2), 'days' => [1, 2, 3, 4, 5, 6], 'capacity' => 10];
        }
        $schedule->setSettings(['windows' => $windows, 'cutoff' => '20:00', 'leadHours' => 2, 'daysAhead' => 7]);

        return $schedule;
    }

    private function time(string $time): DateTime
    {
        return new DateTime('2000-01-01 ' . $time, new DateTimeZone(Craft::$app->getTimeZone()));
    }

    /**
     * @param int[] $days
     * @return array<string, bool>
     */
    private function dayCells(array $days): array
    {
        $cells = [];
        for ($d = 1; $d <= 7; $d++) {
            $cells["d$d"] = in_array($d, $days, true);
        }

        return $cells;
    }

    /**
     * @return array<int, string> Short weekday names, Monday first.
     */
    private function weekdays(): array
    {
        $names = [];
        for ($d = 1; $d <= 7; $d++) {
            $names[$d] = Craft::$app->getFormatter()->asDate(new DateTime("2024-01-0$d"), 'php:D');
        }

        return $names;
    }
}
