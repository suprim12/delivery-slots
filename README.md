# Delivery Slots for Craft Commerce

Let customers choose when their order arrives. Delivery Slots adds a time-slot picker to your checkout for local delivery and in-store pickup, with limits on how many orders each slot takes, ordering cut-offs, closed days, delivery zones and a daily run sheet for your drivers.

Built for grocers, florists, bakeries, butchers, meal kits, liquor stores and anyone who delivers their own orders.

## Features

- **Time windows per shipping method.** Two-hour delivery windows, one-hour pickup windows, different times on different days.
- **Orders per slot.** Full slots can’t be chosen. A slot is held for 15 minutes while the customer pays, so two people can’t take the last place.
- **Ordering rules.** Next-day cut-off (“order by 8pm for tomorrow”), same-day slots, a minimum lead time and how many days ahead to show.
- **Closed days.** Public holidays and stocktakes, with a label customers see.
- **Delivery zones.** With [Geo Maps](https://plugins.craftcms.com/geomaps), draw zones on a map and give each its own delivery days, order limit and slot fee. Addresses outside every zone aren’t offered the shipping method.
- **Run sheet.** Each day’s orders by slot, zone and suburb, with how full each slot is. Print a page per slot for drivers.
- **Packing list.** How many of each item to pack for the day, by slot.
- **Exports.** Orders or the packing list as CSV for any date range, from the control panel or `php craft delivery-slots/export`.
- **Change a slot.** Move an order to another time from the order page, even when it’s full. Close or change one slot on one date.
- **Dashboard and Orders.** A widget showing how full today and tomorrow are, a Delivery time column and a Delivery date filter on Commerce → Orders.
- **Add to calendar.** `order.deliverySlot.calendarUrl` downloads the slot as a calendar event.
- **On the order.** The slot shows on the order in the control panel, and as `order.deliverySlot` in templates and emails.
- **Payment is checked.** Customers can’t pay without a slot, or with one that has filled up.

## Requirements

Craft CMS 5.6+ and Craft Commerce 5. Zones need Geo Maps 1.4 or later (Lite or Pro).

## Setting up

1. Install from the Plugin Store, or `composer require suprim/delivery-slots` then `php craft plugin/install delivery-slots`.
2. Go to **Delivery Slots → Schedules** and choose a shipping method.
3. Turn on **Offer delivery times**, set the time windows and orders per slot, and save.
4. Add the slot picker to the shipping step of your checkout (below).

## The slot picker

Post `deliverySlot` with the rest of your cart form to `commerce/cart/update-cart`:

```twig
{% set cart = cart ?? craft.commerce.carts.cart %}

<form method="post">
  {{ csrfInput() }}
  {{ actionInput('commerce/cart/update-cart') }}
  {{ redirectInput('checkout/payment') }}

  {# Your shipping method radios here (shippingMethodHandle) #}

  {% if cart.hasDeliverySlots %}
    {% if cart.getFirstError('deliverySlot') %}
      <p class="error">{{ cart.getFirstError('deliverySlot') }}</p>
    {% endif %}

    {% for day in cart.deliverySlotDays %}
      <h3>{{ day.date|date('l j F') }}</h3>
      {% if day.unavailableReason %}
        <p>{{ day.unavailableReason }}</p>
      {% else %}
        {% for slot in day.slots %}
          <label>
            <input type="radio" name="deliverySlot" value="{{ slot.value }}"
              {{ slot.value == (cart.deliverySlot.value ?? null) ? 'checked' }}
              {{ slot.isFull ? 'disabled' }}>
            {{ slot.label }}
            {% if slot.isFull %}Full{% elseif slot.isLow %}{{ slot.left }} left{% endif %}
            {% if slot.fee %}+{{ slot.fee|currency(cart.currency) }}{% endif %}
          </label>
        {% endfor %}
      {% endif %}
    {% endfor %}
  {% endif %}

  <button>Continue</button>
</form>
```

To show the slots for a shipping method before it’s chosen, pass its handle: `cart.getDeliverySlotDays('localDelivery')`.

### In emails and order pages

```twig
{% if order.deliverySlot %}
  <p>Delivery: {{ order.deliverySlot.label }}</p>  {# Monday 17 November, 8 – 10am #}
{% endif %}
```

| `order.deliverySlot` | |
|---|---|
| `label` | Day and times: “Monday 17 November, 8 – 10am” |
| `timeLabel` | Times only: “8 – 10am” |
| `startDate`, `endDate` | Dates, for your own formatting |
| `zone` | The Geo Maps zone entry, if the schedule has zones |
| `fee` | The slot’s fee |
| `isBooked` | `true` once the order is complete, `false` while it’s held |

### JavaScript checkouts

- `GET actions/delivery-slots/slots/days` returns the cart’s days and slots as JSON (add `?shippingMethodHandle=` for another method).
- `POST actions/delivery-slots/slots/choose` with `deliverySlot` holds a slot for the cart.

## Configuration

Create `config/delivery-slots.php` to change these:

```php
return [
    // How long a chosen slot is held while the customer pays, in minutes. 0 means only paid orders take places.
    'holdMinutes' => 15,
    // Slots with this many places or fewer count as nearly full (slot.isLow).
    'fewLeft' => 2,
    // An order field to show as the delivery note on the run sheet.
    'noteField' => 'deliveryInstructions',
];
```

## Permissions

- **View the run sheet**
- **Manage slot schedules**

## Support

Email [support@suprim.com.au](mailto:support@suprim.com.au).
