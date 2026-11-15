<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots;

use Craft;
use craft\base\conditions\BaseCondition;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\commerce\adjusters\Tax;
use craft\commerce\controllers\BaseFrontEndController;
use craft\commerce\controllers\CartController;
use craft\commerce\controllers\PaymentsController;
use craft\commerce\elements\conditions\orders\OrderCondition;
use craft\commerce\elements\Order;
use craft\commerce\events\ModifyCartInfoEvent;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\models\ShippingMethod;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\ShippingMethods;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\Html;
use craft\services\Dashboard;
use craft\services\UserPermissions;
use craft\web\Controller;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use suprim\deliveryslots\adjusters\SlotFee;
use suprim\deliveryslots\behaviors\OrderBehavior;
use suprim\deliveryslots\conditions\DeliveryDateConditionRule;
use suprim\deliveryslots\models\Settings;
use suprim\deliveryslots\services\Notifications;
use suprim\deliveryslots\services\RunSheets;
use suprim\deliveryslots\services\Schedules;
use suprim\deliveryslots\services\Slots;
use suprim\deliveryslots\services\Zones;
use suprim\deliveryslots\variables\DeliverySlotsVariable;
use suprim\deliveryslots\widgets\SlotsWidget;
use yii\base\ActionEvent;
use yii\base\Event;
use yii\base\ModelEvent as ValidateEvent;

/**
 * Delivery and pickup time slots for Craft Commerce.
 *
 * @property-read Schedules $schedules
 * @property-read Slots $slots
 * @property-read Zones $zones
 * @property-read RunSheets $runSheets
 * @property-read Notifications $notifications
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const PERMISSION_RUN_SHEET = 'deliverySlots-viewRunSheet';
    public const PERMISSION_SCHEDULES = 'deliverySlots-manageSchedules';

    public string $schemaVersion = '1.1.0';
    public bool $hasCpSection = true;

    /** A slot posted with `commerce/cart/update-cart`, applied when the cart saves. */
    private ?string $_postedSlot = null;

    /** Whether the current request is paying for an order. */
    private bool $_paying = false;

    /** Whether the request completes an order without paying, such as a free order. */
    private bool $_completing = false;

    public static function config(): array
    {
        return [
            'components' => [
                'schedules' => Schedules::class,
                'slots' => Slots::class,
                'zones' => Zones::class,
                'runSheets' => RunSheets::class,
                'notifications' => Notifications::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function() {
            $this->registerOrderEvents();
            $this->registerCheckoutEvents();
            $this->registerControlPanel();
        });

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('deliverySlots', DeliverySlotsVariable::class);
        });
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $user = Craft::$app->getUser();
        $subnav = [];
        if ($user->checkPermission(self::PERMISSION_RUN_SHEET)) {
            $subnav['run-sheet'] = ['label' => Craft::t('delivery-slots', 'Run sheet'), 'url' => 'delivery-slots/run-sheet'];
        }
        if ($user->checkPermission(self::PERMISSION_SCHEDULES)) {
            $subnav['schedules'] = ['label' => Craft::t('delivery-slots', 'Schedules'), 'url' => 'delivery-slots/schedules'];
        }
        if (!$subnav) {
            return null;
        }
        $item['subnav'] = $subnav;
        $item['url'] = reset($subnav)['url'];

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    private function registerOrderEvents(): void
    {
        Event::on(Order::class, Model::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            $event->behaviors['deliverySlots'] = OrderBehavior::class;
        });

        // The slot's fee goes before tax, so it's taxed like the rest of the shipping.
        Event::on(OrderAdjustments::class, OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS, function(RegisterComponentTypesEvent $event) {
            $index = array_search(Tax::class, $event->types, true);
            if ($index === false) {
                $event->types[] = SlotFee::class;
            } else {
                array_splice($event->types, $index, 0, [SlotFee::class]);
            }
        });

        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(Event $event) {
            /** @var Order $order */
            $order = $event->sender;
            $this->slots->book($order);
        });
    }

    private function registerCheckoutEvents(): void
    {
        // Choosing a slot with the cart form: <input name="deliverySlot" value="{{ slot.value }}">.
        Event::on(CartController::class, Controller::EVENT_BEFORE_ACTION, function(ActionEvent $event) {
            if ($event->action->id === 'update-cart') {
                $slot = Craft::$app->getRequest()->getBodyParam('deliverySlot');
                $this->_postedSlot = is_string($slot) ? $slot : null;
            }
        });

        Event::on(Order::class, Element::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
            /** @var Order $order */
            $order = $event->sender;
            // A new cart is chosen for once it's saved and has an ID, below.
            if ($this->_postedSlot === null || $order->isCompleted || !$order->id) {
                return;
            }
            $value = $this->_postedSlot;
            $this->_postedSlot = null;
            if (($error = $this->slots->choose($order, $value)) !== null) {
                $order->addError('deliverySlot', $error);
                $event->isValid = false;
            }
        });

        Event::on(Order::class, Element::EVENT_AFTER_SAVE, function(ModelEvent $event) {
            /** @var Order $order */
            $order = $event->sender;
            if ($this->_postedSlot === null || $order->isCompleted || !$order->id) {
                return;
            }
            $value = $this->_postedSlot;
            $this->_postedSlot = null;
            if (($error = $this->slots->choose($order, $value)) !== null) {
                Craft::warning('Couldn’t hold delivery slot ' . json_encode(mb_substr($value, 0, 40)) . " for a new cart: $error", 'delivery-slots');
            }
        });

        // Paying needs a slot when the shipping method has them.
        Event::on(PaymentsController::class, Controller::EVENT_BEFORE_ACTION, function(ActionEvent $event) {
            // Only customers paying on the site; staff can take payment in the control panel without a slot.
            $this->_paying = $event->action->id === 'pay' && Craft::$app->getRequest()->getIsSiteRequest();
        });

        Event::on(Order::class, Model::EVENT_BEFORE_VALIDATE, function(ValidateEvent $event) {
            /** @var Order $order */
            $order = $event->sender;
            if (!$this->_paying || $order->isCompleted) {
                return;
            }
            // Held for longer while paying, so a payment page that takes a while doesn't lose the slot.
            if (($error = $this->slots->checkBeforePayment($order, true)) !== null) {
                $order->addError('deliverySlot', $error);
                $event->isValid = false;
            }
        });

        // Orders completed without paying, such as free orders, need a slot too.
        Event::on(CartController::class, Controller::EVENT_BEFORE_ACTION, function(ActionEvent $event) {
            $this->_completing = $event->action->id === 'complete' && Craft::$app->getRequest()->getIsSiteRequest();
        });
        Event::on(Order::class, Element::EVENT_BEFORE_SAVE, function(ModelEvent $event) {
            /** @var Order $order */
            $order = $event->sender;
            if (!$this->_completing || !$order->isCompleted) {
                return;
            }
            $this->_completing = false;
            if (($error = $this->slots->checkBeforePayment($order)) !== null) {
                $order->addError('deliverySlot', $error);
                $event->isValid = false;
            }
        });

        // The slot in the cart JSON that Commerce's cart actions return, for headless checkouts.
        Event::on(BaseFrontEndController::class, BaseFrontEndController::EVENT_MODIFY_CART_INFO, function(ModifyCartInfoEvent $event) {
            $slot = $this->slots->getBooking($event->cart);
            $event->cartInfo['deliverySlot'] = $slot ? [
                'value' => $slot->getValue(),
                'label' => $slot->getLabel(),
                'timeLabel' => $slot->getTimeLabel(),
                'date' => $slot->date,
                'start' => $slot->start,
                'end' => $slot->end,
                'fee' => $slot->fee,
                'zoneId' => $slot->zoneId,
                'isBooked' => $slot->getIsBooked(),
            ] : null;
            $event->cartInfo['hasDeliverySlots'] = $this->slots->hasSlots($event->cart);
        });

        // Methods with zones aren't offered to addresses outside them.
        Event::on(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, function(RegisterAvailableShippingMethodsEvent $event) {
            $order = $event->order;
            if ($order->isCompleted) {
                return;
            }
            $methods = [];
            foreach ($event->getShippingMethods() as $method) {
                $schedule = $method instanceof ShippingMethod && $method->id ? $this->schedules->getEnabled($method->id) : null;
                if ($schedule && $this->zones->applies($schedule) && $this->zones->hasAddress($order) && $this->zones->match($schedule, $order) === null) {
                    continue;
                }
                $methods[] = $method;
            }
            $event->setShippingMethods($methods);
        });
    }

    private function registerControlPanel(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions[] = [
                'heading' => Craft::t('delivery-slots', 'Delivery Slots'),
                'permissions' => [
                    self::PERMISSION_RUN_SHEET => ['label' => Craft::t('delivery-slots', 'View the run sheet')],
                    self::PERMISSION_SCHEDULES => ['label' => Craft::t('delivery-slots', 'Manage slot schedules')],
                ],
            ];
        });

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['delivery-slots'] = 'delivery-slots/run-sheet/index';
            $event->rules['delivery-slots/run-sheet'] = 'delivery-slots/run-sheet/index';
            $event->rules['delivery-slots/run-sheet/print'] = 'delivery-slots/run-sheet/print';
            $event->rules['delivery-slots/run-sheet/export'] = 'delivery-slots/run-sheet/export';
            $event->rules['delivery-slots/run-sheet/export-form'] = 'delivery-slots/run-sheet/export-form';
            $event->rules['delivery-slots/orders/<orderId:\d+>'] = 'delivery-slots/orders/edit';
            $event->rules['delivery-slots/schedules'] = 'delivery-slots/schedules/index';
            $event->rules['delivery-slots/schedules/<shippingMethodId:\d+>'] = 'delivery-slots/schedules/edit';
        });

        Craft::$app->getView()->hook('cp.commerce.order.edit.details', function(array &$context) {
            $order = $context['order'] ?? null;
            if (!$order instanceof Order) {
                return '';
            }
            $slot = $this->slots->getBooking($order);
            if (!$slot && !$this->slots->hasSlots($order)) {
                return '';
            }

            return Craft::$app->getView()->renderTemplate('delivery-slots/_order-details', [
                'slot' => $slot,
                'order' => $order,
                'canEdit' => Craft::$app->getUser()->checkPermission('commerce-editOrders'),
            ]);
        });

        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = SlotsWidget::class;
        });

        // Orders index: a Delivery time column and a Delivery date filter.
        Event::on(Order::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['deliverySlot'] = ['label' => Craft::t('delivery-slots', 'Delivery time')];
        });
        Event::on(Order::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, function(DefineAttributeHtmlEvent $event) {
            if ($event->attribute === 'deliverySlot') {
                /** @var Order $order */
                $order = $event->sender;
                $slot = $this->slots->getBooking($order);
                $event->html = $slot
                    ? Html::encode(Craft::$app->getFormatter()->asDate(\DateTime::createFromImmutable($slot->getStartDate()), 'php:D j M') . ', ' . $slot->getTimeLabel())
                    : '';
            }
        });
        Event::on(OrderCondition::class, BaseCondition::EVENT_REGISTER_CONDITION_RULES, function(RegisterConditionRulesEvent $event) {
            $event->conditionRules[] = DeliveryDateConditionRule::class;
        });
    }
}
