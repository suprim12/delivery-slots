# Template Reference

## Order and cart

Delivery Slots adds these to every Commerce order and cart:

| | Returns |
|---|---|
| `cart.deliverySlotDays` | The [days](#day) to choose from, for the cart’s shipping method |
| `cart.getDeliverySlotDays('handle')` | The days for another shipping method |
| `cart.hasDeliverySlots` | Whether the cart’s shipping method has slots |
| `cart.getHasDeliverySlots('handle')` | Whether another shipping method has slots |
| `cart.deliverySlot` | The chosen [slot](#delivery-slot), or `null` |
| `cart.getFirstError('deliverySlot')` | Why the slot couldn’t be held or the order paid for |

## Day

| Property | Description |
|---|---|
| `date` | The day |
| `slots` | Its [slots](#slot), including full ones |
| `openSlots` | Slots that aren’t full |
| `isAvailable` | Whether any slot can be chosen |
| `unavailableReason` | Why there are no slots: *Closed*, a closed day’s label, *Not in your area* or *Ordering has closed*. `null` when the day has slots |

## Slot

| Property | Description |
|---|---|
| `value` | Post this as `deliverySlot`, such as `2026-11-17@08:00` |
| `label` | The times, such as “8 – 10am” |
| `date` | When the slot starts |
| `start`, `end` | The times as `08:00` and `10:00` |
| `capacity` | The slot’s limit |
| `left` | Places left |
| `isFull` | No places left |
| `isLow` | Nearly full |
| `fee` | Its zone’s fee, or 0 |

## Delivery slot

`order.deliverySlot`, the slot chosen for an order.

| Property | Description |
|---|---|
| `label` | The day and times, such as “Tuesday 17 November, 8 – 10am” |
| `timeLabel` | The times, such as “8 – 10am” |
| `startDate`, `endDate` | When it starts and ends |
| `date`, `start`, `end` | `2026-11-17`, `08:00`, `10:00` |
| `value` | `2026-11-17@08:00` |
| `zone` | The zone entry, or `null` |
| `fee` | The slot’s fee |
| `isBooked` | `true` once the order is complete, `false` while it’s held |
| `calendarUrl` | A link to download the slot as a calendar event (`.ics`). Works for completed orders |

## Form fields

| Field | Action | Description |
|---|---|---|
| `deliverySlot` | `commerce/cart/update-cart` | Holds the slot for the cart. Empty clears it |
| `deliverySlot` | `delivery-slots/slots/choose` | The same, on its own ([JavaScript Checkouts](/guides/javascript)) |

## Times

Slot times show as “8 – 10am” and “10am – 12pm” on sites in English, and as “08:00 – 10:00” in other languages. For another format, use `startDate` and `endDate` with Craft’s `date` filter.
