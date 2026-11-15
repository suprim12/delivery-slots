# Release Notes for Delivery Slots

## 1.0.0 - Unreleased

### Added
- Delivery and pickup time slots for Craft Commerce shipping methods, with time windows per weekday and a limit on orders per slot.
- A ready-made slot picker for checkouts, `craft.deliverySlots.picker()`, which shows just the days when each has one time, and a month calendar for long schedules, or your own markup with `cart.deliverySlotDays`, posted with the cart form as `deliverySlot`.
- Slots are held for 15 minutes while customers check out, and for an hour once they go to pay. A slot is needed to go on from the delivery step, to pay, and to complete an order without payment.
- An “As soon as possible” option that takes the earliest free slot.
- Next-day cut-off, same-day slots, a minimum notice and how many days ahead to show, with more notice for some product types.
- Fees by time window, by weekday, and for same-day and next-day slots, added together with zone fees.
- Closed days with a label, as single dates or ranges, and every year for holidays; and changes to one window on one date.
- Changing the shipping method lets go of the old method’s time, and a new address lets go of a time its zone doesn’t offer, with a notice for the customer.
- Delivery zones from Commerce’s shipping zones, or drawn with Geo Maps, each with its own days, limit and slot fee. Addresses outside every zone aren’t offered the shipping method, and the picker says so (`cart.deliverySlotsUnavailableMethods`).
- A run sheet of each day’s orders by slot, zone and suburb, with print and CSV export.
- A packing list of each item’s quantity by slot.
- CSV exports of orders or the packing list for any date range, also as `php craft delivery-slots/export`.
- Staff can move an order to another slot from the order page, and email the customer their new time.
- **Mark delivered** on the run sheet sets the order’s Delivered status.
- A dashboard widget, a Delivery time column and a Delivery date filter on Commerce’s orders.
- `order.deliverySlot`, with `calendarUrl` for an add-to-calendar link.
- JSON actions for headless checkouts, and the slot in Commerce’s cart JSON.
- Developer events to change the notice, the days and slots, refuse a slot, and act on bookings.
