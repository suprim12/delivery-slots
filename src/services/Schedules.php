<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\services;

use craft\base\Component;
use suprim\deliveryslots\models\Schedule;
use suprim\deliveryslots\records\ScheduleRecord;

/**
 * Slot schedules, one per shipping method.
 */
class Schedules extends Component
{
    /** @var array<int, Schedule|null> */
    private array $_byMethod = [];

    /**
     * The shipping method's schedule, or null when it has none.
     */
    public function getByShippingMethodId(int $shippingMethodId): ?Schedule
    {
        if (!array_key_exists($shippingMethodId, $this->_byMethod)) {
            $record = ScheduleRecord::findOne(['shippingMethodId' => $shippingMethodId]);
            $this->_byMethod[$shippingMethodId] = $record ? Schedule::fromRecord($record) : null;
        }

        return $this->_byMethod[$shippingMethodId];
    }

    /**
     * The shipping method's schedule if it offers slots, or null.
     */
    public function getEnabled(?int $shippingMethodId): ?Schedule
    {
        if (!$shippingMethodId) {
            return null;
        }
        $schedule = $this->getByShippingMethodId($shippingMethodId);

        return $schedule && $schedule->enabled && $schedule->windows ? $schedule : null;
    }

    public function save(Schedule $schedule, bool $runValidation = true): bool
    {
        if ($runValidation && !$schedule->validate()) {
            return false;
        }

        $record = ScheduleRecord::findOne(['shippingMethodId' => $schedule->shippingMethodId]) ?? new ScheduleRecord();
        $record->shippingMethodId = (int)$schedule->shippingMethodId;
        $record->enabled = $schedule->enabled;
        $record->settings = $schedule->getSettings();
        $record->save(false);

        $schedule->id = $record->id;
        $this->_byMethod[(int)$schedule->shippingMethodId] = $schedule;

        return true;
    }
}
