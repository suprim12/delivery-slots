<?php

namespace suprim\deliveryslots\tests\Integration;

use Craft;
use suprim\deliveryslots\records\BookingRecord;
use suprim\deliveryslots\tests\TestCase;

/**
 * Finishing checkout: a slot is needed to complete an order without paying, and is held for longer while paying.
 */
class CheckoutTest extends TestCase
{
    private function completing(bool $on): void
    {
        $plugin = $this->plugin();
        (fn() => $this->_completing = $on)->call($plugin);
    }

    public function testCompletingWithoutPayingNeedsASlot(): void
    {
        $this->schedule();
        $cart = $this->cart();

        $this->completing(true);
        $cart->isCompleted = true;
        $this->assertFalse(Craft::$app->getElements()->saveElement($cart, false));
        $this->assertSame('Choose a delivery time.', $cart->getFirstError('deliverySlot'));
    }

    public function testCompletingWithASlotGoesAhead(): void
    {
        $this->schedule();
        $cart = $this->cart();
        $this->assertNull($this->plugin()->slots->choose($cart, '2026-11-17@10:00'));

        $this->completing(true);
        $cart->isCompleted = true;
        $this->assertTrue(Craft::$app->getElements()->saveElement($cart, false), json_encode($cart->getErrors()));
    }

    public function testStaffSavingAnOrderIsntStopped(): void
    {
        $this->schedule();
        $cart = $this->cart();

        $this->completing(false);
        $cart->isCompleted = true;
        $this->assertTrue(Craft::$app->getElements()->saveElement($cart, false));
    }

    public function testPayingHoldsTheSlotForLonger(): void
    {
        $this->schedule();
        $this->settings()->holdMinutes = 15;
        $this->settings()->paymentHoldMinutes = 60;
        $slots = $this->plugin()->slots;
        $cart = $this->cart();
        $this->assertNull($slots->choose($cart, '2026-11-17@10:00'));

        $this->assertNull($slots->checkBeforePayment($cart, true));
        $expires = (new \DateTime(BookingRecord::findOne(['orderId' => $cart->id])->expiresAt, new \DateTimeZone('UTC')))->getTimestamp();
        $this->assertSame($slots->now()->getTimestamp() + 3600, $expires);
    }

    public function testAnotherCartCantTakeTheSlotWhilePaying(): void
    {
        $this->schedule(['windows' => [['start' => '10:00', 'end' => '12:00', 'days' => [1, 2, 3, 4, 5, 6, 7], 'capacity' => 1]]]);
        $slots = $this->plugin()->slots;
        $paying = $this->cart();
        $this->assertNull($slots->choose($paying, '2026-11-17@10:00'));
        $this->assertNull($slots->checkBeforePayment($paying, true));

        // Past the usual hold, but still on the payment page.
        $this->later('+30 minutes');
        $this->assertNotNull($slots->choose($this->cart(), '2026-11-17@10:00'));
    }
}
