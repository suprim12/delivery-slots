# Order Confirmation Email

Add the delivery or pickup time to Commerce’s order emails.

Commerce emails are set up in **Commerce → Settings → Emails**, each with a Twig template that gets an `order` variable. Add this where the email lists the shipping details:

```twig
{% set slot = order.deliverySlot %}
{% if slot %}
  <table role="presentation" width="100%" style="margin: 24px 0; background: #eef4ef; border-radius: 8px;">
    <tr>
      <td style="padding: 16px 20px; font-family: Arial, sans-serif; color: #1c1d1f;">
        <p style="margin: 0 0 4px; font-size: 13px; color: #5d5f63;">
          {{ order.shippingMethodHandle == 'pickup' ? 'Collect your order' : 'Your delivery' }}
        </p>
        <p style="margin: 0; font-size: 18px; font-weight: bold;">
          {{ slot.startDate|date('l j F') }}, {{ slot.timeLabel }}
        </p>
        {% if order.shippingMethodHandle != 'pickup' %}
          <p style="margin: 8px 0 0; font-size: 14px;">
            To {{ order.shippingAddress.addressLine1 }}, {{ order.shippingAddress.locality }}.
            We’ll text you when the driver is on the way.
          </p>
        {% endif %}
      </td>
    </tr>
  </table>
{% endif %}
```

This shows:

> **Your delivery**
> Tuesday 17 November, 8 – 10am
> To 12 Osmond Tce, Norwood. We’ll text you when the driver is on the way.

## A reminder the day before

To email customers the day before their slot, send it from a scheduled console command or queue job that finds tomorrow’s orders:

```php
use craft\commerce\elements\Order;
use suprim\deliveryslots\Plugin as DeliverySlots;

$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');

foreach (DeliverySlots::getInstance()->slots->getBookedOn($tomorrow) as $slot) {
    $order = Order::find()->id($slot->orderId)->one();
    // Send your reminder, using $slot->getLabel() for the time.
}
```

## Calendar files

To let customers add the slot to their calendar, link to an `.ics` file built from `slot.startDate` and `slot.endDate` in a template of your own.
