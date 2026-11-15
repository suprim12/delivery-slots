<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\conditions;

use Craft;
use craft\base\conditions\BaseDateRangeConditionRule;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\db\Query;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use DateTime;
use DateTimeZone;
use suprim\deliveryslots\Plugin;
use suprim\deliveryslots\records\BookingRecord;

/**
 * Filters orders by the date of their delivery slot, such as “Tomorrow” on the Orders index.
 */
class DeliveryDateConditionRule extends BaseDateRangeConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('delivery-slots', 'Delivery date');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        // Slot dates are local, so each booking's start is checked with the same rules as matchElement().
        $ids = [];
        $rows = (new Query())->select(['orderId', 'date', 'start'])->from(BookingRecord::TABLE_NAME)->all();
        foreach ($rows as $row) {
            if ($this->matchValue($this->startOf((string)$row['date'], (string)$row['start']))) {
                $ids[] = (int)$row['orderId'];
            }
        }
        $query->andWhere(['elements.id' => $ids ?: [0]]);
    }

    public function matchElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        $slot = Plugin::getInstance()->slots->getBooking($element);

        return $this->matchValue($slot ? $this->startOf($slot->date, $slot->start) : null);
    }

    private function startOf(string $date, string $start): DateTime
    {
        return new DateTime(substr($date, 0, 10) . ' ' . $start, new DateTimeZone(Craft::$app->getTimeZone()));
    }
}
