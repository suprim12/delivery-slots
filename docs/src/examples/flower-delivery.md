# Flower Delivery

A florist with same-day delivery: morning and afternoon runs, every weekday across the city, and three days a week to the hills for an extra fee. The sender enters the recipient’s address, a card message and a time on one page.

![Delivering red tulips to Hahndorf: only Tuesday, Thursday and Saturday can be chosen, the other days say Not in your area, and both runs cost $8 extra](/examples/flower-delivery.jpg)

- Orders by 10am can go out on the afternoon run the same day.
- The recipient’s address decides the zone, and the zone decides the days and the extra fee.
- Addresses outside both zones aren’t offered delivery, and the page says *Flower delivery isn’t available for Perth 6000*.

## 1. The shipping method and zones

In **Commerce → Store Management**:

1. **Shipping Zones**: add two zones with a **Postal Code Formula** condition:
   - *City and inner suburbs*: `postalCode >= '5000' and postalCode <= '5099'`
   - *Adelaide Hills*: `postalCode in ['5150', '5151', '5152', '5153', '5240', '5241', '5242', '5243', '5244', '5245']`
2. **Shipping Methods**: create **Flower delivery** (handle `flowerDelivery`) with one rule at a $12 base rate.

## 2. The schedule

In **Delivery Slots → Schedules**, open **Flower delivery**:

| Setting | Value |
|---|---|
| **Time windows** | 9:00–12:00 and 12:00–17:00, Monday to Saturday, 10 orders each |
| **Same-day slots** | On |
| **Book at least** | 2 hours |
| **Show slots up to** | 7 days ahead |
| **One-off changes** | 20 per run on the Friday before a busy weekend, with a second van |

Under **Shipping zones**:

| Zone | Days | Orders per slot | Fee |
|---|---|---|---|
| City and inner suburbs | (schedule’s days) | | |
| Adelaide Hills | Tue, Thu, Sat | 4 | $8 |

The $8 is added on top of the $12 delivery when a hills time is chosen. See [Delivery Zones](/guides/zones).

## 3. The delivery step

The recipient’s address has its own form, as the times depend on it. Once it’s saved, show it with a **Change** link, and the rest of the page is one form:

```twig
{% set cart = cart ?? craft.commerce.carts.cart %}
{% set address = cart.shippingAddress %}

{% if not address or craft.app.request.getQueryParam('address') %}
  <form method="post">
    {{ csrfInput() }}
    {{ actionInput('commerce/cart/update-cart') }}
    {{ redirectInput('flowers/delivery') }}
    <input type="hidden" name="shippingAddress[countryCode]" value="AU">
    <input name="shippingAddress[fullName]" placeholder="Their name" required>
    <input name="shippingAddress[addressLine1]" placeholder="Street address" required>
    <input name="shippingAddress[locality]" placeholder="Suburb" required>
    <input name="shippingAddress[postalCode]" placeholder="Postcode" required>
    <button>Show delivery times</button>
  </form>
{% else %}
  <p>{{ address.fullName }}, {{ address.addressLine1 }}, {{ address.locality }} {{ address.postalCode }}</p>
  <a href="?address=1">Change</a>
{% endif %}

<form method="post">
  {{ csrfInput() }}
  {{ actionInput('commerce/cart/update-cart') }}
  {{ redirectInput('checkout/payment') }}
  <input type="hidden" name="shippingMethodHandle" value="flowerDelivery">

  <h2>When should we deliver?</h2>
  {{ craft.deliverySlots.picker(cart, {heading: false}) }}

  {% for item in cart.lineItems %}
    <label>
      Card message
      <textarea name="lineItems[{{ item.id }}][note]" maxlength="200">{{ item.note }}</textarea>
    </label>
  {% endfor %}

  <button {{ not address or cart.shippingMethodHandle != 'flowerDelivery' ? 'disabled' }}>
    Continue to payment
  </button>
</form>
```

Before the address is entered, the picker says *Enter your address to see delivery times*. For a hills address it shows Tuesday, Thursday and Saturday, with “+$8.00” on each time.

## The confirmation

```twig
<p>Delivering to {{ order.shippingAddress.fullName }} on {{ order.deliverySlot.label }}.</p>
{% for item in order.lineItems|filter(i => i.note) %}
  <p>Card: “{{ item.note }}”</p>
{% endfor %}
```
