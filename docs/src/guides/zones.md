# Delivery Zones

A schedule can have zones: the areas you deliver to, each with its own delivery days, order limit and fee. Addresses outside every zone get no slots, and the shipping method isn’t offered to them.

![Slots for an Adelaide Hills address: only Thursday and Saturday can be chosen, the other days say Not in your area, and every slot is $4 extra](/examples/zone-fees.jpg)

There are two kinds, and a schedule can use both:

- **Postcode zones**: a list of postcodes. Nothing else to install.
- **Drawn zones**: areas drawn on a map, with [Geo Maps](https://plugins.craftcms.com/geomaps) 1.4 or later. The free Lite edition is enough.

## Postcode zones

In **Delivery Slots → Schedules**, open the shipping method and add rows under **Postcode zones**:

| Column | Description |
|---|---|
| **Name** | Shown on the run sheet and the order, such as “Inner east” |
| **Postcodes** | Separated by commas: exact (`5067`), ranges (`5000-5099`) or prefixes (`SW1*`). Case and spaces don’t matter |
| **Mon** … **Sun** | The days you deliver there. Leave them all unticked to use the schedule’s days |
| **Orders per slot** | A limit for this zone, on top of the slot’s own limit. Leave it blank for none |
| **Slot fee** | Added to the order’s shipping when a slot is chosen. Leave it blank for none |

For example:

| Name | Postcodes | Days | Orders per slot | Fee |
|---|---|---|---|---|
| Inner east | 5067, 5068, 5069 | (schedule’s days) | | |
| Adelaide CBD | 5000 | Mon, Wed, Fri | 6 | |
| Adelaide Hills | 5150*, 5240-5250 | Thu, Sat | 4 | $4 |

Postcodes are only compared for addresses in your store’s country, the **Store location** in Commerce’s store settings, so the same postcode abroad doesn’t match.

## Drawn zones

1. Create a section for them, such as **Delivery Zones** (a channel, without URLs).
2. Give its entry type a Geo Maps field with **Enable Drawing & GeoJSON** turned on.
3. Add an entry per zone, such as “Inner east”, and draw its area with the polygon tool, or upload a `.geojson` file.
4. In the schedule, add rows under **Drawn zones**: the entry, then the same days, limit and fee as postcode zones.

Any entry with a drawn area can be a zone, so a store’s own delivery area works too. The same entries can price delivery in Commerce’s shipping rules; see below.

## How it works

- **Postcode zones are checked first**, then drawn zones. Within each table, the first matching row is used: drag rows to put a small zone above a larger one around it.
- **Addresses outside every zone get no slots, and the shipping method isn’t offered to them**, once the customer has entered a postcode, or an address that can be placed on the map. Pickup methods usually have no zones, so they’re still offered.
- **Days the zone doesn’t deliver** show as *Not in your area*.
- **Orders per slot** counts only orders in the zone. With 4 for the Hills and 10 for the slot, the slot takes ten orders, at most four of them from the Hills.
- **The fee** is added as a shipping adjustment called *Delivery time*, before tax, so it’s taxed like the rest of the shipping. Show it with `slot.fee` in the picker.
- **Addresses** use the shipping address, or the estimated shipping address before checkout. For drawn zones, addresses with coordinates use them; others are looked up with Geo Maps’ map service.
- **Renaming a postcode zone** starts its orders-per-slot count again for orders that come in after the change.

## Prices by zone

Slot fees are for extra charges on top of the shipping price. To price delivery by drawn zone or distance, use Geo Maps Pro’s *Delivery address is in zone* and *Delivery distance* conditions on your Commerce shipping rules; see [Delivery Zones for Commerce](https://plugins.geomaps.suprim.com.au/examples/delivery-zones) in the Geo Maps docs. Both work together: shipping rules set the price, zones set the days.

## Without zones

A schedule without zones gives every address its slots, wherever it is. Limit where the method is offered with a Commerce shipping zone on its rules, such as your country or state.

If Geo Maps is removed, drawn zones are kept but not used. Postcode zones keep working.
