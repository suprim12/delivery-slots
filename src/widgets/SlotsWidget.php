<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\Cp;
use DateInterval;
use suprim\deliveryslots\Plugin;

/**
 * A dashboard widget showing how full today’s and tomorrow’s slots are.
 */
class SlotsWidget extends Widget
{
    /** The shipping method to show, or null for every method with slots. */
    public ?int $shippingMethodId = null;

    public static function displayName(): string
    {
        return Craft::t('delivery-slots', 'Delivery Slots');
    }

    public static function icon(): ?string
    {
        return Craft::getAlias('@suprim/deliveryslots/icon-mask.svg');
    }

    public static function isSelectable(): bool
    {
        return parent::isSelectable() && Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_RUN_SHEET);
    }

    public function getTitle(): ?string
    {
        return Craft::t('delivery-slots', 'Delivery Slots');
    }

    public function getSettingsHtml(): ?string
    {
        $options = [['label' => Craft::t('delivery-slots', 'All shipping methods'), 'value' => '']];
        foreach (Plugin::getInstance()->runSheets->getMethods() as $id => $method) {
            $options[] = ['label' => $method->getName(), 'value' => $id];
        }

        return Cp::selectFieldHtml([
            'label' => Craft::t('delivery-slots', 'Shipping method'),
            'name' => 'shippingMethodId',
            'options' => $options,
            'value' => $this->shippingMethodId,
        ]);
    }

    public function getBodyHtml(): ?string
    {
        $plugin = Plugin::getInstance();
        $methods = $plugin->runSheets->getMethods();
        if ($this->shippingMethodId) {
            $methods = array_intersect_key($methods, [$this->shippingMethodId => true]);
        }
        $today = $plugin->slots->now()->setTime(0, 0);
        $days = [];
        foreach ([$today, $today->add(new DateInterval('P1D'))] as $i => $date) {
            $ymd = $date->format('Y-m-d');
            $sections = [];
            foreach ($methods as $id => $method) {
                $summary = $plugin->runSheets->getSummary($ymd, $id);
                if ($summary) {
                    $sections[] = [
                        'method' => $method,
                        'summary' => $summary,
                        'count' => array_sum(array_column($summary, 'count')),
                        'capacity' => array_sum(array_column($summary, 'capacity')),
                        'url' => \craft\helpers\UrlHelper::cpUrl('delivery-slots/run-sheet', ['date' => $ymd, 'method' => $id]),
                    ];
                }
            }
            $days[] = ['label' => $i === 0 ? Craft::t('delivery-slots', 'Today') : Craft::t('delivery-slots', 'Tomorrow'), 'date' => $date, 'sections' => $sections];
        }

        return Craft::$app->getView()->renderTemplate('delivery-slots/_widget', ['days' => $days]);
    }
}
