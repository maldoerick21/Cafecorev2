<?php
class ReportController extends Controller
{
    public function index(): void
    {
        require_staff();
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $filters = $this->filters();
        $report = Order::report($filters);
        $this->view('reports/index', [
            'title' => 'Reports',
            'filters' => $filters,
            'report' => $report,
        ], 'admin');
    }

    public function export(): void
    {
        require_staff();
        $filters = $this->filters();
        $report = Order::report($filters);
        $summary = $report['summary'];
        $categories = Order::reportCategorySales($filters);
        $columns = 10;
        $rows = [];
        $merges = [];
        $rowHeights = [1 => 30];

        $titleRow = array_fill(0, $columns, ExcelExport::styled('', 4));
        $titleRow[0] = ExcelExport::styled('CaféCore Sales Report', 4);
        $rows[] = $titleRow;
        $merges[] = 'A1:J1';

        $rows[] = [
            ExcelExport::styled('Reporting period', 1),
            ExcelExport::styled($filters['from'], 6),
            ExcelExport::styled('through', 1),
            ExcelExport::styled($filters['to'], 6),
            ExcelExport::styled('Order status', 1),
            ExcelExport::styled('Completed', 6),
            ExcelExport::styled('Payment status', 1),
            ExcelExport::styled('Paid', 6),
            ExcelExport::styled('Generated at', 1),
            ExcelExport::styled(date('Y-m-d H:i:s'), 6),
        ];
        $rows[] = [];

        $rows[] = $this->mergedSectionRow('Sales summary', $columns);
        $merges[] = 'A4:J4';
        $rows[] = $this->headerRow(['Period', 'Completed orders', 'Order value', 'Payments collected']);
        $rows[] = [
            ExcelExport::styled($this->periodLabel($filters['period']), 8),
            ExcelExport::styled((int) $summary['total_orders'], 9),
            ExcelExport::styled((float) $summary['order_value'], 3),
            ExcelExport::styled((float) $summary['collected_amount'], 3),
        ];
        $rows[] = array_fill(0, $columns, '');

        $breakdownTitle = match ($filters['period']) {
            'weekly' => 'Weekly sales by day',
            'monthly' => 'Monthly sales by week',
            'yearly' => 'Yearly sales by month',
            default => 'Daily sales',
        };

        $rows[] = $this->mergedSectionRow($breakdownTitle, $columns);
        $breakdownSectionRow = count($rows);
        $merges[] = 'A' . $breakdownSectionRow . ':J' . $breakdownSectionRow;
        $breakdownHeaderRow = count($rows) + 1;
        $rows[] = $this->headerRow(['Period', 'Completed orders', 'Order value', 'Payments collected']);
        foreach ($this->salesBreakdown($report['orders'], $filters) as $day) {
            $rows[] = [
                ExcelExport::styled((string) $day['date'], 6),
                ExcelExport::styled((int) $day['orders'], 7),
                ExcelExport::styled((float) $day['order_value'], 2),
                ExcelExport::styled((float) $day['collected_amount'], 2),
            ];
        }
        $breakdownLastRow = count($rows);
        $rows[] = [];
        $rows[] = [
            ExcelExport::styled('Total', 8),
            ExcelExport::styled((int) $summary['total_orders'], 9),
            ExcelExport::styled((float) $summary['order_value'], 3),
            ExcelExport::styled((float) $summary['collected_amount'], 3),
        ];
        $rows[] = array_fill(0, $columns, '');

        $orders = [$this->headerRow([
            'Order #', 'Date & time', 'Customer', 'Email', 'Order status', 'Items',
            'Payment method', 'Payment status', 'Payment amount', 'Total',
        ])];
        foreach ($report['orders'] as $order) {
            $orders[] = [
                ExcelExport::styled((int) $order['id'], 7),
                ExcelExport::styled(date('Y-m-d H:i:s', strtotime((string) $order['created_at'])), 6),
                ExcelExport::styled((string) $order['customer'], 6),
                ExcelExport::styled((string) $order['email'], 6),
                ExcelExport::styled(ucfirst((string) $order['status']), 6),
                ExcelExport::styled((string) $order['items_summary'], 10),
                ExcelExport::styled(ucfirst(str_replace('_', ' ', (string) ($order['payment_method'] ?? 'Not recorded'))), 6),
                ExcelExport::styled(ucfirst((string) ($order['payment_status'] ?? 'Not recorded')), 6),
                $order['payment_amount'] === null ? ExcelExport::styled('', 6) : ExcelExport::styled((float) $order['payment_amount'], 2),
                ExcelExport::styled((float) $order['total'], 2),
            ];
        }

        $categoryReportRows = [
            array_fill(0, 5, ExcelExport::styled('', 4)),
            [
                ExcelExport::styled('Reporting period', 1),
                ExcelExport::styled($this->periodLabel($filters['period']), 6),
                ExcelExport::styled('Date range', 1),
                ExcelExport::styled($filters['from'] . ' through ' . $filters['to'], 6),
                ExcelExport::styled('', 6),
            ],
            $this->mergedSectionRow('Sales by category', 5),
        ];
        $categoryReportRows[0][0] = ExcelExport::styled('CaféCore Category Report', 4);
        $categoryUnitsTotal = 0;
        $categorySalesTotal = 0.0;
        $categoryTotals = [];
        foreach ($categories as $item) {
            $units = (int) $item['units_sold'];
            $sales = (float) $item['order_value'];
            $categoryUnitsTotal += $units;
            $categorySalesTotal += $sales;
            $category = (string) $item['category_name'];
            $categoryTotals[$category] ??= ['units_sold' => 0, 'sales' => 0.0];
            $categoryTotals[$category]['units_sold'] += $units;
            $categoryTotals[$category]['sales'] += $sales;
        }
        $categorySummaryHeaderRow = count($categoryReportRows) + 1;
        $categoryReportRows[] = $this->headerRow(['Category', 'Units sold', 'Sales revenue', '% of total sales']);
        foreach ($categoryTotals as $category => $totals) {
            $share = $categorySalesTotal > 0 ? $totals['sales'] / $categorySalesTotal : 0;
            $categoryReportRows[] = [
                ExcelExport::styled($category, 6),
                ExcelExport::styled((int) $totals['units_sold'], 7),
                ExcelExport::styled((float) $totals['sales'], 2),
                ExcelExport::styled($share, 11),
            ];
        }
        $categorySummaryLastRow = count($categoryReportRows);
        $categoryReportRows[] = [];
        $categoryReportRows[] = [
            ExcelExport::styled('Grand total', 8),
            ExcelExport::styled($categoryUnitsTotal, 9),
            ExcelExport::styled($categorySalesTotal, 3),
                ExcelExport::styled($categorySalesTotal > 0 ? 1 : 0, 12),
            ExcelExport::styled('', 8),
        ];

        $menuReportRows = [
            array_fill(0, 5, ExcelExport::styled('', 4)),
            [
                ExcelExport::styled('Reporting period', 1),
                ExcelExport::styled($this->periodLabel($filters['period']), 6),
                ExcelExport::styled('Date range', 1),
                ExcelExport::styled($filters['from'] . ' through ' . $filters['to'], 6),
                ExcelExport::styled('', 6),
            ],
            $this->headerRow(['Category', 'Menu item', 'Units sold', 'Sales revenue', '% of total sales']),
        ];
        $menuReportRows[0][0] = ExcelExport::styled('CaféCore Menu Report', 4);
        foreach ($categories as $item) {
            $sales = (float) $item['order_value'];
            $menuReportRows[] = [
                ExcelExport::styled((string) $item['category_name'], 6),
                ExcelExport::styled((string) $item['item_name'], 6),
                ExcelExport::styled((int) $item['units_sold'], 7),
                ExcelExport::styled($sales, 2),
                ExcelExport::styled($categorySalesTotal > 0 ? $sales / $categorySalesTotal : 0, 11),
            ];
        }
        if (!$categories) {
            $menuReportRows[] = [ExcelExport::styled('No item sales for the selected filters.', 6)];
        }
        $menuReportLastRow = count($menuReportRows);
        $menuReportRows[] = [];
        $menuReportRows[] = [
            ExcelExport::styled('Grand total', 8),
            ExcelExport::styled('', 8),
            ExcelExport::styled($categoryUnitsTotal, 9),
            ExcelExport::styled($categorySalesTotal, 3),
            ExcelExport::styled($categorySalesTotal > 0 ? 1 : 0, 12),
        ];
        $filename = 'cafecore-report-' . $filters['from'] . '-to-' . $filters['to'] . '.xlsx';
        ExcelExport::download($filename, [
            'Sales Report' => [
                'rows' => $rows,
                'mergeCells' => $merges,
                'rowHeights' => $rowHeights,
                'autoFilter' => true,
                'filterRange' => 'A' . $breakdownHeaderRow . ':D' . $breakdownLastRow,
                'columnWidths' => [1 => 29, 2 => 13, 3 => 14, 4 => 14, 5 => 14, 6 => 18, 7 => 20, 8 => 19, 9 => 17, 10 => 17],
            ],
            'Category Report' => [
                'rows' => $categoryReportRows,
                'mergeCells' => ['A1:E1', 'A3:E3'],
                'rowHeights' => [1 => 30],
                'autoFilter' => true,
                'filterRange' => 'A' . $categorySummaryHeaderRow . ':D' . $categorySummaryLastRow,
                'freezeRows' => 3,
                'columnWidths' => [1 => 26, 2 => 42, 3 => 14, 4 => 18, 5 => 19],
            ],
            'Menu Report' => [
                'rows' => $menuReportRows,
                'autoFilter' => true,
                'filterRange' => 'A3:E' . $menuReportLastRow,
                'freezeRows' => 3,
                'rowHeights' => [1 => 30],
                'columnWidths' => [1 => 26, 2 => 42, 3 => 14, 4 => 18, 5 => 19],
            ],
            'Orders' => [
                'rows' => $orders,
                'autoFilter' => true,
                'freezeRows' => 1,
                'columnWidths' => [1 => 12, 2 => 21, 3 => 26, 4 => 34, 5 => 17, 6 => 52, 7 => 18, 8 => 18, 9 => 17, 10 => 16],
            ],
        ]);
    }

    private function headerRow(array $labels): array
    {
        return array_map(static fn (string $label): array => ExcelExport::styled($label, 1), $labels);
    }

    private function mergedSectionRow(string $label, int $columns): array
    {
        $row = array_fill(0, $columns, ExcelExport::styled('', 5));
        $row[0] = ExcelExport::styled($label, 5);
        return $row;
    }

    private function salesBreakdown(array $orders, array $filters): array
    {
        $from = new DateTimeImmutable($filters['from']);
        $to = new DateTimeImmutable($filters['to']);
        $period = (string) $filters['period'];
        $buckets = [];
        $dateToBucket = [];

        $addBucket = static function (string $key, string $label, DateTimeImmutable $start, DateTimeImmutable $end) use (&$buckets, &$dateToBucket): void {
            $buckets[$key] = [
                'date' => $label,
                'orders' => 0,
                'order_value' => 0.0,
                'collected_amount' => 0.0,
            ];
            for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
                $dateToBucket[$day->format('Y-m-d')] = $key;
            }
        };

        if ($period === 'yearly') {
            $yearStart = $from->setDate((int) $from->format('Y'), 1, 1);
            for ($month = $yearStart; $month <= $to; $month = $month->modify('first day of next month')) {
                $start = $month;
                $end = $month->modify('last day of this month');
                if ($end > $to) {
                    $end = $to;
                }
                $addBucket($month->format('Y-m'), $month->format('F'), $start, $end);
            }
        } elseif ($period === 'monthly') {
            $week = 1;
            for ($start = $from; $start <= $to; $start = $end->modify('+1 day'), $week++) {
                $daysUntilSunday = 7 - (int) $start->format('N');
                $end = $start->modify('+' . $daysUntilSunday . ' days');
                if ($end > $to) {
                    $end = $to;
                }
                $label = 'Week ' . $week . ' (' . $start->format('M j') . '–' . $end->format('M j') . ')';
                $addBucket('week-' . $week, $label, $start, $end);
            }
        } else {
            for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
                $key = $day->format('Y-m-d');
                $label = $period === 'weekly' ? $day->format('D, M j') : $key;
                $addBucket($key, $label, $day, $day);
            }
        }

        foreach ($orders as $order) {
            $orderDate = (new DateTimeImmutable((string) $order['created_at']))->format('Y-m-d');
            $key = $dateToBucket[$orderDate] ?? null;
            if ($key === null) {
                continue;
            }
            $buckets[$key]['orders']++;
            $buckets[$key]['order_value'] += (float) $order['total'];
            $buckets[$key]['collected_amount'] += (float) ($order['payment_amount'] ?? 0);
        }

        return array_values($buckets);
    }

    private function filters(): array
    {
        $today = Order::reportDateDefaults()['to'];
        $period = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'monthly';
        if (!in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            $period = 'monthly';
        }
        $anchor = $this->validDate($_GET['date'] ?? null, $today);
        $date = new DateTimeImmutable($anchor);
        switch ($period) {
            case 'daily':
                $from = $to = $date->format('Y-m-d');
                break;
            case 'weekly':
                $from = $date->modify('monday this week')->format('Y-m-d');
                $to = $date->modify('monday this week')->modify('+6 days')->format('Y-m-d');
                break;
            case 'yearly':
                $from = $date->setDate((int) $date->format('Y'), 1, 1)->format('Y-m-d');
                $to = $date->setDate((int) $date->format('Y'), 12, 31)->format('Y-m-d');
                break;
            default:
                $from = $date->modify('first day of this month')->format('Y-m-d');
                $to = $date->modify('last day of this month')->format('Y-m-d');
        }

        return compact('period', 'anchor', 'from', 'to');
    }

    private function validDate(mixed $value, string $fallback): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $fallback;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return $fallback;
        }
        return $date->format('Y-m-d') === $value ? $value : $fallback;
    }

    private function periodLabel(string $period): string
    {
        return ucfirst($period);
    }
}
