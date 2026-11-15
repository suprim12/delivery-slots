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
     * Postcode zones are checked first. `zoneId` is the zone entry's ID for a drawn zone, and null for a postcode zone.
     *
     * @return array{zoneId: int|null, key: string, name: string, days: int[], capacity: int|null, fee: float}|null
     */
    public function match(Schedule $schedule, Order $order): ?array
    {
        // Postcode zones first: they’re exact, and need no map.
        $address = $order->getShippingAddress() ?? $order->getEstimatedShippingAddress();
        if ($schedule->postcodeZones && $address && $this->inStoreCountry($order, $address->countryCode)) {
            foreach ($schedule->postcodeZones as $rule) {
                if (Schedule::postcodeMatches((string)$address->postalCode, $rule['postcodes'])) {
                    return ['zoneId' => null] + $rule;
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
     * Whether the schedule’s zones are in use: it has postcode zones, or drawn zones and Geo Maps is installed.
     */
    public function applies(Schedule $schedule): bool
    {
        return $schedule->postcodeZones || ($schedule->zones && $this->isAvailable());
    }

    /**
     * Whether the order’s address can be checked against the schedule’s zones yet: it has a postcode, or a place
     * on the map. Before then, methods with zones aren’t hidden.
     */
    public function canPlace(Order $order, Schedule $schedule): bool
    {
        $address = $order->getShippingAddress() ?? $order->getEstimatedShippingAddress();
        if ($schedule->postcodeZones && $address && trim((string)$address->postalCode) !== '') {
            return true;
        }

        return $schedule->zones && $this->hasLocation($order);
    }

    /**
     * Postcodes are only compared within the store’s own country, when the store has a location set.
     */
    private function inStoreCountry(Order $order, ?string $countryCode): bool
    {
        $store = $order->getStore()->getSettings()->getLocationAddress();
        $storeCountry = $store?->countryCode;

        return !$storeCountry || !$countryCode || strtoupper($storeCountry) === strtoupper($countryCode);
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
