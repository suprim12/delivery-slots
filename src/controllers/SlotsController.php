<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use suprim\deliveryslots\models\Day;
use suprim\deliveryslots\models\Slot;
use suprim\deliveryslots\Plugin;
use yii\web\Response;

/**
 * Front-end actions for the current cart.
 */
class SlotsController extends Controller
{
    protected array|int|bool $allowAnonymous = ['days', 'choose'];

    /**
     * The cart's days and slots as JSON, for its shipping method or `?shippingMethodHandle=`.
     */
    public function actionDays(): Response
    {
        $cart = Commerce::getInstance()->getCarts()->getCart();
        $handle = $this->request->getQueryParam('shippingMethodHandle');
        $days = Plugin::getInstance()->slots->getDays($cart, is_string($handle) && $handle !== '' ? $handle : null);
        $chosen = Plugin::getInstance()->slots->getBooking($cart)?->getValue();

        return $this->asJson([
            'chosen' => $chosen,
            'days' => array_map(fn(Day $day) => [
                'date' => $day->date->format('Y-m-d'),
                'available' => $day->getIsAvailable(),
                'unavailableReason' => $day->unavailableReason,
                'slots' => array_map(fn(Slot $slot) => [
                    'value' => $slot->getValue(),
                    'start' => $slot->start,
                    'end' => $slot->end,
                    'label' => $slot->getLabel(),
                    'left' => $slot->left,
                    'full' => $slot->getIsFull(),
                    'fee' => $slot->fee,
                ], $day->slots),
            ], $days),
        ]);
    }

    /**
     * Holds a slot for the cart: post `deliverySlot`, such as `2026-11-17@08:00`.
     */
    public function actionChoose(): ?Response
    {
        $this->requirePostRequest();
        $cart = Commerce::getInstance()->getCarts()->getCart();
        $value = (string)$this->request->getRequiredBodyParam('deliverySlot');

        $error = Plugin::getInstance()->slots->choose($cart, $value);
        if ($error !== null) {
            return $this->asFailure($error, ['deliverySlot' => $value]);
        }
        // Recalculate so a slot fee shows in the totals.
        Craft::$app->getElements()->saveElement($cart, false);
        $slot = Plugin::getInstance()->slots->getBooking($cart);

        return $this->asSuccess(Craft::t('delivery-slots', 'Delivery time saved.'), [
            'deliverySlot' => $slot?->getValue(),
            'label' => $slot?->getLabel(),
        ]);
    }
}
