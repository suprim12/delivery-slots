# Introduction

Delivery Slots lets customers choose when their Craft Commerce order arrives, or when to collect it. It adds a time-slot picker to your checkout, and keeps each slot to the number of orders you can handle.

![A grocery checkout with delivery or pickup, day buttons, two-hour slots and the order summary](/examples/grocery-delivery.jpg)

## What it does

- **Slots per shipping method.** Each Commerce shipping method, such as *Local delivery* or *Pick up in store*, gets its own time windows and days.
- **Orders per slot.** A slot closes when it’s full. While a customer pays, their slot is held so nobody else takes the last place.
- **Ordering rules.** A next-day cut-off, same-day slots, a minimum notice before a slot starts, and how many days ahead to show.
- **Closed days.** Public holidays and other dates with no slots, with a label customers see.
- **Delivery zones.** With [Geo Maps](https://plugins.craftcms.com/geomaps), give each delivery area its own days, order limit and fee.
- **Run sheet.** Each day’s orders by slot, zone and suburb, to print for drivers or export as CSV.
- **Payment check.** Customers can’t pay without a slot, or with one that has filled up.

## How it fits into Commerce

Delivery Slots doesn’t replace Commerce’s shipping. Your shipping methods and rules still decide **which** methods an order can use and **what they cost**. Delivery Slots adds **when**:

1. The customer enters their address and chooses a shipping method, as usual.
2. If that method has a schedule, your checkout shows its slots, from `cart.deliverySlotDays`.
3. The chosen slot posts with the cart form as `deliverySlot`, and is held for the cart.
4. When the order is paid, the slot is booked. It shows on the order and the run sheet.

## Next steps

- [Install the plugin](/getting-started/installation)
- [Set up a schedule](/getting-started/schedules)
- [Add the slot picker to your checkout](/guides/slot-picker)
