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
use craft\web\View;
use suprim\deliveryslots\models\DeliverySlot;
use suprim\deliveryslots\Plugin;
use Throwable;

/**
 * Emails customers about their slot.
 */
class Notifications extends Component
{
    /**
     * Emails the customer their order’s new slot after staff change it.
     *
     * @return bool Whether the email was sent.
     */
    public function sendSlotChanged(Order $order, DeliverySlot $slot, ?DeliverySlot $previousSlot = null): bool
    {
        $email = $order->getEmail();
        if (!$email) {
            return false;
        }

        $settings = Plugin::getInstance()->getSettings();
        $params = [
            'site' => Craft::$app->getSites()->getCurrentSite()->getName(),
            'reference' => $order->reference ?: $order->getShortNumber(),
            'slot' => $slot->getLabel(),
        ];
        $subject = $settings->slotChangedSubject
            ? strtr($settings->slotChangedSubject, array_combine(array_map(fn($k) => '{' . $k . '}', array_keys($params)), array_values($params)))
            : Craft::t('delivery-slots', '{site}: your new delivery time for order {reference}', $params);
        $variables = ['order' => $order, 'slot' => $slot, 'previousSlot' => $previousSlot, 'subject' => $subject];
        $template = $settings->slotChangedTemplate;

        try {
            $view = Craft::$app->getView();
            $html = $template
                ? $view->renderTemplate($template, $variables, View::TEMPLATE_MODE_SITE)
                : $view->renderTemplate('delivery-slots/_emails/slot-changed', $variables, View::TEMPLATE_MODE_CP);

            return Craft::$app->getMailer()->compose()
                ->setTo($email)
                ->setSubject($subject)
                ->setHtmlBody($html)
                ->send();
        } catch (Throwable $e) {
            Craft::error('Couldn’t email the new delivery time for order ' . $order->id . ': ' . $e->getMessage(), 'delivery-slots');

            return false;
        }
    }
}
