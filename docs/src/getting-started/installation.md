# Installation

Delivery Slots requires Craft CMS 5.6 or later, Craft Commerce 5 and PHP 8.2 or later. [Delivery zones](/guides/zones) also need Geo Maps 1.4 or later; Geo Maps Lite, the free edition, is enough.

## From the Plugin Store

In the control panel, go to **Plugin Store**, search for **Delivery Slots** and click **Install**.

You can try it free on local and staging sites. Craft asks for a licence once the site goes live.

## With Composer

```bash
composer require suprim/delivery-slots
php craft plugin/install delivery-slots
```

## Your first slots in ten minutes

1. **Check your shipping method.** In **Commerce → Store Management → Shipping Methods**, make sure you have the method customers will choose a time for, such as *Local delivery*, with a shipping rule that matches their orders.
2. **Set up its schedule.** Go to **Delivery Slots → Schedules**, choose the method, turn on **Offer delivery times** and save. A new schedule starts with two-hour windows from 8am to 6pm, Monday to Saturday. See [Setting Up a Schedule](/getting-started/schedules).
3. **Add the picker to your checkout.** On the page where customers choose a shipping method, add the slots to the same cart form:

```twig
{% if cart.hasDeliverySlots %}
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
        </label>
      {% endfor %}
    {% endif %}
  {% endfor %}
{% endif %}
```

4. **Place a test order.** Choose a slot, pay, and open **Delivery Slots → Run sheet** for that day.

## Permissions

Give staff these permissions under **Settings → Users**:

| Permission | Lets them |
|---|---|
| **View the run sheet** | See, print and export the run sheet |
| **Manage slot schedules** | Change time windows, limits, closed days and zones |
