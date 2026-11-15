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
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\commerce\adjusters\Tax;
use craft\commerce\controllers\CartController;
use craft\commerce\controllers\PaymentsController;
use craft\commerce\elements\Order;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\models\ShippingMethod;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\ShippingMethods;
use craft\events\DefineBehaviorsEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\Controller;
use craft\web\UrlManager;
use suprim\deliveryslots\adjusters\SlotFee;
use suprim\deliveryslots\behaviors\OrderBehavior;
use suprim\deliveryslots\models\Settings;
use suprim\deliveryslots\services\Schedules;
use suprim\deliveryslots\services\Slots;
use suprim\deliveryslots\services\Zones;
use yii\base\ActionEvent;
use yii\base\Event;
use yii\base\ModelEvent as ValidateEvent;

/**
 * Delivery and pickup time slots for Craft Commerce.
 *
 * @property-read Schedules $schedules
 * @property-read Slots $slots
 * @property-read Zones $zones
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const PERMISSION_RUN_SHEET = 'deliverySlots-viewRunSheet';
    public const PERMISSION_SCHEDULES = 'deliverySlots-manageSchedules';

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;

    /** A slot posted with `commerce/cart/update-cart`, applied when the cart saves. */
    private ?string $_postedSlot = null;

    /** Whether the current request is paying for an order. */
    private bool $_paying = false;

    public static function config(): array
    {
        return [
            'components' => [
                'schedules' => Schedules::class,
                'slots' => Slots::class,
                'zones' => Zones::class,
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
                Craft::warning("Couldn’t hold delivery slot $value for a new cart: $error", 'delivery-slots');
            }
        });

        // Paying needs a slot when the shipping method has them.
        Event::on(PaymentsController::class, Controller::EVENT_BEFORE_ACTION, function(ActionEvent $event) {
            $this->_paying = $event->action->id === 'pay';
        });

        Event::on(Order::class, Model::EVENT_BEFORE_VALIDATE, function(ValidateEvent $event) {
            /** @var Order $order */
            $order = $event->sender;
            if (!$this->_paying || $order->isCompleted) {
                return;
            }
            if (($error = $this->slots->checkBeforePayment($order)) !== null) {
                $order->addError('deliverySlot', $error);
                $event->isValid = false;
            }
        });

        // Methods with zones aren't offered to addresses outside them.
        Event::on(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, function(RegisterAvailableShippingMethodsEvent $event) {
            $order = $event->order;
            if ($order->isCompleted || !$this->zones->isAvailable()) {
                return;
            }
            $methods = [];
            foreach ($event->getShippingMethods() as $method) {
                $schedule = $method instanceof ShippingMethod && $method->id ? $this->schedules->getEnabled($method->id) : null;
                if ($schedule && $schedule->zones && $this->zones->hasLocation($order) && $this->zones->match($schedule, $order) === null) {
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
            $event->rules['delivery-slots/schedules'] = 'delivery-slots/schedules/index';
            $event->rules['delivery-slots/schedules/<shippingMethodId:\d+>'] = 'delivery-slots/schedules/edit';
        });

        Craft::$app->getView()->hook('cp.commerce.order.edit.details', function(array &$context) {
            $order = $context['order'] ?? null;
            if (!$order instanceof Order) {
                return '';
            }
            $slot = $this->slots->getBooking($order);
            if (!$slot) {
                return '';
            }

            return Craft::$app->getView()->renderTemplate('delivery-slots/_order-details', ['slot' => $slot, 'order' => $order]);
        });
    }
}
