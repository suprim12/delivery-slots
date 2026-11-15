<?php
/**
 * Delivery Slots plugin for Craft CMS
 *
 * @link      https://suprim.com.au
 * @author suprim <support@suprim.com.au> <https://suprim.com.au>
 * @copyright Copyright (c) 2026 suprim
 */

namespace suprim\deliveryslots\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use DateTimeImmutable;
use DateTimeZone;
use suprim\deliveryslots\Plugin;
use Throwable;
use yii\console\ExitCode;

/**
 * Exports booked orders, or what to pack, as CSV.
 *
 *   php craft delivery-slots/export --from=2026-11-17 --to=2026-11-23 --output=deliveries.csv
 *   php craft delivery-slots/export --from=tomorrow --type=packing
 */
class ExportController extends Controller
{
    public $defaultAction = 'index';

    /** @var string The first day: a date such as 2026-11-17, or `today` or `tomorrow`. */
    public string $from = 'today';

    /** @var string|null The last day (default: the same as --from). */
    public ?string $to = null;

    /** @var string|null A shipping method handle (default: all methods with slots). */
    public ?string $method = null;

    /** @var string `orders` (one row per order) or `packing` (each item’s total quantity by slot). */
    public string $type = 'orders';

    /** @var string|null The file to write (default: print the CSV). */
    public ?string $output = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['from', 'to', 'method', 'type', 'output']);
    }

    public function actionIndex(): int
    {
        try {
            $tz = new DateTimeZone(Craft::$app->getTimeZone());
            $from = new DateTimeImmutable($this->from, $tz);
            $to = new DateTimeImmutable($this->to ?? $this->from, $tz);
        } catch (Throwable) {
            $this->stderr("Give dates as YYYY-MM-DD, or today or tomorrow.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }
        if ($to < $from) {
            $this->stderr("--to is before --from.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }
        if (!in_array($this->type, ['orders', 'packing'], true)) {
            $this->stderr("--type must be orders or packing.\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $runSheets = Plugin::getInstance()->runSheets;
        $methodId = null;
        if ($this->method !== null) {
            foreach ($runSheets->getMethods() as $id => $method) {
                if ($method->getHandle() === $this->method) {
                    $methodId = $id;
                }
            }
            if ($methodId === null) {
                $this->stderr("No shipping method with slots has the handle {$this->method}.\n", Console::FG_RED);
                return ExitCode::USAGE;
            }
        }

        $rows = $runSheets->getRows($from->format('Y-m-d'), $to->format('Y-m-d'), $methodId);
        $csv = $this->type === 'packing' ? $runSheets->packingToCsv($runSheets->getPackingList($rows)) : $runSheets->toCsv($rows);

        if ($this->output === null) {
            $this->stdout($csv);
            return ExitCode::OK;
        }
        if (file_put_contents($this->output, $csv) === false) {
            $this->stderr("Can’t write {$this->output}.\n", Console::FG_RED);
            return ExitCode::CANTCREAT;
        }
        $this->stdout('Exported ' . count($rows) . " orders to {$this->output}.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
