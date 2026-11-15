# Pay on Collection

Let pickup customers pay at the counter instead of online. They still choose a pickup time, and it’s held and booked like a paid order.

![The payment step for a pickup on Tuesday 17 November, 9 – 10am: Pay $51.50 by card, or Pay when I collect, paid by card or cash at the counter](/examples/pay-on-collection.jpg)

- **Pay when I collect** is shown only for pickup, next to the usual payment button.
- The order is completed without a payment, and its pickup time is booked. Without a time, the customer is asked to choose one.
- Delivery orders still pay online.

## Setup

- A pickup shipping method with a schedule, as in [Click and Collect](/examples/click-and-collect). This example uses the handle `pickup`.
- In **Commerce → System Settings → Stores**, open your store and turn on **Allow Checkout Without Payment**.

## 1. Only for pickup

**Allow Checkout Without Payment** applies to every order. The button below only shows for pickup, but to stop anyone completing a delivery order without paying, add this to a [module](https://craftcms.com/docs/5.x/extend/module-guide.html)’s `init()`:

```php
use craft\commerce\controllers\CartController;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use yii\base\ActionEvent;
use yii\base\Event;
use yii\web\BadRequestHttpException;

Event::on(CartController::class, Controller::EVENT_BEFORE_ACTION, function(ActionEvent $event) {
    if ($event->action->id !== 'complete') {
        return;
    }
    $cart = Commerce::getInstance()->getCarts()->getCart();
    if ($cart->shippingMethodHandle !== 'pickup') {
        throw new BadRequestHttpException('Only pickup orders can be paid on collection.');
    }
});
```

## 2. The payment step

Add a second button that completes the order with `commerce/cart/complete`. It submits its own small form, so it can sit next to your payment form’s button:

```twig
{% set cart = cart ?? craft.commerce.carts.cart %}
{% set payLater = cart.shippingMethodHandle == 'pickup'
  and cart.store.allowCheckoutWithoutPayment %}

{% if cart.getFirstError('deliverySlot') %}
  <p class="error">{{ cart.getFirstError('deliverySlot') }}</p>
{% endif %}

<form method="post">
  {{ csrfInput() }}
  {{ actionInput('commerce/payments/pay') }}
  {{ redirectInput('checkout/thanks?number={number}') }}
  {# Your gateway's fields #}

  <button>Pay {{ cart.totalPriceAsCurrency }}</button>
  {% if payLater %}
    <button form="collect">Pay when I collect</button>
  {% endif %}
</form>

{% if payLater %}
  <form id="collect" method="post">
    {{ csrfInput() }}
    {{ actionInput('commerce/cart/complete') }}
    {{ redirectInput('checkout/thanks?number={number}') }}
  </form>
{% endif %}
```

Delivery Slots checks the pickup time when the order is completed, as it does before payment. If there’s no time, or it has just filled up, the page comes back with the error in `cart.getFirstError('deliverySlot')`.

## 3. The confirmation

Say how much is still to pay:

```twig
{% if order.isPaid %}
  <p>We’ve emailed a receipt to {{ order.email }}.</p>
{% else %}
  <p>Pay {{ order.outstandingBalanceAsCurrency }} when you collect it, by card or cash.</p>
{% endif %}

{% if order.deliverySlot %}
  <p>Collect from <strong>175 The Parade, Norwood</strong> on {{ order.deliverySlot.label }}.</p>
{% endif %}
```

## At the counter

The order shows on the [run sheet](/guides/run-sheet) with its pickup time like any other. In Commerce it’s *Unpaid*: when the customer pays, record the payment on the order in the control panel, such as with a Manual gateway.
