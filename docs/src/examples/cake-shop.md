# Cake Shop

A bakery that bakes cakes to order for collection: customers choose the day, not a time. Two days’ notice, twelve cakes a day, closed Sundays and Mondays, with a message piped on top.

![Collecting a dark chocolate cake: Saturday 21 November chosen from two weeks of days, each open day showing 10am – 4pm, with Sundays and Mondays closed and Wednesday 25 a private event](/examples/cake-shop.jpg)

- Days within two days show no times, so nobody orders a cake that can’t be baked in time.
- One window a day, so the picker shows days only: choosing the day chooses the time. The limit is how many cakes you can bake that day.
- The message on the cake is saved on the order’s line item, so it shows on the order and the packing list.

## 1. The shipping method

In **Commerce → Store Management → Shipping Methods**, create **Collect from the bakery** (handle `cakePickup`) with one shipping rule that costs nothing.

If the store sells other things that aren’t collected this way, give cakes their own **shipping category** and allow only that category on this method’s rule. Disallow it on your other methods’ rules.

## 2. The schedule

In **Delivery Slots → Schedules**, open **Collect from the bakery**:

| Setting | Value |
|---|---|
| **Time windows** | One window, 10:00–16:00, Tuesday to Saturday |
| **Orders per slot** | 12, or how many cakes you can bake in a day |
| **Same-day slots** | Off |
| **Book at least** | 48 hours |
| **Show slots up to** | 14 days ahead |
| **Closed days** | Public holidays, and days you’re catering, such as *Private event* |
| **One-off changes** | 18 on a Saturday with an extra baker |

Leave **Zones** empty: anyone can collect.

## 3. The cake page

Each cake is a small form that adds it to the cart with its message, as the line item’s note:

```twig
{% for product in craft.products().type('cakes').all() %}
  {% set variant = product.defaultVariant %}
  <form method="post">
    {{ csrfInput() }}
    {{ actionInput('commerce/cart/update-cart') }}
    {{ redirectInput('cakes/collection') }}
    <input type="hidden" name="purchasableId" value="{{ variant.id }}">
    <input type="hidden" name="shippingMethodHandle" value="cakePickup">

    <h3>{{ product.title }}</h3>
    <p>{{ variant.priceAsCurrency }}</p>
    <label>
      Message on the cake (optional)
      <input name="note" maxlength="30">
    </label>
    <button>Order this cake</button>
  </form>
{% endfor %}
```

## 4. The collection step

```twig
{% set cart = cart ?? craft.commerce.carts.cart %}

<form method="post">
  {{ csrfInput() }}
  {{ actionInput('commerce/cart/update-cart') }}
  {{ redirectInput('checkout/payment') }}
  <input type="hidden" name="shippingMethodHandle" value="cakePickup">

  {% for item in cart.lineItems %}
    <label>
      {{ item.purchasable.product.title }}: message on the cake
      <input name="lineItems[{{ item.id }}][note]" value="{{ item.note }}" maxlength="30">
    </label>
  {% endfor %}

  <h2>Which day will you collect it?</h2>
  <p>Any time 10am to 4pm. Baked to order: two days’ notice.</p>
  {{ craft.deliverySlots.picker(cart, {heading: false}) }}

  <button>Continue to payment</button>
</form>
```

The picker shows two weeks of days, each with its hours. Days inside the two days’ notice say *Ordering has closed*, closed days show their label, and nearly full days say *2 left*.

## Timed collection instead

For cakes that must leave the fridge just before collection, or a counter that gets crowded, use two-hour windows with a few cakes each, such as 10:00–12:00, 12:00–14:00 and 14:00–16:00 with 4 each. The picker then shows the times for the chosen day.

## The confirmation

```twig
{% for item in order.lineItems %}
  <p>{{ item.purchasable.product.title }}{% if item.note %}, with “{{ item.note }}” on top{% endif %}</p>
{% endfor %}
<p>Collect it on {{ order.deliverySlot.label }} from 62 King William Road, Goodwood.</p>
```

To let customers pay at the counter, see [Pay on Collection](/examples/pay-on-collection).
