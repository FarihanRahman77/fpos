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
 * - chart_of_accounts.current_balance (kept by AccountBalanceTrait::postBalances) is the only ledger balance; there is no
 *   posting history table. Balances are company-wide (not per branch).
 * - A balance "as at" a past date = current_balance minus the dated postings after that date, rebuilt from the posted
 *   source documents exactly as their controllers post them (see movementTotals()).
 * - Profit & Loss is built from the source documents (sales, returns, expenses, bills, payroll, journals), branch-scoped,
 *   with the Dashboard's rules: sales are not posted to ledgers in this app, so they only show here.
 *
 * Statement shape shared by the page partial, the PDF and the Excel export:
 *   ['title', 'period', 'columns' => [..], 'sections' => [['title', 'lines' => [['kind', 'label', 'code', 'values', 'indent']]]],
 *    'grand' => ['label', 'values'] | null, 'checks' => [['label', 'ok', 'detail']], 'summary' => [..], 'notes' => [..]]
 *   kind: head (root), group, line, subtotal, total.
 */
class AccountingReportController extends Controller
{
    use AccountBalanceTrait;

    /** Root account types on each side of the balance sheet. */
    private const ASSET_TYPES  = ['Asset', 'Liquid Asset'];
    private const RESULT_TYPES = ['Income', 'Expense'];

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
            . BranchController::name(BranchController::current()) . ').', 'range');
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

    /** What closing would post: Income / Expense ledger balances as at the year end. */
    public function fiscalYearPreview(int $id)
    {
        $year = DB::table('fiscal_years')->where('id', $id)->where('deleted', 'No')->first();
        if (!$year) {
            return response()->json(['status' => false, 'message' => 'Fiscal year not found.'], 404);
        }

        try {
            $plan = self::closingPlan($year->end_date);
            $report = self::profitLossFigures($year->start_date, $year->end_date, false);

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
                $plan = self::closingPlan($year->end_date);

                // Zero every Income / Expense ledger as at the year end into retained earnings.
                $debits = $credits = [];
                foreach ($plan['lines'] as $l) {
                    $side = self::sideFor($l['normal'], -$l['balance']);   // the entry that removes the balance
                    ${$side}[$l['id']] = round((${$side}[$l['id']] ?? 0) + abs($l['balance']), 2);
                }
                if (abs($plan['net']) >= 0.005) {
                    if ($plan['net'] > 0) {
                        $credits[$reId] = round(($credits[$reId] ?? 0) + $plan['net'], 2);
                    } else {
                        $debits[$reId] = round(($debits[$reId] ?? 0) - $plan['net'], 2);
                    }
                }

                $this->postBalances($debits, $credits, 1);

                // Snapshot of every ledger (balance as at the year end, before closing) and what the closing changed.
                $now = now();
                $rows = [];
                foreach (self::ledgers() as $l) {
                    $normalSign = $l->balance === 'Credit' ? -1 : 1;
                    $delta = round((($debits[$l->id] ?? 0) - ($credits[$l->id] ?? 0)) * $normalSign, 2);
                    $before = $plan['balances'][$l->id] ?? 0.0;
                    $rows[] = [
                        'fiscal_year_id' => $year->id,
                        'account_id'     => $l->id,
                        'account_code'   => $l->account_code,
                        'account_name'   => $l->account_name,
                        'account_type'   => $l->account_type,
                        'normal_side'    => $l->balance === 'Credit' ? 'Credit' : 'Debit',
                        'balance'        => $before,
                        'closing_delta'  => $delta,
                        'closed_balance' => round($before + $delta, 2),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('fiscal_year_balances')->insert($chunk);
                }

                DB::table('fiscal_years')->where('id', $year->id)->update([
                    'status'                       => 'Closed',
                    'net_profit'                   => $plan['net'],
                    'report_net_profit'            => self::profitLossFigures($year->start_date, $year->end_date, false)['net'],
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

    /** Reverses exactly what closing posted (only the most recently closed year). */
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

                [$debits, $credits] = self::closingEntries($year->id);
                $this->postBalances($debits, $credits, -1);

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

            return response()->json(['status' => true, 'message' => $year->name . ' reopened; the closing entries were reversed.']);
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
    /*  Ledger balances                                                    */
    /* ================================================================== */

    /** Non-deleted 3rd layer ledgers with their group and root, in chart order. */
    public static function ledgers()
    {
        return DB::table('chart_of_accounts as a')
            ->leftJoin('chart_of_accounts as g', 'g.id', '=', 'a.parent_id')
            ->leftJoin('chart_of_accounts as r', 'r.id', '=', 'g.parent_id')
            ->where('a.layer_level', 3)->where('a.deleted', 'No')
            ->orderBy('r.account_code')->orderBy('g.account_code')->orderBy('a.account_code')
            ->get([
                'a.id', 'a.account_code', 'a.account_name', 'a.account_type', 'a.balance', 'a.current_balance', 'a.opening_balance', 'a.status',
                'g.id as group_id', 'g.account_code as group_code', 'g.account_name as group_name',
                'r.id as root_id', 'r.account_code as root_code', 'r.account_name as root_name',
            ])->keyBy('id');
    }

    /**
     * [ledger id => balance on its normal side] as at the end of $date (null = now):
     * current_balance minus the postings dated after $date.
     */
    public static function balancesAsAt(?string $date = null): array
    {
        $ledgers = self::ledgers();
        $after = $date ? self::movementTotals($date, null) : [];

        $out = [];
        foreach ($ledgers as $l) {
            $m = $after[$l->id] ?? ['dr' => 0, 'cr' => 0];
            $out[$l->id] = round((float) $l->current_balance - self::normalDelta($l->balance, $m['dr'], $m['cr']), 2);
        }

        return $out;
    }

    /**
     * Debit / credit totals per ledger of every posting dated after $after (exclusive) up to $until (inclusive),
     * rebuilt from the source documents the way their controllers post them (AccountBalanceTrait::postBalances):
     * expenses, bills, bill payments, journals, payroll runs, salary payments, loan disbursements / cash repayments,
     * amount transfers, and closings of fiscal years (effective the day after the year end).
     * Deleted, cancelled or reversed documents are left out (their posting and its reversal cancel out).
     *
     * @return array<int, array{dr: float, cr: float}>
     */
    public static function movementTotals(?string $after, ?string $until): array
    {
        $map = [];
        $add = function ($id, $dr, $cr) use (&$map) {
            $id = (int) $id;
            if (!$id) {
                return;
            }
            $map[$id]['dr'] = round(($map[$id]['dr'] ?? 0) + (float) $dr, 2);
            $map[$id]['cr'] = round(($map[$id]['cr'] ?? 0) + (float) $cr, 2);
        };
        $range = function ($q, string $col) use ($after, $until) {
            if ($after !== null) {
                $q->whereRaw("DATE($col) > ?", [$after]);
            }
            if ($until !== null) {
                $q->whereRaw("DATE($col) <= ?", [$until]);
            }

            return $q;
        };

        // Expenses: Dr expense heads / Cr payment account.
        foreach ($range(DB::table('expense_details as d')->join('expenses as e', 'e.id', '=', 'd.expense_id')
            ->where('e.deleted', 'No')->where('d.deleted', 'No'), 'e.expense_date')
            ->groupBy('d.expense_account_id')->selectRaw('d.expense_account_id AS id, SUM(d.amount) AS amt')->get() as $r) {
            $add($r->id, $r->amt, 0);
        }
        foreach ($range(DB::table('expenses as e')->where('e.deleted', 'No'), 'e.expense_date')
            ->groupBy('e.payment_account_id')->selectRaw('e.payment_account_id AS id, SUM(e.total_amount) AS amt')->get() as $r) {
            $add($r->id, 0, $r->amt);
        }

        // Bills: Dr expense lines net of the bill discount / Cr accounts payable. Bill payments: Dr payable / Cr cash-bank.
        $payableId = self::payableAccountId();
        foreach (self::billDebits($range, null) as $bill) {
            foreach ($bill['debits'] as $id => $amt) {
                $add($id, $amt, 0);
            }
            $add($payableId, 0, $bill['total']);
        }
        foreach ($range(DB::table('bill_payments as p')->where('p.deleted', 'No'), 'p.payment_date')
            ->groupBy('p.payment_account_id')->selectRaw('p.payment_account_id AS id, SUM(p.amount) AS amt')->get() as $r) {
            $add($payableId, $r->amt, 0);
            $add($r->id, 0, $r->amt);
        }

        // Journals.
        foreach ($range(DB::table('journal_details as d')->join('journals as j', 'j.id', '=', 'd.journal_id')
            ->where('j.deleted', 'No')->where('d.deleted', 'No'), 'j.journal_date')
            ->groupBy('d.account_id')->selectRaw('d.account_id AS id, SUM(d.debit) AS dr, SUM(d.credit) AS cr')->get() as $r) {
            $add($r->id, $r->dr, $r->cr);
        }

        // Payroll runs: Dr salary expense (net + PF + loan) / Cr salary payable, PF payable, employee loans.
        foreach ($range(DB::table('payroll_runs as r')->where('r.run_status', 'Posted'), 'r.run_date')
            ->get(['r.expense_account_id', 'r.payable_account_id', 'r.pf_account_id', 'r.loan_account_id', 'r.total_net', 'r.total_pf', 'r.total_loan']) as $r) {
            $add($r->expense_account_id, (float) $r->total_net + (float) $r->total_pf + (float) $r->total_loan, 0);
            $add($r->payable_account_id, 0, $r->total_net);
            if ((float) $r->total_pf > 0 && $r->pf_account_id) {
                $add($r->pf_account_id, 0, $r->total_pf);
            }
            if ((float) $r->total_loan > 0 && $r->loan_account_id) {
                $add($r->loan_account_id, 0, $r->total_loan);
            }
        }

        // Salary payments: Dr salary payable / Cr cash-bank.
        foreach ($range(DB::table('salary_payments as s')->join('payroll_runs as r', 'r.id', '=', 's.payroll_run_id')
            ->where('s.payment_status', 'Posted'), 's.payment_date')
            ->get(['r.payable_account_id', 's.payment_account_id', 's.total_paid']) as $r) {
            $add($r->payable_account_id, $r->total_paid, 0);
            $add($r->payment_account_id, 0, $r->total_paid);
        }

        // Employee loans: disbursement Dr loans / Cr cash-bank; cash repayment the other way (payroll recoveries are in the run).
        foreach ($range(DB::table('employee_loan_transactions as t')->join('employee_loans as l', 'l.id', '=', 't.employee_loan_id')
            ->where('l.deleted', 'No')->where('t.txn_status', 'Posted')->whereIn('t.txn_type', ['Disbursement', 'Cash Repayment']), 't.txn_date')
            ->get(['t.txn_type', 't.amount', 't.payment_account_id', 'l.loan_account_id']) as $r) {
            if ($r->txn_type === 'Disbursement') {
                $add($r->loan_account_id, $r->amount, 0);
                $add($r->payment_account_id, 0, $r->amount);
            } else {
                $add($r->payment_account_id, $r->amount, 0);
                $add($r->loan_account_id, 0, $r->amount);
            }
        }

        // Internal amount transfers: Dr to / Cr from.
        foreach ($range(DB::table('internal_amount_transfers as t')->where('t.deleted', 'No'), 't.transfer_date')
            ->get(['t.from_account_id', 't.to_account_id', 't.amount']) as $r) {
            $add($r->to_account_id, $r->amount, 0);
            $add($r->from_account_id, 0, $r->amount);
        }

        // Year-end closings, effective the day after the year end (so a trial balance at the year end is pre-closing).
        $closings = DB::table('fiscal_year_balances as b')->join('fiscal_years as f', 'f.id', '=', 'b.fiscal_year_id')
            ->where('f.status', 'Closed')->where('f.deleted', 'No')->where('b.closing_delta', '!=', 0);
        if ($after !== null) {
            $closings->where('f.end_date', '>=', $after);
        }
        if ($until !== null) {
            $closings->where('f.end_date', '<', $until);
        }
        foreach ($closings->get(['b.account_id', 'b.normal_side', 'b.closing_delta']) as $r) {
            $side = self::sideFor($r->normal_side, (float) $r->closing_delta);
            $add($r->account_id, $side === 'debits' ? abs($r->closing_delta) : 0, $side === 'credits' ? abs($r->closing_delta) : 0);
        }

        return $map;
    }

    /** Change on the normal side from debit / credit amounts. */
    private static function normalDelta(?string $normal, $dr, $cr): float
    {
        return ($normal === 'Credit' ? -1 : 1) * ((float) $dr - (float) $cr);
    }

    /** 'debits' or 'credits': the side that changes a ledger of $normal side by $delta (normal sign). */
    private static function sideFor(?string $normal, float $delta): string
    {
        $debit = ($normal === 'Credit') ? $delta < 0 : $delta > 0;

        return $debit ? 'debits' : 'credits';
    }

    /** Debit / credit maps that a year's closing posted (from its snapshot). */
    private static function closingEntries(int $yearId): array
    {
        $debits = $credits = [];
        foreach (DB::table('fiscal_year_balances')->where('fiscal_year_id', $yearId)->where('closing_delta', '!=', 0)
            ->get(['account_id', 'normal_side', 'closing_delta']) as $r) {
            $side = self::sideFor($r->normal_side, (float) $r->closing_delta);
            ${$side}[(int) $r->account_id] = round((${$side}[(int) $r->account_id] ?? 0) + abs((float) $r->closing_delta), 2);
        }

        return [$debits, $credits];
    }

    /** Income / Expense ledger balances as at $endDate, and the net profit they make. */
    private static function closingPlan(string $endDate): array
    {
        $balances = self::balancesAsAt($endDate);
        $lines = [];
        $income = $expense = 0.0;

        foreach (self::ledgers() as $l) {
            if (!in_array($l->account_type, self::RESULT_TYPES, true)) {
                continue;
            }
            $b = $balances[$l->id] ?? 0.0;
            if (abs($b) < 0.005) {
                continue;
            }
            // Signed so that income adds to profit and expense takes away, whatever the ledger's normal side.
            $toProfit = $l->balance === 'Credit' ? $b : -$b;
            $l->account_type === 'Income' ? $income += $toProfit : $expense -= $toProfit;
            $lines[] = ['id' => (int) $l->id, 'code' => $l->account_code, 'name' => $l->account_name, 'type' => $l->account_type,
                'normal' => $l->balance, 'balance' => $b];
        }

        return ['balances' => $balances, 'lines' => $lines, 'income' => round($income, 2), 'expense' => round($expense, 2),
            'net' => round($income - $expense, 2)];
    }

    private static function payableAccountId(): ?int
    {
        $id = DB::table('chart_of_accounts')->where('slug', 'accounts-payable')->where('layer_level', 3)->where('deleted', 'No')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Each live bill's expense debits net of the bill discount, as BillController::expenseDebits() posts them.
     * $range filters bill_date; $scope (nullable) limits to a branch.
     */
    private static function billDebits(callable $range, ?callable $scope): array
    {
        $q = $range(DB::table('bills as b')->where('b.deleted', 'No'), 'b.bill_date');
        if ($scope) {
            $scope($q, 'b.branch_id');
        }
        $bills = $q->get(['b.id', 'b.subtotal', 'b.grand_total']);
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
    /*  Profit & Loss figures                                              */
    /* ================================================================== */

    /**
     * Document-based profit and loss for [$from, $to] (dates inclusive), same rules as the Dashboard:
     * sales.grand_total (Active), returns by DATE(datetime), cost = sale_items qty × purchase_price,
     * expenses = expense lines + bill lines (net of discount) + posted payroll runs (net + PF + loan);
     * plus journal lines on Income / Expense ledgers. $scoped limits to the branch being viewed.
     */
    public static function profitLossFigures(string $from, string $to, bool $scoped = true): array
    {
        $scope = fn ($q, string $col) => $scoped ? BranchController::scope($q, $col) : $q;
        $between = fn ($q, string $col) => $q->whereRaw("DATE($col) BETWEEN ? AND ?", [$from, $to]);

        $sales = (float) $between($scope(DB::table('sales as s')->where('s.deleted', 'No')->where('s.status', 'Active'), 's.branch_id'), 's.sale_date')
            ->sum('s.grand_total');

        $returns = (float) $between($scope(DB::table('sale_returns as r')->where('r.deleted', 'No')->where('r.status', 'Active'), 'r.branch_id'), 'r.datetime')
            ->sum('r.grand_total');

        $cost = (float) $between($scope(DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.deleted', 'No')->where('s.status', 'Active')->where('si.deleted', 'No'), 's.branch_id'), 's.sale_date')
            ->sum(DB::raw('si.qty * si.purchase_price'));

        $returnedCost = (float) $between($scope(DB::table('sale_return_items as ri')->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->join('sale_items as si', 'si.id', '=', 'ri.sale_item_id')
            ->where('r.deleted', 'No')->where('r.status', 'Active')->where('ri.deleted', 'No'), 'r.branch_id'), 'r.datetime')
            ->sum(DB::raw('ri.qty * si.purchase_price'));

        // Operating expenses by ledger.
        $expenses = [];
        foreach ($between($scope(DB::table('expense_details as d')->join('expenses as e', 'e.id', '=', 'd.expense_id')
            ->where('e.deleted', 'No')->where('d.deleted', 'No'), 'e.branch_id'), 'e.expense_date')
            ->groupBy('d.expense_account_id')->selectRaw('d.expense_account_id AS id, SUM(d.amount) AS amt')->get() as $r) {
            $expenses[(int) $r->id] = round(($expenses[(int) $r->id] ?? 0) + (float) $r->amt, 2);
        }
        $range = fn ($q, string $col) => $between($q, $col);
        foreach (self::billDebits($range, $scoped ? fn ($q, $col) => BranchController::scope($q, $col) : null) as $bill) {
            foreach ($bill['debits'] as $id => $amt) {
                $expenses[$id] = round(($expenses[$id] ?? 0) + $amt, 2);
            }
        }

        $salaries = (float) $between($scope(DB::table('payroll_runs as r')->where('r.run_status', 'Posted'), 'r.branch_id'), 'r.run_date')
            ->sum(DB::raw('r.total_net + r.total_pf + r.total_loan'));

        // Journal lines on Income / Expense ledgers.
        $jIncome = $jExpense = [];
        foreach ($between($scope(DB::table('journal_details as d')->join('journals as j', 'j.id', '=', 'd.journal_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'd.account_id')
            ->where('j.deleted', 'No')->where('d.deleted', 'No')->whereIn('a.account_type', self::RESULT_TYPES), 'j.branch_id'), 'j.journal_date')
            ->groupBy('d.account_id', 'a.account_type')
            ->selectRaw('d.account_id AS id, a.account_type AS type, SUM(d.debit) AS dr, SUM(d.credit) AS cr')->get() as $r) {
            if ($r->type === 'Income') {
                $jIncome[(int) $r->id] = round((float) $r->cr - (float) $r->dr, 2);
            } else {
                $jExpense[(int) $r->id] = round((float) $r->dr - (float) $r->cr, 2);
            }
        }
        $jIncome = array_filter($jIncome, fn ($a) => abs($a) >= 0.005);
        $jExpense = array_filter($jExpense, fn ($a) => abs($a) >= 0.005);
        $expenses = array_filter($expenses, fn ($a) => abs($a) >= 0.005);

        $revenue = round($sales - $returns, 2);
        $cogs = round($cost - $returnedCost, 2);
        $gross = round($revenue - $cogs, 2);
        $opex = round(array_sum($expenses) + $salaries, 2);
        $net = round($gross + array_sum($jIncome) - $opex - array_sum($jExpense), 2);

        return compact('sales', 'returns', 'revenue', 'cost', 'returnedCost', 'cogs', 'gross', 'expenses', 'salaries', 'opex', 'jIncome', 'jExpense', 'net');
    }

    /* ================================================================== */
    /*  Statement builders                                                 */
    /* ================================================================== */

    private function buildTrialBalance(array $in): array
    {
        $asAt = Carbon::parse($in['as_at'] ?? now()->toDateString())->startOfDay();
        $hideZero = (bool) ($in['hide_zero'] ?? true);
        $balances = self::balancesAsAt($asAt->toDateString());
        $ledgers = self::ledgers();

        $sections = [];
        $totDr = $totCr = 0.0;
        foreach ($ledgers->groupBy('root_id') as $rootLedgers) {
            $first = $rootLedgers->first();
            $lines = [];
            $secDr = $secCr = 0.0;
            foreach ($rootLedgers->groupBy('group_id') as $groupLedgers) {
                $groupLines = [];
                foreach ($groupLedgers as $l) {
                    $signed = $l->balance === 'Credit' ? -$balances[$l->id] : $balances[$l->id];   // debit positive
                    if ($hideZero && abs($signed) < 0.005) {
                        continue;
                    }
                    $dr = $signed > 0 ? $signed : 0.0;
                    $cr = $signed < 0 ? -$signed : 0.0;
                    $secDr += $dr;
                    $secCr += $cr;
                    $groupLines[] = ['kind' => 'line', 'code' => $l->account_code, 'label' => $l->account_name . ($l->status !== 'Active' ? ' (inactive)' : ''),
                        'values' => [$dr ?: null, $cr ?: null], 'indent' => 1];
                }
                if ($groupLines) {
                    $lines[] = ['kind' => 'group', 'code' => $groupLedgers->first()->group_code, 'label' => $groupLedgers->first()->group_name ?: 'Ungrouped'];
                    array_push($lines, ...$groupLines);
                }
            }
            if (!$lines) {
                continue;
            }
            $lines[] = ['kind' => 'subtotal', 'label' => 'Total ' . ($first->root_name ?: $first->account_type), 'values' => [round($secDr, 2), round($secCr, 2)]];
            $sections[] = ['title' => $first->root_name ?: $first->account_type, 'lines' => $lines];
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
            'filters_text' => ['As at' => $asAt->format('d M Y'), 'Scope' => 'All branches (ledger balances are company-wide)'],
            'notes'    => array_merge([
                'Balances are chart of accounts ledger balances (company-wide, all branches). For a past date they are rebuilt from today\'s balance by taking out the postings dated after that date: expenses, bills, bill payments, journals, payroll runs, salary payments, employee loans, amount transfers and year-end closings.',
                'Deleted, cancelled or reversed documents are treated as never posted. Year-end closing entries take effect the day after the year end, so a trial balance at a year end shows the balances before closing.',
                'Sales, purchases and customer / supplier payments are not posted to ledgers in this app, so they do not appear here; see Profit & Loss.',
            ], $this->reconciliationNotes()),
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
        $names = DB::table('chart_of_accounts as a')->leftJoin('chart_of_accounts as g', 'g.id', '=', 'a.parent_id')
            ->get(['a.id', 'a.account_code', 'a.account_name', 'g.account_name as group_name'])->keyBy('id');
        $ledgerLines = function (array $a, ?array $b) use ($names, $v) {
            $ids = array_unique(array_merge(array_keys($a), array_keys($b ?? [])));
            usort($ids, fn ($x, $y) => strcmp((string) ($names[$x]->account_code ?? ''), (string) ($names[$y]->account_code ?? '')));

            return array_map(fn ($id) => ['kind' => 'line', 'code' => $names[$id]->account_code ?? null,
                'label' => ($names[$id]->account_name ?? 'Account #' . $id) . (isset($names[$id]->group_name) ? ' · ' . $names[$id]->group_name : ''),
                'values' => $v($a[$id] ?? 0.0, $b[$id] ?? 0.0), 'indent' => 1], $ids);
        };

        $sections = [];
        $sections[] = ['title' => 'Revenue', 'lines' => [
            ['kind' => 'line', 'label' => 'Sales', 'values' => $v($now['sales'], $prev['sales'] ?? 0), 'indent' => 1],
            ['kind' => 'line', 'label' => 'Less: sales returns', 'values' => $v(-$now['returns'], -($prev['returns'] ?? 0)), 'indent' => 1],
            ['kind' => 'subtotal', 'label' => 'Net revenue', 'values' => $v($now['revenue'], $prev['revenue'] ?? 0)],
        ]];
        $sections[] = ['title' => 'Cost of goods sold', 'lines' => [
            ['kind' => 'line', 'label' => 'Cost of goods sold (qty × purchase price)', 'values' => $v($now['cost'], $prev['cost'] ?? 0), 'indent' => 1],
            ['kind' => 'line', 'label' => 'Less: cost of goods returned', 'values' => $v(-$now['returnedCost'], -($prev['returnedCost'] ?? 0)), 'indent' => 1],
            ['kind' => 'subtotal', 'label' => 'Total cost of goods sold', 'values' => $v($now['cogs'], $prev['cogs'] ?? 0)],
            ['kind' => 'total', 'label' => 'Gross profit', 'values' => $v($now['gross'], $prev['gross'] ?? 0)],
        ]];

        $other = $ledgerLines($now['jIncome'], $prev['jIncome'] ?? null);
        if ($other) {
            $other[] = ['kind' => 'subtotal', 'label' => 'Total other income', 'values' => $v(array_sum($now['jIncome']), array_sum($prev['jIncome'] ?? []))];
            $sections[] = ['title' => 'Other income (journals)', 'lines' => $other];
        }

        $opex = $ledgerLines($now['expenses'], $prev['expenses'] ?? null);
        if ($now['salaries'] != 0 || ($prev['salaries'] ?? 0) != 0) {
            $opex[] = ['kind' => 'line', 'label' => 'Salaries & wages (payroll)', 'values' => $v($now['salaries'], $prev['salaries'] ?? 0), 'indent' => 1];
        }
        if (!$opex) {
            $opex[] = ['kind' => 'line', 'label' => 'No operating expenses in this period', 'values' => $v(null, null), 'indent' => 1];
        }
        $opex[] = ['kind' => 'subtotal', 'label' => 'Total operating expenses', 'values' => $v($now['opex'], $prev['opex'] ?? 0)];
        $sections[] = ['title' => 'Operating expenses', 'lines' => $opex];

        $otherExp = $ledgerLines($now['jExpense'], $prev['jExpense'] ?? null);
        if ($otherExp) {
            $otherExp[] = ['kind' => 'subtotal', 'label' => 'Total other expenses', 'values' => $v(array_sum($now['jExpense']), array_sum($prev['jExpense'] ?? []))];
            $sections[] = ['title' => 'Other expenses (journals)', 'lines' => $otherExp];
        }

        $branch = BranchController::name(BranchController::current());
        $period = $from->format('d M Y') . ' – ' . $to->format('d M Y');
        $prevPeriod = $compare ? $prevFrom->format('d M Y') . ' – ' . $prevTo->format('d M Y') : null;
        $margin = $now['revenue'] != 0 ? $now['net'] / $now['revenue'] * 100 : null;

        return $this->statement([
            'key'      => 'profit-loss',
            'title'    => 'Profit & Loss',
            'period'   => $period,
            'columns'  => $compare ? [$from->format('d M y') . ' – ' . $to->format('d M y'), $prevFrom->format('d M y') . ' – ' . $prevTo->format('d M y')] : ['Amount'],
            'sections' => $sections,
            'grand'    => ['label' => $now['net'] >= 0 ? 'Net profit' : 'Net loss', 'values' => $v($now['net'], $prev['net'] ?? 0)],
            'checks'   => [],
            'summary'  => [
                ['label' => 'Net revenue', 'value' => $now['revenue'], 'icon' => 'fas fa-receipt', 'tone' => 'i1'],
                ['label' => 'Gross profit', 'value' => $now['gross'], 'icon' => 'fas fa-layer-group', 'tone' => 'i3'],
                ['label' => 'Operating expenses', 'value' => $now['opex'] + array_sum($now['jExpense']), 'icon' => 'fas fa-minus', 'tone' => 'i2'],
                ['label' => ($now['net'] >= 0 ? 'Net profit' : 'Net loss') . ($margin !== null ? ' (' . number_format($margin, 1) . '%)' : ''), 'value' => $now['net'],
                    'icon' => 'fas fa-chart-line', 'tone' => $now['net'] >= 0 ? 'i4' : 'i2'],
            ],
            'filters_text' => array_filter(['Period' => $period, 'Compared with' => $prevPeriod, 'Branch' => $branch]),
            'notes'    => [
                'Built from documents dated in the period for ' . $branch . ', with the Dashboard\'s rules: sales are invoice grand totals (Active); returns are dated by their return date; cost of goods sold is quantity × the purchase price saved on each sale line, less the cost of returned lines.',
                'Operating expenses: expense vouchers by expense ledger, bills by ledger (net of the bill discount), and posted payroll runs (net pay + provident fund + loan recoveries) as salaries & wages. Journal lines on Income / Expense ledgers are shown as other income / other expenses.',
                'The Dashboard\'s "net" does not take off sales returns, the cost of returned goods or journal lines, so it can differ from this statement.',
            ],
            'fileDate' => $from->format('Y-m-d') . '_' . $to->format('Y-m-d'),
        ]);
    }

    private function buildBalanceSheet(array $in): array
    {
        $asAt = Carbon::parse($in['as_at'] ?? now()->toDateString())->startOfDay();
        $hideZero = (bool) ($in['hide_zero'] ?? true);
        $balances = self::balancesAsAt($asAt->toDateString());
        $ledgers = self::ledgers();

        // Debit-positive balance of each ledger.
        $signed = [];
        foreach ($ledgers as $l) {
            $signed[$l->id] = $l->balance === 'Credit' ? -$balances[$l->id] : $balances[$l->id];
        }

        $block = function (array $types, int $flip) use ($ledgers, $signed, $hideZero) {
            $lines = [];
            $total = 0.0;
            foreach ($ledgers->filter(fn ($l) => in_array($l->account_type, $types, true))->groupBy('root_id') as $rootLedgers) {
                $rootLines = [];
                $rootTotal = 0.0;
                foreach ($rootLedgers->groupBy('group_id') as $groupLedgers) {
                    $gl = [];
                    foreach ($groupLedgers as $l) {
                        $amt = round($signed[$l->id] * $flip, 2);
                        if ($hideZero && abs($amt) < 0.005) {
                            continue;
                        }
                        $rootTotal += $amt;
                        $gl[] = ['kind' => 'line', 'code' => $l->account_code, 'label' => $l->account_name, 'values' => [$amt], 'indent' => 2];
                    }
                    if ($gl) {
                        $rootLines[] = ['kind' => 'group', 'code' => $groupLedgers->first()->group_code, 'label' => $groupLedgers->first()->group_name ?: 'Ungrouped', 'indent' => 1];
                        array_push($rootLines, ...$gl);
                    }
                }
                if ($rootLines && count($types) > 1) {
                    $lines[] = ['kind' => 'head', 'label' => $rootLedgers->first()->root_name];
                    array_push($lines, ...$rootLines);
                    $lines[] = ['kind' => 'subtotal', 'label' => 'Total ' . $rootLedgers->first()->root_name, 'values' => [round($rootTotal, 2)]];
                } else {
                    array_push($lines, ...$rootLines);
                }
                $total += $rootTotal;
            }

            return [$lines, round($total, 2)];
        };

        [$assetLines, $assets] = $block(self::ASSET_TYPES, 1);
        [$liabLines, $liabilities] = $block(['Liability'], -1);
        [$equityLines, $equity] = $block(['Equity'], -1);

        // Income less expense still in the ledgers (since the last closing) = current year profit.
        $profit = 0.0;
        foreach ($ledgers as $l) {
            if (in_array($l->account_type, self::RESULT_TYPES, true)) {
                $profit -= $signed[$l->id];
            }
        }
        $profit = round($profit, 2);
        $lastClosed = DB::table('fiscal_years')->where('deleted', 'No')->where('status', 'Closed')->where('end_date', '<', $asAt->toDateString())
            ->orderByDesc('end_date')->first(['name', 'end_date']);
        $equityLines[] = ['kind' => 'line', 'label' => 'Current year profit / (loss)' . ($lastClosed ? ' since ' . $lastClosed->name . ' closing' : ''), 'values' => [$profit], 'indent' => 1];
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
            'filters_text' => ['As at' => $asAt->format('d M Y'), 'Scope' => 'All branches (ledger balances are company-wide)'],
            'notes'    => array_merge([
                'Figures are chart of accounts ledger balances (company-wide, all branches); a past date is rebuilt the same way as the trial balance. Assets include Liquid Assets (cash, bank, mobile banking).',
                'Current year profit is the Income less Expense ledger balances not yet closed into retained earnings. Sales and cost of goods sold are not posted to ledgers in this app, so this figure is not the Profit & Loss statement\'s net profit, and stock, receivables and supplier dues from purchases are not on the balance sheet.',
            ], $this->reconciliationNotes()),
            'fileDate' => $asAt->format('Y-m-d'),
        ]);
    }

    private function page(string $key, string $title, string $intro, string $filter)
    {
        return view('admin.accounting.statement', [
            'key'    => $key,
            'title'  => $title,
            'intro'  => $intro,
            'filter' => $filter,
            'urls'   => [
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

    /**
     * Notes on what the dated postings cannot explain:
     * ledgers whose current balance differs from the sum of all their dated postings, and unposted opening balances.
     */
    private function reconciliationNotes(): array
    {
        $notes = [];
        $all = self::movementTotals(null, null);
        $off = [];
        $opening = 0.0;
        foreach (self::ledgers() as $l) {
            $m = $all[$l->id] ?? ['dr' => 0, 'cr' => 0];
            $gap = round((float) $l->current_balance - self::normalDelta($l->balance, $m['dr'], $m['cr']), 2);
            if (abs($gap) >= 0.01) {
                $off[] = $l->account_name . ' ' . number_format($gap, 2);
            }
            $opening += abs((float) $l->opening_balance);
        }
        if ($off) {
            $notes[] = 'Part of these ledger balances has no dated document behind it (posted outside the documents above or before they existed): '
                . implode(', ', array_slice($off, 0, 8)) . (count($off) > 8 ? ' and ' . (count($off) - 8) . ' more' : '')
                . '. It is treated as dated before any report date.';
        }
        if ($opening >= 0.01) {
            $notes[] = 'Opening balances typed in the Chart of Accounts (' . number_format($opening, 2) . ' in total) are not posted to ledger balances and are not included.';
        }

        return $notes;
    }

    /* ================================================================== */
    /*  Output: JSON (page), PDF, Excel                                    */
    /* ================================================================== */

    private function respond(Request $request, string $report, string $format)
    {
        $rules = $report === 'profit-loss'
            ? ['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'], 'compare' => ['nullable', 'boolean']]
            : ['as_at' => ['nullable', 'date'], 'hide_zero' => ['nullable', 'boolean']];
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
            $pdf = PDF::loadView('admin.accounting.statement-pdf', $s + ['company' => $this->company()])
                ->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true);
            $this->addPageNumbers($pdf);

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

    /** "Page X of Y" in the footer, drawn on the canvas. */
    private function addPageNumbers($pdf): void
    {
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 95, $canvas->get_height() - 28,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.58, 0.64, 0.72]
        );
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
