# Troubleshooting

## No slots show at checkout

- **Is the schedule on?** In **Delivery Slots → Schedules**, the method should show **On** and have at least one window.
- **Is the method chosen?** `cart.deliverySlotDays` uses the cart’s shipping method. Before one is chosen, pass a handle: `cart.getDeliverySlotDays('localDelivery')`.
- **Does the schedule have zones?** Then only addresses inside one get slots. Check the cart has a shipping address (or an estimated one) that’s inside a zone.
- **Is every day unavailable?** Each day has an `unavailableReason`, such as *Closed*, *Not in your area* or *Ordering has closed*. Show it in your template to see why.

## The shipping method has disappeared

A method whose schedule has zones isn’t offered to addresses outside every zone. That’s deliberate, so customers can’t order delivery you don’t make. Add a zone for the area, or remove the method’s zones to deliver anywhere its shipping rules allow.

## “Choose a delivery time” when paying

The method has slots and the order has none, or the one it had is full. Customers see this as an error on `cart.getFirstError('deliverySlot')`. Show it on your payment page and link back to the shipping step.

## “That delivery time is no longer available”

The slot filled up, closed (its cut-off or notice passed) or doesn’t exist, such as a slot posted from an old tab. The cart isn’t updated, and the customer can choose again.

## Slot times are an hour out

Check the site’s time zone in **Settings → General**. Slot times are in that time zone.

## The run sheet is empty

The run sheet shows completed orders. Carts that are still checking out hold slots, but aren’t on it. Check the date and shipping method at the top of the page.
