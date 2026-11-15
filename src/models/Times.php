<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\models;

use Craft;

/**
 * Formats slot times for customers.
 */
class Times
{
    /**
     * A range such as “8 – 10am”, “10am – 12pm” or “9:30 – 11am”.
     */
    public static function range(string $start, string $end): string
    {
        $differ = ((int)$start < 12) !== ((int)$end < 12);

        return self::time($start, $differ) . ' – ' . self::time($end, true);
    }

    /**
     * A time such as “8am”, “8:30am” or “12pm”, or “8” without the am/pm.
     */
    public static function time(string $time, bool $suffix = true): string
    {
        [$h, $m] = array_map('intval', explode(':', $time . ':0'));
        if (!str_starts_with(Craft::$app->language, 'en')) {
            // 24-hour clocks: “08:00”.
            return sprintf('%02d:%02d', $h, $m);
        }
        $h12 = $h % 12 === 0 ? 12 : $h % 12;
        $text = $m ? sprintf('%d:%02d', $h12, $m) : (string)$h12;

        return $suffix ? $text . ($h < 12 ? 'am' : 'pm') : $text;
    }
}
