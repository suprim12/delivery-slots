<?php

namespace suprim\deliveryslots\tests\Integration;

use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\models\Times;
use suprim\deliveryslots\tests\TestCase;

/**
 * The schedule’s own rules: which windows run on a date, closed dates and one-off changes. And how times read.
 */
class ScheduleTest extends TestCase
{
    public function testWindowsRunOnTheirWeekdaysOnly(): void
    {
        $schedule = new Schedule(['windows' => [
            ['start' => '08:00', 'end' => '10:00', 'days' => [1, 2, 3, 4, 5], 'capacity' => 3],
            ['start' => '10:00', 'end' => '12:00', 'days' => [6], 'capacity' => 3],
        ]]);

        $this->assertSame(['08:00'], array_column($schedule->windowsFor('2026-11-16'), 'start'), 'Monday');
        $this->assertSame(['10:00'], array_column($schedule->windowsFor('2026-11-21'), 'start'), 'Saturday');
        $this->assertSame([], $schedule->windowsFor('2026-11-22'), 'Sunday');
    }

    public function testOneOffChangesAndClosedDates(): void
    {
        $schedule = new Schedule([
            'windows' => [
                ['start' => '08:00', 'end' => '10:00', 'days' => [1, 2, 3, 4, 5, 6, 7], 'capacity' => 3],
                ['start' => '10:00', 'end' => '12:00', 'days' => [1, 2, 3, 4, 5, 6, 7], 'capacity' => 3],
            ],
            'overrides' => [
                ['date' => '2026-11-18', 'start' => '08:00', 'capacity' => 0, 'label' => ''],
                ['date' => '2026-11-18', 'start' => '10:00', 'capacity' => 6, 'label' => ''],
            ],
            'closedDates' => [['date' => '2026-12-25', 'label' => 'Christmas Day'], ['date' => '2026-12-26', 'label' => '']],
        ]);

        $windows = $schedule->windowsFor('2026-11-18');
        $this->assertSame(['10:00'], array_column($windows, 'start'), 'A limit of 0 closes the slot that day.');
        $this->assertSame(6, $windows[0]['capacity'], 'A different limit that day.');
        $this->assertSame(3, $schedule->windowsFor('2026-11-19')[1]['capacity'], 'Other days keep the usual limit.');
        $this->assertSame('Christmas Day', $schedule->closedLabel('2026-12-25'));
        $this->assertSame('Closed', $schedule->closedLabel('2026-12-26'), 'No label shows Closed.');
        $this->assertNull($schedule->closedLabel('2026-12-27'));
    }

    public function testTimesReadNaturally(): void
    {
        $this->assertSame('8 – 10am', Times::range('08:00', '10:00'));
        $this->assertSame('10am – 12pm', Times::range('10:00', '12:00'));
        $this->assertSame('12 – 2pm', Times::range('12:00', '14:00'));
        $this->assertSame('8:30 – 9:30am', Times::range('08:30', '09:30'));
    }
}
