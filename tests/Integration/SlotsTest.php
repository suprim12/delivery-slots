<?php

namespace suprim\deliveryslots\tests\Integration;

use Craft;
use suprim\deliveryslots\adjusters\SlotFee;
use suprim\deliveryslots\models\Day;
use suprim\deliveryslots\records\BookingRecord;
use suprim\deliveryslots\tests\TestCase;

/**
 * Days and slots for a cart, holding and booking places, and their limits. Now is Monday 16 November, 9am.
 */
class SlotsTest extends TestCase
{
    /**
     * @param Day[] $days
     * @return array<string, Day>
     */
    private function byDate(array $days): array
    {
        $out = [];
        foreach ($days as $day) {
            $out[$day->date->format('Y-m-d')] = $day;
        }

        return $out;
    }

    public function testDaysStartTomorrowForTheDaysAhead(): void
    {
        $this->schedule(['daysAhead' => 3]);
        $days = $this->plugin()->slots->getDays($this->cart());

        $this->assertSame(['2026-11-17', '2026-11-18', '2026-11-19'], array_keys($this->byDate($days)));
        $this->assertCount(5, $days[0]->slots);
        $this->assertSame('2026-11-17@08:00', $days[0]->slots[0]->getValue());
    }

    public function testSameDaySlotsKeepTheLeadTime(): void
    {
        $this->schedule(['sameDay' => true, 'leadHours' => 2, 'daysAhead' => 1]);
        $today = $this->byDate($this->plugin()->slots->getDays($this->cart()))['2026-11-16'];

        // 9am now, 2 hours’ notice: 8am and 10am are too soon.
        $this->assertSame(['12:00', '14:00', '16:00'], array_map(fn($s) => $s->start, $today->slots));
    }

    public function testTheCutoffClosesTomorrow(): void
    {
        $this->schedule(['cutoff' => '08:00', 'daysAhead' => 2]);
        $days = $this->byDate($this->plugin()->slots->getDays($this->cart()));

        $this->assertSame('Ordering has closed', $days['2026-11-17']->unavailableReason);
        $this->assertFalse($days['2026-11-17']->getIsAvailable());
        $this->assertTrue($days['2026-11-18']->getIsAvailable());
    }

    public function testClosedDatesAndDaysWithoutSlots(): void
    {
        $this->schedule([
            'closedDates' => [['date' => '2026-11-18', 'label' => 'Stocktake']],
            'windows' => [['start' => '08:00', 'end' => '10:00', 'days' => [1, 2, 3, 4, 5], 'capacity' => 2]],
        ]);
        $days = $this->byDate($this->plugin()->slots->getDays($this->cart()));

        $this->assertSame('Stocktake', $days['2026-11-18']->unavailableReason);
        $this->assertSame('Closed', $days['2026-11-21']->unavailableReason, 'No windows on Saturday.');
    }

    public function testHoldsAndBookingsTakePlaces(): void
    {
        $this->schedule(['windows' => [['start' => '08:00', 'end' => '10:00', 'days' => [1, 2, 3, 4, 5, 6, 7], 'capacity' => 1]]]);
        $slots = $this->plugin()->slots;
        $first = $this->cart();
        $second = $this->cart();

        $this->assertNull($slots->choose($first, '2026-11-17@08:00'));
        $this->assertSame(0, $slots->getDays($second)[0]->slots[0]->left, 'Held for the first cart.');
        $this->assertSame(1, $slots->getDays($first)[0]->slots[0]->left, 'A cart doesn’t count its own hold.');
        $this->assertNotNull($slots->choose($second, '2026-11-17@08:00'), 'Full.');

        $this->later('+20 minutes');
        $this->assertSame(1, $slots->getDays($second)[0]->slots[0]->left, 'An expired hold frees the place.');

        $this->later('-20 minutes');
        $slots->book($first);
        $this->later('+1 day');
        $this->assertNotNull($slots->choose($second, '2026-11-17@08:00'), 'A booked place stays taken.');
    }

    public function testChooseChecksTheCartAndTheValue(): void
    {
        $this->schedule();
        $slots = $this->plugin()->slots;

        $this->assertSame('Add something to your cart first.', $slots->choose($this->cart(false), '2026-11-17@08:00'));
        $cart = $this->cart();
        $this->assertNotNull($slots->choose($cart, 'tomorrow'), 'Not a slot value.');
        $this->assertNotNull($slots->choose($cart, '2026-11-17@09:00'), 'No slot starts then.');
        $this->assertNotNull($slots->choose($cart, '2026-12-25@08:00'), 'Beyond the days ahead.');

        $this->assertNull($slots->choose($cart, '2026-11-17@10:00'));
        $this->assertSame('2026-11-17@10:00', $slots->getBooking($cart)?->getValue());
        $this->assertNull($slots->choose($cart, ''), 'An empty value clears it.');
        $this->assertNull($slots->getBooking($cart));
    }

    public function testBookingIsDroppedWhenTheMethodChanged(): void
    {
        $this->schedule();
        $slots = $this->plugin()->slots;
        $cart = $this->cart();
        $slots->choose($cart, '2026-11-17@08:00');

        $cart->shippingMethodHandle = $this->otherMethod->handle;
        $slots->book($cart);

        $this->assertFalse(BookingRecord::find()->where(['orderId' => $cart->id])->exists());
    }

    public function testBeforePaymentAnExpiredHoldIsHeldAgain(): void
    {
        $this->schedule();
        $slots = $this->plugin()->slots;
        $cart = $this->cart();

        $this->assertSame('Choose a delivery time.', $slots->checkBeforePayment($cart));
        $slots->choose($cart, '2026-11-17@08:00');
        $this->later('+30 minutes');
        (fn() => $this->_bookings = [])->call($slots);

        $this->assertNull($slots->checkBeforePayment($cart), 'Still free, so held again.');
        $expires = BookingRecord::findOne(['orderId' => $cart->id])->expiresAt;
        $this->assertGreaterThan($slots->now()->getTimestamp(), (new \DateTime($expires, new \DateTimeZone('UTC')))->getTimestamp());
    }

    public function testASlotFeeIsAddedToShipping(): void
    {
        $this->schedule();
        $slots = $this->plugin()->slots;
        $cart = $this->cart();
        $slots->choose($cart, '2026-11-17@08:00');
        $this->assertSame([], (new SlotFee())->adjust($cart), 'No fee, no adjustment.');

        Craft::$app->getDb()->createCommand()->update(BookingRecord::TABLE_NAME, ['fee' => 4.5], ['orderId' => $cart->id])->execute();
        (fn() => $this->_bookings = [])->call($slots);
        $adjustments = (new SlotFee())->adjust($cart);

        $this->assertCount(1, $adjustments);
        $this->assertSame('shipping', $adjustments[0]->type);
        $this->assertEquals(4.5, $adjustments[0]->amount);
    }

    public function testRunSheetRowsAndCsv(): void
    {
        $this->schedule();
        $slots = $this->plugin()->slots;
        $cart = $this->cart();
        $slots->choose($cart, '2026-11-17@10:00');
        $slots->book($cart);

        $runSheets = $this->plugin()->runSheets;
        $rows = $runSheets->getRows('2026-11-17', '2026-11-17', $this->method->id);
        $this->assertCount(1, $rows);
        $groups = $runSheets->groupBySlot($rows);
        $this->assertSame('10am – 12pm', $groups[0]['label']);
        $csv = array_map('str_getcsv', array_filter(preg_split('/\R/', ltrim($runSheets->toCsv($rows), "\u{FEFF}"))));
        $this->assertSame(['Date', 'Slot', 'Order'], array_slice($csv[0], 0, 3));
        $this->assertSame('10am – 12pm', $csv[1][1]);
        $this->assertSame('slots.test@example.com', $csv[1][4]);
        $this->assertCount(2, $csv, 'A header and one order.');
    }
}
