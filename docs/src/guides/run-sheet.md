# The Run Sheet

**Delivery Slots → Run sheet** shows a day’s booked orders, grouped by slot.

![The run sheet for Tuesday 17 November: six slot cards showing how full each is, and eight orders grouped by time with customer, phone, address, zone and items](/images/run-sheet.jpg)

- **The date** starts at today. Use the arrows to move a day, or **Today** to come back.
- **The shipping method** menu shows one method, or all of them.
- **The slot cards** show how many orders each window has, out of its limit, when one method is shown.
- **The orders** are grouped by slot, then sorted by zone and suburb, which is roughly the order a driver visits them. Click an order number to open it.

Only completed orders are listed. Carts that are holding a slot aren’t, though they count towards how full a slot is at checkout.

## Columns

| Column | Shows |
|---|---|
| **Order** | The order’s reference, or its short number |
| **Customer** | The shipping address’s name, and its phone number. Without a Phone field on addresses, the customer’s email |
| **Address** | The shipping address |
| **Zone** | The [zone](/guides/zones) the address is in |
| **Items** | How many items the order has |
| **Note** | An order field you choose with `noteField` in the [config file](/getting-started/configuration), such as delivery instructions |

To show phone numbers, add a Phone field (handle `phone`) to addresses under **Settings → Addresses** and ask for it at checkout.

## Print

**Print** opens a plain page for the day and prints it, starting each slot on a new page so each driver gets their own.

![The printed run sheet: Run sheet, Tuesday 17 November 2026, Local delivery, 8 orders, with the orders grouped by slot](/images/print.jpg)

## Export CSV

**Export CSV** downloads the day’s orders with these columns: Date, Slot, Order, Customer, Email, Phone, Address, Suburb, Postcode, Zone, Items, Shipping method, and Note when it’s set. Import it into a routing app to plan the drive.

## Who can see it

Users need the **View the run sheet** permission.
