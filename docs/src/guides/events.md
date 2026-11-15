# Developer Events

Change how slots are worked out from a module or plugin. Each event is on the `slots` service, `suprim\deliveryslots\services\Slots`.

```php
use suprim\deliveryslots\services\Slots;
use yii\base\Event;
```

## Notice

`Slots::EVENT_DEFINE_LEAD_HOURS` sets how many hours before a slot starts it can still be booked, after any [notice for product types](/getting-started/schedules#ordering-window). Use it for rules of your own, such as more notice for large orders:

```php
use suprim\deliveryslots\events\LeadHoursEvent;

Event::on(Slots::class, Slots::EVENT_DEFINE_LEAD_HOURS, function(LeadHoursEvent $event) {
    if ($event->order->totalQty > 20) {
        $event->leadHours = max($event->leadHours, 24);
    }
});
```

| Property | Description |
|---|---|
| `order` | The cart |
| `schedule` | The shipping method’s schedule |
| `leadHours` | The notice in hours. Change it |

## Days and slots

`Slots::EVENT_DEFINE_DAYS` has the days and slots a cart can choose from. Remove slots, change their fees or limits, or close a day. The same days are used when a slot is chosen, so a slot you remove can’t be booked.

```php
use suprim\deliveryslots\events\DaysEvent;

Event::on(Slots::class, Slots::EVENT_DEFINE_DAYS, function(DaysEvent $event) {
    foreach ($event->days as $day) {
        // The van is booked for a service.
        if ($day->date->format('Y-m-d') === '2026-12-02') {
            $day->slots = [];
            $day->unavailableReason = 'Van in for a service';
        }
        // Members pay no slot fees.
        if (Craft::$app->getUser()->getIdentity()?->isInGroup('members')) {
            foreach ($day->slots as $slot) {
                $slot->fee = 0;
            }
        }
    }
});
```

| Property | Description |
|---|---|
| `order` | The cart |
| `method` | The shipping method |
| `schedule` | Its schedule |
| `zone` | The address’s zone, with its `key`, `name`, `days`, `capacity` and `fee`, or `null` |
| `days` | The [days](/guides/reference#day). Change them |

## Choosing a slot

`Slots::EVENT_BEFORE_CHOOSE` runs before a slot is held for a cart. Set `isValid` to `false` to refuse it, with a message for the customer:

```php
use suprim\deliveryslots\events\ChooseEvent;

Event::on(Slots::class, Slots::EVENT_BEFORE_CHOOSE, function(ChooseEvent $event) {
    if ($event->slot->start === '07:00' && !$event->order->getCustomer()?->isInGroup('wholesale')) {
        $event->isValid = false;
        $event->error = 'Early deliveries are for wholesale customers.';
    }
});
```

| Property | Description |
|---|---|
| `order` | The cart |
| `slot` | The [slot](/guides/reference#slot) being chosen |
| `isValid` | Set to `false` to refuse it |
| `error` | The message shown when it’s refused |

## After booking

`Slots::EVENT_AFTER_BOOK` runs when a completed order’s slot is booked, such as to send it to a routing or delivery service:

```php
use suprim\deliveryslots\events\BookEvent;

Event::on(Slots::class, Slots::EVENT_AFTER_BOOK, function(BookEvent $event) {
    MyRouting::addStop($event->order, $event->booking->getStartDate(), $event->booking->getEndDate());
});
```

| Property | Description |
|---|---|
| `order` | The completed order |
| `booking` | Its [delivery slot](/guides/reference#delivery-slot) |
