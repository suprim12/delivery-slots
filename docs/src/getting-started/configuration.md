# Configuration

Schedules are set per shipping method in the control panel ([Setting Up a Schedule](/getting-started/schedules)). A few options that apply to every schedule go in a config file.

Create `config/delivery-slots.php`:

```php
<?php

return [
    // How long a chosen slot is held for a customer who hasn’t paid yet, in minutes.
    'holdMinutes' => 15,

    // Slots with this many places or fewer count as nearly full (slot.isLow).
    'fewLeft' => 2,

    // An order field to show on the run sheet as the delivery note, such as a
    // “Delivery instructions” field in your order field layout.
    'noteField' => 'deliveryInstructions',
];
```

| Option | Default | Description |
|---|---|---|
| `holdMinutes` | `15` | How long a slot is [held](/guides/holds) while the customer checks out. Raise it for slow payment gateways |
| `fewLeft` | `2` | When `slot.isLow` is true, for “2 left” notes |
| `noteField` | `null` | The handle of an order field shown as a **Note** column on the [run sheet](/guides/run-sheet) and in the CSV |

Like any Craft config file, it can return different values per environment:

```php
return [
    '*' => ['holdMinutes' => 15],
    'dev' => ['holdMinutes' => 60],
];
```

## Time zone

Slot times and dates are in the site’s time zone, set in **Settings → General**. A store in Adelaide should use *Australia/Adelaide*, so “Order by 8pm” means 8pm in Adelaide.
