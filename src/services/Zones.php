<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\db\Query;
use craft\elements\Entry;
use suprim\deliveryslots\models\Schedule;
use suprim\geomaps\integrations\commerce\AddressLocation;
use suprim\geomaps\models\GeoValue;
use suprim\geomaps\records\GeoRecord;

/**
 * Delivery zones drawn with Geo Maps.
 */
class Zones extends Component
{
    /** @var array<int, Entry|null> */
    private array $_zones = [];

    /**
     * Whether Geo Maps 1.4 or later is installed, so zones can be used.
     */
    public function isAvailable(): bool
    {
        return class_exists(AddressLocation::class) && Craft::$app->getPlugins()->isPluginEnabled('geomaps');
    }

    /**
     * Entries with areas drawn in a Geo Maps field, as options for the zone column.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function options(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $ids = (new Query())
            ->select('ownerId')
            ->distinct()
            ->from(GeoRecord::TABLE_NAME)
            ->where(['not', ['shape' => null]])
            ->column();

        $options = [];
        foreach (Entry::find()->id($ids ?: [0])->status(null)->all() as $entry) {
            $options[] = ['label' => $entry->getSection()?->name . ': ' . $entry->title, 'value' => $entry->id];
        }
        usort($options, fn($a, $b) => strcmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * Whether the order has an address that can be placed on the map.
     */
    public function hasLocation(Order $order): bool
    {
        return $this->isAvailable() && $this->point($order) !== null;
    }

    /**
     * The schedule's first zone that contains the order's shipping address, or null when there's none
     * (or the address can't be placed on the map).
     *
     * Commerce shipping zones are checked first. `zoneId` is the zone entry's ID for a drawn zone, and null for a shipping zone.
     *
     * @return array{zoneId: int|null, key: string, name: string, days: int[], capacity: int|null, fee: float}|null
     */
    public function match(Schedule $schedule, Order $order): ?array
    {
        // Commerce shipping zones first: they’re exact, and need no map.
        $address = $order->getShippingAddress() ?? $order->getEstimatedShippingAddress();
        if ($schedule->shippingZones && $address) {
            $shippingZones = CommercePlugin::getInstance()->getShippingZones();
            foreach ($schedule->shippingZones as $rule) {
                $zone = $shippingZones->getShippingZoneById($rule['shippingZoneId']);
                if ($zone && $zone->getCondition()->matchElement($address)) {
                    return ['zoneId' => null, 'key' => 'shippingZone:' . $zone->id, 'name' => (string)$zone->name] + $rule;
                }
            }
        }
        if (!$schedule->zones || !$this->isAvailable()) {
            return null;
        }
        $point = $this->point($order);
        if ($point === null) {
            return null;
        }

        foreach ($schedule->zones as $rule) {
            $zone = $this->zone($rule['zoneId']);
            if (!$zone) {
                continue;
            }
            foreach ($zone->getFieldLayout()?->getCustomFields() ?? [] as $field) {
                $value = $zone->getFieldValue($field->handle);
                if ($value instanceof GeoValue && $value->contains($point)) {
                    return $rule + ['key' => 'entry:' . $rule['zoneId'], 'name' => (string)$zone->title];
                }
            }
        }

        return null;
    }

    /**
     * Whether the schedule’s zones are in use: it has Commerce shipping zones, or drawn zones and Geo Maps is installed.
     */
    public function applies(Schedule $schedule): bool
    {
        return $schedule->shippingZones || ($schedule->zones && $this->isAvailable());
    }

    /**
     * Whether the customer has entered an address to check against the zones. Before then, methods with zones are
     * still offered, and the picker asks for the address. After, an address no zone contains, including one that
     * can’t be found on the map, isn’t offered the method, so nobody is left with delivery and no times.
     */
    public function hasAddress(Order $order): bool
    {
        $address = $order->getShippingAddress();

        return $address !== null && (trim((string)$address->addressLine1) !== '' || trim((string)$address->postalCode) !== '' || trim((string)$address->locality) !== '');
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function point(Order $order): ?array
    {
        return AddressLocation::point($order->getShippingAddress() ?? $order->getEstimatedShippingAddress());
    }

    private function zone(int $id): ?Entry
    {
        if (!array_key_exists($id, $this->_zones)) {
            $this->_zones[$id] = Entry::find()->id($id)->status(null)->site('*')->unique()->one();
        }

        return $this->_zones[$id];
    }
}
