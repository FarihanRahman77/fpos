<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
 * Monthly payroll processing. monthLines() is the one place a month's salary line is worked out:
 *
 *   gross + bonuses + additions - deductions (fines, absence) - unpaid leave - PF - loan installments = net payable
 *
 * Processing a month freezes those lines (payroll_run_items) and posts
 *   Dr salary expense (net + PF + loan)  /  Cr salary payable (net), PF payable (PF), employee loans (loan).
 * Salary is then paid in any number of partial payments (SalaryPaymentController).
 */
class PayrollRunController extends Controller
{
    use AccountBalanceTrait, VoucherHelperTrait;

    /** Bonus scopes, most specific first: when one bonus type reaches an employee twice the more specific one wins. */
    public const SCOPES = ['Employee', 'Step', 'Grade', 'Department', 'All'];

    /**
     * Live salary lines of a month for every employee employed in it, keyed by employee id
     * (processed or not: callers drop the ones in processedIds()).
     */
    public static function monthLines(Carbon $month, ?int $departmentId = null, bool $includeDefaultBonus = false, ?array $employeeIds = null): array
    {
        $month = $month->copy()->startOfMonth();
        $settings = PayrollSetupController::salarySettings();

        $employees = BranchController::scope(DB::table('employees as e'), 'e.branch_id')
            ->leftJoin('pay_steps as ps', 'ps.id', '=', 'e.step_id')
            ->leftJoin('pay_grades as pg', 'pg.id', '=', 'ps.pay_grade_id')
            ->where('e.deleted', 'No')->where('e.status', 'Active')
            ->where(fn ($q) => $q->whereNull('e.joining_date')->orWhere('e.joining_date', '<=', $month->copy()->endOfMonth()->toDateString()))
            ->where(fn ($q) => $q->whereNull('e.resign_date')->orWhere('e.resign_date', '>=', $month->toDateString()))
            ->when($departmentId, fn ($q) => $q->where('e.department_id', $departmentId))
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('e.id', $employeeIds ?: [0]))
            ->orderByRaw('e.department IS NULL')->orderBy('e.department')->orderBy('e.employee_code')
            ->get(['e.*', 'pg.id as grade_id', 'pg.name as grade_name', 'ps.step_no']);

        $ids = $employees->pluck('id')->all() ?: [0];

        $bonuses = DB::table('bonus_assignments as a')->join('bonus_types as t', 't.id', '=', 'a.bonus_type_id')
            ->where('a.month', $month->toDateString())->where('a.deleted', 'No')->where('a.status', 'Active')
            ->where('t.deleted', 'No')->where('t.status', 'Active')
            ->get(['a.*', 't.name', 't.calc_type as type_calc', 't.basis', 't.value as type_value', 't.eligible_only']);

        $adjustments = DB::table('salary_adjustments')->where('month', $month->toDateString())->where('deleted', 'No')
            ->where('status', 'Active')->whereIn('employee_id', $ids)->orderBy('id')->get()->groupBy('employee_id');

        $loans = DB::table('employee_loans')->where('deleted', 'No')->where('loan_status', 'Running')
            ->where('start_month', '<=', $month->toDateString())->whereIn('employee_id', $ids)
            ->orderBy('issue_date')->get()->groupBy('employee_id');

        $unpaid = LeaveController::unpaidDays($month);
        $days = $month->daysInMonth;

        $lines = [];
        foreach ($employees as $e) {
            $row = [
                'employee_id'       => $e->id,
                'employee_code'     => $e->employee_code,
                'name'              => $e->name,
                'designation'       => $e->designation,
                'department'        => $e->department,
                'grade_step'        => $e->grade_name ? $e->grade_name . ' / ' . (int) $e->step_no : '-',
                'basic_salary'      => (float) $e->basic_salary,
                'house_rent'        => (float) $e->house_rent,
                'compensation'      => (float) $e->compensation,
                'medical_allowance' => (float) $e->medical_allowance,
                'gross_salary'      => (float) $e->gross_salary,
                'pf_amount'         => (float) $e->pf_amount,
            ];

            // Bonuses: one per type, the most specific matching assignment.
            $bonusDetail = [];
            $matches = $bonuses->filter(fn ($b) => match ($b->scope) {
                'All'        => true,
                'Department' => (int) $b->scope_id === (int) $e->department_id,
                'Grade'      => $e->grade_id && (int) $b->scope_id === (int) $e->grade_id,
                'Step'       => $e->step_id && (int) $b->scope_id === (int) $e->step_id,
                'Employee'   => (int) $b->scope_id === (int) $e->id,
                default      => false,
            })->filter(fn ($b) => $b->eligible_only !== 'Yes' || $e->bonus_eligible === 'Yes' || $b->scope === 'Employee')
                ->sortBy(fn ($b) => array_search($b->scope, self::SCOPES, true))
                ->unique('bonus_type_id');
            foreach ($matches as $b) {
                $calc  = $b->calc_type ?: $b->type_calc;
                $value = (float) ($b->value ?? $b->type_value);
                $base  = $b->basis === 'Gross' ? $row['gross_salary'] : $row['basic_salary'];
                $amount = $calc === 'Percentage' ? round($base * $value / 100, 2) : round($value, 2);
                if ($amount > 0) {
                    $bonusDetail[] = ['assignment_id' => $b->id, 'name' => $b->name, 'amount' => $amount,
                        'rule' => $calc === 'Percentage' ? rtrim(rtrim(number_format($value, 2), '0'), '.') . '% of ' . strtolower($b->basis) : 'fixed'];
                }
            }
            if ($includeDefaultBonus && (float) $e->bonus_amount > 0) {
                $bonusDetail[] = ['name' => 'Bonus', 'amount' => (float) $e->bonus_amount, 'rule' => (float) $settings->bonus_percent . '% of gross'];
            }

            $adds = $deds = [];
            foreach ($adjustments[$e->id] ?? [] as $a) {
                $item = ['id' => $a->id, 'name' => $a->head . ($a->days ? ' (' . rtrim(rtrim((string) $a->days, '0'), '.') . ' day' . ((float) $a->days == 1 ? '' : 's') . ')' : ''),
                    'amount' => (float) $a->amount, 'remarks' => $a->remarks];
                $a->adj_type === 'Addition' ? $adds[] = $item : $deds[] = $item;
            }

            // Joined or left during the month: the calendar days outside employment are not paid
            // (same daily rate as unpaid leave) and PF is taken only on the days employed.
            $first = $e->joining_date && substr($e->joining_date, 0, 10) > $month->toDateString() ? Carbon::parse($e->joining_date) : $month->copy();
            $last  = $e->resign_date && substr($e->resign_date, 0, 10) < $month->copy()->endOfMonth()->toDateString()
                ? Carbon::parse($e->resign_date) : $month->copy()->endOfMonth();
            $employedDays = max(0, (int) $first->copy()->startOfDay()->diffInDays($last->copy()->startOfDay()) + 1);
            if ($employedDays < $days && $row['gross_salary'] > 0) {
                $off = $days - $employedDays;
                $deds[] = [
                    'id'      => null,
                    'name'    => 'Not employed (' . $off . ' day' . ($off === 1 ? '' : 's') . ')',
                    'amount'  => round($row['gross_salary'] / $days * $off, 2),
                    'remarks' => trim(($first->gt($month) ? 'Joined ' . $first->format('d M Y') : '')
                        . ($last->lt($month->copy()->endOfMonth()->startOfDay()) ? ' Left ' . $last->format('d M Y') : '')),
                ];
                $row['pf_amount'] = round($row['pf_amount'] * $employedDays / $days, 2);
            }

            $row['lwp_days']      = (float) ($unpaid[$e->id] ?? 0);
            $row['lwp_deduction'] = round($row['gross_salary'] / $days * $row['lwp_days'], 2);
            $row['bonus_amount']  = round(array_sum(array_column($bonusDetail, 'amount')), 2);
            $row['additions']     = round(array_sum(array_column($adds, 'amount')), 2);
            $row['deductions']    = round(array_sum(array_column($deds, 'amount')), 2);

            $beforeLoan = round($row['gross_salary'] + $row['bonus_amount'] + $row['additions']
                - $row['deductions'] - $row['lwp_deduction'] - $row['pf_amount'], 2);

            // Loan installments never take the salary below zero.
            $room = max(0, $beforeLoan);
            $loanDetail = [];
            foreach ($loans[$e->id] ?? [] as $l) {
                $due = round(min((float) $l->installment_amount, (float) $l->amount - (float) $l->recovered), 2);
                $take = round(min($due, $room), 2);
                if ($take > 0) {
                    $loanDetail[] = ['loan_id' => $l->id, 'loan_no' => $l->loan_no, 'type' => $l->loan_type, 'amount' => $take];
                    $room -= $take;
                }
            }
            $row['loan_deduction'] = round(array_sum(array_column($loanDetail, 'amount')), 2);
            $row['net_payable']    = round(max(0, $beforeLoan - $row['loan_deduction']), 2);

            $row['bonus_detail']      = $bonusDetail;
            $row['additions_detail']  = $adds;
            $row['deductions_detail'] = $deds;
            $row['loan_detail']       = $loanDetail;

            $lines[$e->id] = $row;
        }

        return $lines;
    }

    /** Employee ids already in a posted run of the month. */
    public static function processedIds(Carbon $month): array
    {
        return DB::table('payroll_run_items as i')->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->where('r.month', $month->copy()->startOfMonth()->toDateString())->where('r.run_status', 'Posted')
            ->pluck('i.employee_id')->all();
    }

    /** Process (freeze and post) the month for every not-yet-processed employee of the sheet. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month'              => ['required', 'date_format:Y-m'],
            'department_id'      => ['nullable', 'integer', Rule::exists('departments', 'id')->where('deleted', 'No')],
            'include_bonus'      => ['nullable', 'boolean'],
            'run_date'           => ['required', 'date'],
            'expense_account_id' => ['required', 'integer', $this->coaExists(3, 'Expense')],
            'payable_account_id' => ['required', 'integer', $this->coaExists(3, 'Liability')],
            'pf_account_id'      => ['nullable', 'integer', $this->coaExists(3, 'Liability')],
            'loan_account_id'    => ['nullable', 'integer', $this->coaExists(3, 'Asset')],
            'remarks'            => ['nullable', 'string', 'max:500'],
        ], [
            'expense_account_id.required' => 'Select the salary expense ledger.',
            'payable_account_id.required' => 'Select the salary payable ledger.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            abort_if(BranchController::current() === null && count(BranchController::allowedIds()) > 1, 422,
                'Choose one branch in the top bar before processing salary (each branch is processed on its own).');

            $run = DB::transaction(function () use ($request) {
                $month = Carbon::createFromFormat('!Y-m', $request->input('month'));
                $deptId = $request->input('department_id') ? (int) $request->input('department_id') : null;

                // Lock the month's runs so two people cannot process the same employees at once.
                DB::table('payroll_runs')->where('month', $month->toDateString())->lockForUpdate()->get(['id']);

                $done = self::processedIds($month);
                $lines = collect(self::monthLines($month, $deptId, $request->boolean('include_bonus')))
                    ->reject(fn ($l) => in_array($l['employee_id'], $done))
                    ->filter(fn ($l) => $l['gross_salary'] > 0 || $l['bonus_amount'] > 0 || $l['additions'] > 0)
                    ->values();

                abort_if($lines->isEmpty(), 422, 'Everyone on this salary sheet is already processed (or has no salary set).');

                $t = fn ($k) => round($lines->sum($k), 2);
                $totals = [
                    'gross' => $t('gross_salary'), 'bonus' => $t('bonus_amount'), 'additions' => $t('additions'),
                    'deductions' => $t('deductions'), 'lwp' => $t('lwp_deduction'), 'pf' => $t('pf_amount'),
                    'loan' => $t('loan_deduction'), 'net' => $t('net_payable'),
                ];

                $pfAccount   = $request->input('pf_account_id') ? (int) $request->input('pf_account_id') : null;
                $loanAccount = $request->input('loan_account_id') ? (int) $request->input('loan_account_id') : null;
                abort_if($totals['pf'] > 0 && !$pfAccount, 422, 'Select the PF payable ledger: ' . number_format($totals['pf'], 2) . ' PF is deducted.');
                abort_if($totals['loan'] > 0 && !$loanAccount, 422, 'Select the employee loan ledger: ' . number_format($totals['loan'], 2) . ' is recovered from loans.');

                $last = DB::table('payroll_runs')->lockForUpdate()->max('run_no');
                $next = $last ? ((int) substr($last, 3)) + 1 : 1;

                $runId = DB::table('payroll_runs')->insertGetId([
                    'run_no'             => 'PR-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                    'month'              => $month->toDateString(),
                    'department_id'      => $deptId,
                    'run_date'           => $request->input('run_date'),
                    'salary_structure'   => PayrollSetupController::salarySettings()->salary_structure,
                    'include_bonus'      => $request->boolean('include_bonus') ? 'Yes' : 'No',
                    'expense_account_id' => (int) $request->input('expense_account_id'),
                    'payable_account_id' => (int) $request->input('payable_account_id'),
                    'pf_account_id'      => $totals['pf'] > 0 ? $pfAccount : null,
                    'loan_account_id'    => $totals['loan'] > 0 ? $loanAccount : null,
                    'employee_count'     => $lines->count(),
                    'total_gross'        => $totals['gross'],
                    'total_bonus'        => $totals['bonus'],
                    'total_additions'    => $totals['additions'],
                    'total_deductions'   => $totals['deductions'],
                    'total_lwp'          => $totals['lwp'],
                    'total_pf'           => $totals['pf'],
                    'total_loan'         => $totals['loan'],
                    'total_net'          => $totals['net'],
                    'remarks'            => $this->nullIfBlank($request->input('remarks')),
                    'created_by'         => Auth::id(),
                    'created_date'       => now(),
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                foreach ($lines as $l) {
                    DB::table('payroll_run_items')->insert([
                        'payroll_run_id'    => $runId,
                        'employee_id'       => $l['employee_id'],
                        'department'        => $l['department'],
                        'designation'       => $l['designation'],
                        'grade_step'        => $l['grade_step'] !== '-' ? $l['grade_step'] : null,
                        'basic_salary'      => $l['basic_salary'],
                        'house_rent'        => $l['house_rent'],
                        'compensation'      => $l['compensation'],
                        'medical_allowance' => $l['medical_allowance'],
                        'gross_salary'      => $l['gross_salary'],
                        'bonus_amount'      => $l['bonus_amount'],
                        'bonus_detail'      => json_encode($l['bonus_detail']),
                        'additions'         => $l['additions'],
                        'additions_detail'  => json_encode($l['additions_detail']),
                        'deductions'        => $l['deductions'],
                        'deductions_detail' => json_encode($l['deductions_detail']),
                        'lwp_days'          => $l['lwp_days'],
                        'lwp_deduction'     => $l['lwp_deduction'],
                        'pf_amount'         => $l['pf_amount'],
                        'loan_deduction'    => $l['loan_deduction'],
                        'loan_detail'       => json_encode($l['loan_detail']),
                        'net_payable'       => $l['net_payable'],
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);

                    foreach ($l['loan_detail'] as $d) {
                        EmployeeLoanController::recover((int) $d['loan_id'], (float) $d['amount'], $runId, $request->input('run_date'),
                            'Salary of ' . $month->format('F Y'));
                    }
                }

                $run = DB::table('payroll_runs')->where('id', $runId)->first();
                $this->post($run, 1);

                DB::table('payroll_settings')->update([
                    'salary_expense_account_id' => $run->expense_account_id,
                    'salary_payable_account_id' => $run->payable_account_id,
                    'pf_account_id'             => $pfAccount ?? DB::raw('pf_account_id'),
                    'loan_account_id'           => $loanAccount ?? DB::raw('loan_account_id'),
                ]);

                return $run;
            });

            return response()->json([
                'status'  => true,
                'message' => 'Salary processed (' . $run->run_no . '): ' . $run->employee_count . ' employee(s), '
                    . number_format((float) $run->total_net, 2) . ' payable. Now pay it from the salary sheet.',
            ]);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Payroll run error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to process the salary. Please try again.'], 500);
        }
    }

    /** Undo a run that has no payments yet: reverses the posting and the loan recoveries. */
    public function reverse(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), ['reason' => ['required', 'string', 'max:255']], [
            'reason.required' => 'Write why the processed salary is reversed.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $run = DB::transaction(function () use ($request, $id) {
                $run = BranchController::scope(DB::table('payroll_runs'), 'branch_id')->where('id', $id)->lockForUpdate()->first();
                abort_unless($run, 404, 'Processed salary not found.');
                abort_unless($run->run_status === 'Posted', 422, 'This run is already reversed.');
                abort_if(DB::table('salary_payments')->where('payroll_run_id', $id)->where('payment_status', 'Posted')->exists(), 422,
                    'Cancel the salary payments of ' . $run->run_no . ' first.');

                $this->post($run, -1);
                EmployeeLoanController::reverseRun($id);

                DB::table('payroll_runs')->where('id', $id)->update([
                    'run_status'     => 'Reversed',
                    'reversed_by'    => Auth::id(),
                    'reversed_at'    => now(),
                    'reverse_reason' => trim($request->input('reason')),
                    'updated_at'     => now(),
                ]);

                return $run;
            });

            return response()->json(['status' => true, 'message' => $run->run_no . ' reversed. Those employees can be processed again.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Payroll run reverse error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to reverse the processed salary.'], 500);
        }
    }

    /** Posted / reversed runs of a month, for the salary sheet. */
    public function runs(Request $request)
    {
        $m = (string) $request->query('month', now()->format('Y-m'));
        $month = preg_match('/^\d{4}-\d{2}$/', $m) ? Carbon::createFromFormat('!Y-m', $m) : now()->startOfMonth();

        $runs = BranchController::scope(DB::table('payroll_runs as r'), 'r.branch_id')->leftJoin('departments as d', 'd.id', '=', 'r.department_id')
            ->where('r.month', $month->toDateString())->orderBy('r.id')
            ->get(['r.id', 'r.run_no', 'r.run_date', 'r.employee_count', 'r.total_net', 'r.total_paid', 'r.run_status', 'd.name as department']);

        return response()->json(['status' => true, 'data' => $runs]);
    }

    /** Dr expense (net + PF + loan) / Cr salary payable (net), PF payable, employee loans. */
    private function post(object $run, int $direction): void
    {
        $credits = [(int) $run->payable_account_id => (float) $run->total_net];
        if ((float) $run->total_pf > 0 && $run->pf_account_id) {
            $credits[(int) $run->pf_account_id] = ($credits[(int) $run->pf_account_id] ?? 0) + (float) $run->total_pf;
        }
        if ((float) $run->total_loan > 0) {
            // Each installment goes back to the ledger its loan / advance was paid out of
            // (an advance and a loan can sit in different Asset ledgers); anything else to the run's ledger.
            $byLedger = DB::table('employee_loan_transactions as t')->join('employee_loans as l', 'l.id', '=', 't.employee_loan_id')
                ->where('t.payroll_run_id', $run->id)->where('t.txn_type', 'Salary Deduction')->where('t.txn_status', 'Posted')
                ->whereNotNull('l.loan_account_id')
                ->groupBy('l.loan_account_id')->selectRaw('l.loan_account_id, SUM(t.amount) as amount')->pluck('amount', 'loan_account_id');
            $rest = (float) $run->total_loan;
            foreach ($byLedger as $ledger => $amount) {
                $credits[(int) $ledger] = round(($credits[(int) $ledger] ?? 0) + (float) $amount, 2);
                $rest = round($rest - (float) $amount, 2);
            }
            if (abs($rest) >= 0.005 && $run->loan_account_id) {
                $credits[(int) $run->loan_account_id] = round(($credits[(int) $run->loan_account_id] ?? 0) + $rest, 2);
            }
        }

        $this->postBalances(
            [(int) $run->expense_account_id => round((float) $run->total_net + (float) $run->total_pf + (float) $run->total_loan, 2)],
            $credits,
            $direction
        );
    }
}
