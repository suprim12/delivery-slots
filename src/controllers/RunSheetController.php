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
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use suprim\deliveryslots\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * The run sheet: a day's booked orders by slot, the packing list, and CSV exports.
 */
class RunSheetController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->requireCpRequest();
        $this->requirePermission(Plugin::PERMISSION_RUN_SHEET);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('delivery-slots/run-sheet/_index', $this->sheet());
    }

    public function actionPrint(): Response
    {
        return $this->renderTemplate('delivery-slots/run-sheet/_print', $this->sheet());
    }

    /**
     * The export form: a date range, a shipping method, and orders or the packing list.
     */
    public function actionExportForm(): Response
    {
        $date = $this->date('date');

        return $this->renderTemplate('delivery-slots/run-sheet/_export', [
            'from' => DateTime::createFromImmutable($date),
            'to' => DateTime::createFromImmutable($date->add(new DateInterval('P6D'))),
            'methods' => Plugin::getInstance()->runSheets->getMethods(),
            'methodId' => $this->methodId(),
        ]);
    }

    /**
     * Downloads booked orders, or what to pack, as CSV: `?from=2026-11-17&to=2026-11-23&method=1&type=packing`.
     */
    public function actionExport(): Response
    {
        $from = $this->date('from', 'date');
        $to = $this->date('to', 'date');
        if ($to < $from) {
            throw new BadRequestHttpException('The end date is before the start date.');
        }
        $runSheets = Plugin::getInstance()->runSheets;
        $rows = $runSheets->getRows($from->format('Y-m-d'), $to->format('Y-m-d'), $this->methodId());
        $packing = $this->request->getParam('type') === 'packing';
        $csv = $packing ? $runSheets->packingToCsv($runSheets->getPackingList($rows)) : $runSheets->toCsv($rows);

        $name = ($packing ? 'packing-list-' : 'deliveries-') . $from->format('Y-m-d') . ($to > $from ? '-to-' . $to->format('Y-m-d') : '');

        return $this->response->sendContentAsFile($csv, "$name.csv", ['mimeType' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sheet(): array
    {
        $date = $this->date('date');
        $ymd = $date->format('Y-m-d');
        $methodId = $this->methodId();
        $runSheets = Plugin::getInstance()->runSheets;
        $rows = $runSheets->getRows($ymd, $ymd, $methodId);
        $tab = $this->request->getQueryParam('view') === 'packing' ? 'packing' : 'orders';

        return [
            'date' => $date,
            'prev' => $date->sub(new DateInterval('P1D'))->format('Y-m-d'),
            'next' => $date->add(new DateInterval('P1D'))->format('Y-m-d'),
            'methods' => $runSheets->getMethods(),
            'methodId' => $methodId,
            'sheetView' => $tab,
            'groups' => $runSheets->groupBySlot($rows),
            'packing' => $runSheets->getPackingList($rows),
            'summary' => $methodId ? $runSheets->getSummary($ymd, $methodId) : [],
            'total' => count($rows),
            'showNote' => (bool)Plugin::getInstance()->getSettings()->noteField,
        ];
    }

    /**
     * A date from the query string (Y-m-d, or a date field's value), or today.
     */
    private function date(string $param, ?string $fallbackParam = null): DateTimeImmutable
    {
        $tz = new DateTimeZone(Craft::$app->getTimeZone());
        $value = $this->request->getParam($param) ?? ($fallbackParam ? $this->request->getParam($fallbackParam) : null);
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return new DateTimeImmutable($value, $tz);
        }
        if (is_array($value) && ($parsed = DateTimeHelper::toDateTime($value, true))) {
            return new DateTimeImmutable($parsed->format('Y-m-d'), $tz);
        }

        return Plugin::getInstance()->slots->now()->setTime(0, 0);
    }

    private function methodId(): ?int
    {
        $methodId = (int)$this->request->getParam('method') ?: null;

        return $methodId && isset(Plugin::getInstance()->runSheets->getMethods()[$methodId]) ? $methodId : null;
    }
}
