<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use App\Traits\AccountBalanceTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use PDF;

/**
 * Accounting statements: Trial Balance, Profit & Loss, Balance Sheet, and year-end closing.
 *
 * Where the figures come from
 * - One general ledger built at report time from the operational tables (gl()): sales, returns, purchases, stock,
 *   party sub-ledger rows (payments), expenses, bills, journals, amount transfers, payroll, loans and opening balances.
 *   chart_of_accounts.current_balance is not used: cash & bank are the Cash & Bank Book, receivables / payables the
 *   party ledgers, inventory the Stock Valuation report, so every screen shows one figure per ledger.
 * - Trial Balance and Balance Sheet read every branch or one (branch filter); Profit & Loss reads the branches
 *   BranchController::scope() shows. Year-end closing is applied at report time (balances()).
 *
 * Statement shape shared by the page partial, the PDF and the Excel export:
 *   ['title', 'period', 'columns' => [..], 'sections' => [['title', 'lines' => [['kind', 'label', 'code', 'values', 'indent']]]],
 *    'grand' => ['label', 'values'] | null, 'checks' => [['label', 'ok', 'detail']], 'summary' => [..], 'notes' => [..]]
 *   kind: head (root), group, line, subtotal, total.
 */
class AccountingReportController extends Controller
{
    use AccountBalanceTrait;   // coaExists()

    /* ================================================================== */
    /*  Trial Balance                                                      */
    /* ================================================================== */

    public function trialBalance()
    {
        return $this->page('trial-balance', 'Trial Balance', 'Debit or credit balance of every ledger as at a date; the two columns must agree.', 'as_at');
    }

    public function trialBalanceData(Request $request)
    {
        return $this->respond($request, 'trial-balance', 'data');
    }

    public function trialBalancePdf(Request $request)
    {
        return $this->respond($request, 'trial-balance', 'pdf');
    }

    public function trialBalanceExcel(Request $request)
    {
        return $this->respond($request, 'trial-balance', 'excel');
    }

    /* ================================================================== */
    /*  Profit & Loss                                                      */
    /* ================================================================== */

    public function profitLoss()
    {
        return $this->page('profit-loss', 'Profit & Loss', 'Revenue, cost of goods sold, expenses and net profit for a period ('
            . BranchController::visibleLabel() . ').', 'range');
    }

    public function profitLossData(Request $request)
    {
        return $this->respond($request, 'profit-loss', 'data');
    }

    public function profitLossPdf(Request $request)
    {
        return $this->respond($request, 'profit-loss', 'pdf');
    }

    public function profitLossExcel(Request $request)
    {
        return $this->respond($request, 'profit-loss', 'excel');
    }

    /* ================================================================== */
    /*  Balance Sheet                                                      */
    /* ================================================================== */

    public function balanceSheet()
    {
        return $this->page('balance-sheet', 'Balance Sheet', 'Assets, liabilities and equity as at a date, with the Assets = Liabilities + Equity check.', 'as_at');
    }

    public function balanceSheetData(Request $request)
    {
        return $this->respond($request, 'balance-sheet', 'data');
    }

    public function balanceSheetPdf(Request $request)
    {
        return $this->respond($request, 'balance-sheet', 'pdf');
    }

    public function balanceSheetExcel(Request $request)
    {
        return $this->respond($request, 'balance-sheet', 'excel');
    }

    /* ================================================================== */
    /*  Fiscal years / year-end closing                                    */
    /* ================================================================== */

    public function fiscalYears()
    {
        return view('admin.accounting.fiscal-years', [
            'equityLedgers' => DB::table('chart_of_accounts')->where('layer_level', 3)->where('account_type', 'Equity')
                ->where('deleted', 'No')->where('status', 'Active')->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
        ]);
    }

    public function fiscalYearList()
    {
        try {
            $years = DB::table('fiscal_years as f')
                ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'f.retained_earnings_account_id')
                ->leftJoin('users as u', 'u.id', '=', 'f.closed_by')
                ->where('f.deleted', 'No')->orderByDesc('f.start_date')
                ->get(['f.*', 'a.account_name as re_account', 'u.name as closed_by_name']);

            $latestClosed = $years->where('status', 'Closed')->sortByDesc('end_date')->first();
            $earliestOpen = $years->where('status', 'Open')->sortBy('start_date')->first();

            return response()->json([
                'status' => true,
                'data'   => $years->values()->map(fn ($y) => [
                    'id'          => (int) $y->id,
                    'name'        => $y->name,
                    'start'       => Carbon::parse($y->start_date)->format('d M Y'),
                    'end'         => Carbon::parse($y->end_date)->format('d M Y'),
                    'start_date'  => $y->start_date,
                    'end_date'    => $y->end_date,
                    'status'      => $y->status,
                    'net_profit'  => $y->net_profit !== null ? number_format((float) $y->net_profit, 2) : null,
                    'report_net'  => $y->report_net_profit !== null ? number_format((float) $y->report_net_profit, 2) : null,
                    're_account'  => $y->re_account,
                    'closed'      => $y->closed_at ? Carbon::parse($y->closed_at)->format('d M Y h:i A') . ($y->closed_by_name ? ' by ' . $y->closed_by_name : '') : null,
                    'can_close'   => $y->status === 'Open' && $earliestOpen && (int) $earliestOpen->id === (int) $y->id
                        && $y->end_date <= now()->toDateString(),
                    'can_reopen'  => $y->status === 'Closed' && $latestClosed && (int) $latestClosed->id === (int) $y->id,
                    'can_delete'  => $y->status === 'Open',
                ]),
            ]);
        } catch (Throwable $e) {
            Log::error('Fiscal year list error', $this->ctx($e));

            return response()->json(['status' => false, 'data' => [], 'message' => 'Unable to load the fiscal years.'], 500);
        }
    }

    public function fiscalYearStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => ['required', 'string', 'max:60', Rule::unique('fiscal_years', 'name')->where('deleted', 'No')],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'remarks'    => ['nullable', 'string', 'max:500'],
        ], [
            'name.required'       => 'Enter a name, for example FY 2026.',
            'name.unique'         => 'A fiscal year with this name already exists.',
            'start_date.required' => 'Select the first day of the year.',
            'end_date.required'   => 'Select the last day of the year.',
            'end_date.after'      => 'The last day must be after the first day.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $start = Carbon::parse($request->input('start_date'))->toDateString();
            $end   = Carbon::parse($request->input('end_date'))->toDateString();

            $overlap = DB::table('fiscal_years')->where('deleted', 'No')
                ->where('start_date', '<=', $end)->where('end_date', '>=', $start)->value('name');
            if ($overlap) {
                return response()->json(['status' => false, 'message' => 'These dates overlap ' . $overlap . '.',
                    'errors' => ['start_date' => ['These dates overlap ' . $overlap . '.']]], 422);
            }

            $closedAfter = DB::table('fiscal_years')->where('deleted', 'No')->where('status', 'Closed')->where('start_date', '>', $end)->value('name');
            if ($closedAfter) {
                return response()->json(['status' => false, 'message' => 'A later year (' . $closedAfter . ') is already closed; add years in order.'], 422);
            }

            DB::table('fiscal_years')->insert([
                'name'         => trim((string) $request->input('name')),
                'start_date'   => $start,
                'end_date'     => $end,
                'status'       => 'Open',
                'remarks'      => $this->blank($request->input('remarks')),
                'deleted'      => 'No',
                'created_by'   => Auth::id(),
                'created_date' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Fiscal year saved.']);
        } catch (Throwable $e) {
            Log::error('Fiscal year store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the fiscal year.'], 500);
        }
    }

    public function fiscalYearDelete(int $id)
    {
        try {
            $year = DB::table('fiscal_years')->where('id', $id)->where('deleted', 'No')->first();
            if (!$year) {
                return response()->json(['status' => false, 'message' => 'Fiscal year not found.'], 404);
            }
            if ($year->status !== 'Open') {
                return response()->json(['status' => false, 'message' => 'Reopen the year before deleting it.'], 422);
            }

            DB::table('fiscal_years')->where('id', $id)->update([
                'deleted' => 'Yes', 'deleted_by' => Auth::id(), 'deleted_datetime' => now(), 'updated_at' => now(),
            ]);

            return response()->json(['status' => true, 'message' => $year->name . ' deleted.']);
        } catch (Throwable $e) {
            Log::error('Fiscal year delete error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to delete the fiscal year.'], 500);
        }
    }

    /** What closing would post: the year's Income / Expense balances (all branches) and the net to retained earnings. */
    public function fiscalYearPreview(int $id)
    {
        $year = DB::table('fiscal_years')->where('id', $id)->where('deleted', 'No')->first();
        if (!$year) {
            return response()->json(['status' => false, 'message' => 'Fiscal year not found.'], 404);
        }

        try {
            self::$cache = [];
            $plan = self::closingPlan($year);
            $report = self::profitLossFigures($year->start_date, $year->end_date, self::allBranches());

            return response()->json([
                'status' => true,
                'data'   => [
                    'name'       => $year->name,
                    'income'     => number_format($plan['income'], 2),
                    'expense'    => number_format($plan['expense'], 2),
                    'net'        => number_format($plan['net'], 2),
                    'net_raw'    => $plan['net'],
                    'report_net' => number_format($report['net'], 2),
                    'ledgers'    => collect($plan['lines'])->map(fn ($l) => [
                        'code' => $l['code'], 'name' => $l['name'], 'type' => $l['type'], 'balance' => number_format($l['balance'], 2),
                    ])->values(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Fiscal year preview error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to prepare the closing.'], 500);
        }
    }

    /**
     * Closes a year: records the year's net profit (all branches) as moved to the chosen retained earnings ledger and
     * saves a snapshot of every balance as at the year end. The statements apply the closing themselves (balances()):
     * from the day after the year end, the year's Income / Expense balances show in retained earnings instead.
     */
    public function fiscalYearClose(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'retained_earnings_account_id' => ['required', 'integer', $this->coaExists(3, 'Equity')],
        ], [
            'retained_earnings_account_id.required' => 'Select the retained earnings ledger.',
            'retained_earnings_account_id.exists'   => 'Select an Equity ledger (3rd layer).',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            self::$cache = [];
            $year = DB::transaction(function () use ($request, $id) {
                $year = DB::table('fiscal_years')->where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();
                abort_unless($year, 404, 'Fiscal year not found.');
                abort_unless($year->status === 'Open', 422, $year->name . ' is already closed.');
                abort_if($year->end_date > now()->toDateString(), 422, $year->name . ' has not ended yet (it ends on '
                    . Carbon::parse($year->end_date)->format('d M Y') . ').');

                $earlier = DB::table('fiscal_years')->where('deleted', 'No')->where('status', 'Open')
                    ->where('start_date', '<', $year->start_date)->orderBy('start_date')->value('name');
                abort_if($earlier, 422, 'Close ' . $earlier . ' first: years are closed in order.');

                $reId = (int) $request->input('retained_earnings_account_id');
                $plan = self::closingPlan($year);

                // Snapshot (all branches, before closing) and what the closing moves, per Chart of Accounts ledger.
                // Derived lines without a ledger of their own (e.g. cost of goods sold) are kept on their group.
                $before = self::balances($year->end_date, self::allBranches())['acc'];
                $delta = [];
                foreach ($plan['pl'] as $key => $net) {
                    $delta[$key] = -$net;
                }
                $reKey = 'a:' . $reId;
                $delta[$reKey] = ($delta[$reKey] ?? 0) - $plan['net'];

                $chart = self::chart()['rows'];
                $rows = [];
                foreach (array_unique(array_merge(array_keys($before), array_keys($delta))) as $key) {
                    $info = self::info($key);
                    $storeId = str_starts_with($key, 'a:') && isset($chart[(int) substr($key, 2)]) ? (int) substr($key, 2) : $info['group_id'];
                    if (!$storeId || !isset($chart[$storeId])) {
                        continue;
                    }
                    $acct = $chart[$storeId];
                    $sign = $acct->balance === 'Credit' ? -1 : 1;
                    $rows[$storeId] ??= [
                        'fiscal_year_id' => $year->id, 'account_id' => $storeId, 'account_code' => $acct->account_code,
                        'account_name' => $acct->account_name . ($storeId === (int) substr($key, 2) ? '' : ' (derived lines)'),
                        'account_type' => $acct->account_type, 'normal_side' => $acct->balance === 'Credit' ? 'Credit' : 'Debit',
                        'balance' => 0.0, 'closing_delta' => 0.0, 'closed_balance' => 0.0, 'created_at' => now(), 'updated_at' => now(),
                    ];
                    $rows[$storeId]['balance'] = round($rows[$storeId]['balance'] + $sign * ($before[$key] ?? 0), 2);
                    $rows[$storeId]['closing_delta'] = round($rows[$storeId]['closing_delta'] + $sign * ($delta[$key] ?? 0), 2);
                    $rows[$storeId]['closed_balance'] = round($rows[$storeId]['balance'] + $rows[$storeId]['closing_delta'], 2);
                }
                foreach (array_chunk(array_values($rows), 200) as $chunk) {
                    DB::table('fiscal_year_balances')->insert($chunk);
                }

                $now = now();
                DB::table('fiscal_years')->where('id', $year->id)->update([
                    'status'                       => 'Closed',
                    'net_profit'                   => $plan['net'],
                    'report_net_profit'            => self::profitLossFigures($year->start_date, $year->end_date, self::allBranches())['net'],
                    'retained_earnings_account_id' => $reId,
                    'closed_by'                    => Auth::id(),
                    'closed_at'                    => $now,
                    'updated_by'                   => Auth::id(),
                    'updated_date'                 => $now,
                    'updated_at'                   => $now,
                ]);

                return DB::table('fiscal_years')->where('id', $year->id)->first();
            }, 3);

            return response()->json(['status' => true, 'message' => $year->name . ' closed. Net ' . ((float) $year->net_profit >= 0 ? 'profit ' : 'loss ')
                . number_format(abs((float) $year->net_profit), 2) . ' moved to retained earnings.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Fiscal year close error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to close the fiscal year.'], 500);
        }
    }

    /** Reopens the most recently closed year: its profit goes back to the Income / Expense lines. */
    public function fiscalYearReopen(int $id)
    {
        try {
            $year = DB::transaction(function () use ($id) {
                $year = DB::table('fiscal_years')->where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();
                abort_unless($year, 404, 'Fiscal year not found.');
                abort_unless($year->status === 'Closed', 422, $year->name . ' is not closed.');

                $later = DB::table('fiscal_years')->where('deleted', 'No')->where('status', 'Closed')
                    ->where('end_date', '>', $year->end_date)->orderByDesc('end_date')->value('name');
                abort_if($later, 422, 'Reopen ' . $later . ' first: only the most recently closed year can be reopened.');

                DB::table('fiscal_year_balances')->where('fiscal_year_id', $year->id)->delete();
                DB::table('fiscal_years')->where('id', $year->id)->update([
                    'status'       => 'Open',
                    'net_profit'   => null,
                    'report_net_profit' => null,
                    'retained_earnings_account_id' => null,
                    'closed_by'    => null,
                    'closed_at'    => null,
                    'reopened_by'  => Auth::id(),
                    'reopened_at'  => now(),
                    'updated_by'   => Auth::id(),
                    'updated_date' => now(),
                    'updated_at'   => now(),
                ]);

                return $year;
            }, 3);

            return response()->json(['status' => true, 'message' => $year->name . ' reopened; its profit is back in the Income and Expense lines.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Fiscal year reopen error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to reopen the fiscal year.'], 500);
        }
    }

    /** Closing snapshot of a closed year. */
    public function fiscalYearShow(int $id)
    {
        $year = DB::table('fiscal_years as f')->leftJoin('chart_of_accounts as a', 'a.id', '=', 'f.retained_earnings_account_id')
            ->where('f.id', $id)->where('f.deleted', 'No')->first(['f.*', 'a.account_name as re_account']);
        if (!$year) {
            return response()->json(['status' => false, 'message' => 'Fiscal year not found.'], 404);
        }

        $rows = DB::table('fiscal_year_balances')->where('fiscal_year_id', $id)
            ->where(fn ($q) => $q->where('balance', '!=', 0)->orWhere('closing_delta', '!=', 0))
            ->orderBy('account_code')->get();

        return response()->json([
            'status' => true,
            'data'   => [
                'name'       => $year->name,
                'status'     => $year->status,
                'net_profit' => $year->net_profit !== null ? number_format((float) $year->net_profit, 2) : null,
                're_account' => $year->re_account,
                'rows'       => $rows->map(fn ($r) => [
                    'code' => $r->account_code, 'name' => $r->account_name, 'type' => $r->account_type,
                    'balance' => number_format((float) $r->balance, 2), 'delta' => number_format((float) $r->closing_delta, 2),
                    'closed' => number_format((float) $r->closed_balance, 2),
                ]),
            ],
        ]);
    }

    /** True when $date falls inside a closed fiscal year (for blocking back-dated entries). */
    public static function isClosedDate(string $date): bool
    {
        $day = Carbon::parse($date)->toDateString();

        return DB::table('fiscal_years')->where('deleted', 'No')->where('status', 'Closed')
            ->where('start_date', '<=', $day)->where('end_date', '>=', $day)->exists();
    }

    /**
     * Message for editing / deleting a saved document dated $date when that date is in a closed year, else null.
     * (BlockClosedPeriod only sees the date a form posts, not the saved document's original date.)
     */
    public static function closedDateMessage($date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            if (self::isClosedDate((string) $date)) {
                return 'The books are closed for the year that includes ' . Carbon::parse($date)->format('d M Y')
                    . ', so this entry cannot be changed or deleted. Reopen that year in Accounting › Year-end Closing first.';
            }
        } catch (Throwable $e) {
            // Before the fiscal_years table exists: nothing is closed.
        }

        return null;
    }

    /* ================================================================== */
    /*  General ledger built from the documents                            */
    /* ================================================================== */

    /**
     * Control accounts the documents post to: [Chart of Accounts slug (null = none), name, type, normal side, group slug].
     * A key posts to its Chart of Accounts ledger when that ledger exists ('a:<id>'), otherwise it is shown as a
     * derived line 'v:<key>' inside the group.
     */
    private const CONTROL = [
        'ar'           => ['accounts-receivable', 'Accounts Receivable', 'Asset', 'Debit', 'current-asset'],
        'inventory'    => ['inventory', 'Inventory', 'Asset', 'Debit', 'current-asset'],
        'vat_in'       => ['vat-current-account-input-vat', 'VAT Current Account (Input VAT)', 'Asset', 'Debit', 'current-asset'],
        'interbranch'  => [null, 'Inter-branch Stock Transfers', 'Asset', 'Debit', 'current-asset'],
        'suspense'     => [null, 'Suspense: Unmatched Documents', 'Asset', 'Debit', 'current-asset'],
        'suspense_cr'  => [null, 'Suspense: Unmatched Documents', 'Liability', 'Credit', 'current-liability'],
        'sup_adv'      => [null, 'Advances to Suppliers', 'Asset', 'Debit', 'advance-prepayments'],
        'ap'           => ['accounts-payable', 'Accounts Payable', 'Liability', 'Credit', 'current-liability'],
        'vat_out'      => ['vat-payable-output-vat', 'VAT Payable (Output VAT)', 'Liability', 'Credit', 'current-liability'],
        'cust_adv'     => ['customer-advances', 'Customer Advances', 'Liability', 'Credit', 'current-liability'],
        'orders'       => [null, 'Sale Orders Charged, Not Invoiced', 'Liability', 'Credit', 'current-liability'],
        'obe'          => [null, 'Opening Balance Equity', 'Equity', 'Credit', 'owner-s-equity'],
        're'           => ['retained-earnings', 'Retained Earnings', 'Equity', 'Credit', 'reserves-surplus'],
        'sales'        => ['sales-revenue', 'Sales Revenue', 'Income', 'Credit', 'sales-income'],
        'sales_ret'    => ['sales-return-allowances', 'Sales Return & Allowances', 'Income', 'Credit', 'sales-income'],
        'disc_recv'    => ['discount-received', 'Discount Received', 'Income', 'Credit', 'other-income'],
        'cogs'         => [null, 'Cost of Goods Sold', 'Expense', 'Debit', 'cogs'],
        'stock_adj'    => [null, 'Stock Adjustments (damage / count)', 'Expense', 'Debit', 'cogs'],
        'stock_val'    => [null, 'Inventory Valuation Adjustment', 'Expense', 'Debit', 'cogs'],
        'disc_allowed' => [null, 'Discount Allowed', 'Expense', 'Debit', 'selling-marketing'],
    ];

    private const RESULT_TYPES = ['Income', 'Expense'];

    /** Per-request cache of the chart. */
    private static array $cache = [];

    /** Scope that reads every branch (company-wide statements). */
    public static function allBranches(): callable
    {
        return fn ($q, string $col) => $q;
    }

    /** Scope that reads one branch. */
    public static function oneBranch(int $branchId): callable
    {
        return fn ($q, string $col) => $q->where($col, $branchId);
    }

    /** Key of a control account: its Chart of Accounts ledger when it exists, else the derived line. */
    private static function ctl(string $key): string
    {
        $slug = self::CONTROL[$key][0];
        $id = $slug ? (self::chart()['slugs'][$slug] ?? null) : null;

        return $id ? 'a:' . $id : 'v:' . $key;
    }

    /** Cash & bank key exactly as the Cash & Bank Book keys a payment row. */
    private static function cashKey($accountId, $method): string
    {
        return $accountId ? 'a:' . (int) $accountId : 'm:' . (trim((string) $method) ?: 'Unspecified');
    }

    /**
     * The general ledger, built at report time from the operational tables (the single source of truth):
     * net (debit − credit) per account for everything dated $from (null = from the beginning, with opening balances)
     * to $to inclusive, in the branches $scope($query, 'x.branch_id') reads.
     *
     * Each document posts both sides, so the ledger always balances:
     * - payments (party sub-ledger rows): the party side exactly as the Customer / Supplier ledgers read it, the money
     *   side exactly as the Cash & Bank Book reads it; the rest goes to the document (sale, return, purchase, bill, ...).
     * - sales Cr Sales Revenue (net of VAT) + Output VAT; sale returns the reverse into Sales Return & Allowances;
     *   purchases Dr Inventory (net of VAT) + Input VAT; purchase returns Cr Inventory; bills Dr their expense lines.
     *   A document whose party rows do not add up to it leaves the difference in Suspense (listed in the notes).
     * - cost of goods sold = sale lines qty × saved purchase price (less returned lines); stock adjustments; opening
     *   stock and branch transfers at the Stock Valuation price; Inventory is then set to the Stock Valuation total
     *   and the remaining difference is the Inventory Valuation Adjustment.
     * - expenses, journals, amount transfers, payroll runs, salary payments, employee loans and suspense entries (advances,
 *   investor capital) as their screens post them.
     * - opening balances (Chart of Accounts ledgers, parties) against Opening Balance Equity, in the main branch
     *   (a branch's own cash ledger keeps its opening in that branch), as the Cash & Bank Book and party ledgers do.
     *
     * @return array{acc: array<string, float>, party: array<int, float>, ptype: array<int, string>, suspense: array<string, float>}
     */
    public static function gl(?string $from, string $to, callable $scope): array
    {
        $acc = $party = $ptype = $susp = [];
        $add = function (string $key, float $net) use (&$acc) {
            if (abs($net) >= 0.000001) {
                $acc[$key] = ($acc[$key] ?? 0) + $net;
            }
        };
        $toSuspense = function (string $why, float $net) use ($add, &$susp) {
            if (abs($net) >= 0.005) {
                $add(self::ctl('suspense'), $net);
                $susp[$why] = round(($susp[$why] ?? 0) + $net, 2);
            }
        };
        $win = function ($q, string $col) use ($from, $to) {
            if ($from !== null) {
                $q->whereRaw("DATE($col) >= ?", [$from]);
            }

            return $q->whereRaw("DATE($col) <= ?", [$to]);
        };
        $ledger = fn ($id) => $id ? 'a:' . (int) $id : null;

        // What each document group still has to post (debit − credit) to match its party / money rows.
        $pending = ['Sales' => 0.0, 'Sale returns' => 0.0, 'Purchases' => 0.0, 'Purchase returns' => 0.0, 'Bills' => 0.0,
            'Expenses' => 0.0, 'Journals' => 0.0, 'Payroll' => 0.0, 'Other payment rows' => 0.0];
        $customerTypes = ReportController::PARTY_LEDGERS['customer']['types'];
        $supplierTypes = ReportController::PARTY_LEDGERS['supplier']['types'];

        // 1. Payments: party side (party ledgers' rule) and money side (Cash & Bank Book's rule).
        $rows = $win($scope(DB::table('payments as p'), 'p.branch_id'), 'p.datetime')
            ->leftJoin('parties as pa', 'pa.id', '=', 'p.party_id')
            ->where('p.deleted', 'No')->where('p.status', 'Active')
            ->groupBy('p.payment_type', 'p.party_id', 'p.party_type', 'pa.type', 'pa.deleted', 'p.payment_method_account_id', 'p.payment_method',
                DB::raw('(p.purchase_return_id IS NOT NULL)'), DB::raw('(p.sale_id IS NOT NULL)'))
            ->selectRaw('p.payment_type, p.party_id, p.party_type, pa.type AS ptype, pa.deleted AS pdeleted, p.payment_method_account_id AS acc,
                p.payment_method AS method, (p.purchase_return_id IS NOT NULL) AS pr, (p.sale_id IS NOT NULL) AS linked, SUM(COALESCE(p.debit, 0)) AS d, SUM(COALESCE(p.credit, 0)) AS c')
            ->get();
        foreach ($rows as $r) {
            $type = (string) $r->payment_type;
            $d = (float) $r->d;
            $c = (float) $r->c;
            $pr = (bool) $r->pr;

            $kind = null;
            if ($r->party_type !== 'Employee' && $r->ptype !== null && $r->pdeleted === 'No') {
                $kind = in_array($r->ptype, $customerTypes, true) ? 'customer' : (in_array($r->ptype, $supplierTypes, true) ? 'supplier' : null);
            }
            if ($r->party_type === 'Employee') {
                $partyNet = 0.0;   // expense copies to employees: not in the party ledgers
            } elseif ($pr) {
                $partyNet = $type === 'Purchase Payment' ? $d + $c : -($d + $c);
            } else {
                $partyNet = $c - $d;
            }
            if ($kind) {
                $party[(int) $r->party_id] = ($party[(int) $r->party_id] ?? 0) + $partyNet;
                $ptype[(int) $r->party_id] = $kind;
            } else {
                $toSuspense('Payment rows of unknown or deleted parties', $partyNet);
            }

            $cash = 0.0;
            if ($pr) {
                $cash = $type === 'Payment' ? $c + $d : 0.0;
            } elseif (in_array($type, ReportController::LIQUID_RECEIVE_TYPES, true)) {
                $cash = $d;
            } elseif (in_array($type, ReportController::LIQUID_PAY_TYPES, true)) {
                $cash = -$c;
            }
            if (abs($cash) >= 0.005) {
                $add(self::cashKey($r->acc, $r->method), $cash);
            }

            $rest = -($partyNet + $cash);
            if (abs($rest) < 0.005) {
                continue;
            }
            if ($pr) {
                $type === 'Purchase Payment' ? $pending['Purchase returns'] += $rest : $pending['Other payment rows'] += $rest;
            } elseif (in_array($type, ['Walk-In', 'Wholesale'], true) || ($type === 'Sale Order' && $r->linked)) {
                $pending['Sales'] += $rest;
            } elseif (in_array($type, ['Walk-In Sale Return', 'Wholesale Return'], true)) {
                $pending['Sale returns'] += $rest;
            } elseif ($type === 'Purchase') {
                $pending['Purchases'] += $rest;
            } elseif ($type === 'Bill') {
                $pending['Bills'] += $rest;
            } elseif ($type === 'Sale Order') {
                $add(self::ctl('orders'), $rest);   // orders charged in full when saved, without an invoice (old order flow)
            } elseif ($type === 'Discount') {
                $add(self::ctl($kind === 'supplier' ? 'disc_recv' : 'disc_allowed'), $rest);
            } else {
                $pending['Other payment rows'] += $rest;
            }
        }

        // 2. Sales and sale returns (net of VAT), cost of goods sold.
        $s = $win($scope(DB::table('sales as s'), 's.branch_id'), 's.sale_date')->where('s.deleted', 'No')->where('s.status', 'Active')
            ->selectRaw('COALESCE(SUM(s.grand_total), 0) g, COALESCE(SUM(s.vat_amount), 0) v')->first();
        $add(self::ctl('sales'), -((float) $s->g - (float) $s->v));
        $add(self::ctl('vat_out'), -(float) $s->v);
        $pending['Sales'] += (float) $s->g;

        $r = $win($scope(DB::table('sale_returns as r'), 'r.branch_id'), 'r.datetime')->where('r.deleted', 'No')->where('r.status', 'Active')
            ->selectRaw('COALESCE(SUM(r.grand_total), 0) g, COALESCE(SUM(r.vat_amount), 0) v')->first();
        $add(self::ctl('sales_ret'), (float) $r->g - (float) $r->v);
        $add(self::ctl('vat_out'), (float) $r->v);
        $pending['Sale returns'] -= (float) $r->g;

        $cost = (float) $win($scope(DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id'), 's.branch_id'), 's.sale_date')
            ->where('s.deleted', 'No')->where('s.status', 'Active')->where('si.deleted', 'No')->sum(DB::raw('si.qty * si.purchase_price'));
        $returnedCost = (float) $win($scope(DB::table('sale_return_items as ri')->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->join('sale_items as si', 'si.id', '=', 'ri.sale_item_id'), 'r.branch_id'), 'r.datetime')
            ->where('r.deleted', 'No')->where('r.status', 'Active')->where('ri.deleted', 'No')->sum(DB::raw('ri.qty * si.purchase_price'));
        $add(self::ctl('cogs'), round($cost - $returnedCost, 2));
        $add(self::ctl('inventory'), -round($cost - $returnedCost, 2));

        // 3. Purchases (a purchase's grand total is lowered by its returns, which post on their own) and purchase returns.
        $returned = DB::table('purchase_returns')->where('deleted', 'No')->groupBy('purchase_id')->selectRaw('purchase_id, SUM(grand_total) amt');
        $p = $win($scope(DB::table('purchases as pu'), 'pu.branch_id'), 'pu.purchase_date')->leftJoinSub($returned, 'rt', 'rt.purchase_id', '=', 'pu.id')
            ->where('pu.deleted', 'No')->where('pu.status', 'Active')
            ->selectRaw('COALESCE(SUM(pu.grand_total + COALESCE(rt.amt, 0)), 0) g, COALESCE(SUM(pu.vat_amount), 0) v')->first();
        $add(self::ctl('inventory'), (float) $p->g - (float) $p->v);
        $add(self::ctl('vat_in'), (float) $p->v);
        $pending['Purchases'] -= (float) $p->g;

        $pr = (float) $win($scope(DB::table('purchase_returns as r'), 'r.branch_id'), 'r.datetime')->where('r.deleted', 'No')->sum('r.grand_total');
        $add(self::ctl('inventory'), -$pr);
        $pending['Purchase returns'] += $pr;

        // 4. Bills: expense lines net of the bill discount.
        foreach (self::billDebits($win, $scope) as $bill) {
            foreach ($bill['debits'] as $id => $amt) {
                $add('a:' . $id, $amt);
                $pending['Bills'] -= $amt;
            }
        }

        // 5. Expenses: Dr expense heads / Cr the payment account (Cash & Bank Book rule).
        foreach ($win($scope(DB::table('expenses as e'), 'e.branch_id'), 'e.expense_date')->where('e.deleted', 'No')->whereNotNull('e.payment_account_id')
            ->groupBy('e.payment_account_id')->selectRaw('e.payment_account_id id, SUM(e.total_amount) amt')->get() as $e) {
            $add('a:' . $e->id, -(float) $e->amt);
            $pending['Expenses'] += (float) $e->amt;
        }
        foreach ($win($scope(DB::table('expense_details as d')->join('expenses as e', 'e.id', '=', 'd.expense_id'), 'e.branch_id'), 'e.expense_date')
            ->where('e.deleted', 'No')->where('d.deleted', 'No')
            ->groupBy('d.expense_account_id')->selectRaw('d.expense_account_id id, SUM(d.amount) amt')->get() as $e) {
            if ($key = $ledger($e->id)) {
                $add($key, (float) $e->amt);
                $pending['Expenses'] -= (float) $e->amt;
            }
        }

        // 6. Journals and amount transfers.
        foreach ($win($scope(DB::table('journal_details as jd')->join('journals as j', 'j.id', '=', 'jd.journal_id'), 'j.branch_id'), 'j.journal_date')
            ->where('j.deleted', 'No')->where('j.status', 'Active')->where('jd.deleted', 'No')->where('jd.status', 'Active')
            ->groupBy('jd.account_id')->selectRaw('jd.account_id id, SUM(jd.debit) dr, SUM(jd.credit) cr')->get() as $j) {
            $net = (float) $j->dr - (float) $j->cr;
            if ($key = $ledger($j->id)) {
                $add($key, $net);
                $pending['Journals'] -= $net;
            }
        }
        foreach ($win($scope(DB::table('internal_amount_transfers as t'), 't.branch_id'), 't.transfer_date')->where('t.deleted', 'No')->where('t.status', 'Active')
            ->groupBy('t.from_account_id', 't.to_account_id')->selectRaw('t.from_account_id f, t.to_account_id t, SUM(t.amount) amt')->get() as $t) {
            $add('a:' . $t->t, (float) $t->amt);
            $add('a:' . $t->f, -(float) $t->amt);
        }

        // 7. Payroll: runs (Dr salary expense / Cr salary payable, PF payable, employee loans), salary payments, loans.
        foreach ($win($scope(DB::table('payroll_runs as r'), 'r.branch_id'), 'r.run_date')->where('r.run_status', 'Posted')
            ->get(['r.expense_account_id', 'r.payable_account_id', 'r.pf_account_id', 'r.loan_account_id', 'r.total_net', 'r.total_pf', 'r.total_loan']) as $run) {
            foreach ([[$run->expense_account_id, (float) $run->total_net + (float) $run->total_pf + (float) $run->total_loan],
                [$run->payable_account_id, -(float) $run->total_net], [$run->pf_account_id, -(float) $run->total_pf],
                [$run->loan_account_id, -(float) $run->total_loan]] as [$id, $amt]) {
                if (abs($amt) < 0.005) {
                    continue;
                }
                $key = $ledger($id);
                $key ? $add($key, $amt) : $toSuspense('Payroll runs without a ledger', $amt);
            }
        }
        foreach ($win($scope(DB::table('salary_payments as s'), 's.branch_id'), 's.payment_date')->leftJoin('payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
            ->where('s.payment_status', 'Posted')
            ->groupBy('s.payment_account_id', 'r.payable_account_id')->selectRaw('s.payment_account_id cash, r.payable_account_id payable, SUM(s.total_paid) amt')->get() as $sp) {
            $add('a:' . $sp->cash, -(float) $sp->amt);
            $key = $ledger($sp->payable);
            $key ? $add($key, (float) $sp->amt) : $toSuspense('Salary payments without a payroll run', (float) $sp->amt);
        }
        foreach ($win($scope(DB::table('employee_loan_transactions as t')->join('employee_loans as l', 'l.id', '=', 't.employee_loan_id'), 'l.branch_id'), 't.txn_date')
            ->whereIn('t.txn_type', ['Disbursement', 'Cash Repayment'])->where('t.txn_status', 'Posted')->whereNotNull('t.payment_account_id')
            ->groupBy('t.txn_type', 't.payment_account_id', 'l.loan_account_id')
            ->selectRaw('t.txn_type, t.payment_account_id cash, l.loan_account_id loan, SUM(t.amount) amt')->get() as $l) {
            $amt = $l->txn_type === 'Disbursement' ? (float) $l->amt : -(float) $l->amt;
            $add('a:' . $l->cash, -$amt);
            $key = $ledger($l->loan);
            $key ? $add($key, $amt) : $toSuspense('Employee loans without a ledger', $amt);
        }

        // 7b. Suspense module (SuspenseController): each entry debits dr_account_id and credits cr_account_id —
        // advances in the Suspense Account, investor capital in Owner's Capital, adjustments in expenses / drawings,
        // the cash / bank side as the Cash & Bank Book reads it.
        foreach ($win($scope(DB::table('suspense_transactions as su'), 'su.branch_id'), 'su.txn_date')->where('su.deleted', 'No')
            ->groupBy('su.dr_account_id', 'su.cr_account_id')->selectRaw('su.dr_account_id dr, su.cr_account_id cr, SUM(su.amount) amt')->get() as $su) {
            $add('a:' . $su->dr, (float) $su->amt);
            $add('a:' . $su->cr, -(float) $su->amt);
        }

        // 8. Stock: adjustments at their value, opening stock and branch transfers at the Stock Valuation price.
        $adj = (float) $win($scope(DB::table('stock_adjustments as a'), 'a.branch_id'), 'a.adjustment_date')
            ->where('a.deleted', 'No')->where('a.adjustment_status', 'Posted')->sum('a.value_change');
        $add(self::ctl('inventory'), $adj);
        $add(self::ctl('stock_adj'), -$adj);
        foreach ($win($scope(DB::table('stocks as st')->join('products as pr', 'pr.id', '=', 'st.product_id'), 'st.branch_id'), 'st.date')
            ->where('st.deleted', 'No')->where('pr.deleted', 'No')->whereIn('st.reference_type', ['Opening Stock', 'Transfer In', 'Transfer Out'])
            ->groupBy('st.reference_type')->selectRaw('st.reference_type t, SUM((st.in_qty - st.out_qty) * pr.purchase_price) v')->get() as $st) {
            $v = round((float) $st->v, 2);
            $add(self::ctl('inventory'), $v);
            $add(self::ctl($st->t === 'Opening Stock' ? 'obe' : 'interbranch'), -$v);
        }

        // Inventory = the Stock Valuation total; the rest is the valuation adjustment.
        $valuation = BusinessReportController::stockValueAt($to, $scope)
            - ($from !== null ? BusinessReportController::stockValueAt(Carbon::parse($from)->subDay()->toDateString(), $scope) : 0.0);
        $plug = round($valuation - ($acc[self::ctl('inventory')] ?? 0), 2);
        $add(self::ctl('inventory'), $plug);
        $add(self::ctl('stock_val'), -$plug);

        // 9. Opening balances.
        $branches = array_map('intval', $scope(DB::table('branches as b'), 'b.id')->pluck('b.id')->all());
        $mainId = (int) BranchController::mainId();
        $mainIn = in_array($mainId, $branches, true);
        if ($from === null) {
            foreach (DB::table('chart_of_accounts')->where('deleted', 'No')->whereIn('layer_level', [2, 3])->where('opening_balance', '!=', 0)
                ->get(['id', 'layer_level', 'account_type', 'balance', 'branch_id', 'opening_balance']) as $a) {
                $liquid = $a->account_type === 'Liquid Asset';
                if ($a->layer_level == 2 && $liquid) {
                    continue;   // not a ledger; the Cash & Bank Book does not count it either
                }
                $owner = $liquid && $a->branch_id ? (int) $a->branch_id : $mainId;
                if (!in_array($owner, $branches, true)) {
                    continue;
                }
                $signed = ($a->balance === 'Credit' ? -1 : 1) * (float) $a->opening_balance;
                $add(($a->layer_level == 2 ? 'g:' : 'a:') . $a->id, $signed);
                $add(self::ctl('obe'), -$signed);
            }
        }
        if ($mainIn) {
            foreach (DB::table('parties')->where('deleted', 'No')->whereIn('type', array_merge($customerTypes, $supplierTypes))->where('opening_balance', '!=', 0)
                ->get(['id', 'type', 'opening_balance', 'opening_balance_type', 'opening_balance_date']) as $pt) {
                $date = $pt->opening_balance_date ? Carbon::parse($pt->opening_balance_date)->toDateString() : null;
                $in = $date === null ? $from === null : ($date <= $to && ($from === null || $date >= $from));
                if (!$in) {
                    continue;
                }
                $customer = in_array($pt->type, $customerTypes, true);
                $signed = ($customer === ($pt->opening_balance_type !== 'Advance') ? 1 : -1) * (float) $pt->opening_balance;
                $party[(int) $pt->id] = ($party[(int) $pt->id] ?? 0) + $signed;
                $ptype[(int) $pt->id] = $customer ? 'customer' : 'supplier';
                $add(self::ctl('obe'), -$signed);
            }
            // Investors' opening capital: moved from Opening Balance Equity to Owner's Capital (dated like a party opening).
            $capital = SuspenseController::ledgers()['capital'];
            foreach ($capital ? DB::table('parties')->where('deleted', 'No')->where('type', 'Investor')->where('opening_capital', '!=', 0)
                ->get(['opening_capital', 'opening_balance_date']) : [] as $iv) {
                $date = $iv->opening_balance_date ? Carbon::parse($iv->opening_balance_date)->toDateString() : null;
                if ($date === null ? $from === null : ($date <= $to && ($from === null || $date >= $from))) {
                    $add('a:' . $capital, -(float) $iv->opening_capital);
                    $add(self::ctl('obe'), (float) $iv->opening_capital);
                }
            }
        }

        foreach ($pending as $why => $net) {
            $toSuspense($why . ' not matching their party / money rows', $net);
        }

        return [
            'acc'      => array_map(fn ($v) => round($v, 2), $acc),
            'party'    => array_map(fn ($v) => round($v, 2), $party),
            'ptype'    => $ptype,
            'suspense' => array_filter($susp, fn ($v) => abs($v) >= 0.005),
        ];
    }

    /** Income / Expense part of a ledger map. */
    private static function resultPart(array $acc): array
    {
        return array_filter($acc, fn ($v, $k) => in_array(self::info($k)['type'], self::RESULT_TYPES, true), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Balances (debit − credit) as at the end of $asAt in the branches $scope reads, as the Trial Balance and Balance Sheet
     * show them: party balances in Accounts Receivable / Customer Advances / Accounts Payable / Advances to Suppliers, and
     * the Income / Expense balances of closed years (closed the day after the year end) moved to retained earnings.
     *
     * @return array{acc: array<string, float>, profit: float, closed: ?object, parties: array, suspense: array}
     */
    public static function balances(string $asAt, callable $scope): array
    {
        $g = self::gl(null, $asAt, $scope);
        $acc = $g['acc'];

        $years = DB::table('fiscal_years')->where('deleted', 'No')->where('status', 'Closed')->where('end_date', '<', $asAt)
            ->orderBy('end_date')->get(['name', 'end_date', 'retained_earnings_account_id']);
        $prevPl = [];
        $prevNet = 0.0;
        foreach ($years as $y) {
            $pl = self::resultPart(self::gl(null, $y->end_date, $scope)['acc']);
            $net = -array_sum($pl);
            $re = $y->retained_earnings_account_id ? 'a:' . $y->retained_earnings_account_id : self::ctl('re');
            $acc[$re] = ($acc[$re] ?? 0) - ($net - $prevNet);
            $prevNet = $net;
            $prevPl = $pl;
        }
        foreach ($prevPl as $k => $v) {
            $acc[$k] = ($acc[$k] ?? 0) - $v;
        }

        // Postings on the party control ledgers that are not party rows (e.g. a payroll run or journal using Accounts Payable).
        $other = [];
        foreach (['ar', 'cust_adv', 'ap', 'sup_adv'] as $c) {
            if (abs($g['acc'][self::ctl($c)] ?? 0) >= 0.005) {
                $other[self::info(self::ctl($c))['name']] = $g['acc'][self::ctl($c)] * (self::info(self::ctl($c))['normal'] === 'Credit' ? -1 : 1);
            }
        }

        $parties = ['customer' => ['dr' => 0.0, 'cr' => 0.0], 'supplier' => ['dr' => 0.0, 'cr' => 0.0]];
        foreach ($g['party'] as $id => $net) {
            if (abs($net) < 0.005) {
                continue;
            }
            $kind = $g['ptype'][$id];
            $key = $kind === 'customer' ? self::ctl($net > 0 ? 'ar' : 'cust_adv') : self::ctl($net > 0 ? 'sup_adv' : 'ap');
            $acc[$key] = ($acc[$key] ?? 0) + $net;
            $parties[$kind][$net > 0 ? 'dr' : 'cr'] += abs($net);
        }

        // A credit suspense balance is shown with the liabilities.
        $susp = self::ctl('suspense');
        if (($acc[$susp] ?? 0) < 0) {
            $acc[self::ctl('suspense_cr')] = $acc[$susp];
            unset($acc[$susp]);
        }

        $acc = array_map(fn ($v) => round($v, 2), $acc);

        return [
            'acc'      => $acc,
            'profit'   => round(-array_sum(self::resultPart($acc)), 2),
            'closed'   => $years->last(),
            'parties'  => array_map(fn ($p) => array_map(fn ($v) => round($v, 2), $p), $parties),
            'other'    => array_map(fn ($v) => round($v, 2), $other),
            'suspense' => $g['suspense'],
        ];
    }

    /** The year's Income / Expense balances (all branches, since the previous closing) and the net they make. */
    private static function closingPlan(object $year): array
    {
        $prevEnd = DB::table('fiscal_years')->where('deleted', 'No')->where('status', 'Closed')->where('id', '<>', $year->id)
            ->where('end_date', '<', $year->end_date)->max('end_date');
        $pl = self::resultPart(self::gl($prevEnd ? Carbon::parse($prevEnd)->addDay()->toDateString() : null, $year->end_date, self::allBranches())['acc']);

        $lines = [];
        $income = $expense = 0.0;
        foreach (self::sortKeys(array_keys($pl)) as $key) {
            $net = $pl[$key];
            if (abs($net) < 0.005) {
                continue;
            }
            $info = self::info($key);
            $info['type'] === 'Income' ? $income -= $net : $expense += $net;
            $lines[] = ['key' => $key, 'code' => $info['code'], 'name' => $info['name'], 'type' => $info['type'],
                'balance' => round($info['normal'] === 'Credit' ? -$net : $net, 2)];
        }

        return ['pl' => $pl, 'lines' => $lines, 'income' => round($income, 2), 'expense' => round($expense, 2), 'net' => round($income - $expense, 2)];
    }

    /**
     * Each live bill's expense debits net of the bill discount, as BillController::expenseDebits() posts them.
     * $range filters bill_date; $scope limits the branches.
     */
    private static function billDebits(callable $range, callable $scope): array
    {
        $bills = $range($scope(DB::table('bills as b'), 'b.branch_id'), 'b.bill_date')->where('b.deleted', 'No')
            ->get(['b.id', 'b.subtotal', 'b.grand_total']);
        if ($bills->isEmpty()) {
            return [];
        }

        $lines = DB::table('bill_details')->whereIn('bill_id', $bills->pluck('id'))->where('deleted', 'No')
            ->get(['bill_id', 'account_id', 'amount'])->groupBy('bill_id');

        $out = [];
        foreach ($bills as $b) {
            $gross = [];
            foreach ($lines->get($b->id, collect()) as $l) {
                $gross[(int) $l->account_id] = round(($gross[(int) $l->account_id] ?? 0) + (float) $l->amount, 2);
            }
            $gross = array_filter($gross, fn ($a) => abs($a) >= 0.005);
            $subtotal = (float) $b->subtotal;
            $grand = (float) $b->grand_total;
            if (!$gross || $subtotal <= 0) {
                $out[] = ['debits' => [], 'total' => $grand];
                continue;
            }
            $factor = $grand / $subtotal;
            $net = array_map(fn ($a) => round($a * $factor, 2), $gross);
            $diff = round($grand - array_sum($net), 2);
            if (abs($diff) >= 0.005) {
                $largest = array_search(max($net), $net, true);
                $net[$largest] = round($net[$largest] + $diff, 2);
            }
            $out[] = ['debits' => $net, 'total' => $grand];
        }

        return $out;
    }

    /* ================================================================== */
    /*  Account names and places in the chart                              */
    /* ================================================================== */

    /** The Chart of Accounts (cached per request): rows by id, ledger ids by slug, group ids by slug. */
    private static function chart(): array
    {
        if (!isset(self::$cache['chart'])) {
            $rows = DB::table('chart_of_accounts')->orderBy('account_code')
                ->get(['id', 'parent_id', 'layer_level', 'account_code', 'account_name', 'account_type', 'balance', 'slug', 'status', 'deleted'])->keyBy('id');
            $live = $rows->where('deleted', 'No');
            self::$cache['chart'] = [
                'rows'   => $rows->all(),
                'slugs'  => $live->where('layer_level', 3)->pluck('id', 'slug')->all(),
                'groups' => $live->where('layer_level', 2)->pluck('id', 'slug')->all(),
            ];
        }

        return self::$cache['chart'];
    }

    /**
     * Name and place of an account key: 'a:<id>' Chart of Accounts ledger, 'v:<control>' derived line, 'm:<method>'
     * money without an account (Cash & Bank Book "— no account"), 'g:<id>' opening balance typed on a group.
     */
    private static function info(string $key): array
    {
        if (isset(self::$cache['info'][$key])) {
            return self::$cache['info'][$key];
        }

        $chart = self::chart();
        $rows = $chart['rows'];
        [$kind, $ref] = explode(':', $key, 2) + [1 => ''];
        $group = null;
        $code = null;
        $order = 1;

        if ($kind === 'a' && isset($rows[(int) $ref])) {
            $a = $rows[(int) $ref];
            $name = $a->account_name . ($a->deleted === 'Yes' ? ' (deleted)' : ($a->status !== 'Active' ? ' (inactive)' : ''));
            $type = $a->account_type;
            $normal = $a->balance === 'Credit' ? 'Credit' : 'Debit';
            $code = $a->account_code;
            $order = 0;
            $group = (int) $a->layer_level === 3 ? ($rows[$a->parent_id] ?? null) : ((int) $a->layer_level === 2 ? $a : null);
        } elseif ($kind === 'v') {
            [, $name, $type, $normal, $groupSlug] = self::CONTROL[$ref];
            $group = isset($chart['groups'][$groupSlug]) ? $rows[$chart['groups'][$groupSlug]] : null;
        } elseif ($kind === 'g' && isset($rows[(int) $ref])) {
            $group = $rows[(int) $ref];
            $name = $group->account_name . ' (opening balance entered on the group)';
            $type = $group->account_type;
            $normal = $group->balance === 'Credit' ? 'Credit' : 'Debit';
        } elseif ($kind === 'm') {
            $name = $ref . ' — no account';
            $type = 'Liquid Asset';
            $normal = 'Debit';
            $group = collect($rows)->first(fn ($r) => (int) $r->layer_level === 2 && $r->account_type === 'Liquid Asset' && $r->deleted === 'No'
                && strcasecmp($r->account_name, $ref) === 0)
                ?? collect($rows)->first(fn ($r) => (int) $r->layer_level === 2 && $r->account_type === 'Liquid Asset' && $r->deleted === 'No');
        } else {
            $name = 'Account #' . $ref;
            $type = 'Asset';
            $normal = 'Debit';
        }

        $root = $group ? ($rows[$group->parent_id] ?? null) : null;
        if (!$root) {
            $root = collect($rows)->first(fn ($r) => (int) $r->layer_level === 1 && $r->account_type === $type && $r->deleted === 'No');
        }

        return self::$cache['info'][$key] = [
            'key' => $key, 'code' => $code, 'name' => $name, 'type' => $type, 'normal' => $normal,
            'group_id' => $group->id ?? null, 'group_code' => $group->account_code ?? null, 'group_name' => $group->account_name ?? null,
            'group_slug' => $group->slug ?? null,
            'root_id' => $root->id ?? null, 'root_code' => $root->account_code ?? '9', 'root_name' => $root->account_name ?? $type,
            'sort' => ($root->account_code ?? '9') . '|' . ($group->account_code ?? '9') . '|' . $order . '|' . ($code ?? $name),
        ];
    }

    /** Keys in chart order. */
    private static function sortKeys(array $keys): array
    {
        usort($keys, fn ($a, $b) => strcmp(self::info($a)['sort'], self::info($b)['sort']));

        return $keys;
    }

    /* ================================================================== */
    /*  Profit & Loss figures                                              */
    /* ================================================================== */

    /**
     * Profit and loss for [$from, $to] in the branches $scope reads (default: BranchController::scope, the branches
     * the user is viewing): the Income / Expense side of the same general ledger as the Trial Balance and Balance Sheet.
     * Amounts are positive when they add to their section (income credit, expense debit).
     */
    public static function profitLossFigures(string $from, string $to, ?callable $scope = null): array
    {
        $scope ??= fn ($q, string $col) => BranchController::scope($q, $col);
        $pl = self::resultPart(self::gl($from, $to, $scope)['acc']);
        $revenueKeys = [self::ctl('sales'), self::ctl('sales_ret')];
        $cogsKeys = [self::ctl('cogs'), self::ctl('stock_adj'), self::ctl('stock_val')];

        $out = ['revenue' => [], 'cogs' => [], 'other' => [], 'opex' => []];
        foreach (self::sortKeys(array_keys($pl)) as $key) {
            $info = self::info($key);
            $income = $info['type'] === 'Income';
            $amt = round($income ? -$pl[$key] : $pl[$key], 2);
            if (abs($amt) < 0.005) {
                continue;
            }
            $section = match (true) {
                $income && (in_array($key, $revenueKeys, true) || $info['group_slug'] === 'sales-income') => 'revenue',
                $income                                                                                => 'other',
                in_array($key, $cogsKeys, true) || $info['group_slug'] === 'cogs'                       => 'cogs',
                default                                                                                => 'opex',
            };
            $out[$section][$key] = $amt;
        }

        $revenue = round(array_sum($out['revenue']), 2);
        $cogs = round(array_sum($out['cogs']), 2);
        $gross = round($revenue - $cogs, 2);
        $other = round(array_sum($out['other']), 2);
        $opex = round(array_sum($out['opex']), 2);

        return $out + [
            'sales'      => $out['revenue'][self::ctl('sales')] ?? 0.0,
            'returns'    => -($out['revenue'][self::ctl('sales_ret')] ?? 0.0),
            'revenueTotal' => $revenue, 'cogsTotal' => $cogs, 'gross' => $gross, 'otherTotal' => $other, 'opexTotal' => $opex,
            'net'        => round($gross + $other - $opex, 2),
        ];
    }

    /* ================================================================== */
    /*  Statement builders                                                 */
    /* ================================================================== */

    /** [scope, label] for the Trial Balance / Balance Sheet branch filter (empty = all branches). */
    private static function branchFilter(array $in): array
    {
        $id = (int) ($in['branch_id'] ?? 0);

        return $id ? [self::oneBranch($id), BranchController::name($id)] : [self::allBranches(), 'All branches'];
    }

    private function buildTrialBalance(array $in): array
    {
        $asAt = Carbon::parse($in['as_at'] ?? now()->toDateString())->startOfDay();
        $hideZero = (bool) ($in['hide_zero'] ?? true);
        [$scope, $branch] = self::branchFilter($in);
        $b = self::balances($asAt->toDateString(), $scope);

        $sections = [];
        $totDr = $totCr = 0.0;
        $keys = collect(self::sortKeys(array_keys(array_filter($b['acc'], fn ($v) => !$hideZero || abs($v) >= 0.005))));
        foreach ($keys->groupBy(fn ($k) => self::info($k)['root_code']) as $rootKeys) {
            $first = self::info($rootKeys->first());
            $lines = [];
            $secDr = $secCr = 0.0;
            foreach ($rootKeys->groupBy(fn ($k) => self::info($k)['group_code'] ?? '') as $groupKeys) {
                $g = self::info($groupKeys->first());
                $lines[] = ['kind' => 'group', 'code' => $g['group_code'], 'label' => $g['group_name'] ?: 'Ungrouped'];
                foreach ($groupKeys as $k) {
                    $signed = $b['acc'][$k] ?? 0.0;
                    $dr = $signed > 0 ? $signed : 0.0;
                    $cr = $signed < 0 ? -$signed : 0.0;
                    $secDr += $dr;
                    $secCr += $cr;
                    $lines[] = ['kind' => 'line', 'code' => self::info($k)['code'], 'label' => self::info($k)['name'], 'values' => [$dr ?: null, $cr ?: null], 'indent' => 1];
                }
            }
            $lines[] = ['kind' => 'subtotal', 'label' => 'Total ' . $first['root_name'], 'values' => [round($secDr, 2), round($secCr, 2)]];
            $sections[] = ['title' => $first['root_name'], 'lines' => $lines];
            $totDr += $secDr;
            $totCr += $secCr;
        }
        $totDr = round($totDr, 2);
        $totCr = round($totCr, 2);
        $diff = round($totDr - $totCr, 2);

        return $this->statement([
            'key'      => 'trial-balance',
            'title'    => 'Trial Balance',
            'period'   => 'As at ' . $asAt->format('d M Y'),
            'columns'  => ['Debit', 'Credit'],
            'sections' => $sections,
            'grand'    => ['label' => 'Total', 'values' => [$totDr, $totCr]],
            'checks'   => [['label' => 'Debit = Credit', 'ok' => abs($diff) < 0.01,
                'detail' => number_format($totDr, 2) . ' vs ' . number_format($totCr, 2) . (abs($diff) < 0.01 ? '' : ' (difference ' . number_format($diff, 2) . ')')]],
            'summary'  => [
                ['label' => 'Total Debit', 'value' => $totDr, 'icon' => 'fas fa-arrow-down', 'tone' => 'i1'],
                ['label' => 'Total Credit', 'value' => $totCr, 'icon' => 'fas fa-arrow-up', 'tone' => 'i4'],
                ['label' => 'Difference', 'value' => $diff, 'icon' => 'fas fa-balance-scale', 'tone' => abs($diff) < 0.01 ? 'i4' : 'i2'],
                ['label' => 'Ledgers', 'value' => collect($sections)->sum(fn ($s) => collect($s['lines'])->where('kind', 'line')->count()), 'icon' => 'fas fa-list', 'tone' => 'i3', 'count' => true],
            ],
            'filters_text' => ['As at' => $asAt->format('d M Y'), 'Branch' => $branch],
            'notes'    => $this->ledgerNotes($b),
            'fileDate' => $asAt->format('Y-m-d'),
        ]);
    }

    private function buildProfitLoss(array $in): array
    {
        $from = Carbon::parse($in['from'])->startOfDay();
        $to = Carbon::parse($in['to'])->startOfDay();
        $compare = (bool) ($in['compare'] ?? false);

        $now = self::profitLossFigures($from->toDateString(), $to->toDateString());
        // Previous equal period: the same number of whole months when the range is whole months, else the same number of days.
        $prevTo = $from->copy()->subDay();
        if ($from->day === 1 && $to->isSameDay($to->copy()->endOfMonth())) {
            $months = ($to->year - $from->year) * 12 + $to->month - $from->month + 1;
            $prevFrom = $from->copy()->subMonthsNoOverflow($months);
        } else {
            $prevFrom = $prevTo->copy()->subDays($from->diffInDays($to));
        }
        $prev = $compare ? self::profitLossFigures($prevFrom->toDateString(), $prevTo->toDateString()) : null;

        $v = fn ($a, $b) => $compare ? [$a, $b] : [$a];
        $lines = function (string $section, int $indent = 1, bool $groups = false) use ($now, $prev, $v) {
            $keys = self::sortKeys(array_unique(array_merge(array_keys($now[$section]), array_keys($prev[$section] ?? []))));
            $out = [];
            $lastGroup = null;
            foreach ($keys as $k) {
                $info = self::info($k);
                if ($groups && $info['group_code'] !== $lastGroup) {
                    $out[] = ['kind' => 'group', 'code' => $info['group_code'], 'label' => $info['group_name'] ?: 'Ungrouped', 'indent' => 1];
                    $lastGroup = $info['group_code'];
                }
                $out[] = ['kind' => 'line', 'code' => $info['code'], 'label' => $info['name'],
                    'values' => $v($now[$section][$k] ?? 0.0, $prev[$section][$k] ?? 0.0), 'indent' => $groups ? 2 : $indent];
            }

            return $out;
        };

        $sections = [];
        $rev = $lines('revenue') ?: [['kind' => 'line', 'label' => 'No sales in this period', 'values' => $v(null, null), 'indent' => 1]];
        $rev[] = ['kind' => 'subtotal', 'label' => 'Net revenue', 'values' => $v($now['revenueTotal'], $prev['revenueTotal'] ?? 0)];
        $sections[] = ['title' => 'Revenue', 'lines' => $rev];

        $cogs = $lines('cogs');
        $cogs[] = ['kind' => 'subtotal', 'label' => 'Total cost of goods sold', 'values' => $v($now['cogsTotal'], $prev['cogsTotal'] ?? 0)];
        $cogs[] = ['kind' => 'total', 'label' => 'Gross profit', 'values' => $v($now['gross'], $prev['gross'] ?? 0)];
        $sections[] = ['title' => 'Cost of goods sold', 'lines' => $cogs];

        if ($other = $lines('other')) {
            $other[] = ['kind' => 'subtotal', 'label' => 'Total other income', 'values' => $v($now['otherTotal'], $prev['otherTotal'] ?? 0)];
            $sections[] = ['title' => 'Other income', 'lines' => $other];
        }

        $opex = $lines('opex', 1, true) ?: [['kind' => 'line', 'label' => 'No operating expenses in this period', 'values' => $v(null, null), 'indent' => 1]];
        $opex[] = ['kind' => 'subtotal', 'label' => 'Total operating expenses', 'values' => $v($now['opexTotal'], $prev['opexTotal'] ?? 0)];
        $sections[] = ['title' => 'Operating expenses', 'lines' => $opex];

        $branch = BranchController::visibleLabel();   // P&L covers the branches the user sees
        $period = $from->format('d M Y') . ' – ' . $to->format('d M Y');
        $prevPeriod = $compare ? $prevFrom->format('d M Y') . ' – ' . $prevTo->format('d M Y') : null;
        $margin = $now['revenueTotal'] != 0 ? $now['net'] / $now['revenueTotal'] * 100 : null;

        return $this->statement([
            'key'      => 'profit-loss',
            'title'    => 'Profit & Loss',
            'period'   => $period,
            'columns'  => $compare ? [$from->format('d M y') . ' – ' . $to->format('d M y'), $prevFrom->format('d M y') . ' – ' . $prevTo->format('d M y')] : ['Amount'],
            'sections' => $sections,
            'grand'    => ['label' => $now['net'] >= 0 ? 'Net profit' : 'Net loss', 'values' => $v($now['net'], $prev['net'] ?? 0)],
            'checks'   => [],
            'summary'  => [
                ['label' => 'Net revenue', 'value' => $now['revenueTotal'], 'icon' => 'fas fa-receipt', 'tone' => 'i1'],
                ['label' => 'Gross profit', 'value' => $now['gross'], 'icon' => 'fas fa-layer-group', 'tone' => 'i3'],
                ['label' => 'Operating expenses', 'value' => $now['opexTotal'], 'icon' => 'fas fa-minus', 'tone' => 'i2'],
                ['label' => ($now['net'] >= 0 ? 'Net profit' : 'Net loss') . ($margin !== null ? ' (' . number_format($margin, 1) . '%)' : ''), 'value' => $now['net'],
                    'icon' => 'fas fa-chart-line', 'tone' => $now['net'] >= 0 ? 'i4' : 'i2'],
            ],
            'filters_text' => array_filter(['Period' => $period, 'Compared with' => $prevPeriod, 'Branch' => $branch]),
            'notes'    => [
                'The Income and Expense side of the same ledger as the Trial Balance and Balance Sheet, for documents dated in the period in ' . $branch . '.',
                'Sales are invoice totals without VAT (loyalty and invoice discounts already taken off); returns are dated by their return date. Cost of goods sold is quantity × the purchase price saved on each sale line, less returned lines; stock adjustments are at their value.',
                'Inventory Valuation Adjustment: the change in stock value at the current purchase price (Stock Valuation) that the documents do not explain — price changes, stock delivered from sale orders without an invoice, purchase costs that differ from the product\'s purchase price.',
                'Operating expenses: expense vouchers, bills (net of the bill discount), posted payroll runs (net pay + provident fund + loan recoveries), customer discounts, and journal lines on Income / Expense ledgers.',
            ],
            'fileDate' => $from->format('Y-m-d') . '_' . $to->format('Y-m-d'),
        ]);
    }

    private function buildBalanceSheet(array $in): array
    {
        $asAt = Carbon::parse($in['as_at'] ?? now()->toDateString())->startOfDay();
        $hideZero = (bool) ($in['hide_zero'] ?? true);
        [$scope, $branch] = self::branchFilter($in);
        $b = self::balances($asAt->toDateString(), $scope);

        $block = function (array $types, int $flip) use ($b, $hideZero) {
            $keys = collect(self::sortKeys(array_keys(array_filter($b['acc'], fn ($v, $k) => in_array(self::info($k)['type'], $types, true)
                && (!$hideZero || abs($v) >= 0.005), ARRAY_FILTER_USE_BOTH))));
            $lines = [];
            $total = 0.0;
            foreach ($keys->groupBy(fn ($k) => self::info($k)['root_code']) as $rootKeys) {
                $rootLines = [];
                $rootTotal = 0.0;
                foreach ($rootKeys->groupBy(fn ($k) => self::info($k)['group_code'] ?? '') as $groupKeys) {
                    $g = self::info($groupKeys->first());
                    $rootLines[] = ['kind' => 'group', 'code' => $g['group_code'], 'label' => $g['group_name'] ?: 'Ungrouped', 'indent' => 1];
                    foreach ($groupKeys as $k) {
                        $amt = round(($b['acc'][$k] ?? 0) * $flip, 2);
                        $rootTotal += $amt;
                        $rootLines[] = ['kind' => 'line', 'code' => self::info($k)['code'], 'label' => self::info($k)['name'], 'values' => [$amt], 'indent' => 2];
                    }
                }
                $rootName = self::info($rootKeys->first())['root_name'];
                if (count($types) > 1) {
                    $lines[] = ['kind' => 'head', 'label' => $rootName];
                    array_push($lines, ...$rootLines);
                    $lines[] = ['kind' => 'subtotal', 'label' => 'Total ' . $rootName, 'values' => [round($rootTotal, 2)]];
                } else {
                    array_push($lines, ...$rootLines);
                }
                $total += $rootTotal;
            }

            return [$lines, round($total, 2)];
        };

        [$assetLines, $assets] = $block(['Asset', 'Liquid Asset'], 1);
        [$liabLines, $liabilities] = $block(['Liability'], -1);
        [$equityLines, $equity] = $block(['Equity'], -1);

        $profit = $b['profit'];
        $equityLines[] = ['kind' => 'line', 'label' => 'Current year profit / (loss)' . ($b['closed'] ? ' since ' . $b['closed']->name . ' closing' : ''), 'values' => [$profit], 'indent' => 1];
        $equityTotal = round($equity + $profit, 2);

        if (!$assetLines) {
            $assetLines[] = ['kind' => 'line', 'label' => 'No asset balances', 'values' => [null], 'indent' => 1];
        }
        if (!$liabLines) {
            $liabLines[] = ['kind' => 'line', 'label' => 'No liability balances', 'values' => [null], 'indent' => 1];
        }

        $assetLines[] = ['kind' => 'total', 'label' => 'Total assets', 'values' => [$assets]];
        $liabLines[] = ['kind' => 'subtotal', 'label' => 'Total liabilities', 'values' => [$liabilities]];
        $equityLines[] = ['kind' => 'subtotal', 'label' => 'Total equity', 'values' => [$equityTotal]];

        $right = round($liabilities + $equityTotal, 2);
        $diff = round($assets - $right, 2);

        return $this->statement([
            'key'      => 'balance-sheet',
            'title'    => 'Balance Sheet',
            'period'   => 'As at ' . $asAt->format('d M Y'),
            'columns'  => ['Amount'],
            'sections' => [
                ['title' => 'Assets', 'lines' => $assetLines],
                ['title' => 'Liabilities', 'lines' => $liabLines],
                ['title' => 'Equity', 'lines' => $equityLines],
            ],
            'grand'    => ['label' => 'Total liabilities and equity', 'values' => [$right]],
            'checks'   => [['label' => 'Assets = Liabilities + Equity', 'ok' => abs($diff) < 0.01,
                'detail' => number_format($assets, 2) . ' = ' . number_format($liabilities, 2) . ' + ' . number_format($equityTotal, 2)
                    . (abs($diff) < 0.01 ? '' : ' (difference ' . number_format($diff, 2) . ')')]],
            'summary'  => [
                ['label' => 'Total assets', 'value' => $assets, 'icon' => 'fas fa-wallet', 'tone' => 'i1'],
                ['label' => 'Total liabilities', 'value' => $liabilities, 'icon' => 'fas fa-file-invoice', 'tone' => 'i2'],
                ['label' => 'Total equity', 'value' => $equityTotal, 'icon' => 'fas fa-landmark', 'tone' => 'i3'],
                ['label' => 'Current year profit', 'value' => $profit, 'icon' => 'fas fa-chart-line', 'tone' => $profit >= 0 ? 'i4' : 'i2'],
            ],
            'filters_text' => ['As at' => $asAt->format('d M Y'), 'Branch' => $branch],
            'notes'    => array_merge($this->ledgerNotes($b), [
                'Current year profit is the Profit & Loss net profit from ' . ($b['closed'] ? 'the day after the ' . $b['closed']->name . ' closing' : 'the first entry')
                    . ' to this date, for the same branches; closed years are in retained earnings.',
            ]),
            'fileDate' => $asAt->format('Y-m-d'),
        ]);
    }

    /** Where the Trial Balance / Balance Sheet figures come from, and anything that needs attention. */
    private function ledgerNotes(array $b): array
    {
        $notes = [
            'Built at report time from the documents, so every screen shows one figure: cash and bank ledgers are the Cash & Bank Book closing (opening balance + movements); Accounts Receivable / Customer Advances and Accounts Payable / Advances to Suppliers are the customer and supplier ledger closings, party by party, including their opening balances; Inventory is the Stock Valuation total at cost.',
            'Sales and returns post Sales Revenue (without VAT) and Output VAT; purchases post Inventory and Input VAT; cost of goods sold is the purchase price saved on each sale line. Expenses, bills, journals, amount transfers, payroll runs, salary payments and employee loans post as their screens do.',
            'Opening balances typed in the Chart of Accounts and on parties are included against Opening Balance Equity (in the main branch; a branch\'s own cash ledger keeps its opening in that branch).',
        ];
        if ($b['suspense']) {
            $notes[] = 'Suspense holds documents that do not match their party / money rows (usually entries saved before the party ledgers existed): '
                . implode('; ', array_map(fn ($k, $v) => $k . ' ' . number_format($v, 2), array_keys($b['suspense']), $b['suspense'])) . '.';
        }
        if ($b['other']) {
            $notes[] = 'Besides the party ledgers, these ledgers also hold amounts posted to them directly by payroll runs or journals: '
                . implode('; ', array_map(fn ($k, $v) => $k . ' ' . number_format($v, 2), array_keys($b['other']), $b['other'])) . '.';
        }
        if ($b['closed']) {
            $notes[] = 'Years closed up to ' . Carbon::parse($b['closed']->end_date)->format('d M Y') . ' are in retained earnings from the day after their year end; a statement at a year end shows the year before closing.';
        }

        return $notes;
    }

    private function page(string $key, string $title, string $intro, string $filter)
    {
        return view('admin.accounting.statement', [
            'key'      => $key,
            'title'    => $title,
            'intro'    => $intro,
            'filter'   => $filter,
            'branches' => $filter === 'as_at' ? DB::table('branches')->orderByDesc('is_main')->orderBy('name')->get(['id', 'name']) : collect(),
            'urls'     => [
                'data'  => route('admin.accounting.' . $key . '.data'),
                'pdf'   => route('admin.accounting.' . $key . '.pdf'),
                'excel' => route('admin.accounting.' . $key . '.excel'),
            ],
        ]);
    }

    /** Common fields of a statement. */
    private function statement(array $s): array
    {
        return $s + ['printedBy' => Auth::user()->name ?? '', 'printedAt' => now()->format('d-m-Y h:i A')];
    }

    /* ================================================================== */
    /*  Output: JSON (page), PDF, Excel                                    */
    /* ================================================================== */

    private function respond(Request $request, string $report, string $format)
    {
        $rules = $report === 'profit-loss'
            ? ['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'compare' => ['nullable', 'boolean']]
            : ['as_at' => ['nullable', 'date'], 'hide_zero' => ['nullable', 'boolean'], 'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')]];
        $validator = Validator::make($request->query(), $rules, [
            'from.required'     => 'Select the start date.',
            'to.required'       => 'Select the end date.',
            'to.after_or_equal' => 'End date cannot be before the start date.',
        ]);

        if ($validator->fails()) {
            if ($format === 'data') {
                return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
            }
            abort(422, $validator->errors()->first());
        }

        $in = $validator->validated();
        foreach (['hide_zero', 'compare'] as $flag) {
            if (array_key_exists($flag, $in)) {
                $in[$flag] = $request->boolean($flag);
            }
        }

        try {
            self::$cache = [];
            $s = match ($report) {
                'trial-balance' => $this->buildTrialBalance($in),
                'profit-loss'   => $this->buildProfitLoss($in),
                'balance-sheet' => $this->buildBalanceSheet($in),
            };
        } catch (Throwable $e) {
            Log::error('Accounting statement error', ['report' => $report] + $this->ctx($e));
            if ($format === 'data') {
                return response()->json(['status' => false, 'message' => 'Unable to build the statement.'], 500);
            }
            abort(500, 'Unable to build the statement.');
        }

        $fileName = str_replace(' ', '-', str_replace('&', 'and', $s['title'])) . '-' . $s['fileDate'];

        if ($format === 'data') {
            return response()->json([
                'status'  => true,
                'html'    => view('admin.accounting.partials.statement', $s)->render(),
                'summary' => array_map(fn ($c) => ['label' => $c['label'], 'value' => !empty($c['count']) ? (string) $c['value'] : number_format((float) $c['value'], 2)], $s['summary']),
            ]);
        }

        if ($format === 'pdf') {
            $pdf = SettingController::pdf('admin.accounting.statement-pdf', $s);

            return $request->boolean('download') ? $pdf->download($fileName . '.pdf') : $pdf->stream($fileName . '.pdf');
        }

        return $this->excel($s, $fileName . '.xlsx');
    }

    private function excel(array $s, string $fileName)
    {
        $company = $this->company();
        $cols = count($s['columns']);
        $last = chr(ord('B') + $cols);   // A = code, B = particulars, C.. = amounts

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle(substr(str_replace('&', 'and', $s['title']), 0, 31));
        $book->getProperties()->setCreator($company['name'])->setTitle($s['title']);
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(52);
        for ($i = 0; $i < $cols; $i++) {
            $sheet->getColumnDimension(chr(ord('C') + $i))->setWidth(20);
        }

        $money = '#,##0.00;-#,##0.00;"0.00"';
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::xl('border_strong')]]]];
        $head = ['font' => ['bold' => true, 'color' => ['rgb' => self::xl('on_brand')]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('brand')]]];
        $band = ['font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('soft')]]];

        $sheet->setCellValue('A1', $company['name'])->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', $s['title'] . ' — ' . $s['period'])->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));

        $row = 3;
        foreach (array_filter($s['filters_text'] + ['Printed Date' => $s['printedAt'], 'Printed By' => $s['printedBy']]) as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ':');
            $sheet->setCellValue("B{$row}", $value)->mergeCells("B{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $row++;

        $first = $row;
        $sheet->setCellValue("A{$row}", 'Code');
        $sheet->setCellValue("B{$row}", 'Particulars');
        foreach ($s['columns'] as $i => $c) {
            $sheet->setCellValue(chr(ord('C') + $i) . $row, $c);
        }
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray($head);
        $sheet->getStyle("C{$row}:{$last}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $row++;

        $put = function (array $values) use ($sheet, &$row) {
            foreach ($values as $i => $val) {
                $sheet->setCellValue(chr(ord('C') + $i) . $row, $val === null ? null : (float) $val);
            }
        };

        foreach ($s['sections'] as $sec) {
            $sheet->setCellValue("A{$row}", $sec['title'])->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray($band);
            $row++;
            foreach ($sec['lines'] as $l) {
                $sheet->setCellValue("A{$row}", $l['code'] ?? null);
                $sheet->setCellValue("B{$row}", $l['label']);
                $sheet->getStyle("B{$row}")->getAlignment()->setIndent((int) ($l['indent'] ?? 0));
                if (isset($l['values'])) {
                    $put($l['values']);
                }
                if (in_array($l['kind'], ['head', 'group', 'subtotal', 'total'], true)) {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
                }
                if (in_array($l['kind'], ['subtotal', 'total'], true)) {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                }
                $row++;
            }
        }
        if ($s['grand']) {
            $sheet->setCellValue("B{$row}", $s['grand']['label']);
            $put($s['grand']['values']);
            $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray($band);
            $sheet->getStyle("A{$row}:{$last}{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE);
            $row++;
        }
        $sheet->getStyle("A{$first}:{$last}" . ($row - 1))->applyFromArray($thin);
        $sheet->getStyle("C{$first}:{$last}" . ($row - 1))->getNumberFormat()->setFormatCode($money);
        $row++;

        foreach ($s['checks'] as $c) {
            $sheet->setCellValue("A{$row}", ($c['ok'] ? 'OK: ' : 'NOT BALANCED: ') . $c['label'] . ' — ' . $c['detail'])->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::xl($c['ok'] ? 'success' : 'danger'));
            $row++;
        }
        $row++;
        foreach ($s['notes'] as $n) {
            $sheet->setCellValue("A{$row}", $n)->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::xl('muted'));
            $sheet->getRowDimension($row)->setRowHeight(max(15, ceil(mb_strlen($n) / 110) * 14));
            $row++;
        }

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /* ================================================================== */
    /*  Helpers (copied from ReportController)                             */
    /* ================================================================== */

    /** A Settings > Theme Colors palette color as Excel RRGGBB (see SettingController::pdfColors()). */
    private static function xl(string $key): string
    {
        return strtoupper(ltrim(SettingController::pdfColors()[$key], '#'));
    }

    private function company(): array
    {
        $s = Setting::where('deleted', 'No')->where('status', 'Active')->first();

        $logo = null;
        if ($s && $s->logo && is_file($path = public_path($s->logo))) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $logo = in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)
                ? 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($path))
                : null;
        }

        return [
            'name'    => $s->company_name ?? 'Company Name',
            'address' => trim(implode(', ', array_filter([$s->address ?? null, $s->city ?? null, $s->country ?? null]))),
            'phone'   => $s->phone ?? null,
            'email'   => $s->email ?? null,
            'logo'    => $logo,
        ];
    }

    private function blank($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }

    private function ctx(Throwable $e): array
    {
        return ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'user' => Auth::id()];
    }
}
