<?php

namespace suprim\deliveryslots\tests\Integration;

use suprim\deliveryslots\tests\TestCase;
use suprim\deliveryslots\variables\DeliverySlotsVariable;

/**
 * The ready-made picker. Now is Monday 16 November, 9am.
 */
class PickerTest extends TestCase
{
    private function picker(): string
    {
        $cart = $this->cart();
        $this->assertNull($this->plugin()->slots->choose($cart, '2026-11-18@10:00'));

        return (string)(new DeliverySlotsVariable())->picker($cart, ['styles' => false]);
    }

    public function testOneWindowADayIsChosenByDay(): void
    {
        // A bakery: collect any time from 10 to 4, twelve cakes a day, closed on Sundays.
        $this->schedule(['windows' => [['start' => '10:00', 'end' => '16:00', 'days' => [1, 2, 3, 4, 5, 6], 'capacity' => 12]]]);
        $html = $this->picker();

        $this->assertStringNotContainsString('data-slots', $html, 'No second step for the time.');
        $this->assertMatchesRegularExpression('/<label class="ds-day">\s*<input type="radio" name="deliverySlot" value="2026-11-18@10:00"\s+checked>/', $html);
        $this->assertStringContainsString('10am – 4pm', $html);
        $this->assertMatchesRegularExpression('/ds-off">\s*<small>Sun<\/small>\s*<b>22 Nov<\/b>\s*<small>Closed<\/small>/', $html);
    }

    public function testSeveralWindowsKeepTheTimes(): void
    {
        $this->schedule();
        $html = $this->picker();

        $this->assertStringContainsString('data-slots', $html);
        $this->assertStringContainsString('<button type="button" class="ds-day"', $html);
    }
}
