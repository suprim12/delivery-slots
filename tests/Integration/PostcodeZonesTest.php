<?php

namespace suprim\deliveryslots\tests\Integration;

use craft\commerce\Plugin as Commerce;
use suprim\deliveryslots\adjusters\SlotFee;
use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\tests\TestCase;

/**
 * Postcode zones: which addresses get slots, on which days, with what limit and fee. Now is Monday 16 November, 9am.
 */
class PostcodeZonesTest extends TestCase
{
    private function storeCountry(): ?string
    {
        return Commerce::getInstance()->getStores()->getPrimaryStore()->getSettings()->getLocationAddress()?->countryCode;
    }

    private function address(string $postcode, ?string $country = null): array
    {
        return ['countryCode' => $country ?? $this->storeCountry() ?? 'AU', 'addressLine1' => '1 Test St', 'locality' => 'Testville', 'postalCode' => $postcode];
    }

    /**
     * Hills: Thursdays and Saturdays, 1 order per slot, $4. Inner: every slot day, no fee.
     */
    private function zonedSchedule(): Schedule
    {
        // Rows as the schedule editor sends them, tidied the same way.
        $rows = new Schedule();
        $rows->setSettings(['postcodeZones' => [
            ['name' => 'Inner', 'postcodes' => '5067, 5068 5069'],
            ['name' => 'Hills', 'postcodes' => '5240-5250, 5150*', 'd4' => '1', 'd6' => '1', 'capacity' => '1', 'fee' => '4'],
        ]]);

        return $this->schedule(['postcodeZones' => $rows->postcodeZones]);
    }

    public function testPostcodesAreTidiedAndMatched(): void
    {
        $this->assertSame(['5067', '5000-5099', 'SW1*'], Schedule::postcodes(" 5067, 5000 - 5099\nsw1* , 5067"));

        $patterns = ['5067', '5000-5099', 'SW1*'];
        $this->assertTrue(Schedule::postcodeMatches('5067', $patterns));
        $this->assertTrue(Schedule::postcodeMatches('5050', $patterns), 'In the range.');
        $this->assertTrue(Schedule::postcodeMatches('sw1a 2aa', $patterns), 'A prefix, ignoring case and spaces.');
        $this->assertFalse(Schedule::postcodeMatches('5100', $patterns));
        $this->assertFalse(Schedule::postcodeMatches('50500', $patterns), 'A range only covers postcodes of its length.');
        $this->assertFalse(Schedule::postcodeMatches('', $patterns));
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
        $this->zonedSchedule();
        $slots = $this->plugin()->slots;
        $cart = $this->cart(true, $this->address('5245'));

        $this->assertNull($slots->choose($cart, '2026-11-19@08:00'));
        $booking = $slots->getBooking($cart);
        $this->assertSame('postcode:hills', $booking->zoneKey);
        $this->assertSame('Hills', $booking->getZoneName());
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

    public function testTheSamePostcodeOverseasDoesntMatch(): void
    {
        $country = $this->storeCountry();
        if (!$country) {
            $this->markTestSkipped('The store has no location, so postcodes aren’t limited to a country.');
        }
        $this->zonedSchedule();
        $abroad = $country === 'NZ' ? 'AU' : 'NZ';

        $this->assertSame([], $this->plugin()->slots->getDays($this->cart(true, $this->address('5067', $abroad)), $this->method));
    }
}
