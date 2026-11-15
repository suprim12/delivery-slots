<?php

namespace suprim\deliveryslots\tests;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin as Commerce;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase as BaseTestCase;
use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\models\Settings;
use suprim\deliveryslots\Plugin;
use suprim\deliveryslots\records\BookingRecord;
use suprim\deliveryslots\records\ScheduleRecord;

/**
 * Gives each test its own shipping method with a schedule, and carts to book with, and removes them afterwards.
 * Time is fixed at Monday 16 November 2026, 9am, so the days and slots don’t depend on when the tests run.
 */
abstract class TestCase extends BaseTestCase
{
    protected const NOW = '2026-11-16 09:00';

    protected ShippingMethod $method;
    protected ShippingMethod $otherMethod;

    /** @var Order[] */
    private array $orders = [];
    private Settings $originalSettings;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Variant::find()->exists()) {
            $this->markTestSkipped('The tests need a product variant to add to carts.');
        }
        $this->originalSettings = clone $this->settings();
        $this->plugin()->slots->now = new DateTimeImmutable(self::NOW, new DateTimeZone(Craft::$app->getTimeZone()));
        $this->method = $this->makeMethod();
        $this->otherMethod = $this->makeMethod();
    }

    protected function tearDown(): void
    {
        foreach ($this->orders as $order) {
            BookingRecord::deleteAll(['orderId' => $order->id]);
            Craft::$app->getElements()->deleteElement($order, true);
        }
        foreach ([$this->method ?? null, $this->otherMethod ?? null] as $method) {
            if ($method?->id) {
                ScheduleRecord::deleteAll(['shippingMethodId' => $method->id]);
                Commerce::getInstance()->getShippingMethods()->deleteShippingMethodById($method->id);
            }
        }
        $this->plugin()->slots->now = null;
        (fn() => $this->_bookings = [])->call($this->plugin()->slots);
        if (isset($this->originalSettings)) {
            foreach (get_object_vars($this->originalSettings) as $name => $value) {
                $this->settings()->$name = $value;
            }
        }
        parent::tearDown();
    }

    protected function plugin(): Plugin
    {
        return Plugin::getInstance();
    }

    protected function settings(): Settings
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        return $settings;
    }

    /**
     * A shipping method that every order can use: one rule, no conditions.
     */
    private function makeMethod(): ShippingMethod
    {
        $commerce = Commerce::getInstance();
        $storeId = $commerce->getStores()->getPrimaryStore()->id;
        $handle = 'dsTest' . bin2hex(random_bytes(3));
        $method = new ShippingMethod(['name' => "Test $handle", 'handle' => $handle, 'enabled' => true, 'storeId' => $storeId]);
        $this->assertTrue($commerce->getShippingMethods()->saveShippingMethod($method), json_encode($method->getErrors()));
        $rule = new ShippingRule(['name' => 'Everyone', 'methodId' => $method->id, 'storeId' => $storeId, 'enabled' => true, 'baseRate' => 0]);
        $this->assertTrue($commerce->getShippingRules()->saveShippingRule($rule), json_encode($rule->getErrors()));

        return $method;
    }

    /**
     * Saves a schedule for the test method: by default two-hour slots from 8am to 6pm every day, 2 orders each.
     *
     * @param array<string, mixed> $config
     */
    protected function schedule(array $config = []): Schedule
    {
        $schedule = new Schedule(array_merge([
            'shippingMethodId' => $this->method->id,
            'enabled' => true,
            'windows' => array_map(fn($h) => [
                'start' => sprintf('%02d:00', $h),
                'end' => sprintf('%02d:00', $h + 2),
                'days' => [1, 2, 3, 4, 5, 6, 7],
                'capacity' => 2,
            ], [8, 10, 12, 14, 16]),
            'leadHours' => 2,
            'daysAhead' => 7,
        ], $config));
        $existing = $this->plugin()->schedules->getByShippingMethodId($this->method->id);
        $schedule->id = $existing?->id;
        $this->assertTrue($this->plugin()->schedules->save($schedule), json_encode($schedule->getErrors()));

        return $schedule;
    }

    /**
     * A saved cart with one item, using the test method.
     */
    protected function cart(bool $withItems = true): Order
    {
        $commerce = Commerce::getInstance();
        $order = new Order([
            'number' => $commerce->getCarts()->generateCartNumber(),
            'storeId' => $commerce->getStores()->getPrimaryStore()->id,
            'email' => 'slots.test@example.com',
        ]);
        if ($withItems) {
            $order->setLineItems([$commerce->getLineItems()->create($order, ['purchasableId' => Variant::find()->one()->id, 'qty' => 1])]);
        }
        $order->shippingMethodHandle = $this->method->handle;
        $this->assertTrue(Craft::$app->getElements()->saveElement($order, false));
        $this->orders[] = $order;

        return $order;
    }

    /**
     * Moves the fixed time on, such as past a hold.
     */
    protected function later(string $interval): void
    {
        $this->plugin()->slots->now = $this->plugin()->slots->now()->modify($interval);
    }
}
