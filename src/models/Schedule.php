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
use craft\base\Model;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use DateTime;
use suprim\deliveryslots\records\ScheduleRecord;

/**
 * The slots offered for one shipping method.
 */
class Schedule extends Model
{
    public ?int $id = null;
    public ?int $shippingMethodId = null;
    public bool $enabled = true;

    /**
     * Time windows, each with the weekdays it runs on (ISO, 1 = Monday) and how many orders it takes.
     *
     * @var array<int, array{start: string, end: string, days: int[], capacity: int}>
     */
    public array $windows = [];

    /** Whether customers can book a slot later today. */
    public bool $sameDay = false;

    /** Slots can be booked until this many hours before they start. */
    public int $leadHours = 2;

    /** Orders for the next day close at this time today (H:i), or null for no cut-off. */
    public ?string $cutoff = null;

    /** How many days ahead slots are shown. */
    public int $daysAhead = 7;

    /** @var array<int, array{date: string, label: string}> Dates with no slots (Y-m-d). */
    public array $closedDates = [];

    /**
     * One-off changes to a slot on a date: a different limit, or 0 to close it.
     *
     * @var array<int, array{date: string, start: string, capacity: int, label: string}>
     */
    public array $overrides = [];

    /**
     * Geo Maps zones, in order. When any are set, addresses outside all of them get no slots.
     *
     * @var array<int, array{zoneId: int, days: int[], capacity: int|null, fee: float}>
     */
    public array $zones = [];

    /**
     * Postcode zones, checked before drawn zones. They need no map. A postcode can be exact (`5067`), a range
     * (`5000-5099`) or a prefix (`SW1*`).
     *
     * @var array<int, array{key: string, name: string, postcodes: string[], days: int[], capacity: int|null, fee: float}>
     */
    public array $postcodeZones = [];

    public static function fromRecord(ScheduleRecord $record): self
    {
        $settings = is_string($record->settings) ? Json::decodeIfJson($record->settings) : $record->settings;

        $schedule = new self([
            'id' => $record->id,
            'shippingMethodId' => $record->shippingMethodId,
            'enabled' => (bool)$record->enabled,
        ]);
        $schedule->setSettings(is_array($settings) ? $settings : []);

        return $schedule;
    }

    /**
     * @return array<string, mixed> The settings saved as JSON.
     */
    public function getSettings(): array
    {
        return [
            'windows' => array_values($this->windows),
            'sameDay' => $this->sameDay,
            'leadHours' => $this->leadHours,
            'cutoff' => $this->cutoff,
            'daysAhead' => $this->daysAhead,
            'closedDates' => array_values($this->closedDates),
            'overrides' => array_values($this->overrides),
            'zones' => array_values($this->zones),
            'postcodeZones' => array_values($this->postcodeZones),
        ];
    }

    /**
     * Sets the settings from saved JSON or from the control panel form, normalising times, dates and days.
     *
     * @param array<string, mixed> $settings
     */
    public function setSettings(array $settings): void
    {
        $this->sameDay = (bool)($settings['sameDay'] ?? false);
        $this->leadHours = max(0, (int)($settings['leadHours'] ?? 2));
        $this->cutoff = self::time($settings['cutoff'] ?? null);
        $this->daysAhead = max(1, min(90, (int)($settings['daysAhead'] ?? 7)));

        $windows = [];
        foreach ((array)($settings['windows'] ?? []) as $row) {
            $start = self::time($row['start'] ?? null);
            $end = self::time($row['end'] ?? null);
            if ($start === null && $end === null) {
                continue;
            }
            $windows[] = [
                'start' => (string)$start,
                'end' => (string)$end,
                'days' => self::days($row),
                'capacity' => (int)($row['capacity'] ?? 0),
            ];
        }
        usort($windows, fn($a, $b) => strcmp($a['start'], $b['start']));
        $this->windows = $windows;

        $closed = [];
        foreach ((array)($settings['closedDates'] ?? []) as $row) {
            $date = self::date($row['date'] ?? null);
            if ($date !== null) {
                $closed[$date] = ['date' => $date, 'label' => trim((string)($row['label'] ?? ''))];
            }
        }
        ksort($closed);
        $this->closedDates = array_values($closed);

        $overrides = [];
        foreach ((array)($settings['overrides'] ?? []) as $row) {
            $date = self::date($row['date'] ?? null);
            $start = self::time($row['start'] ?? null);
            if ($date === null || $start === null) {
                continue;
            }
            $capacity = $row['capacity'] ?? 0;
            $overrides["$date@$start"] = [
                'date' => $date,
                'start' => $start,
                'capacity' => max(0, (int)($capacity === '' ? 0 : $capacity)),
                'label' => trim((string)($row['label'] ?? '')),
            ];
        }
        ksort($overrides);
        $this->overrides = array_values($overrides);

        $zones = [];
        foreach ((array)($settings['zones'] ?? []) as $row) {
            $zoneId = (int)(is_array($row['zoneId'] ?? null) ? reset($row['zoneId']) : ($row['zoneId'] ?? 0));
            if (!$zoneId) {
                continue;
            }
            $capacity = $row['capacity'] ?? null;
            $zones[] = [
                'zoneId' => $zoneId,
                'days' => self::days($row),
                'capacity' => $capacity === null || $capacity === '' ? null : max(0, (int)$capacity),
                'fee' => max(0, (float)($row['fee'] ?? 0)),
            ];
        }
        $this->zones = $zones;

        $postcodeZones = [];
        foreach ((array)($settings['postcodeZones'] ?? []) as $i => $row) {
            $postcodes = self::postcodes($row['postcodes'] ?? []);
            if (!$postcodes) {
                continue;
            }
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                $name = Craft::t('delivery-slots', 'Postcodes {list}', ['list' => implode(', ', array_slice($postcodes, 0, 3))]);
            }
            $capacity = $row['capacity'] ?? null;
            $key = 'postcode:' . (StringHelper::slugify($name) ?: (string)$i);
            $postcodeZones[] = [
                'key' => $key,
                'name' => $name,
                'postcodes' => $postcodes,
                'days' => self::days($row),
                'capacity' => $capacity === null || $capacity === '' ? null : max(0, (int)$capacity),
                'fee' => max(0, (float)($row['fee'] ?? 0)),
            ];
        }
        $this->postcodeZones = $postcodeZones;
    }

    /**
     * Postcodes from text or a list, tidied: upper case, no spaces, each once. `5000 - 5099` becomes `5000-5099`.
     *
     * @param string|string[] $value
     * @return string[]
     */
    public static function postcodes(string|array $value): array
    {
        $text = is_array($value) ? implode(',', $value) : $value;
        $text = (string)preg_replace('/\s*-\s*/', '-', strtoupper($text));
        $list = preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($list));
    }

    /**
     * Whether a postcode is in a list of exact postcodes, numeric ranges and prefixes ending in `*`.
     *
     * @param string[] $patterns
     */
    public static function postcodeMatches(string $postcode, array $patterns): bool
    {
        $postcode = strtoupper((string)preg_replace('/\s+/', '', $postcode));
        if ($postcode === '') {
            return false;
        }
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($postcode, rtrim($pattern, '*'))) {
                    return true;
                }
            } elseif (preg_match('/^(\d+)-(\d+)$/', $pattern, $m)) {
                if (ctype_digit($postcode) && strlen($postcode) === strlen($m[1]) && (int)$postcode >= (int)$m[1] && (int)$postcode <= (int)$m[2]) {
                    return true;
                }
            } elseif ($postcode === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * The windows on an ISO weekday (1 = Monday).
     *
     * @return array<int, array{start: string, end: string, days: int[], capacity: int}>
     */
    public function windowsOn(int $weekday): array
    {
        return array_values(array_filter($this->windows, fn($w) => in_array($weekday, $w['days'], true)));
    }

    /**
     * The windows on a date (Y-m-d), with that date's one-off limits applied. Slots closed for the date are left out.
     *
     * @return array<int, array{start: string, end: string, days: int[], capacity: int}>
     */
    public function windowsFor(string $date): array
    {
        $weekday = (int)(new \DateTimeImmutable($date))->format('N');
        $windows = [];
        foreach ($this->windowsOn($weekday) as $window) {
            $override = $this->override($date, $window['start']);
            if ($override !== null) {
                if ($override['capacity'] === 0) {
                    continue;
                }
                $window['capacity'] = $override['capacity'];
            }
            $windows[] = $window;
        }

        return $windows;
    }

    /**
     * @return array{date: string, start: string, capacity: int, label: string}|null
     */
    public function override(string $date, string $start): ?array
    {
        foreach ($this->overrides as $override) {
            if ($override['date'] === $date && $override['start'] === $start) {
                return $override;
            }
        }

        return null;
    }

    public function closedLabel(string $date): ?string
    {
        foreach ($this->closedDates as $closed) {
            if ($closed['date'] === $date) {
                return $closed['label'] !== '' ? $closed['label'] : Craft::t('delivery-slots', 'Closed');
            }
        }

        return null;
    }

    protected function defineRules(): array
    {
        return [
            [['windows'], 'validateWindows'],
            [['overrides'], 'validateOverrides'],
            [['leadHours', 'daysAhead'], 'integer', 'min' => 0],
        ];
    }

    public function validateOverrides(): void
    {
        $starts = array_column($this->windows, 'start');
        foreach ($this->overrides as $override) {
            if (!in_array($override['start'], $starts, true)) {
                $this->addError('overrides', Craft::t('delivery-slots', '{date}: no window starts at {time}.', ['date' => $override['date'], 'time' => $override['start']]));
            }
        }
    }

    public function validateWindows(): void
    {
        if ($this->enabled && !$this->windows) {
            $this->addError('windows', Craft::t('delivery-slots', 'Add at least one time window.'));
        }
        foreach ($this->windows as $i => $window) {
            $row = $i + 1;
            if ($window['start'] === '' || $window['end'] === '' || $window['end'] <= $window['start']) {
                $this->addError('windows', Craft::t('delivery-slots', 'Row {row}: the end time must be after the start time.', ['row' => $row]));
            }
            if ($window['capacity'] < 1) {
                $this->addError('windows', Craft::t('delivery-slots', 'Row {row}: orders per slot must be at least 1.', ['row' => $row]));
            }
            if (!$window['days']) {
                $this->addError('windows', Craft::t('delivery-slots', 'Row {row}: choose at least one day.', ['row' => $row]));
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return int[] ISO weekdays from `days` or from `d1` … `d7` checkbox columns.
     */
    private static function days(array $row): array
    {
        if (isset($row['days']) && is_array($row['days'])) {
            $days = array_map('intval', $row['days']);
        } else {
            $days = [];
            for ($d = 1; $d <= 7; $d++) {
                if (!empty($row["d$d"])) {
                    $days[] = $d;
                }
            }
        }
        $days = array_values(array_unique(array_filter($days, fn($d) => $d >= 1 && $d <= 7)));
        sort($days);

        return $days;
    }

    /**
     * A time as H:i, from `08:00`, a time field's posted array, or a DateTime.
     */
    private static function time(mixed $value): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && trim((string)($value['time'] ?? '')) === '')) {
            return null;
        }
        if (is_string($value) && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($value), $m)) {
            return sprintf('%02d:%s', $m[1], $m[2]);
        }
        $date = DateTimeHelper::toDateTime($value, true);

        return $date instanceof DateTime ? $date->format('H:i') : null;
    }

    /**
     * A date as Y-m-d, from `2026-12-25`, a date field's posted array, or a DateTime.
     */
    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && trim((string)($value['date'] ?? '')) === '')) {
            return null;
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        $date = DateTimeHelper::toDateTime($value, true);

        return $date instanceof DateTime ? $date->format('Y-m-d') : null;
    }
}
