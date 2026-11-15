# Grocery Delivery Checkout

The delivery step of a grocery checkout: delivery or pickup, a row of days, two-hour slots with places left, and the order summary with the chosen time.

![A checkout for 12 Osmond Tce, Norwood: Local delivery chosen, Tuesday 17 selected, 8 – 10am with 2 left, 2 – 4pm available, 10am – 12pm chosen and shown in the order summary](/examples/grocery-delivery.jpg)

- Choosing **Pick up in store** or **Local delivery** saves the cart, and the page shows that method’s slots.
- Days without slots are greyed out with the reason: *Closed*, *Not in your area* or *Ordering has closed*.
- The day buttons switch between days without a page load. The page opens on the chosen slot’s day, or the first day with free slots.
- **Continue to payment** saves the cart with the slot. If the slot has just filled up, the page shows why.

## Setup

- Shipping methods **Local delivery** (handle `localDelivery`) and **Pick up in store** (handle `pickup`), each with a [schedule](/getting-started/schedules).
- The cart has a shipping address, from the previous step.

## Template

`templates/checkout/delivery.twig`. The **Continue to payment** button goes on to `checkout/payment`; change it to your payment step.

```twig
{% set cart = cart ?? craft.commerce.carts.cart %}
{% set address = cart.shippingAddress %}
{% set chosen = cart.deliverySlot.value ?? null %}

<header class="top">
  <strong>Green Grocer</strong>
  <ol class="steps"><li>1. Address</li><li class="on">2. Delivery</li><li>3. Payment</li></ol>
</header>

<form method="post" class="wrap">
  {{ csrfInput() }}
  {{ actionInput('commerce/cart/update-cart') }}
  {# Changing method reloads this page; Continue goes on to payment. #}
  {{ redirectInput(craft.app.request.pathInfo) }}

  <div>
    <div class="card row">
      <div><div class="muted">Delivering to</div>{{ address ? address.addressLine1 ~ ', ' ~ address.locality ~ ' ' ~ address.postalCode : 'No address yet' }}</div>
      <a href="#" style="color: var(--brand); font-size: 14px;">Change</a>
    </div>

    <div class="card">
      <h2>How would you like it?</h2>
      <div class="methods">
        {% for handle, option in cart.getAvailableShippingMethodOptions() %}
          <label class="choice">
            <input type="radio" name="shippingMethodHandle" value="{{ handle }}" {{ cart.shippingMethodHandle == handle ? 'checked' }} onchange="this.form.submit()">
            <span class="row"><b>{{ option.name }}</b><span>{{ option.price ? option.priceAsCurrency : 'Free' }}</span></span>
          </label>
        {% else %}
          <p class="closed">Sorry, we don’t deliver to this address.</p>
        {% endfor %}
      </div>
    </div>

    {% if cart.hasDeliverySlots %}
      {% set days = cart.deliverySlotDays %}
      {# Open the chosen slot's day, or the first day with free slots. #}
      {% set openDay = days|filter(d => chosen and d.date|date('Y-m-d') == chosen|split('@')|first)|first
        ?: days|filter(d => d.isAvailable)|first %}
      <div class="card">
        <div class="row" style="margin-bottom: 14px;">
          <h2 style="margin: 0;">{{ cart.shippingMethodHandle == 'pickup' ? 'Choose a pickup time' : 'Choose a delivery time' }}</h2>
          {% if cart.shippingMethodHandle != 'pickup' %}<span class="muted">Order by 8pm for next-day delivery</span>{% endif %}
        </div>
        {% if cart.getFirstError('deliverySlot') %}<p class="error">{{ cart.getFirstError('deliverySlot') }}</p>{% endif %}

        <div class="days">
          {% for day in days %}
            <button type="button" class="day" data-day="{{ loop.index }}" aria-pressed="{{ day == openDay ? 'true' : 'false' }}" {{ not day.isAvailable ? 'disabled' }}>
              <small>{{ day.date|date('D') }}</small>
              <b>{{ day.date|date('j') }}</b>
              <small>{{ day.unavailableReason ?? (day.slots|filter(s => s.isLow)|length ? 'Few left') }}</small>
            </button>
          {% endfor %}
        </div>

        {% for day in days %}
          {% if day.isAvailable %}
          <div class="slots" data-slots="{{ loop.index }}" {{ day != openDay ? 'hidden' }}>
            {% for slot in day.slots %}
              <label class="choice slot{{ slot.isFull ? ' full' }}">
                <input type="radio" name="deliverySlot" value="{{ slot.value }}" {{ slot.value == chosen ? 'checked' }} {{ slot.isFull ? 'disabled' }}>
                <b>{{ slot.label }}</b>
                {% if slot.isFull %}
                  <small>Full</small>
                {% elseif slot.isLow %}
                  <small class="low">{{ slot.left }} left</small>
                {% else %}
                  <small>{{ slot.fee ? '+' ~ slot.fee|currency(cart.currency) : 'Available' }}</small>
                {% endif %}
              </label>
            {% endfor %}
          </div>
          {% endif %}
        {% endfor %}
      </div>
    {% endif %}
  </div>

  <aside class="card">
    <h2>Order summary</h2>
    {% for item in cart.lineItems %}
      <div class="line"><span>{{ item.purchasable.title ?? item.description }}{{ item.qty > 1 ? ' × ' ~ item.qty }}</span><span>{{ item.subtotalAsCurrency }}</span></div>
    {% endfor %}
    <div class="line muted"><span>Delivery</span><span>{{ cart.totalShippingCostAsCurrency }}</span></div>
    <div class="line total"><span>Total</span><span>{{ cart.totalPriceAsCurrency }}</span></div>
    {% if cart.deliverySlot %}
      <div class="when"><b>{{ cart.shippingMethod.name }}</b><br>{{ cart.deliverySlot.label }}</div>
    {% endif %}
    <button class="go" name="redirect" value="{{ 'checkout/payment'|hash }}">Continue to payment</button>
  </aside>
</form>

```

## Script

The day buttons show that day’s slots. Without it, only the opening day’s slots show; the rest of the page works the same.

```html
<script>
  // Show the slots for the day that's picked.
  document.querySelectorAll('.day').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelectorAll('.day').forEach((b) => b.setAttribute('aria-pressed', b === button));
      document.querySelectorAll('[data-slots]').forEach((group) => { group.hidden = group.dataset.slots !== button.dataset.day; });
    });
  });
</script>
```

## Styles

```css
:root { --ink: #1c1d1f; --muted: #5d5f63; --line: #d8d6cf; --soft: #eef4ef; --brand: #1f5f3f; }
* { box-sizing: border-box; }
body { margin: 0; background: #f6f5f1; font-family: Inter, system-ui, sans-serif; color: var(--ink); }
.top { background: #fff; border-bottom: 1px solid #e4e2dc; padding: 0 32px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
.top strong { font-size: 19px; }
.steps { display: flex; gap: 24px; font-size: 14px; color: var(--muted); margin: 0; padding: 0; list-style: none; }
.steps .on { color: var(--ink); font-weight: 600; }
.wrap { max-width: 1120px; margin: 0 auto; padding: 32px; display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 32px; align-items: start; }
.card { background: #fff; border: 1px solid #e4e2dc; border-radius: 12px; padding: 22px; margin-bottom: 18px; }
.card h2 { margin: 0 0 14px; font-size: 17px; }
.row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
.muted { color: var(--muted); font-size: 13px; }
.methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.choice { display: block; border: 1px solid var(--line); border-radius: 10px; padding: 13px 15px; cursor: pointer; }
.choice:has(input:checked) { border: 2px solid var(--brand); background: var(--soft); }
.choice input { position: absolute; opacity: 0; }
.choice:has(input:focus-visible) { outline: 2px solid var(--brand); outline-offset: 2px; }
.days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; margin-bottom: 14px; }
.day { border: 1px solid var(--line); background: #fff; border-radius: 10px; height: 70px; font: inherit; cursor: pointer; color: var(--ink); }
.day b { display: block; font-size: 18px; }
.day small { display: block; font-size: 11px; color: var(--muted); }
.day[aria-pressed="true"] { background: var(--brand); border-color: var(--brand); color: #fff; }
.day[aria-pressed="true"] small { color: #d7e7dc; }
.day:disabled { background: #f1f0ec; color: #8a8c90; cursor: not-allowed; }
.slots { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.slots[hidden] { display: none; }
.slot.full { background: #f1f0ec; color: #8a8c90; border-color: #e4e2dc; cursor: not-allowed; }
.slot b { display: block; font-size: 15px; }
.slot small { font-size: 12px; color: var(--muted); }
.slot small.low { color: #a4470b; font-weight: 600; }
.closed { margin: 0; color: var(--muted); font-size: 14px; }
.error { background: #fdf1ee; color: #8a2a17; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
.line { display: flex; justify-content: space-between; font-size: 14px; margin: 8px 0; }
.total { border-top: 1px solid #e4e2dc; padding-top: 12px; font-weight: 600; font-size: 16px; }
.when { background: var(--soft); border-radius: 8px; padding: 11px 13px; font-size: 13px; color: #1f3f2c; margin: 14px 0; }
button.go { width: 100%; height: 48px; border: 0; border-radius: 10px; background: var(--brand); color: #fff; font: 600 15px Inter, sans-serif; cursor: pointer; }
@media (max-width: 860px) { .wrap { grid-template-columns: 1fr; } .slots { grid-template-columns: repeat(2, minmax(0, 1fr)); } .days { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
```

## Variations

- **The ready-made picker.** Replace the time card’s days and slots, the script and the day and slot styles with <code v-pre>{{ craft.deliverySlots.picker(cart, {heading: false}) }}</code>, and match its colours with `.ds-picker { --ds-accent: var(--brand); }`. See [The Slot Picker](/guides/slot-picker#the-ready-made-picker).
- **A list instead of day tabs.** Leave out the script and the `hidden` attribute, and every day shows its slots under a heading, as in [The Slot Picker](/guides/slot-picker).
- **Prices by slot.** With [zones](/guides/zones), `slot.fee` is the extra charge, shown here as “+$4.00”. See [Zone Days and Fees](/examples/zone-fees).
- **Weekday names.** `day.date|date('D')` gives “Tue”; use `date('l')` for “Tuesday”.
