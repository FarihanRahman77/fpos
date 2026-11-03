<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\SafeJsonErrors;
use App\Traits\AccountBalanceTrait;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Advance salary and employee loans.
 *
 * Disbursement: Dr employee loans (asset) / Cr cash-bank.
 * Recovery: monthly installments taken by the payroll run (posted with the run), or a cash repayment
 * (Dr cash-bank / Cr employee loans). The schedule can be changed while the loan runs.
 */
class EmployeeLoanController extends Controller
{
    use AccountBalanceTrait, VoucherHelperTrait, SafeJsonErrors;

    public function index()
    {
        // Paid from: cash / bank usable in the logged-in branch (shared + its own). Ledger: employee loan / advance asset ledgers only.
        return view('admin.payroll.loans.index', [
            // the loan form gives loans to the working branch's employees (the list filter uses the same list)
            'employees'       => BranchController::scopeOwn(DB::table('employees'), 'branch_id')->where('deleted', 'No')->where('status', 'Active')->orderBy('name')
                ->get(['id', 'name', 'employee_code', 'department', 'gross_salary', 'net_salary']),
            'paymentAccounts' => PayrollSetupController::payrollLedgers('Liquid Asset'),
            'loanAccounts'    => PayrollSetupController::loanLedgers(),
            'ledgerDefaults'  => PayrollSetupController::payrollLedgerDefaults(),
            'payroll'         => PayrollSetupController::salarySettings(),
        ]);
    }

    public function list(Request $request)
    {
        try {
            $rows = BranchController::scope(DB::table('employee_loans as l'), 'l.branch_id')->join('employees as e', 'e.id', '=', 'l.employee_id')
                ->where('l.deleted', 'No')
                ->when($request->filled('loan_status'), fn ($q) => $q->where('l.loan_status', $request->query('loan_status')))
                ->when($request->filled('employee_id'), fn ($q) => $q->where('l.employee_id', (int) $request->query('employee_id')))
                ->orderByRaw("FIELD(l.loan_status, 'Running', 'Closed', 'Cancelled')")->orderByDesc('l.issue_date')->orderByDesc('l.id')
                ->get(['l.*', 'e.name', 'e.employee_code']);

            $m = fn ($v) => '<span class="d-block text-end">' . number_format((float) $v, 2) . '</span>';
            $badge = ['Running' => 'bg-warning text-dark', 'Closed' => 'bg-success', 'Cancelled' => 'bg-secondary'];

            return response()->json([
                'data' => $rows->values()->map(function ($r, $i) use ($m, $badge) {
                    $id = (int) $r->id;
                    $items = [['label' => 'View schedule', 'icon' => 'fas fa-eye', 'onclick' => "Loan.view($id)"]];
                    if ($r->loan_status === 'Running') {
                        $items[] = ['label' => 'Change schedule', 'icon' => 'fas fa-calendar-alt', 'onclick' => "Loan.reschedule($id)"];
                        $items[] = ['label' => 'Cash repayment', 'icon' => 'fas fa-hand-holding-usd', 'onclick' => "Loan.repay($id)"];
                        if ((float) $r->recovered == 0) {
                            $items[] = ['divider' => true];
                            $items[] = ['label' => 'Cancel', 'icon' => 'fas fa-ban', 'class' => 'text-danger', 'onclick' => "Loan.cancel($id)"];
                        }
                    }
                    $balance = (float) $r->amount - (float) $r->recovered;

                    return [
                        $i + 1,
                        '<span class="fw-semibold">' . e($r->loan_no) . '</span><div class="text-muted small">' . e($r->loan_type) . '</div>' . branch_tag($r->branch_id),
                        e($r->name) . '<div class="text-muted small">' . e($r->employee_code) . '</div>',
                        e(Carbon::parse($r->issue_date)->format('d M Y')),
                        $m($r->amount),
                        '<span class="d-block text-end">' . number_format((float) $r->installment_amount, 2) . '</span><div class="text-muted small text-end">'
                            . (int) $r->installments . ' × from ' . e(Carbon::parse($r->start_month)->format('M Y')) . '</div>',
                        $m($r->recovered),
                        '<span class="d-block text-end fw-semibold">' . number_format($balance, 2) . '</span>',
                        '<span class="badge ' . ($badge[$r->loan_status] ?? 'bg-secondary') . '">' . e($r->loan_status) . '</span>',
                        action_dropdown($items, $r->branch_id),
                    ];
                }),
            ]);
        } catch (Throwable $e) {
            Log::error('Loan list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load loans.'], 500);
        }
    }

    public function show(int $id)
    {
        $loan = BranchController::scope(DB::table('employee_loans as l'), 'l.branch_id')->join('employees as e', 'e.id', '=', 'l.employee_id')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'l.payment_account_id')
            ->where('l.id', $id)->where('l.deleted', 'No')
            ->first(['l.*', 'e.name', 'e.employee_code', 'e.department', 'a.account_name as paid_from']);

        if (!$loan) {
            return response()->json(['status' => false, 'message' => 'Loan not found.'], 404);
        }

        $txns = DB::table('employee_loan_transactions as t')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 't.payment_account_id')
            ->leftJoin('payroll_runs as r', 'r.id', '=', 't.payroll_run_id')
            ->where('t.employee_loan_id', $id)->orderBy('t.txn_date')->orderBy('t.id')
            ->get(['t.*', 'a.account_name as account', 'r.run_no']);

        return response()->json(['status' => true, 'data' => $loan, 'transactions' => $txns, 'schedule' => self::schedule($loan)]);
    }

    /** Remaining installments by month, from the next unrecovered month. */
    public static function schedule(object $loan): array
    {
        $left = round((float) $loan->amount - (float) $loan->recovered, 2);
        if ($loan->loan_status !== 'Running' || $left <= 0) {
            return [];
        }

        $recoveredMonths = DB::table('employee_loan_transactions as t')->join('payroll_runs as r', 'r.id', '=', 't.payroll_run_id')
            ->where('t.employee_loan_id', $loan->id)->where('t.txn_type', 'Salary Deduction')->where('t.txn_status', 'Posted')
            ->pluck('r.month')->map(fn ($m) => substr($m, 0, 7))->all();

        $month = Carbon::parse($loan->start_month)->startOfMonth();
        $rows = [];
        while ($left > 0.004 && count($rows) < 240) {
            if (!in_array($month->format('Y-m'), $recoveredMonths, true)) {
                $amount = round(min((float) $loan->installment_amount, $left), 2);
                $left = round($left - $amount, 2);
                $rows[] = ['ym' => $month->format('Y-m'), 'month' => $month->format('M Y'), 'amount' => $amount, 'balance' => $left];
            }
            $month->addMonth();
        }

        return $rows;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id'        => ['required', 'integer', Rule::exists('employees', 'id')->where('deleted', 'No')->where('status', 'Active')
                ->where('branch_id', (int) BranchController::activeId())],
            'loan_type'          => ['required', Rule::in(['Advance Salary', 'Loan'])],
            'amount'             => ['required', 'numeric', 'min:1'],
            'issue_date'         => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', $this->coaExists(3, 'Liquid Asset')],
            'loan_account_id'    => ['required', 'integer', $this->coaExists(3, 'Asset'),
                Rule::in(PayrollSetupController::loanLedgers()->pluck('id')->all())],
            'installments'       => ['required', 'integer', 'min:1', 'max:240'],
            'start_month'        => ['required', 'date_format:Y-m'],
            'remarks'            => ['nullable', 'string', 'max:255'],
        ], [
            'payment_account_id.required' => 'Select the cash / bank account the money is paid from.',
            'loan_account_id.required'    => 'Select the employee loan / advance ledger (an Asset ledger).',
            'loan_account_id.in'          => 'Select an employee loan / advance ledger (e.g. "Employee Loans & Advances").',
            'payment_account_id.exists'   => 'Select a cash / bank account of this branch.',
            'start_month.required'        => 'Select the first salary month to deduct from.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $loan = DB::transaction(function () use ($request) {
                $amount = round((float) $request->input('amount'), 2);
                $count  = (int) $request->input('installments');
                $start  = Carbon::createFromFormat('!Y-m', $request->input('start_month'));
                abort_if($start->lt(Carbon::parse($request->input('issue_date'))->startOfMonth()), 422, 'Deduction cannot start before the issue month.');
                ReportController::ensureCash($request->input('payment_account_id'), $amount);

                $last = DB::table('employee_loans')->lockForUpdate()->max('loan_no');
                $next = $last ? ((int) substr($last, 3)) + 1 : 1;
                $loanNo = 'LN-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);

                $id = DB::table('employee_loans')->insertGetId([
                    'loan_no'            => $loanNo,
                    'employee_id'        => (int) $request->input('employee_id'),
                    'loan_type'          => $request->input('loan_type'),
                    'amount'             => $amount,
                    'issue_date'         => $request->input('issue_date'),
                    'payment_account_id' => (int) $request->input('payment_account_id'),
                    'loan_account_id'    => (int) $request->input('loan_account_id'),
                    'installments'       => $count,
                    'installment_amount' => round(ceil($amount / $count * 100) / 100, 2),
                    'start_month'        => $start->toDateString(),
                    'remarks'            => $this->nullIfBlank($request->input('remarks')),
                    'created_by'         => Auth::id(),
                    'created_date'       => now(),
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                self::log($id, $request->input('issue_date'), 'Disbursement', $amount, null, (int) $request->input('payment_account_id'), $loanNo,
                    $request->input('loan_type') . ' paid out');

                $this->postBalances([(int) $request->input('loan_account_id') => $amount], [(int) $request->input('payment_account_id') => $amount], 1);

                DB::table('payroll_settings')->update(['loan_account_id' => (int) $request->input('loan_account_id')]);

                return DB::table('employee_loans')->where('id', $id)->first();
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => $loan->loan_no . ' issued: ' . number_format((float) $loan->amount, 2)
                . ', ' . $loan->installments . ' installment(s) of ' . number_format((float) $loan->installment_amount, 2) . '.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Loan store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the loan.'], 500);
        }
    }

    /** Change the installment (amount or count) and / or the next deduction month for the remaining balance. */
    public function reschedule(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'installments' => ['required', 'integer', 'min:1', 'max:240'],
            'start_month'  => ['required', 'date_format:Y-m'],
            'remarks'      => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            DB::transaction(function () use ($request, $id) {
                BranchController::guardOwn('employee_loans', $id);
                $loan = BranchController::scopeOwn(DB::table('employee_loans'), 'branch_id')->where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();
                abort_unless($loan && $loan->loan_status === 'Running', 422, 'Only a running loan can be rescheduled.');

                $left = round((float) $loan->amount - (float) $loan->recovered, 2);
                $count = (int) $request->input('installments');
                $installment = round(ceil($left / $count * 100) / 100, 2);

                DB::table('employee_loans')->where('id', $id)->update([
                    'installments'       => (int) DB::table('employee_loan_transactions')->where('employee_loan_id', $id)
                        ->where('txn_type', 'Salary Deduction')->where('txn_status', 'Posted')->count() + $count,
                    'installment_amount' => $installment,
                    'start_month'        => Carbon::createFromFormat('!Y-m', $request->input('start_month'))->toDateString(),
                    'updated_by'         => Auth::id(),
                    'updated_date'       => now(),
                    'updated_at'         => now(),
                ]);

                self::log($id, now()->toDateString(), 'Reschedule', 0, null, null, null,
                    trim($count . ' × ' . number_format($installment, 2) . ' from ' . Carbon::createFromFormat('!Y-m', $request->input('start_month'))->format('M Y')
                        . ($request->input('remarks') ? ' · ' . $request->input('remarks') : '')));
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => 'Schedule updated.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Loan reschedule error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to change the schedule.'], 500);
        }
    }

    /** Employee pays back in cash / bank: Dr cash-bank / Cr employee loans. */
    public function repay(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'amount'             => ['required', 'numeric', 'min:0.01'],
            'txn_date'           => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', $this->coaExists(3, 'Liquid Asset')],
            'remarks'            => ['nullable', 'string', 'max:255'],
        ], [
            'payment_account_id.required' => 'Select the cash / bank account the money is received in.',
            'payment_account_id.exists'   => 'Select a cash / bank account of this branch.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $loan = DB::transaction(function () use ($request, $id) {
                BranchController::guardOwn('employee_loans', $id);
                $loan = BranchController::scopeOwn(DB::table('employee_loans'), 'branch_id')->where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();
                abort_unless($loan && $loan->loan_status === 'Running', 422, 'Only a running loan can be repaid.');

                $left = round((float) $loan->amount - (float) $loan->recovered, 2);
                $amount = round((float) $request->input('amount'), 2);
                abort_if($amount > $left + 0.004, 422, 'Only ' . number_format($left, 2) . ' is left on this loan.');

                $last = DB::table('employee_loan_transactions')->where('txn_type', 'Cash Repayment')->lockForUpdate()->max('voucher_no');
                $next = $last ? ((int) substr($last, 4)) + 1 : 1;

                self::log($id, $request->input('txn_date'), 'Cash Repayment', $amount, null, (int) $request->input('payment_account_id'),
                    'LRP-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT), $this->nullIfBlank($request->input('remarks')));

                self::applyRecovery($loan, $amount);
                $this->postBalances([(int) $request->input('payment_account_id') => $amount], [(int) $loan->loan_account_id => $amount], 1);

                return DB::table('employee_loans')->where('id', $id)->first();
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => 'Repayment saved. ' . ($loan->loan_status === 'Closed' ? $loan->loan_no . ' is fully repaid.'
                : number_format((float) $loan->amount - (float) $loan->recovered, 2) . ' left.')]);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Loan repay error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the repayment.'], 500);
        }
    }

    /** A loan nothing was recovered from can be cancelled: reverses the disbursement. */
    public function cancel(Request $request, int $id)
    {
        try {
            DB::transaction(function () use ($request, $id) {
                BranchController::guardOwn('employee_loans', $id);
                $loan = BranchController::scopeOwn(DB::table('employee_loans'), 'branch_id')->where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();
                abort_unless($loan && $loan->loan_status === 'Running', 422, 'Only a running loan can be cancelled.');
                abort_if((float) $loan->recovered > 0, 422, 'Part of this loan is already recovered; it cannot be cancelled.');

                $this->postBalances([(int) $loan->loan_account_id => (float) $loan->amount], [(int) $loan->payment_account_id => (float) $loan->amount], -1);

                DB::table('employee_loan_transactions')->where('employee_loan_id', $id)->where('txn_type', 'Disbursement')
                    ->update(['txn_status' => 'Reversed', 'updated_at' => now()]);
                self::log($id, now()->toDateString(), 'Cancel', (float) $loan->amount, null, null, null, $this->nullIfBlank($request->input('reason')));

                DB::table('employee_loans')->where('id', $id)->update([
                    'loan_status' => 'Cancelled', 'updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now(),
                ]);
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => 'Loan cancelled and the payout reversed.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Loan cancel error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to cancel the loan.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Used by PayrollRunController (posting is part of the run)          */
    /* ------------------------------------------------------------------ */

    public static function recover(int $loanId, float $amount, int $runId, string $date, string $remarks): void
    {
        $loan = DB::table('employee_loans')->where('id', $loanId)->lockForUpdate()->first();
        self::log($loanId, $date, 'Salary Deduction', $amount, $runId, null, null, $remarks);
        self::applyRecovery($loan, $amount);
    }

    public static function reverseRun(int $runId): void
    {
        $txns = DB::table('employee_loan_transactions')->where('payroll_run_id', $runId)->where('txn_status', 'Posted')->get();

        foreach ($txns as $t) {
            $loan = DB::table('employee_loans')->where('id', $t->employee_loan_id)->lockForUpdate()->first();
            $recovered = round((float) $loan->recovered - (float) $t->amount, 2);
            DB::table('employee_loans')->where('id', $loan->id)->update([
                'recovered'   => max(0, $recovered),
                'loan_status' => $loan->loan_status === 'Cancelled' ? 'Cancelled' : 'Running',
                'updated_at'  => now(),
            ]);
            DB::table('employee_loan_transactions')->where('id', $t->id)->update(['txn_status' => 'Reversed', 'updated_at' => now()]);
        }
    }

    private static function applyRecovery(object $loan, float $amount): void
    {
        $recovered = round((float) $loan->recovered + $amount, 2);
        DB::table('employee_loans')->where('id', $loan->id)->update([
            'recovered'   => $recovered,
            'loan_status' => $recovered >= (float) $loan->amount - 0.004 ? 'Closed' : 'Running',
            'updated_at'  => now(),
        ]);
    }

    private static function log(int $loanId, string $date, string $type, float $amount, ?int $runId, ?int $accountId, ?string $voucher, ?string $remarks): void
    {
        DB::table('employee_loan_transactions')->insert([
            'employee_loan_id'   => $loanId,
            'txn_date'           => $date,
            'txn_type'           => $type,
            'amount'             => round($amount, 2),
            'payroll_run_id'     => $runId,
            'payment_account_id' => $accountId,
            'voucher_no'         => $voucher,
            'remarks'            => $remarks,
            'created_by'         => Auth::id(),
            'created_date'       => now(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }
}
