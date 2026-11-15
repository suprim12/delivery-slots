<?php

namespace suprim\deliveryslots\tests\Integration;

use craft\commerce\elements\conditions\addresses\PostalCodeFormulaConditionRule;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\Plugin as Commerce;
use craft\elements\Address;
use suprim\deliveryslots\adjusters\SlotFee;
use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\tests\TestCase;
use suprim\deliveryslots\variables\DeliverySlotsVariable;

/**
 * Commerce shipping zones: which addresses get slots, on which days, with what limit and fee. Now is Monday
 * 16 November, 9am.
 */
class ShippingZonesTest extends TestCase
{
    /** @var ShippingAddressZone[] */
    private array $zones = [];

    protected function tearDown(): void
    {
        foreach ($this->zones as $zone) {
            Commerce::getInstance()->getShippingZones()->deleteShippingZoneById($zone->id);
        }
        parent::tearDown();
    }

    private function zone(string $name, array $postcodes): ShippingAddressZone
    {
        $zone = new ShippingAddressZone([
            'name' => $name . ' ' . bin2hex(random_bytes(3)),
            'storeId' => Commerce::getInstance()->getStores()->getPrimaryStore()->id,
        ]);
        $zone->setCondition([
            'class' => ZoneAddressCondition::class,
            'conditionRules' => [[
                'class' => PostalCodeFormulaConditionRule::class,
                'value' => 'postalCode in ' . json_encode($postcodes),
            ]],
        ]);
        $this->assertTrue(Commerce::getInstance()->getShippingZones()->saveShippingZone($zone), json_encode($zone->getErrors()));
        $this->zones[] = $zone;

        return $zone;
    }

    private function address(string $postcode): array
    {
        return ['countryCode' => 'AU', 'addressLine1' => '1 Test St', 'locality' => 'Testville', 'postalCode' => $postcode];
    }

    /**
     * Hills: Thursdays and Saturdays, 1 order per slot, $4. Inner: every slot day, no fee.
     */
    private function zonedSchedule(): array
    {
        $inner = $this->zone('Inner', ['5067', '5068']);
        $hills = $this->zone('Hills', ['5245', '5150']);
        // Rows as the schedule editor sends them, tidied the same way.
        $rows = new Schedule();
        $rows->setSettings(['shippingZones' => [
            ['shippingZoneId' => (string)$inner->id],
            ['shippingZoneId' => (string)$hills->id, 'd4' => '1', 'd6' => '1', 'capacity' => '1', 'fee' => '4'],
        ]]);
        $this->schedule(['shippingZones' => $rows->shippingZones]);

        return [$inner, $hills];
    }

    public function testAZonesDaysAndFeeApply(): void
    {
        $this->zonedSchedule();
        $days = $this->plugin()->slots->getDays($this->cart(true, $this->address('5245')));
        $open = array_map(fn($d) => $d->date->format('D'), array_filter($days, fn($d) => $d->getIsAvailable()));

        $this->assertSame(['Thu', 'Sat'], array_values($open));
        $this->assertSame('Not in your area', $days[0]->unavailableReason);
        $thursday = array_values(array_filter($days, fn($d) => $d->getIsAvailable()))[0];
        $this->assertEquals(4.0, $thursday->slots[0]->fee);
    }

    public function testTheBookingRemembersItsZoneAndChargesTheFee(): void
    {
        [, $hills] = $this->zonedSchedule();
        $slots = $this->plugin()->slots;
        $cart = $this->cart(true, $this->address('5245'));

        $this->assertNull($slots->choose($cart, '2026-11-19@08:00'));
        $booking = $slots->getBooking($cart);
        $this->assertSame('shippingZone:' . $hills->id, $booking->zoneKey);
        $this->assertSame($hills->name, $booking->getZoneName());
        $this->assertNull($booking->zoneId);
        $this->assertEquals(4.0, (new SlotFee())->adjust($cart)[0]->amount);
    }

    public function testAZonesLimitIsOnTopOfTheSlots(): void
    {
        $this->zonedSchedule();
        $slots = $this->plugin()->slots;
        $first = $this->cart(true, $this->address('5245'));
        $second = $this->cart(true, $this->address('5150'));
        $inner = $this->cart(true, $this->address('5067'));

        $this->assertNull($slots->choose($first, '2026-11-19@08:00'));
        $this->assertNotNull($slots->choose($second, '2026-11-19@08:00'), 'The Hills take 1 order per slot.');
        $this->assertNull($slots->choose($inner, '2026-11-19@08:00'), 'The slot itself still has room.');
    }

    public function testAddressesOutsideEveryZoneGetNoSlotsOrMethod(): void
    {
        $this->zonedSchedule();
        $cart = $this->cart(true, $this->address('6000'));

        // Asked for the zoned method, as the cart itself is moved to another method it can use.
        $this->assertSame([], $this->plugin()->slots->getDays($cart, $this->method));
        $this->assertArrayNotHasKey($this->method->handle, $cart->getAvailableShippingMethodOptions());
        $this->assertArrayHasKey($this->method->handle, $this->cart(true, $this->address('5067'))->getAvailableShippingMethodOptions());
    }

    public function testBeforeAnAddressTheMethodStaysAndAsksForOne(): void
    {
        $this->zonedSchedule();
        $cart = $this->cart();

        $this->assertArrayHasKey($this->method->handle, $cart->getAvailableShippingMethodOptions(), 'Not hidden before the customer says where.');
        $this->assertTrue($this->plugin()->slots->needsAddress($cart, $this->method));
        $this->assertFalse($this->plugin()->slots->needsAddress($this->cart(true, $this->address('5067')), $this->method));
        $this->assertStringContainsString('Enter your address', (string)(new DeliverySlotsVariable())->picker($cart));
    }

    public function testThePickerShowsTheTimes(): void
    {
        $this->zonedSchedule();
        $cart = $this->cart(true, $this->address('5067'));
        $this->assertNull($this->plugin()->slots->choose($cart, '2026-11-17@10:00'));

        $html = (string)(new DeliverySlotsVariable())->picker($cart, ['heading' => 'When?', 'styles' => false]);

        $this->assertStringContainsString('When?', $html);
        $this->assertMatchesRegularExpression('/value="2026-11-17@10:00"\s+checked/', $html);
        $this->assertStringNotContainsString('<style>', $html);
    }

    public function testThePickerIsEmptyWithoutSlots(): void
    {
        $this->assertSame('', trim((string)(new DeliverySlotsVariable())->picker($this->cart())));
    }

    public function testZonesThatWereDeletedAreSkipped(): void
    {
        [$inner] = $this->zonedSchedule();
        Commerce::getInstance()->getShippingZones()->deleteShippingZoneById($inner->id);
        $this->zones = array_slice($this->zones, 1);

        $this->assertSame([], $this->plugin()->slots->getDays($this->cart(true, $this->address('5067')), $this->method));
        $this->assertNotSame([], $this->plugin()->slots->getDays($this->cart(true, $this->address('5245')), $this->method));
    }

    public function testANewAddressLetsGoOfATimeItsZoneDoesntOffer(): void
    {
        $this->zonedSchedule();
        $cart = $this->cart(true, $this->address('5067'));
        $this->assertTrue($this->saveForm($cart, '2026-11-17@08:00'));

        // Tuesday: the Hills only get Thursdays and Saturdays.
        $cart->setShippingAddress(new Address($this->address('5245') + ['ownerId' => $cart->id]));
        $this->assertTrue($this->saveForm($cart));

        $this->assertNull($this->plugin()->slots->getBooking($cart));
        $this->assertStringContainsString('isn’t available for this address', (string)$cart->getFirstNotice('deliverySlotUnavailable')?->message);

        $this->assertTrue($this->saveForm($cart, '2026-11-19@08:00'));
        $this->assertNull($cart->getFirstNotice('deliverySlotUnavailable'), 'Choosing again clears the notice.');
    }

    public function testANewAddressTakesItsZonesFee(): void
    {
        [, $hills] = $this->zonedSchedule();
        $cart = $this->cart(true, $this->address('5067'));
        $this->assertTrue($this->saveForm($cart, '2026-11-19@08:00'));
        $this->assertEquals(0.0, $this->plugin()->slots->getBooking($cart)->fee);

        $cart->setShippingAddress(new Address($this->address('5245') + ['ownerId' => $cart->id]));
        $this->assertTrue($this->saveForm($cart));

        $booking = $this->plugin()->slots->getBooking($cart);
        $this->assertSame('shippingZone:' . $hills->id, $booking->zoneKey);
        $this->assertEquals(4.0, $booking->fee);
    }

    public function testSaysWhichMethodsArentAvailableForTheAddress(): void
    {
        $this->zonedSchedule();
        // Only this test's method: the site's own methods may have zones too.
        $ids = fn($cart) => array_values(array_intersect([$this->method->id], array_map(fn($m) => $m->id, $this->plugin()->slots->getUnavailableMethods($cart))));
        $outside = $this->cart(true, $this->address('6000'));

        $this->assertSame([$this->method->id], $ids($outside));
        $this->assertStringContainsString($this->method->name, (string)(new DeliverySlotsVariable())->picker($outside));
        $this->assertStringContainsString('available for Testville 6000.', (string)(new DeliverySlotsVariable())->picker($outside));

        $this->assertSame([], $ids($this->cart(true, $this->address('5067'))));
        $this->assertSame([], $this->plugin()->slots->getUnavailableMethods($this->cart()), 'Not before there’s an address.');

        // A method whose rules don't take this cart isn't listed, wherever the address is.
        $rule = Commerce::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($this->method->id)->first();
        $rule->orderConditionFormula = 'false';
        Commerce::getInstance()->getShippingRules()->saveShippingRule($rule);
        $this->assertSame([], $ids($this->cart(true, $this->address('6000'))));
    }
}
