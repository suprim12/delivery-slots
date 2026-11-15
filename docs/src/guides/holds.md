# Holds and Payment

When a customer chooses a slot, it’s **held** for their cart. When they pay, it’s **booked**.

## Holds

A held slot counts towards the slot’s limit, so two customers checking out at once can’t both take the last place. The hold lasts 15 minutes, or `holdMinutes` in the [config file](/getting-started/configuration).

If the customer takes longer, the hold runs out, and someone else can take the place. When they come back and pay:

- If the slot still has room, it’s held again and the payment goes ahead.
- If it filled up, payment stops with *That delivery time is no longer available*, and the customer chooses another.

Choosing again, or saving the cart with the same slot, renews the hold.

## Payment

When an order’s shipping method has slots, Commerce’s payment form (`commerce/payments/pay`) checks the slot first. Payment doesn’t go ahead, and the order shows an error on `deliverySlot`, when:

- the order has no slot: *Choose a delivery time.*
- the slot is full or has closed: *That delivery time is no longer available. Choose another.*

Show the error on your payment page:

```twig
{% if cart.getFirstError('deliverySlot') %}
  <p class="error">
    {{ cart.getFirstError('deliverySlot') }}
    <a href="{{ url('checkout/shipping') }}">Choose a time</a>
  </p>
{% endif %}
```

## Booking

When the order is completed, its slot is booked. Booked slots never expire, show on the [run sheet](/guides/run-sheet), and appear on the order in the control panel.

Payments taken in the control panel aren’t checked for a slot, so staff can take phone orders without one.

## Cancelled and deleted orders

When an order is deleted, its slot is freed, and it leaves the run sheet. A cancelled order keeps its slot, so it still shows on the run sheet and takes a place; delete it to free the place.
