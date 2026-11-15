<?php

namespace suprim\deliveryslots\tests\Integration;

use craft\commerce\elements\Variant;
use suprim\deliveryslots\adjusters\SlotFee;
use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\tests\TestCase;

/**
 * Fees by window, weekday, same day and next day; more notice for some products; closed ranges and yearly holidays.
 * Now is Monday 16 November 2026, 9am.
 */
class FeesAndNoticeTest extends TestCase
{
    /** @return array<string, float> Fees by slot value. */
    private function fees(): array
    {
        $fees = [];
        foreach ($this->plugin()->slots->getDays($this->cart()) as $day) {
            foreach ($day->slots as $slot) {
                $fees[$slot->getValue()] = $slot->fee;
            }
        }

        return $fees;
    }

    public function testFeesAddUp(): void
    {
        $this->schedule([
            'sameDay' => true,
            'windows' => [
                ['start' => '08:00', 'end' => '10:00', 'days' => [1, 2, 3, 4, 5, 6], 'capacity' => 5],
                ['start' => '18:00', 'end' => '20:00', 'days' => [1, 2, 3, 4, 5, 6], 'capacity' => 5, 'fee' => 3.0],
            ],
            'dayFees' => [6 => 5.0],
            'sameDayFee' => 4.0,
            'nextDayFee' => 2.0,
        ]);
        $fees = $this->fees();

        $this->assertEquals(7.0, $fees['2026-11-16@18:00'], 'Evening window and same day.');
        $this->assertEquals(2.0, $fees['2026-11-17@08:00'], 'Next day.');
        $this->assertEquals(0.0, $fees['2026-11-18@08:00']);
        $this->assertEquals(5.0, $fees['2026-11-21@08:00'], 'Saturday.');
        $this->assertEquals(8.0, $fees['2026-11-21@18:00'], 'Saturday evening.');

        $cart = $this->cart();
        $this->assertNull($this->plugin()->slots->choose($cart, '2026-11-16@18:00'));
        $this->assertEquals(7.0, (new SlotFee())->adjust($cart)[0]->amount);
    }

    public function testFeesFromTheEditorAreTidied(): void
    {
        $schedule = new Schedule();
        $schedule->setSettings([
            'windows' => [['start' => '08:00', 'end' => '10:00', 'd1' => '1', 'capacity' => '5', 'fee' => '2.50']],
            'dayFees' => [['d6' => '5', 'd7' => '', 'd1' => '-3']],
            'sameDayFee' => '4',
            'nextDayFee' => '',
        ]);

        $this->assertSame(2.5, $schedule->windows[0]['fee']);
        $this->assertSame([6 => 5.0], $schedule->dayFees);
        $this->assertSame(4.0, $schedule->sameDayFee);
        $this->assertSame(0.0, $schedule->nextDayFee);
    }

    public function testSomeProductsNeedMoreNotice(): void
    {
        $typeId = Variant::find()->one()->getProduct()->typeId;
        $this->schedule(['productNotice' => [['productTypeId' => $typeId, 'leadHours' => 48]]]);
        $first = array_key_first($this->fees());

        $this->assertSame('2026-11-18@10:00', $first, 'Two days from Monday 9am.');
        $this->assertNotNull($this->plugin()->slots->choose($this->cart(), '2026-11-17@10:00'));

        $this->schedule(['productNotice' => [['productTypeId' => $typeId + 1000, 'leadHours' => 48]]]);
        $this->assertSame('2026-11-17@08:00', array_key_first($this->fees()), 'Other product types don’t count.');
    }

    public function testClosedRanges(): void
    {
        $this->schedule(['closedDates' => [['date' => '2026-11-18', 'endDate' => '2026-11-20', 'label' => 'Stocktake']]]);
        $reasons = [];
        foreach ($this->plugin()->slots->getDays($this->cart()) as $day) {
            $reasons[$day->date->format('D')] = $day->unavailableReason;
        }

        $this->assertNull($reasons['Tue']);
        $this->assertSame('Stocktake', $reasons['Wed']);
        $this->assertSame('Stocktake', $reasons['Fri']);
        $this->assertNull($reasons['Sat']);
    }

    public function testYearlyHolidaysOverTheNewYear(): void
    {
        $schedule = new Schedule();
        $schedule->setSettings(['closedDates' => [
            ['date' => '2020-12-24', 'endDate' => '2021-01-02', 'label' => 'Summer break', 'yearly' => '1'],
            ['date' => '2020-01-26', 'label' => 'Australia Day', 'yearly' => true],
            ['date' => '2020-11-11', 'label' => 'Just once'],
        ]]);

        $this->assertSame('Summer break', $schedule->closedLabel('2026-12-31'));
        $this->assertSame('Summer break', $schedule->closedLabel('2027-01-02'));
        $this->assertNull($schedule->closedLabel('2026-12-23'));
        $this->assertNull($schedule->closedLabel('2027-01-03'));
        $this->assertSame('Australia Day', $schedule->closedLabel('2031-01-26'));
        $this->assertNull($schedule->closedLabel('2026-11-11'), 'Not yearly.');
    }
}
