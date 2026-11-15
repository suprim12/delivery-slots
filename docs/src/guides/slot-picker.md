# The Slot Picker

The chosen slot posts with your cart form as `deliverySlot`. Use the ready-made picker, or write your own markup.

## The ready-made picker

Put it inside the form where customers choose a shipping method, which posts to `commerce/cart/update-cart`:

```twig
{{ craft.deliverySlots.picker() }}
```

It shows:

- the days, as buttons, and the chosen day’s times as radio buttons, with *Full*, *2 left* and slot fees;
- just the days, when no day has more than one time, such as a bakery open 10am to 4pm: choosing the day chooses its time;
- the error when a slot can’t be held, such as when it has just filled up;
- *Choose a delivery time* when the form is sent without one, so customers can’t go on to payment without a time;
- a notice when a new address means the chosen time isn’t offered there any more;
- *Enter your address to see delivery times* when the method has [zones](/guides/zones) and the customer hasn’t entered an address yet;
- *Local delivery isn’t available for Perth 6000* when the address is outside all of a method’s [zones](/guides/zones), so customers know why it’s missing from the shipping methods;
- nothing when the cart’s shipping method has no slots.

Options:

```twig
{{ craft.deliverySlots.picker(cart, {
  heading: 'When should we deliver?',  {# or false for none #}
  styles: false,                       {# leave the styling to you #}
  required: false,                     {# let the form save without a time #}
}) }}
```

Its classes start with `ds-`, and its colours can be changed with CSS variables:

```css
.ds-picker { --ds-accent: #1f5f3f; --ds-line: #d8d6cf; --ds-muted: #5d5f63; }
```

Pass `cart` when your template has one from a failed form, as below, so the error shows.

A time is only asked for when the form saves without changing the shipping method or the address, so a one-page checkout can still save its address first. Changing the shipping method lets go of the time held for the old one.

## Your own markup

A list of days, each with radio buttons for its slots.

### Where it goes

Put it in the form where customers choose a shipping method, which posts to `commerce/cart/update-cart`. When that form saves, Delivery Slots holds the chosen slot for the cart.

```twig
{% set cart = cart ?? craft.commerce.carts.cart %}

<form method="post">
  {{ csrfInput() }}
  {{ actionInput('commerce/cart/update-cart') }}
  {{ redirectInput('checkout/payment') }}

  {% for handle, option in cart.getAvailableShippingMethodOptions() %}
    <label>
      <input type="radio" name="shippingMethodHandle" value="{{ handle }}"
        {{ cart.shippingMethodHandle == handle ? 'checked' }}>
      {{ option.name }}: {{ option.priceAsCurrency }}
    </label>
  {% endfor %}

  {% set unavailable = cart.deliverySlotsUnavailableMethods %}
  {% if unavailable %}
    <p>{{ unavailable|map(m => m.name)|join(', ', ' and ') }} isn’t available for this address.</p>
  {% endif %}

  {% if cart.deliverySlotsNeedAddress %}
    <p>Enter your address to see delivery times.</p>
  {% elseif cart.hasDeliverySlots %}
    {# Ask for a time before the customer goes on to pay. #}
    <input type="hidden" name="deliverySlotRequired" value="1">

    {% set error = cart.getFirstError('deliverySlot')
      ?: (cart.getFirstNotice('deliverySlotUnavailable').message ?? null) %}
    {% if error %}
      <p class="error">{{ error }}</p>
    {% endif %}

    {% set chosen = cart.deliverySlot.value ?? null %}
    {% for day in cart.deliverySlotDays %}
      <h3>{{ day.date|date('l j F') }}</h3>
      {% if day.unavailableReason %}
        <p>{{ day.unavailableReason }}</p>
      {% else %}
        {% for slot in day.slots %}
          <label>
            <input type="radio" name="deliverySlot" value="{{ slot.value }}"
              {{ slot.value == chosen ? 'checked' }}
              {{ slot.isFull ? 'disabled' }}>
            {{ slot.label }}
            {% if slot.isFull %}
              Full
            {% elseif slot.isLow %}
              {{ slot.left }} left
            {% endif %}
          </label>
        {% endfor %}
      {% endif %}
    {% endfor %}
  {% endif %}

  <button>Continue</button>
</form>
```

`{% set cart = cart ?? … %}` matters: when the form fails, such as when the slot has just filled up, Commerce returns the cart with its errors in a `cart` variable.

### Days

`cart.deliverySlotDays` lists every day from tomorrow (or today, with same-day slots) to the schedule’s last day, including days with no slots, so you can show a full week. Each day has:

- `date`: the day, for `|date('D j')`.
- `slots`: its slots, including full ones.
- `unavailableReason`: why there are no slots, or `null`. One of *Closed*, the label of a [closed day](/getting-started/schedules#closed-days), *Not in your area* (a [zone](/guides/zones) that doesn’t deliver that day) or *Ordering has closed* (the cut-off or notice has passed).
- `isAvailable`: whether any slot can be chosen.
- `openSlots`: the slots that aren’t full.

### Slots

| Property | Example | Description |
|---|---|---|
| `value` | `2026-11-17@08:00` | Post this as `deliverySlot` |
| `label` | 8 – 10am | The times |
| `date` | | When the slot starts |
| `left` | 3 | Places left |
| `isFull` | | No places left |
| `isLow` | | Nearly full: `fewLeft` places or fewer ([Configuration](/getting-started/configuration)) |
| `fee` | 4 | The slot’s fee, from its [zone](/guides/zones), or 0 |

## Changing shipping method

`cart.deliverySlotDays` uses the cart’s current shipping method. When a customer switches method, save the cart (for example, submit the form on change) so the page shows the new method’s slots:

```twig
<input type="radio" name="shippingMethodHandle" value="{{ handle }}" onchange="this.form.submit()">
```

To show a method’s slots before it’s chosen, pass its handle: `cart.getDeliverySlotDays('pickup')`, and check with `cart.getHasDeliverySlots('pickup')`.

A slot belongs to one shipping method. If the customer changes method after choosing a slot, the slot no longer applies and they choose again.

## Clearing the slot

Post an empty `deliverySlot` to remove it:

```twig
<input type="hidden" name="deliverySlot" value="">
```

## Errors

If the slot can’t be held, the cart isn’t saved and `cart.getFirstError('deliverySlot')` says why:

- *That delivery time is no longer available. Choose another.* The slot filled up or closed.
- *Choose a shipping method with delivery times first.* The cart’s method has no schedule.

A complete, styled picker with day tabs is in the [grocery delivery example](/examples/grocery-delivery).
