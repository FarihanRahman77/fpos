<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use PDF;

/**
 * Business reports (Reports Center). Every report is a build*() method returning one shape:
 *
 *   cards   [[label, value, type, tone?], ...]               summary tiles
 *   columns [key => [label, type]]                           type: text|money|qty|int|pct|date|heat
 *   rows    [[key => value, ...], ...]                       or groups: [[name, rows, totals], ...]
 *   totals  [key => value]                                   footer (money/qty/int columns)
 *   notes   [string, ...]
 *
 * so one page, one PDF and one Excel writer serve all of them. Figures follow the branch picked in the
 * top bar (BranchController::scope) unless a report says otherwise. Net sales are always without VAT.
 */
class BusinessReportController extends Controller
{
    use VoucherHelperTrait;

    /** key => [title, group, icon, description, filters] */
    public const REPORTS = [
        // ---- Daily control ----
        'sales'             => ['Sales Report', 'Sales', 'bi-graph-up-arrow', 'What sold, to whom and by whom — by day, month, product, category, brand, customer, sales person, payment method or sale type.', ['range', 'group_by']],
        'cash-closing'      => ['Daily Cash Closing (Z-report)', 'Sales', 'bi-safe2', 'Cash in and out for one day, with the opening (yesterday’s closing) and the closing amount.', ['date']],
        'product-profit'    => ['Product Profit', 'Sales', 'bi-pie-chart', 'Real margin per product, category or invoice: net sales minus cost, after discounts and returns.', ['range', 'group_by', 'category']],
        'discounts'         => ['Discount Report', 'Sales', 'bi-tags', 'Line, invoice and loyalty discounts given — per invoice, cashier, customer or day.', ['range', 'group_by']],
        'returns'           => ['Returns Report', 'Sales', 'bi-arrow-return-left', 'Sales returns and purchase returns by product, party or month, with return rate.', ['range', 'return_type', 'group_by']],
        'sales-heatmap'     => ['Sales by Hour & Weekday', 'Sales', 'bi-grid-3x3', 'When sales happen: invoices and amount per weekday and hour, for staffing.', ['range', 'measure']],
        'top-customers'     => ['Top Customers', 'Customers', 'bi-trophy', 'Best customers by sales, with profit, visits, average invoice, last purchase, due and points.', ['range', 'limit']],
        'receivable-aging'  => ['Receivable Aging', 'Customers', 'bi-hourglass-split', 'What each customer owes, split by how old it is (0–30, 31–60, 61–90, 90+ days).', ['as_at']],
        'quotation-conversion' => ['Quotation Conversion', 'Customers', 'bi-funnel', 'Quotations sent vs accepted, rejected and expired — count, value and conversion rate.', ['range', 'group_by']],
        'order-fulfilment'  => ['Sale Order Fulfilment', 'Customers', 'bi-truck', 'Every sale order: ordered vs delivered quantity, delivery date and how late.', ['range', 'order_status']],
        'loyalty'           => ['Loyalty Points', 'Customers', 'bi-stars', 'Points earned, redeemed and outstanding per customer, and their money value (a liability).', ['range']],
        // ---- Stock & purchases ----
        'purchases'         => ['Purchase Report', 'Purchases & Stock', 'bi-bag', 'What you bought and from whom — by day, month, supplier, product, category or brand.', ['range', 'group_by']],
        'payable-aging'     => ['Payable Aging', 'Purchases & Stock', 'bi-hourglass-bottom', 'What you owe each supplier, split by how old it is.', ['as_at']],
        'stock-valuation'   => ['Stock Valuation', 'Purchases & Stock', 'bi-boxes', 'Stock on hand at cost and at sale price, by category, for the branch.', ['category', 'brand', 'hide_zero']],
        'reorder'           => ['Reorder List', 'Purchases & Stock', 'bi-cart-plus', 'Products at or below their alert level, with sales speed, days of cover and a suggested order quantity.', ['cover_days', 'category']],
        'dead-stock'        => ['Slow & Dead Stock', 'Purchases & Stock', 'bi-hourglass', 'Stock that has not sold for a while — money tied up and when it last moved.', ['days', 'category']],
        // ---- Money ----
        'cash-flow'         => ['Cash Flow Statement', 'Money', 'bi-water', 'Cash in and out of the business for the period by activity, from opening to closing cash.', ['range']],
        'expenses'          => ['Expense Report', 'Money', 'bi-receipt', 'Where operating money goes — by expense head, month, branch or employee (expenses, bills and salary).', ['range', 'group_by']],
        'budget'            => ['Budget vs Actual', 'Money', 'bi-clipboard-data', 'Monthly budget per expense head against what was spent, with variance.', ['month']],
        // ---- People ----
        'payroll-summary'   => ['Payroll Summary', 'People', 'bi-people', 'Processed salary by month and department: gross, bonus, deductions, PF, loans, net, paid and due.', ['range']],
        'attendance-summary' => ['Leave & Attendance Summary', 'People', 'bi-calendar-check', 'Present, late, half day, absent, leave and overtime by department or employee.', ['range', 'group_by']],
    ];

    /** Group-by choices per report. */
    public const GROUP_BY = [
        'sales'                => ['day' => 'Day', 'month' => 'Month', 'product' => 'Product', 'category' => 'Category', 'brand' => 'Brand', 'customer' => 'Customer',
            'user' => 'Sales person', 'payment' => 'Payment method', 'type' => 'Sale type', 'invoice' => 'Invoice'],
        'product-profit'       => ['product' => 'Product', 'category' => 'Category', 'brand' => 'Brand', 'invoice' => 'Invoice'],
        'discounts'            => ['invoice' => 'Invoice', 'user' => 'Cashier', 'customer' => 'Customer', 'day' => 'Day'],
        'returns'              => ['product' => 'Product', 'party' => 'Customer / supplier', 'month' => 'Month', 'return' => 'Return voucher'],
        'quotation-conversion' => ['month' => 'Month', 'customer' => 'Customer', 'user' => 'Prepared by'],
        'purchases'            => ['day' => 'Day', 'month' => 'Month', 'supplier' => 'Supplier', 'product' => 'Product', 'category' => 'Category', 'brand' => 'Brand', 'invoice' => 'Invoice'],
        'expenses'             => ['head' => 'Expense head', 'month' => 'Month', 'employee' => 'Employee', 'source' => 'Source'],
        'attendance-summary'   => ['department' => 'Department', 'employee' => 'Employee'],
    ];

    /* ================================================================== */
    /*  Pages                                                             */
    /* ================================================================== */

    public function index()
    {
        $groups = [];
        foreach (self::REPORTS as $key => [$title, $group, $icon, $desc]) {
            $groups[$group][] = compact('key', 'title', 'icon', 'desc');
        }

        return view('admin.business-reports.index', ['groups' => $groups]);
    }

    public function show(string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        [$title, $group, $icon, $desc, $filters] = self::REPORTS[$report];

        return view('admin.business-reports.show', [
            'key'        => $report,
            'title'      => $title,
            'icon'       => $icon,
            'desc'       => $desc,
            'filters'    => $filters,
            'groupBy'    => self::GROUP_BY[$report] ?? [],
            'categories' => DB::table('categories')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']),
            'brands'     => DB::table('brands')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']),
            'branch'     => BranchController::name(BranchController::current()),
        ]);
    }

    public function data(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        try {
            $r = $this->build($report, $request);

            return response()->json([
                'status' => true,
                'html'   => view(isset($r['extra']['cash_day']) ? 'admin.business-reports.partials.cash-day' : 'admin.business-reports.partials.table', $r)->render(),
                'cards'  => view('admin.business-reports.partials.cards', $r)->render(),
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Business report error', ['report' => $report] + $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to build the report.'], 500);
        }
    }

    public function pdf(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $r = $this->build($report, $request);
        $wide = count($r['columns']) > 8;
        $pdf = PDF::loadView('admin.business-reports.pdf', $r + ['printedAt' => now()->format('d M Y, h:i A'), 'printedBy' => Auth::user()->name ?? ''])
            ->setPaper('a4', $wide ? 'landscape' : 'portrait');

        return $request->boolean('download') ? $pdf->download($r['file'] . '.pdf') : $pdf->stream($r['file'] . '.pdf');
    }

    public function excel(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $r = $this->build($report, $request);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $r['title']), 0, 30));
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $keys = array_keys($r['columns']);
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($keys));

        $sheet->setCellValue('A1', $r['company'])->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', $r['title'])->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));
        $row = 3;
        foreach ($r['filters_text'] as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ': ' . $value)->mergeCells("A{$row}:{$last}{$row}");
            $row++;
        }
        foreach ($r['cards'] as $card) {
            $sheet->setCellValue("A{$row}", $card[0]);
            $sheet->setCellValue("B{$row}", is_numeric($card[1]) ? (float) $card[1] : $card[1]);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $row++;

        $head = ['font' => ['bold' => true, 'color' => ['rgb' => self::xl('on_brand')]], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('brand')]]];
        $soft = ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('soft')]]];
        $first = $row;
        foreach ($keys as $i => $k) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$row}", $r['columns'][$k][0]);
            $sheet->getColumnDimension($col)->setWidth(in_array($r['columns'][$k][1], ['text', 'date'], true) ? ($i === 0 ? 30 : 18) : 14);
        }
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray($head);
        $row++;

        $write = function (array $values, ?array $style = null) use (&$row, $sheet, $keys, $r, $last) {
            foreach ($keys as $i => $k) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $v = $values[$k] ?? null;
                $type = $r['columns'][$k][1];
                if ($v !== null && $v !== '' && in_array($type, ['money', 'qty', 'int', 'pct', 'heat'], true)) {
                    $sheet->setCellValue("{$col}{$row}", (float) $v);
                    $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($type === 'money' ? '#,##0.00;-#,##0.00' : ($type === 'pct' ? '0.0"%"' : '#,##0.###'));
                } else {
                    $sheet->setCellValueExplicit("{$col}{$row}", (string) ($v ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            if ($style) {
                $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray($style);
            }
            $row++;
        };

        if (!empty($r['groups'])) {
            foreach ($r['groups'] as $g) {
                $sheet->setCellValue("A{$row}", $g['name'])->mergeCells("A{$row}:{$last}{$row}");
                $sheet->getStyle("A{$row}")->applyFromArray($soft);
                $row++;
                foreach ($g['rows'] as $line) {
                    $write($line);
                }
                if (!empty($g['totals'])) {
                    $write([$keys[0] => 'Subtotal'] + $g['totals'], $soft);
                }
            }
        } else {
            foreach ($r['rows'] as $line) {
                $write($line);
            }
        }
        if (!empty($r['totals'])) {
            $write([$keys[0] => 'Total'] + $r['totals'], $soft);
        }
        $sheet->getStyle("A{$first}:{$last}" . ($row - 1))->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::xl('border_strong')]]]]);
        $sheet->freezePane('A' . ($first + 1));
        foreach ($r['notes'] as $note) {
            $row++;
            $sheet->setCellValue("A{$row}", $note)->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::xl('muted'));
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $r['file'] . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /* ================================================================== */
    /*  Cash closing & budgets (the two reports you can write to)         */
    /* ================================================================== */

    /** Save the day's cash report: its opening (= the previous closing), today's net cash and the closing amount. */
    public function saveClosing(Request $request)
    {
        $data = $request->validate([
            'closing_date' => ['required', 'date', 'before_or_equal:today'],
            'remarks'      => ['nullable', 'string', 'max:255'],
        ], ['closing_date.before_or_equal' => 'A day can be closed only once it has started — not a future date.']);
        $day = Carbon::parse($data['closing_date'])->toDateString();

        $closing = DB::transaction(function () use ($day, $data) {
            $figures = $this->storeCashDay($day, ['remarks' => $this->nullIfBlank($data['remarks'] ?? null), 'saved_by' => Auth::id(), 'saved_at' => now()]);
            $this->reflowClosings($day);

            return $figures['closing'];
        });

        return response()->json(['status' => true, 'message' => 'Report saved for ' . Carbon::parse($day)->format('d M Y') . '. Closing amount '
            . number_format($closing, 2) . ' is the opening for the next day.']);
    }

    /** Remove a saved day; the days after it then open from the closing before it. */
    public function clearClosing(Request $request)
    {
        $data = $request->validate(['closing_date' => ['required', 'date']]);
        $day = Carbon::parse($data['closing_date'])->toDateString();

        $deleted = DB::transaction(function () use ($day) {
            $n = BranchController::scope(DB::table('cash_day_closings'), 'branch_id')->where('closing_date', $day)->delete();
            $this->reflowClosings($day);

            return $n;
        });

        return response()->json($deleted
            ? ['status' => true, 'message' => 'The saved report for ' . Carbon::parse($day)->format('d M Y') . ' was removed.']
            : ['status' => false, 'message' => 'There is no saved report for that day.'], $deleted ? 200 : 404);
    }

    /** Write (insert or update) a day's figures, worked out on the server, and return them. */
    private function storeCashDay(string $day, array $extra = []): array
    {
        $fig = $this->cashFigures($day);
        $values = ['opening_balance' => $fig['opening'], 'today_amount' => $fig['today'], 'closing_amount' => $fig['closing'],
            'details' => json_encode(['cash' => $fig['cash'], 'others' => $fig['others']]), 'updated_at' => now()] + $extra;

        $q = fn () => BranchController::scope(DB::table('cash_day_closings'), 'branch_id')->where('closing_date', $day);
        $q()->exists()
            ? $q()->update($values)
            : DB::table('cash_day_closings')->insert(['closing_date' => $day, 'created_at' => now()] + $values);

        return $fig;
    }

    /** Today's closing is tomorrow's opening: after a change, recompute every later saved day in date order. */
    private function reflowClosings(string $day): void
    {
        $later = BranchController::scope(DB::table('cash_day_closings'), 'branch_id')->where('closing_date', '>', $day)
            ->orderBy('closing_date')->pluck('closing_date');
        foreach ($later as $d) {
            $this->storeCashDay(Carbon::parse($d)->toDateString());
        }
    }

    public function saveBudget(Request $request)
    {
        $data = $request->validate([
            'month'     => ['required', 'date_format:Y-m'],
            'budgets'   => ['required', 'array'],
            'budgets.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $month = Carbon::createFromFormat('!Y-m', $data['month'])->toDateString();
        $branch = BranchController::activeId();
        foreach ($data['budgets'] as $accountId => $amount) {
            DB::table('budgets')->updateOrInsert(
                ['branch_id' => $branch, 'month' => $month, 'account_id' => (int) $accountId],
                ['amount' => round((float) $amount, 2), 'updated_by' => Auth::id(), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return response()->json(['status' => true, 'message' => 'Budget saved for ' . Carbon::parse($month)->format('F Y') . ' (' . BranchController::name($branch) . ').']);
    }

    /* ================================================================== */
    /*  Shared                                                            */
    /* ================================================================== */

    private function build(string $report, Request $request): array
    {
        $f = $this->filters($request);
        $method = 'build' . str_replace(' ', '', ucwords(str_replace('-', ' ', $report)));
        $r = $this->$method($f);

        $company = DB::table('settings')->value('company_name') ?: config('app.name');
        $text = [];
        if (isset($f['from']) && in_array('range', self::REPORTS[$report][4], true)) {
            $text['Period'] = $f['from']->format('d M Y') . ' – ' . $f['to']->format('d M Y');
        }
        $text['Branch'] = $r['branch_text'] ?? BranchController::name(BranchController::current());
        if (!empty($f['group_by']) && isset(self::GROUP_BY[$report][$f['group_by']])) {
            $text['Grouped by'] = self::GROUP_BY[$report][$f['group_by']];
        }

        return $r + [
            'key'          => $report,
            'title'        => self::REPORTS[$report][0],
            'company'      => $company,
            'filters_text' => array_merge($text, $r['filters_extra'] ?? []),
            'cards'        => [],
            'rows'         => [],
            'groups'       => [],
            'totals'       => [],
            'notes'        => [],
            'extra'        => [],
            'file'         => str_replace(' ', '-', self::REPORTS[$report][0]) . '-' . now()->format('Ymd'),
        ];
    }

    private function filters(Request $r): array
    {
        $date = fn ($k, $d) => $r->filled($k) && strtotime($r->query($k)) ? Carbon::parse($r->query($k)) : $d;
        $from = $date('from', now()->startOfMonth())->startOfDay();
        $to = $date('to', now())->endOfDay();
        abort_if($to->lt($from), 422, 'The end date cannot be before the start date.');

        return [
            'from'        => $from,
            'to'          => $to,
            'date'        => $date('date', now())->startOfDay(),
            'as_at'       => $date('as_at', now())->endOfDay(),
            'month'       => preg_match('/^\d{4}-\d{2}$/', (string) $r->query('month')) ? Carbon::createFromFormat('!Y-m', $r->query('month')) : now()->startOfMonth(),
            'group_by'    => (string) $r->query('group_by', ''),
            'category_id' => $r->filled('category_id') ? (int) $r->query('category_id') : null,
            'brand_id'    => $r->filled('brand_id') ? (int) $r->query('brand_id') : null,
            'hide_zero'   => $r->query('hide_zero', '1') === '1',
            'days'        => max(1, min(3650, (int) $r->query('days', 90))),
            'cover_days'  => max(1, min(365, (int) $r->query('cover_days', 30))),
            'limit'       => max(5, min(500, (int) $r->query('limit', 50))),
            'return_type' => $r->query('return_type') === 'purchase' ? 'purchase' : 'sale',
            'measure'     => $r->query('measure') === 'count' ? 'count' : 'amount',
            'order_status' => (string) $r->query('order_status', ''),
        ];
    }

    private function group(array $f, string $report, string $default): string
    {
        return isset(self::GROUP_BY[$report][$f['group_by']]) ? $f['group_by'] : $default;
    }

    /** Display format of a report value by column type (screen and PDF). */
    public static function fmt($v, string $type): string
    {
        if ($v === null || $v === '') {
            return $type === 'text' || $type === 'date' ? '-' : '';
        }

        return match ($type) {
            'money' => number_format((float) $v, 2),
            'qty'   => rtrim(rtrim(number_format((float) $v, 3), '0'), '.'),
            'int', 'heat' => is_numeric($v) ? number_format((float) $v, (float) $v == (int) $v ? 0 : 2) : (string) $v,
            'pct'   => number_format((float) $v, 1) . '%',
            'date'  => Carbon::parse($v)->format('d M Y'),
            default => (string) $v,
        };
    }

    private static function xl(string $key): string
    {
        return ltrim(SettingController::pdfColors()[$key] ?? '#000000', '#');
    }

    private static function pct(float $part, float $whole): ?float
    {
        return abs($whole) > 0.004 ? round($part / $whole * 100, 1) : null;
    }

    /** Sum the given keys over rows. */
    private static function sum(array $rows, array $keys): array
    {
        $t = array_fill_keys($keys, 0.0);
        foreach ($rows as $row) {
            foreach ($keys as $k) {
                $t[$k] += (float) ($row[$k] ?? 0);
            }
        }

        return array_map(fn ($v) => round($v, 3), $t);
    }

    /** Payment-method name of a sale (stored as a ledger id or as text). */
    private static function methodNames(): array
    {
        return DB::table('chart_of_accounts')->where('account_type', 'Liquid Asset')->pluck('account_name', 'id')->all();
    }

    private function salesQuery(array $f)
    {
        return BranchController::scope(DB::table('sales as s'), 's.branch_id')
            ->where('s.deleted', 'No')->where('s.status', 'Active')
            ->whereBetween('s.sale_date', [$f['from']->toDateString(), $f['to']->toDateString()]);
    }

    /**
     * Sale lines with their share of the invoice: net (after line + invoice + loyalty discounts, without VAT),
     * discount and cost. The invoice discount is spread over lines by line total.
     */
    private function saleLines(array $f): Collection
    {
        $lines = DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'si.product_variant_id')
            ->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
            ->where('si.deleted', 'No')->where('s.deleted', 'No')->where('s.status', 'Active')
            ->whereBetween('s.sale_date', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->when($f['category_id'], fn ($q) => $q->where('p.category_id', $f['category_id']))
            ->when($f['brand_id'], fn ($q) => $q->where('p.brand_id', $f['brand_id']));
        $lines = BranchController::scope($lines, 's.branch_id')->get([
            'si.id', 'si.sale_id', 'si.product_id', 'si.qty', 'si.subtotal', 'si.total', 'si.discount_amount', 'si.purchase_price', 'si.vat_amount',
            's.invoice_no', 's.sale_date', 's.subtotal as s_subtotal', 's.discount_amount as s_discount', 's.loyalty_discount',
            'p.name as product', 'a.name as variant', 'c.name as category', 'b.name as brand',
        ]);

        return $lines->map(function ($l) {
            $sub = (float) $l->s_subtotal;
            $share = $sub > 0 ? (float) $l->total / $sub : 0;
            $invoiceDisc = ((float) $l->s_discount + (float) $l->loyalty_discount) * $share;
            $l->gross = round((float) $l->subtotal, 2);
            $l->discount = round((float) $l->discount_amount + $invoiceDisc, 2);
            $l->net = round((float) $l->total - $invoiceDisc, 2);
            $l->cost = round((float) $l->qty * (float) $l->purchase_price, 2);
            $l->vat = (float) $l->vat_amount;

            return $l;
        });
    }

    /* ================================================================== */
    /*  1. Sales                                                          */
    /* ================================================================== */

    private function buildSales(array $f): array
    {
        $by = $this->group($f, 'sales', 'day');
        $methods = self::methodNames();

        $columns = ['label' => [self::GROUP_BY['sales'][$by], 'text'], 'invoices' => ['Invoices', 'int'], 'qty' => ['Qty', 'qty'],
            'gross' => ['Gross', 'money'], 'discount' => ['Discounts', 'money'], 'net' => ['Net Sales', 'money'], 'vat' => ['VAT', 'money'],
            'cost' => ['Cost', 'money'], 'profit' => ['Gross Profit', 'money'], 'margin' => ['Margin %', 'pct']];

        if (in_array($by, ['product', 'category', 'brand'], true)) {
            $lines = $this->saleLines($f);
            $label = fn ($l) => match ($by) {
                'product'  => $l->product . ($l->variant ? ' (' . $l->variant . ')' : ''),
                'category' => $l->category ?: 'No category',
                default    => $l->brand ?: 'No brand',
            };
            $rows = $lines->groupBy($label)->map(function ($g, $name) {
                return ['label' => $name, 'invoices' => $g->pluck('sale_id')->unique()->count(), 'qty' => $g->sum('qty'),
                    'gross' => $g->sum('gross'), 'discount' => $g->sum('discount'), 'net' => $g->sum('net'), 'vat' => $g->sum('vat'), 'cost' => $g->sum('cost')];
            });
        } else {
            $sales = $this->salesQuery($f)->leftJoin('parties as p', 'p.id', '=', 's.customer_id')->leftJoin('users as u', 'u.id', '=', 's.created_by')
                ->leftJoin('branches as br', 'br.id', '=', 's.branch_id')
                ->get(['s.*', 'p.name as customer', 'u.name as user', 'br.name as branch']);
            $items = DB::table('sale_items')->whereIn('sale_id', $sales->pluck('id')->all() ?: [0])->where('deleted', 'No')
                ->groupBy('sale_id')->selectRaw('sale_id, SUM(qty) qty, SUM(subtotal) gross, SUM(discount_amount) line_disc, SUM(qty * purchase_price) cost')->get()->keyBy('sale_id');
            $label = fn ($s) => match ($by) {
                'month'    => Carbon::parse($s->sale_date)->format('Y-m'),
                'customer' => $s->customer ?: 'Walk-In Customer',
                'user'     => $s->user ?: 'Unknown',
                'payment'  => (float) $s->due_amount >= (float) $s->grand_total - 0.004 ? 'Credit (due)' : ($methods[$s->payment_method] ?? ($s->payment_method ?: 'Not set')),
                'type'     => $s->sale_type,
                'invoice'  => $s->invoice_no,
                default    => $s->sale_date,
            };
            $rows = $sales->groupBy($label)->map(function ($g, $name) use ($items) {
                $sum = fn ($k) => $g->sum(fn ($s) => (float) ($items[$s->id]->$k ?? 0));
                $vat = $g->sum('vat_amount');
                $excl = $g->sum(fn ($s) => $s->vat_inclusive === 'Yes' ? 0 : (float) $s->vat_amount);

                return ['label' => $name, 'invoices' => $g->count(), 'qty' => $sum('qty'), 'gross' => $sum('gross'),
                    'discount' => $sum('line_disc') + $g->sum('discount_amount') + $g->sum('loyalty_discount'),
                    'net' => $g->sum('grand_total') - $excl - $g->sum(fn ($s) => $s->vat_inclusive === 'Yes' ? (float) $s->vat_amount : 0),
                    'vat' => $vat, 'cost' => $sum('cost')];
            });
        }

        $rows = $rows->map(fn ($r) => $r + ['profit' => round($r['net'] - $r['cost'], 2), 'margin' => self::pct($r['net'] - $r['cost'], $r['net'])])
            ->sortBy(in_array($by, ['day', 'month', 'invoice'], true) ? 'label' : fn ($r) => -$r['net'])->values()->all();
        $t = self::sum($rows, ['invoices', 'qty', 'gross', 'discount', 'net', 'vat', 'cost', 'profit']);
        if (in_array($by, ['product', 'category', 'brand'], true)) {
            $t['invoices'] = null; // an invoice can hold several products
        }
        $t['margin'] = self::pct($t['profit'], $t['net']);

        return [
            'columns' => $columns, 'rows' => $rows, 'totals' => $t,
            'cards' => [['Net sales', $t['net'], 'money', 'accent'], ['Gross profit', $t['profit'], 'money', 'success'],
                ['Margin', $t['margin'], 'pct'], ['Discounts given', $t['discount'], 'money', 'warning']],
            'notes' => ['Net sales exclude VAT and are after line, invoice and loyalty discounts. Cost = quantity × purchase price at the time of sale. Returns are not deducted here — see Product Profit or Returns.'],
        ];
    }

    /* ================================================================== */
    /*  2. Daily cash closing                                             */
    /* ================================================================== */

    private function buildCashClosing(array $f): array
    {
        $day = $f['date']->toDateString();
        $fig = $this->cashFigures($day);
        $saved = BranchController::scope(DB::table('cash_day_closings as c'), 'c.branch_id')->leftJoin('users as u', 'u.id', '=', 'c.saved_by')
            ->where('c.closing_date', $day)->first(['c.*', 'u.name as saved_by_name']);
        // Something posted after the day was saved: the saved figures no longer match the books.
        $changed = $saved && (abs((float) $saved->today_amount - $fig['today']) > 0.004 || abs((float) $saved->opening_balance - $fig['opening']) > 0.004);

        $toRows = fn ($lines) => array_map(fn ($l) => ['label' => $l['label'], 'in' => $l['sign'] > 0 ? $l['amount'] : null,
            'out' => $l['sign'] < 0 ? $l['amount'] : null], $lines);
        $groups = [['name' => 'Cash ledger', 'rows' => $toRows($fig['cash']), 'totals' => $this->inOut($fig['cash'])]];
        foreach ($fig['others'] as $method => $lines) {
            $groups[] = ['name' => $method, 'rows' => $toRows($lines), 'totals' => $this->inOut($lines)];
        }

        $openNote = $fig['opening_from']
            ? 'closing of ' . Carbon::parse($fig['opening_from'])->format('d M Y') . ($fig['gap_days'] ? ' + ' . $fig['gap_days'] . ' unsaved day(s)' : '')
            : 'cash in the books (no saved day yet)';

        return [
            'columns' => ['label' => ['Type', 'text'], 'in' => ['In (+)', 'money'], 'out' => ['Out (−)', 'money']],
            'groups' => $groups, 'rows' => [], 'totals' => [],
            'filters_extra' => ['Day' => $f['date']->format('l, d M Y'), 'Opening balance' => number_format($fig['opening'], 2) . ' (' . $openNote . ')',
                'Total amount (today)' => number_format($fig['today'], 2), 'Closing amount' => number_format($fig['closing'], 2)],
            'cards' => [
                ['Opening balance', $fig['opening'], 'money', 'accent'],
                ['Total amount (today)', $fig['today'], 'money', $fig['today'] < 0 ? 'danger' : 'success'],
                ['Closing amount', $fig['closing'], 'money', 'accent'],
                ['Status', $saved ? ($changed ? 'Saved · changed since' : 'Saved') : 'Not saved', 'text', $saved && !$changed ? 'success' : 'warning'],
            ],
            'extra' => ['cash_day' => $fig + ['date' => $day, 'open_note' => $openNote, 'changed' => $changed,
                'saved' => $saved ? ['at' => Carbon::parse($saved->saved_at)->format('d M Y, h:i A'), 'by' => $saved->saved_by_name, 'remarks' => $saved->remarks,
                    'closing' => (float) $saved->closing_amount] : null]],
            'notes' => [
                'Opening balance is the closing amount of the last saved day — yesterday\'s closing is today\'s opening. If days in between were not saved, their net cash is added. Before the first saved day it is the cash in the books.',
                'Total amount (today) = everything into cash − everything out of cash on the day (all Cash ledgers of this branch). Closing amount = opening + today, and becomes the next day\'s opening once saved.',
                'Bank and other methods are shown for reference; they do not change the cash closing. Transfers between cash and another method appear on both sides.',
            ],
        ];
    }

    private function inOut(array $lines): array
    {
        return ['in' => round(array_sum(array_map(fn ($l) => $l['sign'] > 0 ? $l['amount'] : 0, $lines)), 2),
            'out' => round(array_sum(array_map(fn ($l) => $l['sign'] < 0 ? $l['amount'] : 0, $lines)), 2)];
    }

    /**
     * A day's cash closing for the logged-in branch: the cash ledger lines, the other methods' lines, the opening
     * (last saved closing + unsaved days in between, or the books before the first save), today's net and closing.
     */
    private function cashFigures(string $day): array
    {
        [$rc, $ctx, $accounts] = $this->cashEngine();
        $methodOf = fn ($key) => $accounts[$key]['method'] ?? (substr($key, 2) ?: 'Unspecified');
        $isCash = fn ($method) => stripos($method, 'cash') !== false;
        $moves = $rc->liquidAssetMovements($ctx, Carbon::parse($day)->startOfDay(), Carbon::parse($day)->endOfDay());

        // Fixed lines (shown even at zero), then whatever else moved.
        $base = ['Sales Collection (+)' => 1, 'Payment Received (+)' => 1, 'Payment (−)' => -1, 'Expense (−)' => -1, 'Bill (−)' => -1, 'Salary (−)' => -1];
        $labelOf = fn ($m) => match (true) {
            str_starts_with($m->type, 'Sale Receive')                          => ['Sales Collection (+)', 1],
            $m->type === 'Payment Receive'                                   => ['Payment Received (+)', 1],
            $m->type === 'Purchase Return Refund'                            => ['Supplier Refund (+)', 1],
            $m->type === 'Loan Repayment'                                    => ['Loan Repayment (+)', 1],
            in_array($m->type, ['Payment to Supplier', 'Purchase Payment'], true) => ['Payment (−)', -1],
            $m->type === 'Expense'                                           => ['Expense (−)', -1],
            $m->type === 'Bill Payment'                                      => ['Bill (−)', -1],
            $m->type === 'Salary'                                            => ['Salary (−)', -1],
            $m->type === 'Loan / Advance Paid'                               => ['Loan / Advance Paid (−)', -1],
            default                                                          => $m->in > 0 ? ['Journal In (+)', 1] : ['Journal Out (−)', -1],
        };

        $buckets = [];   // method ('Cash' for every cash ledger) => label => [sign, amount]
        $add = function ($method, $label, $sign, $amount) use (&$buckets) {
            $buckets[$method][$label] ??= ['sign' => $sign, 'amount' => 0.0];
            $buckets[$method][$label]['amount'] += $amount;
        };
        foreach ($moves as $m) {
            if ($m->type === 'Amount Transfer') {
                continue;   // read below with both sides, so the line can say where the money went
            }
            [$label, $sign] = $labelOf($m);
            $method = $isCash($methodOf($m->key)) ? 'Cash' : $methodOf($m->key);
            $add($method, $label, $sign, $sign > 0 ? $m->in - $m->out : $m->out - $m->in);
        }

        $transfers = BranchController::scope(DB::table('internal_amount_transfers as t'), 't.branch_id')->where('t.deleted', 'No')
            ->where('t.status', 'Active')->whereDate('t.transfer_date', $day)->get(['t.from_account_id', 't.to_account_id', 't.amount']);
        foreach ($transfers as $t) {
            $fm = $methodOf('a:' . $t->from_account_id);
            $tm = $methodOf('a:' . $t->to_account_id);
            $fromSide = $isCash($fm) ? 'Cash' : $fm;
            $toSide = $isCash($tm) ? 'Cash' : $tm;
            if ($fromSide === $toSide) {
                continue;   // between two ledgers of the same method: no change to that method
            }
            $add($fromSide, $fromSide . ' To ' . $toSide . ' (−)', -1, (float) $t->amount);
            $add($toSide, $fromSide . ' To ' . $toSide . ' (+)', 1, (float) $t->amount);
        }

        // Cash ledger: fixed lines, transfer lines for every other method, then anything else.
        $otherMethods = collect($accounts)->pluck('method')->reject($isCash)->unique()->values();
        $cashLines = $base;
        foreach ($otherMethods as $om) {
            $cashLines['Cash To ' . $om . ' (−)'] = -1;
            $cashLines[$om . ' To Cash (+)'] = 1;
        }
        $lines = function (array $fixed, array $got) {
            $out = [];
            foreach ($fixed as $label => $sign) {
                $out[] = ['label' => $label, 'sign' => $sign, 'amount' => round($got[$label]['amount'] ?? 0, 2)];
                unset($got[$label]);
            }
            foreach ($got as $label => $g) {
                $out[] = ['label' => $label, 'sign' => $g['sign'], 'amount' => round($g['amount'], 2)];
            }

            return $out;
        };
        $cash = $lines($cashLines, $buckets['Cash'] ?? []);
        $others = [];
        foreach ($otherMethods->merge(array_keys($buckets))->reject(fn ($m) => $m === 'Cash')->unique() as $om) {
            $others[$om] = $lines(['Payment (−)' => -1, 'Expense (−)' => -1, 'Payment Received (+)' => 1, 'Sales Collection (+)' => 1], $buckets[$om] ?? []);
        }

        $today = round(array_sum(array_map(fn ($l) => $l['sign'] * $l['amount'], $cash)), 2);
        $open = $this->cashOpening($day);

        return ['cash' => $cash, 'others' => $others, 'today' => $today, 'opening' => $open['amount'], 'opening_from' => $open['from'],
            'gap_days' => $open['gap_days'], 'closing' => round($open['amount'] + $today, 2),
            'books_closing' => round($this->cashInBooks(Carbon::parse($day)->addDay()->toDateString()), 2)];
    }

    /** Opening = last saved closing before the day + net cash of the unsaved days in between; the books if none saved. */
    private function cashOpening(string $day): array
    {
        $prev = BranchController::scope(DB::table('cash_day_closings'), 'branch_id')->where('closing_date', '<', $day)
            ->orderByDesc('closing_date')->first(['closing_date', 'closing_amount']);
        if (!$prev) {
            return ['amount' => round($this->cashInBooks($day), 2), 'from' => null, 'gap_days' => 0];
        }

        $from = Carbon::parse($prev->closing_date)->addDay();
        $to = Carbon::parse($day)->subDay();
        $gap = $from->lte($to) ? $this->cashNet($from->copy()->startOfDay(), $to->copy()->endOfDay()) : 0.0;

        return ['amount' => round((float) $prev->closing_amount + $gap, 2), 'from' => Carbon::parse($prev->closing_date)->toDateString(),
            'gap_days' => $from->lte($to) ? (int) $from->diffInDays($to) + 1 : 0];
    }

    /** Cash on hand in the books before a day starts: cash ledgers' opening balances + all cash movements before it. */
    private function cashInBooks(string $day): float
    {
        [, , $accounts] = $this->cashEngine();
        $opening = collect($accounts)->filter(fn ($a) => stripos($a['method'], 'cash') !== false)->sum('opening_balance');

        return (float) $opening + $this->cashNet(null, Carbon::parse($day)->startOfDay()->subSecond());
    }

    /** Net cash (in − out) over all Cash ledgers of the branch in a period ($from null = from the beginning). */
    private function cashNet(?Carbon $from, Carbon $to): float
    {
        [$rc, $ctx, $accounts] = $this->cashEngine();
        $methodOf = fn ($key) => $accounts[$key]['method'] ?? (substr($key, 2) ?: 'Unspecified');

        return (float) $rc->liquidAssetMovements($ctx, $from, $to)
            ->filter(fn ($m) => stripos($methodOf($m->key), 'cash') !== false)->sum(fn ($m) => $m->in - $m->out);
    }

    /** The Cash & Bank Book engine (ReportController) and this branch's liquid ledgers, built once per request. */
    private function cashEngine(): array
    {
        static $engine = [];
        $branch = BranchController::current() ?? 0;
        if (!isset($engine[$branch])) {
            $rc = new ReportController();
            $ctx = (object) ['filters' => []];
            $engine[$branch] = [$rc, $ctx, $rc->liquidAssetAccounts($ctx)];
        }

        return $engine[$branch];
    }

    /* ================================================================== */
    /*  3. Product profit (after returns)                                 */
    /* ================================================================== */

    private function buildProductProfit(array $f): array
    {
        $by = $this->group($f, 'product-profit', 'product');
        $lines = $this->saleLines($f);
        $label = fn ($l) => match ($by) {
            'category' => $l->category ?: 'No category',
            'brand'    => $l->brand ?: 'No brand',
            'invoice'  => $l->invoice_no,
            default    => $l->product . ($l->variant ? ' (' . $l->variant . ')' : ''),
        };

        // Returns in the same period, valued at the sold price and cost of the original line.
        $returns = DB::table('sale_return_items as ri')->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->leftJoin('sale_items as si', 'si.id', '=', 'ri.sale_item_id')->leftJoin('sales as s', 's.id', '=', 'ri.sale_id')
            ->leftJoin('products as p', 'p.id', '=', 'ri.product_id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')->leftJoin('product_variants as v', 'v.id', '=', 'ri.product_variant_id')
            ->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
            ->where('ri.deleted', 'No')->where('r.deleted', 'No')
            ->whereBetween(DB::raw('DATE(r.datetime)'), [$f['from']->toDateString(), $f['to']->toDateString()])
            ->when($f['category_id'], fn ($q) => $q->where('p.category_id', $f['category_id']));
        $returns = BranchController::scope($returns, 'r.branch_id')
            ->get(['ri.qty', 'ri.total', 'si.purchase_price', 's.invoice_no', 'p.name as product', 'a.name as variant', 'c.name as category', 'b.name as brand']);

        $ret = $returns->groupBy($label);
        $rows = $lines->groupBy($label)->map(fn ($g, $name) => ['label' => $name, 'qty' => $g->sum('qty'), 'net' => $g->sum('net'), 'cost' => $g->sum('cost')])->all();
        foreach ($ret as $name => $g) {
            $rows[$name] ??= ['label' => $name, 'qty' => 0, 'net' => 0, 'cost' => 0];
            $rows[$name]['ret_qty'] = $g->sum('qty');
            $rows[$name]['ret_value'] = $g->sum('total');
            $rows[$name]['ret_cost'] = $g->sum(fn ($r) => (float) $r->qty * (float) $r->purchase_price);
        }
        $rows = collect($rows)->map(function ($r) {
            $r += ['ret_qty' => 0, 'ret_value' => 0, 'ret_cost' => 0];
            $r['net_qty'] = $r['qty'] - $r['ret_qty'];
            $r['revenue'] = round($r['net'] - $r['ret_value'], 2);
            $r['cogs'] = round($r['cost'] - $r['ret_cost'], 2);
            $r['profit'] = round($r['revenue'] - $r['cogs'], 2);
            $r['margin'] = self::pct($r['profit'], $r['revenue']);
            $r['per_unit'] = $r['net_qty'] > 0 ? round($r['profit'] / $r['net_qty'], 2) : null;

            return $r;
        })->sortByDesc('profit')->values()->all();

        $t = self::sum($rows, ['qty', 'ret_qty', 'net_qty', 'net', 'ret_value', 'revenue', 'cogs', 'profit']);
        $t['margin'] = self::pct($t['profit'], $t['revenue']);
        $loss = collect($rows)->where('profit', '<', 0)->count();

        return [
            'columns' => ['label' => [self::GROUP_BY['product-profit'][$by], 'text'], 'qty' => ['Sold', 'qty'], 'ret_qty' => ['Returned', 'qty'],
                'net_qty' => ['Net Qty', 'qty'], 'net' => ['Sales', 'money'], 'ret_value' => ['Returns', 'money'], 'revenue' => ['Net Revenue', 'money'],
                'cogs' => ['Cost', 'money'], 'profit' => ['Profit', 'money'], 'margin' => ['Margin %', 'pct'], 'per_unit' => ['Profit / Unit', 'money']],
            'rows' => $rows, 'totals' => $t,
            'cards' => [['Net revenue', $t['revenue'], 'money', 'accent'], ['Profit', $t['profit'], 'money', 'success'], ['Margin', $t['margin'], 'pct'],
                ['Sold at a loss', $loss . ' line(s)', 'text', $loss ? 'danger' : 'success']],
            'notes' => ['Sales are net of every discount and without VAT. Returns in the period are taken off at their refund value and original cost.'],
        ];
    }

    /* ================================================================== */
    /*  4/5. Aging (receivable / payable)                                 */
    /* ================================================================== */

    /**
     * Due per party split by age. The due (opening + ledger) is matched to the newest charges first
     * (older charges are assumed paid first), and each matched part is aged from its date.
     */
    private function aging(array $f, bool $customers): array
    {
        $asAt = $f['as_at'];
        $types = $customers ? ['Customer', 'Walk-In Customer'] : ['Supplier'];
        $parties = DB::table('parties')->where('deleted', 'No')->whereIn('type', $types)->get(['id', 'name', 'phone', 'type', 'opening_balance', 'opening_balance_type', 'opening_balance_date', 'created_at']);
        $ledger = BranchController::scope(DB::table('payments'), 'branch_id')->whereIn('party_id', $parties->pluck('id')->all() ?: [0])->where('deleted', 'No')->where('status', 'Active')
            ->where(fn ($q) => $q->whereNull('party_type')->orWhere('party_type', '<>', 'Employee')) // party_id holds employee ids there
            ->where('datetime', '<=', $asAt)->orderBy('datetime')->get(['party_id', 'datetime', 'debit', 'credit', 'code', 'payment_type'])->groupBy('party_id');

        $buckets = ['b0' => [0, 30], 'b31' => [31, 60], 'b61' => [61, 90], 'b90' => [91, 100000]];
        $rows = [];
        foreach ($parties as $p) {
            $lines = $ledger[$p->id] ?? collect();
            // charges raise the due: customer = credit, supplier = debit
            $charges = $lines->map(fn ($l) => ['date' => $l->datetime, 'amount' => (float) ($customers ? $l->credit : $l->debit)])->filter(fn ($c) => $c['amount'] > 0)->values()->all();
            $settled = $lines->sum(fn ($l) => (float) ($customers ? $l->debit : $l->credit));
            $opening = BranchController::isMainBranch() ? (float) $p->opening_balance : 0.0;   // opening belongs to the main branch
            if (($p->opening_balance_type ?? 'Due') === 'Advance') {
                $opening = -$opening; // an opening advance is settled against later charges
            }
            if ($opening > 0) {
                array_unshift($charges, ['date' => $p->opening_balance_date ?: $p->created_at, 'amount' => $opening]);
            }
            $due = round(array_sum(array_column($charges, 'amount')) - $settled - ($opening < 0 ? -$opening : 0), 2);
            if ($due <= 0.004) {
                continue;
            }
            $row = ['label' => $p->name, 'phone' => $p->phone, 'b0' => 0.0, 'b31' => 0.0, 'b61' => 0.0, 'b90' => 0.0, 'oldest' => null];
            $left = $due;
            for ($i = count($charges) - 1; $i >= 0 && $left > 0.004; $i--) {
                $part = min($left, $charges[$i]['amount']);
                $age = Carbon::parse($charges[$i]['date'])->startOfDay()->diffInDays($asAt->copy()->startOfDay());
                foreach ($buckets as $k => [$lo, $hi]) {
                    if ($age >= $lo && $age <= $hi) {
                        $row[$k] += $part;
                    }
                }
                $row['oldest'] = $age;
                $left -= $part;
            }
            if ($left > 0.004) {
                $row['b90'] += $left;
            }
            $row['due'] = $due;
            $rows[] = $row;
        }
        usort($rows, fn ($a, $b) => $b['due'] <=> $a['due']);
        $t = self::sum($rows, ['b0', 'b31', 'b61', 'b90', 'due']);

        return [
            'columns' => ['label' => [$customers ? 'Customer' : 'Supplier', 'text'], 'phone' => ['Phone', 'text'], 'b0' => ['0–30 days', 'money'],
                'b31' => ['31–60 days', 'money'], 'b61' => ['61–90 days', 'money'], 'b90' => ['Over 90 days', 'money'], 'due' => ['Total Due', 'money'],
                'oldest' => ['Oldest (days)', 'int']],
            'rows' => $rows, 'totals' => $t,
            'filters_extra' => ['As at' => $asAt->format('d M Y')],
            'cards' => [[$customers ? 'Total receivable' : 'Total payable', $t['due'], 'money', 'accent'], ['0–30 days', $t['b0'], 'money', 'success'],
                ['31–90 days', $t['b31'] + $t['b61'], 'money', 'warning'], ['Over 90 days', $t['b90'], 'money', 'danger']],
            'notes' => ['Payments are matched to the oldest charges first, so what remains due is aged from the most recent invoices. Only this branch\'s transactions count; opening balances belong to the main branch.'],
        ];
    }

    private function buildReceivableAging(array $f): array
    {
        return $this->aging($f, true);
    }

    private function buildPayableAging(array $f): array
    {
        return $this->aging($f, false);
    }

    /* ================================================================== */
    /*  6. Stock valuation                                                */
    /* ================================================================== */

    /** On-hand per product / variant for the branch being viewed. */
    private function onHand(?int $categoryId = null, ?int $brandId = null): Collection
    {
        $stock = BranchController::scope(DB::table('stocks as st'), 'st.branch_id')->where('st.deleted', 'No')
            ->groupBy('st.product_id', 'st.product_variant_id')
            ->selectRaw('st.product_id, st.product_variant_id, SUM(st.in_qty - st.out_qty) qty');

        return DB::table('products as p')->leftJoinSub($stock, 'k', 'k.product_id', '=', 'p.id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'k.product_variant_id')->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('p.deleted', 'No')
            ->when($categoryId, fn ($q) => $q->where('p.category_id', $categoryId))
            ->when($brandId, fn ($q) => $q->where('p.brand_id', $brandId))
            ->orderBy('c.name')->orderBy('p.name')
            ->get(['p.id', 'p.name', 'p.sku', 'p.purchase_price', 'p.sale_price', 'p.stock_alert', 'v.id as variant_id', 'v.sale_price as v_price', 'a.name as variant',
                'c.name as category', 'b.name as brand', DB::raw('COALESCE(k.qty, 0) as qty')]);
    }

    private function buildStockValuation(array $f): array
    {
        $items = $this->onHand($f['category_id'], $f['brand_id'])->filter(fn ($i) => !$f['hide_zero'] || abs((float) $i->qty) > 0.0005);
        $groups = [];
        foreach ($items->groupBy(fn ($i) => $i->category ?: 'No category') as $cat => $g) {
            $rows = $g->map(function ($i) {
                $price = (float) ($i->v_price ?: $i->sale_price);

                return ['label' => $i->name . ($i->variant ? ' (' . $i->variant . ')' : ''), 'sku' => $i->sku, 'qty' => (float) $i->qty,
                    'unit_cost' => (float) $i->purchase_price, 'cost_value' => round((float) $i->qty * (float) $i->purchase_price, 2),
                    'unit_price' => $price, 'sale_value' => round((float) $i->qty * $price, 2),
                    'potential' => round((float) $i->qty * ($price - (float) $i->purchase_price), 2)];
            })->values()->all();
            $groups[] = ['name' => $cat, 'rows' => $rows, 'totals' => self::sum($rows, ['qty', 'cost_value', 'sale_value', 'potential'])];
        }
        $all = collect($groups)->flatMap(fn ($g) => $g['rows'])->all();
        $t = self::sum($all, ['qty', 'cost_value', 'sale_value', 'potential']);
        $negative = collect($all)->where('qty', '<', 0)->count();

        return [
            'columns' => ['label' => ['Product', 'text'], 'sku' => ['SKU', 'text'], 'qty' => ['On Hand', 'qty'], 'unit_cost' => ['Unit Cost', 'money'],
                'cost_value' => ['Value at Cost', 'money'], 'unit_price' => ['Sale Price', 'money'], 'sale_value' => ['Value at Sale Price', 'money'],
                'potential' => ['Potential Profit', 'money']],
            'groups' => $groups, 'totals' => $t,
            'filters_extra' => ['As at' => now()->format('d M Y, h:i A')],
            'cards' => [['Stock value (cost)', $t['cost_value'], 'money', 'accent'], ['Stock value (sale price)', $t['sale_value'], 'money', 'success'],
                ['Units on hand', $t['qty'], 'qty'], ['Negative stock lines', $negative, 'int', $negative ? 'danger' : 'success']],
            'notes' => ['Valued at the current purchase price of each product. Negative quantities mean more was sold than recorded in stock — check with a stock count.'],
        ];
    }

    /* ================================================================== */
    /*  7. Purchases                                                      */
    /* ================================================================== */

    private function buildPurchases(array $f): array
    {
        $by = $this->group($f, 'purchases', 'supplier');
        $range = [$f['from']->toDateString(), $f['to']->toDateString()];

        if (in_array($by, ['product', 'category', 'brand'], true)) {
            $lines = BranchController::scope(DB::table('purchase_items as i'), 'pu.branch_id')->join('purchases as pu', 'pu.id', '=', 'i.purchase_id')
                ->leftJoin('products as p', 'p.id', '=', 'i.product_id')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')->leftJoin('product_variants as v', 'v.id', '=', 'i.product_variant_id')
                ->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
                ->where('i.deleted', 'No')->where('pu.deleted', 'No')->where('pu.status', 'Active')->whereBetween('pu.purchase_date', $range)
                ->get(['i.purchase_id', 'i.qty', 'i.subtotal', 'i.discount_amount', 'i.total', 'p.name as product', 'a.name as variant', 'c.name as category', 'b.name as brand']);
            $label = fn ($l) => match ($by) {
                'category' => $l->category ?: 'No category',
                'brand'    => $l->brand ?: 'No brand',
                default    => $l->product . ($l->variant ? ' (' . $l->variant . ')' : ''),
            };
            $rows = $lines->groupBy($label)->map(fn ($g, $n) => ['label' => $n, 'invoices' => $g->pluck('purchase_id')->unique()->count(), 'qty' => $g->sum('qty'),
                'gross' => $g->sum('subtotal'), 'discount' => $g->sum('discount_amount'), 'net' => $g->sum('total'),
                'avg_cost' => $g->sum('qty') > 0 ? round($g->sum('total') / $g->sum('qty'), 2) : null])->sortByDesc('net')->values()->all();
            $t = self::sum($rows, ['qty', 'gross', 'discount', 'net']);
            $columns = ['label' => [self::GROUP_BY['purchases'][$by], 'text'], 'invoices' => ['Invoices', 'int'], 'qty' => ['Qty', 'qty'],
                'gross' => ['Gross', 'money'], 'discount' => ['Line Discount', 'money'], 'net' => ['Net Amount', 'money'], 'avg_cost' => ['Avg Unit Cost', 'money']];
            $cards = [['Bought', $t['net'], 'money', 'accent'], ['Units', $t['qty'], 'qty'], ['Lines', count($rows), 'int']];
        } else {
            $p = BranchController::scope(DB::table('purchases as pu'), 'pu.branch_id')->leftJoin('parties as s', 's.id', '=', 'pu.supplier_id')
                ->where('pu.deleted', 'No')->where('pu.status', 'Active')->whereBetween('pu.purchase_date', $range)
                ->get(['pu.*', 's.name as supplier']);
            $label = fn ($x) => match ($by) {
                'day'     => $x->purchase_date,
                'month'   => Carbon::parse($x->purchase_date)->format('Y-m'),
                'invoice' => $x->invoice_no,
                default   => $x->supplier ?: '-',
            };
            $rows = $p->groupBy($label)->map(fn ($g, $n) => ['label' => $n, 'invoices' => $g->count(), 'gross' => $g->sum('subtotal'),
                'discount' => $g->sum('discount_amount'), 'vat' => $g->sum('vat_amount'), 'total' => $g->sum('grand_total'),
                'paid' => $g->sum('paid_amount'), 'due' => $g->sum('due_amount')])
                ->sortBy(in_array($by, ['day', 'month', 'invoice'], true) ? 'label' : fn ($r) => -$r['total'])->values()->all();
            $t = self::sum($rows, ['invoices', 'gross', 'discount', 'vat', 'total', 'paid', 'due']);
            $columns = ['label' => [self::GROUP_BY['purchases'][$by], 'text'], 'invoices' => ['Invoices', 'int'], 'gross' => ['Subtotal', 'money'],
                'discount' => ['Discount', 'money'], 'vat' => ['VAT', 'money'], 'total' => ['Total', 'money'], 'paid' => ['Paid', 'money'], 'due' => ['Due', 'money']];
            $cards = [['Bought', $t['total'], 'money', 'accent'], ['Paid', $t['paid'], 'money', 'success'], ['Still due', $t['due'], 'money', 'danger'], ['Invoices', $t['invoices'], 'int']];
        }

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $t, 'cards' => $cards,
            'notes' => ['Purchase returns are not deducted here — see the Returns report (purchase returns).']];
    }

    /* ================================================================== */
    /*  Discounts                                                         */
    /* ================================================================== */

    private function buildDiscounts(array $f): array
    {
        $by = $this->group($f, 'discounts', 'invoice');
        $sales = $this->salesQuery($f)->leftJoin('parties as p', 'p.id', '=', 's.customer_id')->leftJoin('users as u', 'u.id', '=', 's.created_by')
            ->get(['s.id', 's.invoice_no', 's.sale_date', 's.subtotal', 's.discount_amount', 's.loyalty_discount', 's.grand_total', 'p.name as customer', 'u.name as user']);
        $items = DB::table('sale_items')->whereIn('sale_id', $sales->pluck('id')->all() ?: [0])->where('deleted', 'No')
            ->groupBy('sale_id')->selectRaw('sale_id, SUM(subtotal) gross, SUM(discount_amount) line_disc')->get()->keyBy('sale_id');
        $label = fn ($s) => match ($by) {
            'user'     => $s->user ?: 'Unknown',
            'customer' => $s->customer ?: 'Walk-In Customer',
            'day'      => $s->sale_date,
            default    => $s->invoice_no,
        };
        $rows = $sales->groupBy($label)->map(function ($g, $n) use ($items) {
            $gross = $g->sum(fn ($s) => (float) ($items[$s->id]->gross ?? 0));
            $line = $g->sum(fn ($s) => (float) ($items[$s->id]->line_disc ?? 0));
            $total = $line + $g->sum('discount_amount') + $g->sum('loyalty_discount');

            return ['label' => $n, 'invoices' => $g->count(), 'gross' => $gross, 'line' => $line, 'invoice' => $g->sum('discount_amount'),
                'loyalty' => $g->sum('loyalty_discount'), 'total' => $total, 'pct' => self::pct($total, $gross), 'net' => $g->sum('grand_total')];
        })->filter(fn ($r) => $r['total'] > 0.004)->sortByDesc('total')->values()->all();
        $t = self::sum($rows, ['invoices', 'gross', 'line', 'invoice', 'loyalty', 'total', 'net']);
        $t['pct'] = self::pct($t['total'], $t['gross']);

        return [
            'columns' => ['label' => [self::GROUP_BY['discounts'][$by], 'text'], 'invoices' => ['Invoices', 'int'], 'gross' => ['Gross Sales', 'money'],
                'line' => ['Line Discounts', 'money'], 'invoice' => ['Invoice Discounts', 'money'], 'loyalty' => ['Points Redeemed', 'money'],
                'total' => ['Total Discount', 'money'], 'pct' => ['% of Gross', 'pct'], 'net' => ['Invoice Total', 'money']],
            'rows' => $rows, 'totals' => $t,
            'cards' => [['Total discounts', $t['total'], 'money', 'warning'], ['% of gross sales', $t['pct'], 'pct'],
                ['Invoices with a discount', $t['invoices'], 'int'], ['Paid with points', $t['loyalty'], 'money']],
            'notes' => ['Only invoices with a discount are listed.'],
        ];
    }

    /* ================================================================== */
    /*  Returns                                                           */
    /* ================================================================== */

    private function buildReturns(array $f): array
    {
        $by = $this->group($f, 'returns', 'product');
        $range = [$f['from']->toDateString(), $f['to']->toDateString()];
        $sale = $f['return_type'] === 'sale';

        $q = $sale
            ? DB::table('sale_return_items as i')->join('sale_returns as r', 'r.id', '=', 'i.sale_return_id')->leftJoin('parties as pa', 'pa.id', '=', 'r.customer_id')
                ->leftJoin('sales as d', 'd.id', '=', 'r.sale_id')
            : DB::table('purchase_return_items as i')->join('purchase_returns as r', 'r.id', '=', 'i.purchase_return_id')->leftJoin('parties as pa', 'pa.id', '=', 'r.supplier_id')
                ->leftJoin('purchases as d', 'd.id', '=', 'r.purchase_id');
        $lines = BranchController::scope($q, 'r.branch_id')->leftJoin('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'i.product_variant_id')->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
            ->where('i.deleted', 'No')->where('r.deleted', 'No')->whereBetween(DB::raw('DATE(r.datetime)'), $range)
            ->get(['i.product_id', 'i.product_variant_id', 'i.qty', 'i.total', 'r.id as rid', 'r.return_no', 'r.datetime', 'pa.name as party', 'd.invoice_no as source',
                'p.name as product', 'a.name as variant']);

        $label = fn ($l) => match ($by) {
            'party'  => $l->party ?: '-',
            'month'  => Carbon::parse($l->datetime)->format('Y-m'),
            'return' => $l->return_no . ($l->source ? ' (' . $l->source . ')' : ''),
            default  => $l->product . ($l->variant ? ' (' . $l->variant . ')' : ''),
        };

        $soldQty = [];
        if ($by === 'product') {
            $moved = $sale
                ? BranchController::scope(DB::table('sale_items as i')->join('sales as s', 's.id', '=', 'i.sale_id'), 's.branch_id')
                    ->where('i.deleted', 'No')->where('s.deleted', 'No')->whereBetween('s.sale_date', $range)
                : BranchController::scope(DB::table('purchase_items as i')->join('purchases as s', 's.id', '=', 'i.purchase_id'), 's.branch_id')
                    ->where('i.deleted', 'No')->where('s.deleted', 'No')->whereBetween('s.purchase_date', $range);
            foreach ($moved->groupBy('i.product_id', 'i.product_variant_id')->selectRaw('i.product_id, i.product_variant_id, SUM(i.qty) q')->get() as $m) {
                $soldQty[$m->product_id . ':' . ($m->product_variant_id ?: 0)] = (float) $m->q;
            }
        }

        $rows = $lines->groupBy($label)->map(function ($g, $n) use ($by, $soldQty) {
            $row = ['label' => $n, 'returns' => $g->pluck('rid')->unique()->count(), 'qty' => $g->sum('qty'), 'value' => $g->sum('total')];
            if ($by === 'product') {
                $first = $g->first();
                $moved = $soldQty[$first->product_id . ':' . ($first->product_variant_id ?: 0)] ?? 0;
                $row['moved'] = $moved;
                $row['rate'] = self::pct($row['qty'], $moved);
            }

            return $row;
        })->sortByDesc('value')->values()->all();

        $columns = ['label' => [self::GROUP_BY['returns'][$by], 'text'], 'returns' => ['Returns', 'int'], 'qty' => ['Qty Returned', 'qty'], 'value' => ['Value', 'money']];
        if ($by === 'product') {
            $columns['moved'] = [$sale ? 'Qty Sold' : 'Qty Bought', 'qty'];
            $columns['rate'] = ['Return Rate', 'pct'];
        }
        $t = self::sum($rows, ['qty', 'value']);
        $t['returns'] = $lines->pluck('rid')->unique()->count();

        return [
            'columns' => $columns, 'rows' => $rows, 'totals' => $t,
            'filters_extra' => ['Returns' => $sale ? 'Sales returns (from customers)' : 'Purchase returns (to suppliers)'],
            'cards' => [['Returns', $t['returns'], 'int'], ['Quantity returned', $t['qty'], 'qty'], ['Value', $t['value'], 'money', $sale ? 'danger' : 'accent']],
        ];
    }

    /* ================================================================== */
    /*  Sales by hour and weekday                                         */
    /* ================================================================== */

    private function buildSalesHeatmap(array $f): array
    {
        $sales = $this->salesQuery($f)->whereNotNull('s.created_date')->get(['s.created_date', 's.grand_total']);
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $hours = $sales->map(fn ($s) => (int) Carbon::parse($s->created_date)->format('G'));
        $from = min(8, $hours->min() ?? 8);
        $to = max(21, $hours->max() ?? 21);

        $grid = [];
        foreach ($sales as $s) {
            $d = Carbon::parse($s->created_date);
            $grid[$d->isoWeekday()][(int) $d->format('G')][] = (float) $s->grand_total;
        }

        $columns = ['label' => ['Day', 'text']];
        for ($h = $from; $h <= $to; $h++) {
            $columns['h' . $h] = [Carbon::createFromTime($h)->format('ga'), 'heat'];
        }
        $columns['total'] = [$f['measure'] === 'count' ? 'Invoices' : 'Sales', $f['measure'] === 'count' ? 'int' : 'money'];

        $rows = [];
        $max = 0;
        foreach ($days as $n => $name) {
            $row = ['label' => $name];
            for ($h = $from; $h <= $to; $h++) {
                $v = $f['measure'] === 'count' ? count($grid[$n][$h] ?? []) : array_sum($grid[$n][$h] ?? []);
                $row['h' . $h] = $v ?: null;
                $max = max($max, $v);
            }
            $row['total'] = $f['measure'] === 'count' ? count(array_merge(...array_values($grid[$n] ?? [[]]))) : array_sum(array_map('array_sum', $grid[$n] ?? []));
            $rows[] = $row;
        }

        $byHour = [];
        foreach ($sales as $s) {
            $h = (int) Carbon::parse($s->created_date)->format('G');
            $byHour[$h] = ($byHour[$h] ?? 0) + ($f['measure'] === 'count' ? 1 : (float) $s->grand_total);
        }
        arsort($byHour);
        $byDay = collect($rows)->sortByDesc('total')->first();
        $t = self::sum($rows, array_merge(array_map(fn ($h) => 'h' . $h, range($from, $to)), ['total']));

        return [
            'columns' => $columns, 'rows' => $rows, 'totals' => $t, 'extra' => ['heat_max' => $max],
            'filters_extra' => ['Showing' => $f['measure'] === 'count' ? 'Number of invoices' : 'Sales amount'],
            'cards' => [['Busiest hour', $byHour ? Carbon::createFromTime(array_key_first($byHour))->format('g a') . ' – ' . Carbon::createFromTime(array_key_first($byHour) + 1 > 23 ? 23 : array_key_first($byHour) + 1)->format('g a') : '-', 'text', 'accent'],
                ['Busiest day', $byDay && $byDay['total'] ? $byDay['label'] : '-', 'text', 'success'],
                ['Invoices', $sales->count(), 'int'], ['Sales', $sales->sum('grand_total'), 'money']],
            'notes' => ['Uses the time each invoice was saved. Darker cells are busier.'],
        ];
    }

    /* ================================================================== */
    /*  Top customers                                                     */
    /* ================================================================== */

    private function buildTopCustomers(array $f): array
    {
        $sales = $this->salesQuery($f)->join('parties as p', 'p.id', '=', 's.customer_id')
            ->get(['s.id', 's.customer_id', 's.sale_date', 's.grand_total', 's.vat_amount', 's.vat_inclusive', 'p.name', 'p.phone', 'p.type', 'p.loyalty_points', 'p.opening_balance', 'p.opening_balance_type']);
        $cost = DB::table('sale_items')->whereIn('sale_id', $sales->pluck('id')->all() ?: [0])->where('deleted', 'No')
            ->groupBy('sale_id')->selectRaw('sale_id, SUM(qty * purchase_price) c')->pluck('c', 'sale_id');
        $ids = $sales->pluck('customer_id')->unique()->all() ?: [0];
        $ledger = BranchController::scope(DB::table('payments'), 'branch_id')->whereIn('party_id', $ids)->where('deleted', 'No')->where('status', 'Active')
            ->where(fn ($q) => $q->whereNull('party_type')->orWhere('party_type', '<>', 'Employee')) // party_id holds employee ids there
            ->groupBy('party_id')->selectRaw('party_id, SUM(credit) - SUM(debit) bal')->pluck('bal', 'party_id');
        $last = BranchController::scope(DB::table('sales'), 'branch_id')->whereIn('customer_id', $ids)->where('deleted', 'No')
            ->groupBy('customer_id')->selectRaw('customer_id, MAX(sale_date) d')->pluck('d', 'customer_id');
        $isMain = BranchController::isMainBranch();

        $rows = $sales->groupBy('customer_id')->map(function ($g, $id) use ($cost, $ledger, $last, $isMain) {
            $p = $g->first();
            $net = $g->sum(fn ($s) => (float) $s->grand_total - (float) $s->vat_amount);

            return ['label' => $p->name, 'phone' => $p->phone, 'type' => $p->type, 'invoices' => $g->count(), 'net' => round($net, 2),
                'profit' => round($net - $g->sum(fn ($s) => (float) ($cost[$s->id] ?? 0)), 2), 'avg' => round($net / max(1, $g->count()), 2),
                'last' => $last[$id] ?? null, 'due' => round(($isMain ? (float) $p->opening_balance * ($p->opening_balance_type === 'Advance' ? -1 : 1) : 0) + (float) ($ledger[$id] ?? 0), 2),
                'points' => (float) $p->loyalty_points];
        })->sortByDesc('net')->take($f['limit'])->values()->all();
        $all = $sales->sum(fn ($s) => (float) $s->grand_total - (float) $s->vat_amount);
        $t = self::sum($rows, ['invoices', 'net', 'profit', 'due', 'points']);

        return [
            'columns' => ['label' => ['Customer', 'text'], 'phone' => ['Phone', 'text'], 'type' => ['Type', 'text'], 'invoices' => ['Invoices', 'int'],
                'net' => ['Net Sales', 'money'], 'profit' => ['Profit', 'money'], 'avg' => ['Avg Invoice', 'money'], 'last' => ['Last Purchase', 'date'],
                'due' => ['Due Now', 'money'], 'points' => ['Points', 'qty']],
            'rows' => $rows, 'totals' => $t,
            'filters_extra' => ['Showing' => 'Top ' . $f['limit']],
            'cards' => [['Customers who bought', $sales->pluck('customer_id')->unique()->count(), 'int'], ['Top ' . count($rows) . ' share of sales', self::pct($t['net'], $all), 'pct', 'accent'],
                ['Their sales', $t['net'], 'money', 'success'], ['They owe', $t['due'], 'money', 'warning']],
        ];
    }

    /* ================================================================== */
    /*  Quotation conversion                                              */
    /* ================================================================== */

    private function buildQuotationConversion(array $f): array
    {
        $by = $this->group($f, 'quotation-conversion', 'month');
        $q = BranchController::scope(DB::table('sale_quotations as q'), 'q.branch_id')->leftJoin('parties as p', 'p.id', '=', 'q.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'q.created_by')->where('q.deleted', 'No')
            ->whereBetween('q.quotation_date', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->get(['q.quotation_status', 'q.grand_total', 'q.quotation_date', 'p.name as customer', 'u.name as user']);
        $label = fn ($x) => match ($by) {
            'customer' => $x->customer ?: '-',
            'user'     => $x->user ?: '-',
            default    => Carbon::parse($x->quotation_date)->format('Y-m'),
        };
        $row = function ($g, $n) {
            $c = fn ($s) => $g->where('quotation_status', $s);
            $decided = $c('Accepted')->count() + $c('Rejected')->count() + $c('Expired')->count();

            return ['label' => $n, 'count' => $g->count(), 'value' => $g->sum('grand_total'), 'open' => $c('Draft')->count() + $c('Sent')->count(),
                'accepted' => $c('Accepted')->count(), 'accepted_value' => $c('Accepted')->sum('grand_total'), 'rejected' => $c('Rejected')->count(),
                'expired' => $c('Expired')->count(), 'rate' => self::pct($c('Accepted')->count(), $decided)];
        };
        $rows = $q->groupBy($label)->map($row)->sortBy($by === 'month' ? 'label' : fn ($r) => -$r['value'])->values()->all();
        $t = $row($q, 'Total');
        unset($t['label']);

        return [
            'columns' => ['label' => [self::GROUP_BY['quotation-conversion'][$by], 'text'], 'count' => ['Quotations', 'int'], 'value' => ['Value', 'money'],
                'open' => ['Open', 'int'], 'accepted' => ['Accepted', 'int'], 'accepted_value' => ['Accepted Value', 'money'], 'rejected' => ['Rejected', 'int'],
                'expired' => ['Expired', 'int'], 'rate' => ['Win Rate', 'pct']],
            'rows' => $rows, 'totals' => $t,
            'cards' => [['Quotations', $t['count'], 'int'], ['Win rate', $t['rate'], 'pct', 'success'], ['Won value', $t['accepted_value'], 'money', 'accent'], ['Still open', $t['open'], 'int', 'warning']],
            'notes' => ['Win rate = accepted ÷ (accepted + rejected + expired). Draft and sent quotations are still open.'],
        ];
    }

    /* ================================================================== */
    /*  Sale order fulfilment                                             */
    /* ================================================================== */

    private function buildOrderFulfilment(array $f): array
    {
        $orders = BranchController::scope(DB::table('sale_orders as o'), 'o.branch_id')->leftJoin('parties as p', 'p.id', '=', 'o.customer_id')
            ->where('o.deleted', 'No')->whereBetween('o.order_date', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->when($f['order_status'], fn ($q) => $q->where('o.order_status', $f['order_status']))
            ->orderBy('o.order_date')->get(['o.*', 'p.name as customer']);
        $qty = DB::table('sale_order_items')->whereIn('sale_order_id', $orders->pluck('id')->all() ?: [0])->where('deleted', 'No')
            ->groupBy('sale_order_id')->selectRaw('sale_order_id, SUM(qty) q, SUM(delivery_qty) d')->get()->keyBy('sale_order_id');
        $today = now()->startOfDay();

        $rows = $orders->map(function ($o) use ($qty, $today) {
            $q = (float) ($qty[$o->id]->q ?? 0);
            $d = (float) ($qty[$o->id]->d ?? 0);
            $late = null;
            if ($o->delivery_date) {
                $promised = Carbon::parse($o->delivery_date)->startOfDay();
                $done = $o->order_status === 'Delivered' && $o->delivery_datetime ? Carbon::parse($o->delivery_datetime)->startOfDay() : null;
                $late = $done ? max(0, $promised->diffInDays($done, false)) : ($o->order_status === 'Cancelled' ? null : max(0, $promised->diffInDays($today, false)));
            }

            return ['label' => $o->order_no, 'customer' => $o->customer, 'order_date' => $o->order_date, 'delivery_date' => $o->delivery_date,
                'status' => $o->order_status, 'ordered' => $q, 'delivered' => $d, 'done' => self::pct($d, $q), 'total' => (float) $o->grand_total,
                'advance' => (float) $o->advance_paid, 'due' => (float) $o->due_amount, 'late' => $late];
        })->values()->all();
        $t = self::sum($rows, ['ordered', 'delivered', 'total', 'advance', 'due']);
        $t['done'] = self::pct($t['delivered'], $t['ordered']);
        $late = collect($rows)->filter(fn ($r) => ($r['late'] ?? 0) > 0 && !in_array($r['status'], ['Delivered', 'Cancelled'], true))->count();

        return [
            'columns' => ['label' => ['Order', 'text'], 'customer' => ['Customer', 'text'], 'order_date' => ['Ordered', 'date'], 'delivery_date' => ['Promised', 'date'],
                'status' => ['Status', 'text'], 'ordered' => ['Qty Ordered', 'qty'], 'delivered' => ['Qty Delivered', 'qty'], 'done' => ['Delivered %', 'pct'],
                'total' => ['Order Total', 'money'], 'advance' => ['Advance', 'money'], 'due' => ['Due', 'money'], 'late' => ['Days Late', 'int']],
            'rows' => $rows, 'totals' => $t,
            'filters_extra' => $f['order_status'] ? ['Status' => $f['order_status']] : [],
            'cards' => [['Orders', count($rows), 'int'], ['Delivered (qty)', $t['done'], 'pct', 'success'], ['Late & still open', $late, 'int', $late ? 'danger' : 'success'], ['Due on orders', $t['due'], 'money', 'warning']],
            'notes' => ['Days late: for delivered orders, delivery day after the promised date; for open orders, days since the promised date.'],
        ];
    }

    /* ================================================================== */
    /*  Loyalty                                                           */
    /* ================================================================== */

    private function buildLoyalty(array $f): array
    {
        $value = (float) CustomerProgramController::setting('loyalty_point_value');
        $tx = BranchController::scope(DB::table('loyalty_transactions as t'), 't.branch_id')
            ->whereBetween('t.created_at', [$f['from'], $f['to']])->groupBy('t.party_id')
            ->selectRaw("t.party_id, SUM(CASE WHEN t.txn_type = 'Earn' THEN t.points ELSE 0 END) earned,
                -SUM(CASE WHEN t.txn_type = 'Redeem' THEN t.points ELSE 0 END) redeemed,
                SUM(CASE WHEN t.txn_type IN ('Adjust', 'Reverse') THEN t.points ELSE 0 END) adjusted,
                SUM(CASE WHEN t.txn_type = 'Redeem' THEN t.amount ELSE 0 END) discount")->get()->keyBy('party_id');
        $parties = DB::table('parties')->where('deleted', 'No')->where(fn ($q) => $q->where('loyalty_points', '>', 0)->orWhereIn('id', $tx->keys()->all() ?: [0]))
            ->orderByDesc('loyalty_points')->get(['id', 'name', 'phone', 'loyalty_points']);

        $rows = $parties->map(fn ($p) => ['label' => $p->name, 'phone' => $p->phone, 'earned' => (float) ($tx[$p->id]->earned ?? 0),
            'redeemed' => (float) ($tx[$p->id]->redeemed ?? 0), 'adjusted' => (float) ($tx[$p->id]->adjusted ?? 0),
            'discount' => (float) ($tx[$p->id]->discount ?? 0), 'balance' => (float) $p->loyalty_points, 'worth' => round((float) $p->loyalty_points * $value, 2)])->values()->all();
        $t = self::sum($rows, ['earned', 'redeemed', 'adjusted', 'discount', 'balance', 'worth']);

        return [
            'columns' => ['label' => ['Customer', 'text'], 'phone' => ['Phone', 'text'], 'earned' => ['Earned', 'qty'], 'redeemed' => ['Redeemed', 'qty'],
                'adjusted' => ['Adjusted', 'qty'], 'discount' => ['Discount Given', 'money'], 'balance' => ['Points Now', 'qty'], 'worth' => ['Worth', 'money']],
            'rows' => $rows, 'totals' => $t,
            'cards' => [['Points earned', $t['earned'], 'qty', 'success'], ['Points redeemed', $t['redeemed'], 'qty', 'accent'],
                ['Outstanding points', $t['balance'], 'qty'], ['Liability (worth)', $t['worth'], 'money', 'warning']],
            'notes' => ['Earned / redeemed are this branch\'s activity in the period. "Points now" is the customer\'s balance, which can be used in any branch; its worth is what the business owes in discounts.'],
        ];
    }

    /* ================================================================== */
    /*  Reorder list                                                      */
    /* ================================================================== */

    /** Qty sold per product:variant in the branch over the last $days days. */
    private function soldSince(int $days): array
    {
        $rows = BranchController::scope(DB::table('sale_items as i')->join('sales as s', 's.id', '=', 'i.sale_id'), 's.branch_id')
            ->where('i.deleted', 'No')->where('s.deleted', 'No')->where('s.sale_date', '>=', now()->subDays($days)->toDateString())
            ->groupBy('i.product_id', 'i.product_variant_id')->selectRaw('i.product_id, i.product_variant_id, SUM(i.qty) q')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->product_id . ':' . ($r->product_variant_id ?: 0)] = (float) $r->q;
        }

        return $out;
    }

    private function buildReorder(array $f): array
    {
        $sold = $this->soldSince(30);
        $lastBuy = DB::table('purchase_items as i')->join('purchases as pu', 'pu.id', '=', 'i.purchase_id')->leftJoin('parties as s', 's.id', '=', 'pu.supplier_id')
            ->where('i.deleted', 'No')->where('pu.deleted', 'No')->orderByDesc('pu.purchase_date')->orderByDesc('pu.id')
            ->get(['i.product_id', 'i.product_variant_id', 'i.purchase_price', 's.name as supplier'])
            ->unique(fn ($r) => $r->product_id . ':' . ($r->product_variant_id ?: 0))->keyBy(fn ($r) => $r->product_id . ':' . ($r->product_variant_id ?: 0));

        $rows = $this->onHand($f['category_id'])->map(function ($i) use ($sold, $lastBuy, $f) {
            $key = $i->id . ':' . ($i->variant_id ?: 0);
            $perDay = round(($sold[$key] ?? 0) / 30, 3);
            $qty = (float) $i->qty;
            $alert = (float) $i->stock_alert;
            $suggest = max(0, ceil($perDay * $f['cover_days'] + $alert - $qty));

            return ['label' => $i->name . ($i->variant ? ' (' . $i->variant . ')' : ''), 'sku' => $i->sku, 'qty' => $qty, 'alert' => $alert,
                'per_day' => $perDay, 'cover' => $perDay > 0 ? round(max(0, $qty) / $perDay, 1) : null, 'suggest' => $suggest,
                'supplier' => $lastBuy[$key]->supplier ?? '-', 'unit_cost' => (float) ($lastBuy[$key]->purchase_price ?? $i->purchase_price),
                'order_value' => round($suggest * (float) ($lastBuy[$key]->purchase_price ?? $i->purchase_price), 2), 'need' => $qty <= $alert || ($perDay > 0 && $qty / $perDay < $f['cover_days'] / 2)];
        })->filter(fn ($r) => $r['need'] && $r['suggest'] > 0)->sortBy('cover')->values()->all();
        $t = self::sum($rows, ['suggest', 'order_value']);

        return [
            'columns' => ['label' => ['Product', 'text'], 'sku' => ['SKU', 'text'], 'qty' => ['On Hand', 'qty'], 'alert' => ['Alert Level', 'qty'],
                'per_day' => ['Sold / Day', 'qty'], 'cover' => ['Days of Cover', 'qty'], 'suggest' => ['Suggested Order', 'qty'], 'supplier' => ['Last Supplier', 'text'],
                'unit_cost' => ['Last Cost', 'money'], 'order_value' => ['Order Value', 'money']],
            'rows' => $rows, 'totals' => $t,
            'filters_extra' => ['Cover' => $f['cover_days'] . ' days of sales after the order arrives'],
            'cards' => [['Products to reorder', count($rows), 'int', count($rows) ? 'warning' : 'success'], ['Suggested order value', $t['order_value'], 'money', 'accent'],
                ['Out of stock', collect($rows)->where('qty', '<=', 0)->count(), 'int', 'danger']],
            'notes' => ['Sales speed = units sold in this branch over the last 30 days ÷ 30. Suggested order = speed × cover days + alert level − on hand. Listed when stock is at or below the alert level or would last less than half the cover period.'],
        ];
    }

    /* ================================================================== */
    /*  Slow & dead stock                                                 */
    /* ================================================================== */

    private function buildDeadStock(array $f): array
    {
        $lastSale = BranchController::scope(DB::table('sale_items as i')->join('sales as s', 's.id', '=', 'i.sale_id'), 's.branch_id')
            ->where('i.deleted', 'No')->where('s.deleted', 'No')->groupBy('i.product_id', 'i.product_variant_id')
            ->selectRaw('i.product_id, i.product_variant_id, MAX(s.sale_date) d')->get()
            ->keyBy(fn ($r) => $r->product_id . ':' . ($r->product_variant_id ?: 0));
        $lastIn = BranchController::scope(DB::table('stocks'), 'branch_id')->where('deleted', 'No')->where('in_qty', '>', 0)
            ->groupBy('product_id', 'product_variant_id')->selectRaw('product_id, product_variant_id, MAX(date) d')->get()
            ->keyBy(fn ($r) => $r->product_id . ':' . ($r->product_variant_id ?: 0));
        $today = now()->startOfDay();

        $rows = $this->onHand($f['category_id'])->filter(fn ($i) => (float) $i->qty > 0)->map(function ($i) use ($lastSale, $lastIn, $today) {
            $key = $i->id . ':' . ($i->variant_id ?: 0);
            $sold = $lastSale[$key]->d ?? null;
            $since = $sold ? Carbon::parse($sold)->startOfDay()->diffInDays($today) : null;
            $in = $lastIn[$key]->d ?? null;

            return ['label' => $i->name . ($i->variant ? ' (' . $i->variant . ')' : ''), 'category' => $i->category ?: '-', 'qty' => (float) $i->qty,
                'value' => round((float) $i->qty * (float) $i->purchase_price, 2), 'last_sale' => $sold, 'since' => $since,
                'last_in' => $in ? substr($in, 0, 10) : null, 'age' => $in ? Carbon::parse($in)->startOfDay()->diffInDays($today) : null];
        })->filter(fn ($r) => $r['since'] === null || $r['since'] >= $f['days'])
            ->sortByDesc(fn ($r) => $r['since'] ?? 100000)->values()->all();
        $t = self::sum($rows, ['qty', 'value']);
        $never = collect($rows)->whereNull('since')->count();

        return [
            'columns' => ['label' => ['Product', 'text'], 'category' => ['Category', 'text'], 'qty' => ['On Hand', 'qty'], 'value' => ['Value at Cost', 'money'],
                'last_sale' => ['Last Sold', 'date'], 'since' => ['Days Since Sale', 'int'], 'last_in' => ['Last Received', 'date'], 'age' => ['Days Since Received', 'int']],
            'rows' => $rows, 'totals' => $t,
            'filters_extra' => ['Not sold for' => $f['days'] . ' days or more'],
            'cards' => [['Products', count($rows), 'int', 'warning'], ['Money tied up', $t['value'], 'money', 'danger'], ['Never sold here', $never, 'int']],
            'notes' => ['Consider discounts, bundles or transfers to a branch where they sell.'],
        ];
    }

    /* ================================================================== */
    /*  Cash flow statement                                               */
    /* ================================================================== */

    private function buildCashFlow(array $f): array
    {
        $report = new ReportController();
        $ctx = (object) ['filters' => []];
        $opening = 0.0;
        foreach ($report->liquidAssetAccounts($ctx) as $a) {
            $opening += (float) $a['opening_balance'];
        }
        foreach ($report->liquidAssetMovements($ctx, null, $f['from']->copy()->subSecond()) as $m) {
            $opening += $m->in - $m->out;
        }
        $moves = $report->liquidAssetMovements($ctx, $f['from'], $f['to']);

        $section = fn ($type) => match (true) {
            str_starts_with($type, 'Sale Receive'), $type === 'Payment Receive', $type === 'Purchase Payment', $type === 'Payment to Supplier',
            $type === 'Purchase Return Refund', $type === 'Bill Payment', $type === 'Expense', $type === 'Salary' => 'Operating activities',
            in_array($type, ['Loan / Advance Paid', 'Loan Repayment'], true) => 'Employee loans & advances',
            $type === 'Amount Transfer' => 'Transfers between cash & bank (net)',
            default => 'Other (journals)',
        };
        $label = fn ($type) => match (true) {
            str_starts_with($type, 'Sale Receive'), $type === 'Payment Receive' => 'Received from customers',
            in_array($type, ['Purchase Payment', 'Payment to Supplier', 'Bill Payment'], true) => 'Paid to suppliers',
            default => $type,
        };

        $groups = [];
        foreach (['Operating activities', 'Employee loans & advances', 'Other (journals)', 'Transfers between cash & bank (net)'] as $name) {
            $lines = $moves->filter(fn ($m) => $section($m->type) === $name)->groupBy(fn ($m) => $label($m->type))
                ->map(fn ($g, $n) => ['label' => $n, 'count' => $g->count(), 'in' => $g->sum('in'), 'out' => $g->sum('out'), 'net' => round($g->sum('in') - $g->sum('out'), 2)])
                ->sortByDesc(fn ($r) => abs($r['net']))->values()->all();
            if ($lines) {
                $groups[] = ['name' => $name, 'rows' => $lines, 'totals' => self::sum($lines, ['count', 'in', 'out', 'net'])];
            }
        }
        $t = self::sum(collect($groups)->flatMap(fn ($g) => $g['rows'])->all(), ['count', 'in', 'out', 'net']);
        $closing = round($opening + $t['net'], 2);
        $operating = collect($groups)->firstWhere('name', 'Operating activities')['totals']['net'] ?? 0;

        return [
            'columns' => ['label' => ['Activity', 'text'], 'count' => ['Entries', 'int'], 'in' => ['Cash In', 'money'], 'out' => ['Cash Out', 'money'], 'net' => ['Net', 'money']],
            'groups' => $groups, 'totals' => $t,
            'cards' => [['Opening cash & bank', round($opening, 2), 'money'], ['Net from operations', $operating, 'money', $operating < 0 ? 'danger' : 'success'],
                ['Net change', $t['net'], 'money', $t['net'] < 0 ? 'danger' : 'accent'], ['Closing cash & bank', $closing, 'money', 'accent']],
            'notes' => ['Opening ' . number_format($opening, 2) . ' + net change ' . number_format($t['net'], 2) . ' = closing ' . number_format($closing, 2)
                . '. Built from the same movements as the Cash & Bank Book, so the two always agree.'],
        ];
    }

    /* ================================================================== */
    /*  Expenses                                                          */
    /* ================================================================== */

    /** Expense lines of the period: expenses, bills and processed salary, each with its expense ledger. */
    private function expenseLines(Carbon $from, Carbon $to): Collection
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $exp = BranchController::scope(DB::table('expense_details as d')->join('expenses as e', 'e.id', '=', 'd.expense_id'), 'e.branch_id')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'd.expense_account_id')->leftJoin('employees as em', 'em.id', '=', 'e.employee_id')
            ->where('d.deleted', 'No')->where('e.deleted', 'No')->whereBetween('e.expense_date', $range)
            ->get(['d.expense_account_id as account_id', 'a.account_name as head', 'd.amount', 'e.expense_date as date', 'em.name as employee'])
            ->map(fn ($r) => (object) ((array) $r + ['source' => 'Expense']));
        $bills = BranchController::scope(DB::table('bill_details as d')->join('bills as b', 'b.id', '=', 'd.bill_id'), 'b.branch_id')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'd.account_id')
            ->where('d.deleted', 'No')->where('b.deleted', 'No')->whereBetween('b.bill_date', $range)
            ->get(['d.account_id', 'a.account_name as head', 'd.amount', 'b.bill_date as date'])
            ->map(fn ($r) => (object) ((array) $r + ['source' => 'Bill', 'employee' => null]));
        $pay = BranchController::scope(DB::table('payroll_runs as r'), 'r.branch_id')->leftJoin('chart_of_accounts as a', 'a.id', '=', 'r.expense_account_id')
            ->where('r.run_status', 'Posted')->whereBetween('r.run_date', $range)
            ->get(['r.expense_account_id as account_id', 'a.account_name as head', DB::raw('r.total_net + r.total_pf + r.total_loan as amount'), 'r.run_date as date'])
            ->map(fn ($r) => (object) ((array) $r + ['source' => 'Salary', 'employee' => null]));

        return $exp->concat($bills)->concat($pay)->values();
    }

    private function buildExpenses(array $f): array
    {
        $by = $this->group($f, 'expenses', 'head');
        $lines = $this->expenseLines($f['from'], $f['to']);
        $label = fn ($l) => match ($by) {
            'month'    => Carbon::parse($l->date)->format('Y-m'),
            'employee' => $l->employee ?: '(no employee)',
            'source'   => $l->source,
            default    => $l->head ?: 'Unassigned',
        };
        $total = $lines->sum('amount');
        $rows = $lines->groupBy($label)->map(fn ($g, $n) => ['label' => $n, 'entries' => $g->count(), 'amount' => round($g->sum('amount'), 2),
            'share' => self::pct($g->sum('amount'), $total)])->sortBy($by === 'month' ? 'label' : fn ($r) => -$r['amount'])->values()->all();
        $t = self::sum($rows, ['entries', 'amount']);
        $t['share'] = $total ? 100.0 : null;
        $days = max(1, $f['from']->diffInDays($f['to']) + 1);

        return [
            'columns' => ['label' => [self::GROUP_BY['expenses'][$by], 'text'], 'entries' => ['Entries', 'int'], 'amount' => ['Amount', 'money'], 'share' => ['Share', 'pct']],
            'rows' => $rows, 'totals' => $t,
            'cards' => [['Total spent', $t['amount'], 'money', 'danger'], ['Per day', round($t['amount'] / $days, 2), 'money'],
                ['Biggest', $rows[0]['label'] ?? '-', 'text', 'warning'], ['Entries', $t['entries'], 'int']],
            'notes' => ['Includes expenses, bills (by expense line) and processed salary (net + PF + loan recovery, on its posting date).'],
        ];
    }

    /* ================================================================== */
    /*  Budget vs actual                                                  */
    /* ================================================================== */

    private function buildBudget(array $f): array
    {
        $month = $f['month']->copy()->startOfMonth();
        $lines = $this->expenseLines($month, $month->copy()->endOfMonth())->groupBy('account_id');
        $budgets = BranchController::scope(DB::table('budgets'), 'branch_id')->where('month', $month->toDateString())->pluck('amount', 'account_id');
        $accounts = DB::table('chart_of_accounts as a')->leftJoin('chart_of_accounts as g', 'g.id', '=', 'a.parent_id')
            ->where('a.account_type', 'Expense')->where('a.layer_level', 3)->where('a.deleted', 'No')
            ->orderBy('g.account_code')->orderBy('a.account_code')->get(['a.id', 'a.account_name', 'g.account_name as group_name']);

        $groups = [];
        foreach ($accounts->groupBy('group_name') as $gName => $accs) {
            $rows = $accs->map(function ($a) use ($lines, $budgets) {
                $actual = round((float) ($lines[$a->id] ?? collect())->sum('amount'), 2);
                $budget = (float) ($budgets[$a->id] ?? 0);

                return ['label' => $a->account_name, 'account_id' => $a->id, 'budget' => $budget, 'actual' => $actual,
                    'variance' => round($budget - $actual, 2), 'used' => self::pct($actual, $budget)];
            })->values()->all();
            $tt = self::sum($rows, ['budget', 'actual', 'variance']);
            $tt['used'] = self::pct($tt['actual'], $tt['budget']);
            $groups[] = ['name' => $gName ?: 'Expense', 'rows' => $rows, 'totals' => $tt];
        }
        $all = collect($groups)->flatMap(fn ($g) => $g['rows'])->all();
        $t = self::sum($all, ['budget', 'actual', 'variance']);
        $t['used'] = self::pct($t['actual'], $t['budget']);
        $over = collect($all)->filter(fn ($r) => $r['budget'] > 0 && $r['actual'] > $r['budget'])->count();
        $unbudgeted = collect($all)->filter(fn ($r) => $r['budget'] == 0 && $r['actual'] > 0)->sum('actual');

        return [
            'columns' => ['label' => ['Expense Head', 'text'], 'budget' => ['Budget', 'money'], 'actual' => ['Actual', 'money'], 'variance' => ['Left / (Over)', 'money'], 'used' => ['Used', 'pct']],
            'groups' => $groups, 'totals' => $t,
            'filters_extra' => ['Month' => $month->format('F Y')],
            'extra' => ['budget' => ['month' => $month->format('Y-m'), 'rows' => $all]],
            'cards' => [['Budget', $t['budget'], 'money', 'accent'], ['Spent', $t['actual'], 'money', 'danger'], ['Heads over budget', $over, 'int', $over ? 'danger' : 'success'],
                ['Spent with no budget', $unbudgeted, 'money', 'warning']],
            'notes' => ['Set the budget below the table and save; it is kept per branch and month.'],
        ];
    }

    /* ================================================================== */
    /*  Payroll summary                                                   */
    /* ================================================================== */

    private function buildPayrollSummary(array $f): array
    {
        $items = BranchController::scope(DB::table('payroll_run_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id'), 'r.branch_id')
            ->where('r.run_status', 'Posted')->whereBetween('r.month', [$f['from']->copy()->startOfMonth()->toDateString(), $f['to']->toDateString()])
            ->orderBy('r.month')->get(['r.month', 'i.department', 'i.gross_salary', 'i.bonus_amount', 'i.additions', 'i.deductions', 'i.lwp_deduction', 'i.pf_amount', 'i.loan_deduction', 'i.net_payable', 'i.paid_amount']);
        $keys = ['employees', 'gross', 'bonus', 'additions', 'deductions', 'pf', 'loan', 'net', 'paid', 'due'];
        $groups = [];
        foreach ($items->groupBy(fn ($i) => Carbon::parse($i->month)->format('F Y')) as $month => $g) {
            $rows = $g->groupBy(fn ($i) => $i->department ?: 'No department')->map(fn ($d, $n) => ['label' => $n, 'employees' => $d->count(),
                'gross' => $d->sum('gross_salary'), 'bonus' => $d->sum('bonus_amount'), 'additions' => $d->sum('additions'),
                'deductions' => $d->sum('deductions') + $d->sum('lwp_deduction'), 'pf' => $d->sum('pf_amount'), 'loan' => $d->sum('loan_deduction'),
                'net' => $d->sum('net_payable'), 'paid' => $d->sum('paid_amount'), 'due' => round($d->sum('net_payable') - $d->sum('paid_amount'), 2)])->values()->all();
            $groups[] = ['name' => $month, 'rows' => $rows, 'totals' => self::sum($rows, $keys)];
        }
        $t = self::sum(collect($groups)->flatMap(fn ($g) => $g['rows'])->all(), $keys);

        return [
            'columns' => ['label' => ['Department', 'text'], 'employees' => ['Employees', 'int'], 'gross' => ['Gross', 'money'], 'bonus' => ['Bonus', 'money'],
                'additions' => ['Additions', 'money'], 'deductions' => ['Deductions', 'money'], 'pf' => ['PF', 'money'], 'loan' => ['Loan / Advance', 'money'],
                'net' => ['Net Payable', 'money'], 'paid' => ['Paid', 'money'], 'due' => ['Due', 'money']],
            'groups' => $groups, 'totals' => $t,
            'cards' => [['Salary cost (net)', $t['net'], 'money', 'accent'], ['Paid', $t['paid'], 'money', 'success'], ['Still due', $t['due'], 'money', $t['due'] > 0 ? 'danger' : 'success'],
                ['Months', count($groups), 'int']],
            'notes' => ['Only processed salary is counted (Salary Sheet › Process Salary). Months are taken by salary month.'],
        ];
    }

    /* ================================================================== */
    /*  Leave & attendance summary                                        */
    /* ================================================================== */

    private function buildAttendanceSummary(array $f): array
    {
        $by = $this->group($f, 'attendance-summary', 'department');
        $range = [$f['from']->toDateString(), $f['to']->toDateString()];
        $att = BranchController::scope(DB::table('attendances as a')->join('employees as e', 'e.id', '=', 'a.employee_id'), 'e.branch_id')
            ->where('a.deleted', 'No')->whereBetween('a.date', $range)
            ->get(['a.employee_id', 'a.attendance_status', 'a.late_minutes', 'a.overtime_minutes', 'e.name', 'e.employee_code', 'e.department']);
        $leave = BranchController::scope(DB::table('leave_applications as l')->join('employees as e', 'e.id', '=', 'l.employee_id'), 'e.branch_id')
            ->where('l.deleted', 'No')->where('l.leave_status', 'Approved')
            ->where('l.from_date', '<=', $range[1])->where('l.to_date', '>=', $range[0])
            ->get(['l.employee_id', 'l.from_date', 'l.to_date', 'l.days', 'e.name', 'e.employee_code', 'e.department']);
        $leaveDays = [];
        foreach ($leave as $l) {
            $span = max(1, Carbon::parse($l->from_date)->diffInDays(Carbon::parse($l->to_date)) + 1);
            $in = Carbon::parse(max($l->from_date, $range[0]))->diffInDays(Carbon::parse(min($l->to_date, $range[1]))) + 1;
            $leaveDays[$l->employee_id] = ($leaveDays[$l->employee_id] ?? 0) + (float) $l->days * $in / $span;
        }
        $people = $att->concat($leave)->unique('employee_id')->keyBy('employee_id');
        $label = fn ($p) => $by === 'employee' ? $p->name . ($p->employee_code ? ' (' . $p->employee_code . ')' : '') : ($p->department ?: 'No department');

        $rows = $people->groupBy($label)->map(function ($g, $n) use ($att, $leaveDays) {
            $ids = $g->pluck('employee_id')->all();
            $a = $att->whereIn('employee_id', $ids);
            $c = fn ($s) => $a->where('attendance_status', $s)->count();

            return ['label' => $n, 'employees' => count($ids), 'present' => $c('Present'), 'late' => $c('Late'), 'half' => $c('Half Day'), 'absent' => $c('Absent'),
                'leave' => round(array_sum(array_intersect_key($leaveDays, array_flip($ids))), 1), 'late_hours' => round($a->sum('late_minutes') / 60, 1),
                'ot_hours' => round($a->sum('overtime_minutes') / 60, 1)];
        })->map(fn ($r) => $r + ['attendance' => self::pct($r['present'] + $r['late'] + $r['half'] * 0.5, $r['present'] + $r['late'] + $r['half'] + $r['absent'])])
            ->sortBy('label')->values()->all();
        $keys = ['employees', 'present', 'late', 'half', 'absent', 'leave', 'late_hours', 'ot_hours'];
        $t = self::sum($rows, $keys);
        $t['attendance'] = self::pct($t['present'] + $t['late'] + $t['half'] * 0.5, $t['present'] + $t['late'] + $t['half'] + $t['absent']);

        return [
            'columns' => ['label' => [self::GROUP_BY['attendance-summary'][$by], 'text'], 'employees' => ['Employees', 'int'], 'present' => ['Present', 'int'],
                'late' => ['Late', 'int'], 'half' => ['Half Day', 'int'], 'absent' => ['Absent', 'int'], 'leave' => ['Leave Days', 'qty'],
                'late_hours' => ['Late Hours', 'qty'], 'ot_hours' => ['Overtime Hours', 'qty'], 'attendance' => ['Attendance', 'pct']],
            'rows' => $rows, 'totals' => $t,
            'cards' => [['Attendance rate', $t['attendance'], 'pct', 'success'], ['Absent days', $t['absent'], 'int', 'danger'], ['Late days', $t['late'], 'int', 'warning'],
                ['Overtime', $t['ot_hours'] . ' h', 'text', 'accent']],
            'notes' => ['From the daily attendance (Payroll › Daily Attendance) and approved leave. Attendance rate counts a half day as half.'],
        ];
    }
}
