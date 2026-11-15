# JavaScript Checkouts

For checkouts that update without page loads, Delivery Slots has two actions for the current cart.

## Get the slots

```js
const response = await fetch('/actions/delivery-slots/slots/days', {
  headers: { Accept: 'application/json' },
})
const { chosen, days } = await response.json()
```

Add `?shippingMethodHandle=pickup` for a method the cart hasn’t chosen yet.

```json
{
  "chosen": "2026-11-17@10:00",
  "days": [
    {
      "date": "2026-11-16",
      "available": true,
      "unavailableReason": null,
      "slots": [
        { "value": "2026-11-16@08:00", "start": "08:00", "end": "10:00", "label": "8 – 10am", "left": 6, "full": false, "fee": 0 }
      ]
    },
    { "date": "2026-11-22", "available": false, "unavailableReason": "Closed", "slots": [] }
  ]
}
```

## Choose a slot

```js
const body = new FormData()
body.append(window.csrfTokenName, window.csrfTokenValue)
body.append('deliverySlot', '2026-11-17@10:00')

const response = await fetch('/actions/delivery-slots/slots/choose', {
  method: 'POST',
  headers: { Accept: 'application/json' },
  body,
})
const data = await response.json()
// Success: { message: 'Delivery time saved.', deliverySlot: '2026-11-17@10:00', label: 'Tuesday 17 November, 10am – 12pm' }
// Failure (status 400): { message: 'That delivery time is no longer available. Choose another.' }
```

Output the CSRF token name and value into the page for this:

```twig
<script>
  window.csrfTokenName = '{{ craft.app.config.general.csrfTokenName }}';
  window.csrfTokenValue = '{{ craft.app.request.csrfToken }}';
</script>
```

Choosing a slot also recalculates the cart, so a [zone fee](/guides/zones) shows in its totals. Post an empty `deliverySlot` to clear it.

You can also post `deliverySlot` with Commerce’s own `commerce/cart/update-cart` action over Ajax, along with other cart changes.

A complete picker is in the [JavaScript example](/examples/javascript-picker).
