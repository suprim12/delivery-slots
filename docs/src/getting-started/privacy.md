# Privacy and Data

Delivery Slots stores little of its own. Customer details stay in Commerce orders and addresses; the plugin adds when each order is delivered.

## What it stores

| Table | Holds |
|---|---|
| `deliveryslots_schedules` | Each shipping method’s windows, limits, closed days, date changes and zones |
| `deliveryslots_bookings` | For each order with a slot: the shipping method, date, times, zone, fee, and whether it’s held or booked |

Bookings hold no names, emails, phone numbers or addresses. When an order is deleted, its booking is deleted with it.

## Who can see customer details

- **The run sheet, packing list, exports and print views** show names, phone numbers, emails and addresses from orders. Only users with the **View the run sheet** permission can open them, and only in the control panel.
- **Exported CSV files** contain personal data. Store and share them as you would any customer list, and delete them when you’re done.
- **Changing an order’s slot** needs Commerce’s **Edit orders** permission.

## What customers can see

- **The slot picker and JSON actions** show only the visitor’s own cart and how many places are left in each slot. They never show other customers’ orders.
- **The calendar link** (`slot.calendarUrl`) uses the order’s 32-character number, which only the customer has. It works only for completed orders, and includes the shipping address as the event’s location.

## Emails

When staff change an order’s slot with **Email the customer** ticked, the customer gets an email at the order’s address, sent with Craft’s own email settings. Nothing else is emailed by the plugin.

## Sent to other services

Delivery Slots doesn’t send data anywhere itself.

With [zones](/guides/zones), the shipping address is placed on the map by Geo Maps. Addresses with coordinates are used as they are. Others are looked up with Geo Maps’ map service (OpenStreetMap, Mapbox or Google), which receives the street, suburb, postcode and country, but no name or contact details. See Geo Maps’ privacy notes for each service.

## Logs

Craft’s logs record a slot that couldn’t be held for a new cart, with the slot’s value and the reason. They don’t include customer details.
