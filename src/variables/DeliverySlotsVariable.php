<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\variables;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\web\View;
use Twig\Markup;

/**
 * `craft.deliverySlots` in site templates.
 */
class DeliverySlotsVariable
{
    /**
     * A ready-made slot picker to put inside the checkout form that posts to `commerce/cart/update-cart`. It shows the
     * days and times for the cart’s shipping method, asks for an address when the times depend on it, and says so when
     * there are no times for the address. It renders nothing when the method has no slots.
     *
     * Options: `heading` (text, or false for none), `styles` (false to leave the styling to you).
     *
     * @param array{heading?: string|false, styles?: bool} $options
     */
    public function picker(?Order $cart = null, array $options = []): Markup
    {
        $cart ??= CommercePlugin::getInstance()->getCarts()->getCart();
        $html = Craft::$app->getView()->renderTemplate('delivery-slots/_site/picker', [
            'cart' => $cart,
            'heading' => $options['heading'] ?? Craft::t('delivery-slots', 'Choose a delivery time'),
            'styles' => $options['styles'] ?? true,
        ], View::TEMPLATE_MODE_CP);

        return new Markup($html, Craft::$app->charset);
    }
}
