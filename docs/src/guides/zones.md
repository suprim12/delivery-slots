# Delivery Zones

A schedule can have zones: the areas you deliver to, each with its own delivery days, order limit and fee. Addresses outside every zone get no slots, and the shipping method isn’t offered to them.

![Slots for an Adelaide Hills address: only Thursday and Saturday can be chosen, the other days say Not in your area, and every slot is $4 extra](/examples/zone-fees.jpg)

There are two kinds, and a schedule can use both:

- **Shipping zones**: Commerce’s own shipping zones, by country, state or postcode. Nothing else to install, and the same zones can price delivery in your shipping rules.
- **Drawn zones**: areas drawn on a map, with [Geo Maps](https://plugins.craftcms.com/geomaps) 1.4 or later. The free Lite edition is enough.

## Shipping zones

1. In **Commerce → Store Management → Shipping Zones**, add a zone per area, such as “Inner east”. For postcodes, add a **Postal Code Formula** condition, such as `postalCode in ['5067', '5068', '5069']`, or `postalCode >= '5000' and postalCode <= '5099'` for a range.
2. In **Delivery Slots → Schedules**, open the shipping method and add rows under **Shipping zones**:

| Column | Description |
|---|---|
| **Zone** | One of the store’s shipping zones. Its name shows on the run sheet and the order |
| **Mon** … **Sun** | The days you deliver there. Leave them all unticked to use the schedule’s days |
| **Orders per slot** | A limit for this zone, on top of the slot’s own limit. Leave it blank for none |
| **Slot fee** | Added to the order’s shipping when a slot is chosen. Leave it blank for none |

For example:

| Zone | Days | Orders per slot | Fee |
|---|---|---|---|
| Inner east | (schedule’s days) | | |
| Adelaide CBD | Mon, Wed, Fri | 6 | |
| Adelaide Hills | Thu, Sat | 4 | $4 |

## Drawn zones

1. Create a section for them, such as **Delivery Zones** (a channel, without URLs).
2. Give its entry type a Geo Maps field with **Enable Drawing & GeoJSON** turned on.
3. Add an entry per zone, such as “Inner east”, and draw its area with the polygon tool, or upload a `.geojson` file.
4. In the schedule, add rows under **Drawn zones**: the entry, then the same days, limit and fee as shipping zones.

Any entry with a drawn area can be a zone, so a store’s own delivery area works too.

## How it works

- **Shipping zones are checked first**, then drawn zones. Within each table, the first matching row is used: drag rows to put a small zone above a larger one around it.
- **Before the customer enters an address**, the method is offered and the picker asks for an address, as the times depend on it.
- **Addresses outside every zone get no slots, and the shipping method isn’t offered to them**, once the customer has entered an address. Pickup methods usually have no zones, so they’re still offered.
- **Days the zone doesn’t deliver** show as *Not in your area*.
- **Orders per slot** counts only orders in the zone. With 4 for the Hills and 10 for the slot, the slot takes ten orders, at most four of them from the Hills.
- **The fee** is added as a shipping adjustment called *Delivery time*, before tax, so it’s taxed like the rest of the shipping. Show it with `slot.fee` in the picker.
- **Addresses** use the shipping address, or the estimated shipping address before checkout. For drawn zones, addresses with coordinates use them; others are looked up with Geo Maps’ map service.
- **A deleted shipping zone** is skipped, and the schedule editor warns when none of its zones are left.

## Prices by zone

Slot fees are for extra charges on top of the shipping price. To price delivery by zone, put the same shipping zones on your Commerce shipping rules: shipping rules set the price, the schedule sets the days. For drawn zones or distance, use Geo Maps Pro’s *Delivery address is in zone* and *Delivery distance* conditions; see [Delivery Zones for Commerce](https://plugins.geomaps.suprim.com.au/examples/delivery-zones) in the Geo Maps docs.

## Without zones

A schedule without zones gives every address its slots, wherever it is. Limit where the method is offered with a Commerce shipping zone on its rules, such as your country or state.

If Geo Maps is removed, drawn zones are kept but not used. Shipping zones keep working.
