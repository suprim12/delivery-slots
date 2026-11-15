<?php

namespace suprim\deliveryslots\tests\Integration;

use suprim\deliveryslots\events\BookEvent;
use suprim\deliveryslots\events\ChooseEvent;
use suprim\deliveryslots\events\DaysEvent;
use suprim\deliveryslots\events\LeadHoursEvent;
use suprim\deliveryslots\services\Slots;
use suprim\deliveryslots\tests\TestCase;

/**
 * Developer events. Now is Monday 16 November 2026, 9am.
 */
class EventsTest extends TestCase
{
    /** @var array<int, array{string, callable}> */
    private array $handlers = [];

    protected function tearDown(): void
    {
        foreach ($this->handlers as [$name, $handler]) {
            $this->plugin()->slots->off($name, $handler);
        }
        parent::tearDown();
    }

    private function on(string $name, callable $handler): void
    {
        $this->plugin()->slots->on($name, $handler);
        $this->handlers[] = [$name, $handler];
    }

    public function testNoticeCanBeChanged(): void
    {
        $this->schedule();
        $this->on(Slots::EVENT_DEFINE_LEAD_HOURS, fn(LeadHoursEvent $e) => $e->leadHours = 72);
        $days = $this->plugin()->slots->getDays($this->cart());

        $this->assertSame('2026-11-19@10:00', array_values(array_filter($days, fn($d) => $d->getIsAvailable()))[0]->slots[0]->getValue());
    }

    public function testDaysCanBeChangedAndAreUsedWhenChoosing(): void
    {
        $this->schedule();
        $this->on(Slots::EVENT_DEFINE_DAYS, function(DaysEvent $e) {
            foreach ($e->days as $day) {
                if ($day->date->format('D') === 'Tue') {
                    $day->slots = [];
                    $day->unavailableReason = 'Van in for a service';
                }
                foreach ($day->slots as $slot) {
                    $slot->fee += 1;
                }
            }
        });
        $slots = $this->plugin()->slots;
        $days = $slots->getDays($this->cart());

        $this->assertSame('Van in for a service', $days[0]->unavailableReason);
        $this->assertEquals(1.0, $days[1]->slots[0]->fee);
        $this->assertNotNull($slots->choose($this->cart(), '2026-11-17@10:00'));
    }

    public function testChoosingCanBeRefused(): void
    {
        $this->schedule();
        $this->on(Slots::EVENT_BEFORE_CHOOSE, function(ChooseEvent $e) {
            if ($e->slot->start === '08:00') {
                $e->isValid = false;
                $e->error = 'Early slots are for members.';
            }
        });
        $slots = $this->plugin()->slots;

        $this->assertSame('Early slots are for members.', $slots->choose($this->cart(), '2026-11-17@08:00'));
        $this->assertNull($slots->choose($this->cart(), '2026-11-17@10:00'));
    }

    public function testBookingIsAnnounced(): void
    {
        $this->schedule();
        $booked = null;
        $this->on(Slots::EVENT_AFTER_BOOK, function(BookEvent $e) use (&$booked) {
            $booked = $e->booking->getValue();
        });
        $slots = $this->plugin()->slots;
        $cart = $this->cart();
        $this->assertNull($slots->choose($cart, '2026-11-17@10:00'));
        $slots->book($cart);

        $this->assertSame('2026-11-17@10:00', $booked);
    }
}
