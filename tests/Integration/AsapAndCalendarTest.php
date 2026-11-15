<?php

namespace suprim\deliveryslots\tests\Integration;

use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\records\BookingRecord;
use suprim\deliveryslots\tests\TestCase;
use suprim\deliveryslots\variables\DeliverySlotsVariable;

/**
 * As soon as possible, and the month calendar. Now is Monday 16 November 2026, 9am.
 */
class AsapAndCalendarTest extends TestCase
{
    private function oneWindow(array $config = []): Schedule
    {
        return $this->schedule(array_merge([
            'asap' => true,
            'windows' => [['start' => '10:00', 'end' => '12:00', 'days' => [1, 2, 3, 4, 5, 6, 7], 'capacity' => 1]],
        ], $config));
    }

    public function testAsapTakesTheEarliestFreeSlot(): void
    {
        $this->oneWindow();
        $slots = $this->plugin()->slots;
        $first = $this->cart();
        $second = $this->cart();

        $this->assertSame('2026-11-17@10:00', $slots->getAsapSlot($first)?->getValue());
        $this->assertNull($slots->choose($first, 'asap'));
        $this->assertTrue($slots->getBooking($first)->asap);
        $this->assertSame('2026-11-17@10:00', $slots->getBooking($first)->getValue());

        // Tuesday is now full, so the next customer gets Wednesday.
        $this->assertNull($slots->choose($second, 'asap'));
        $this->assertSame('2026-11-18@10:00', $slots->getBooking($second)->getValue());

        // Choosing a time afterwards isn't as soon as possible any more.
        $this->assertNull($slots->choose($second, '2026-11-20@10:00'));
        $this->assertFalse($slots->getBooking($second)->asap);
    }

    public function testAsapMovesOnWhenItsTimeFillsBeforePayment(): void
    {
        $this->oneWindow();
        $this->settings()->holdMinutes = 15;
        $slots = $this->plugin()->slots;
        $cart = $this->cart();
        $this->assertNull($slots->choose($cart, 'asap'));

        // The hold runs out and someone else takes Tuesday.
        $this->later('+20 minutes');
        $this->assertNull($slots->choose($this->cart(), '2026-11-17@10:00'));

        $this->assertNull($slots->checkBeforePayment($cart, true));
        $this->assertSame('2026-11-18@10:00', BookingRecord::findOne(['orderId' => $cart->id])->date . '@10:00');
    }

    public function testAsapIsOnlyOfferedWhenTurnedOn(): void
    {
        $this->oneWindow(['asap' => false]);
        $cart = $this->cart();

        $this->assertNull($this->plugin()->slots->getAsapSlot($cart));
        $this->assertNotNull($this->plugin()->slots->choose($cart, 'asap'));
    }

    public function testThePickerOffersAsap(): void
    {
        $this->oneWindow();
        $cart = $this->cart();
        $this->assertNull($this->plugin()->slots->choose($cart, 'asap'));
        $html = (string)(new DeliverySlotsVariable())->picker($cart, ['styles' => false]);

        $this->assertMatchesRegularExpression('/value="asap"\s+checked/', $html);
        $this->assertStringContainsString('Tuesday 17 November, 10am – 12pm', $html);
        $this->assertDoesNotMatchRegularExpression('/value="2026-11-17@10:00"\s+checked/', $html, 'Only one choice is ticked.');
    }

    public function testLongSchedulesGetAMonthCalendar(): void
    {
        $this->oneWindow(['asap' => false, 'daysAhead' => 30]);
        $html = (string)(new DeliverySlotsVariable())->picker($this->cart(), ['styles' => false]);

        $this->assertStringContainsString('data-month="2026-11"', $html);
        $this->assertStringContainsString('data-month="2026-12"', $html);
        $this->assertStringContainsString('<b>November 2026</b>', $html);
        // December 2026 starts on a Tuesday: one blank before its first day.
        $december = substr($html, (int)strpos($html, 'data-month="2026-12"'));
        $beforeFirstDay = substr($december, 0, (int)strpos($december, 'class="ds-day'));
        $this->assertSame(1, substr_count($beforeFirstDay, '<span aria-hidden="true"></span>'));
        $this->assertSame(7, substr_count($beforeFirstDay, 'class="ds-wd"'));

        $short = (string)(new DeliverySlotsVariable())->picker($this->cart(), ['styles' => false, 'calendar' => false]);
        $this->assertStringNotContainsString('data-month', $short);
    }
}
