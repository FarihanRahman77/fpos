<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Bonus types, the months and employees they are granted to, and per-employee monthly
 * additions / deductions (incl. absence fines). All of it feeds PayrollRunController::monthLines().
 * A month that is already processed for an employee is frozen: changes apply to employees not processed yet.
 */
class SalaryAdjustmentController extends Controller
{
    use VoucherHelperTrait;

    public const ADDITION_HEADS  = ['Overtime', 'Arrear', 'Incentive', 'Allowance', 'Other Addition'];
    public const DEDUCTION_HEADS = ['Absence', 'Late Fine', 'Penalty', 'Tax', 'Other Deduction'];

    public function index()
    {
        $settings = PayrollSetupController::salarySettings();

        return view('admin.payroll.adjustments.index', [
            'isScale'        => $settings->salary_structure === 'Scale',
            'settings'       => $settings,
            'departments'    => DB::table('departments')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']),
            'grades'         => DB::table('pay_grades')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']),
            'steps'          => DB::table('pay_steps as s')->join('pay_grades as g', 'g.id', '=', 's.pay_grade_id')
                ->leftJoin('departments as d', 'd.id', '=', 's.department_id')->where('s.deleted', 'No')
                ->orderBy('g.name')->orderBy('s.step_no')->get(['s.id', 's.step_no', 's.basic_salary', 'g.name as grade', 'd.name as department']),
            'employees'      => BranchController::scope(DB::table('employees'), 'branch_id')->where('deleted', 'No')->where('status', 'Active')->orderBy('name')
                ->get(['id', 'name', 'employee_code', 'department', 'basic_salary', 'gross_salary']),
            'bonusTypes'     => DB::table('bonus_types')->where('deleted', 'No')->orderBy('name')->get(),
            'additionHeads'  => self::ADDITION_HEADS,
            'deductionHeads' => self::DEDUCTION_HEADS,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Bonus types                                                        */
    /* ------------------------------------------------------------------ */

    public function types()
    {
        $rows = DB::table('bonus_types')->where('deleted', 'No')->orderBy('name')->get();

        return response()->json(['status' => true, 'data' => $rows]);
    }

    public function saveType(Request $request)
    {
        $id = $request->input('id') ? (int) $request->input('id') : null;
        $validator = Validator::make($request->all(), [
            'name'          => ['required', 'string', 'max:100', Rule::unique('bonus_types', 'name')->where('deleted', 'No')->ignore($id)],
            'calc_type'     => ['required', Rule::in(['Percentage', 'Amount'])],
            'basis'         => ['required', Rule::in(['Basic', 'Gross'])],
            'value'         => ['required', 'numeric', 'min:0', $request->input('calc_type') === 'Percentage' ? 'max:1000' : 'max:99999999'],
            'eligible_only' => ['required', Rule::in(['Yes', 'No'])],
            'status'        => ['required', Rule::in(['Active', 'Inactive'])],
            'description'   => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $validator->validated();
            $data['description'] = $this->nullIfBlank($data['description'] ?? null);

            if ($id) {
                DB::table('bonus_types')->where('id', $id)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
            } else {
                DB::table('bonus_types')->insert($data + ['created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }

            return response()->json(['status' => true, 'message' => 'Bonus type saved.']);
        } catch (Throwable $e) {
            Log::error('Bonus type save error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the bonus type.'], 500);
        }
    }

    public function deleteType(int $id)
    {
        if (DB::table('bonus_assignments')->where('bonus_type_id', $id)->where('deleted', 'No')->exists()) {
            return response()->json(['status' => false, 'message' => 'This bonus type is granted in some months. Remove those first, or set it Inactive.'], 422);
        }

        DB::table('bonus_types')->where('id', $id)->update($this->deletedColumns());

        return response()->json(['status' => true, 'message' => 'Bonus type removed.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Bonus assignments (month + who)                                    */
    /* ------------------------------------------------------------------ */

    public function assignments(Request $request)
    {
        $month = $this->month($request->query('month'));

        $rows = DB::table('bonus_assignments as a')->join('bonus_types as t', 't.id', '=', 'a.bonus_type_id')
            ->where('a.month', $month->toDateString())->where('a.deleted', 'No')
            ->orderBy('t.name')->orderByRaw("FIELD(a.scope, 'All', 'Department', 'Grade', 'Step', 'Employee')")
            ->get(['a.*', 't.name', 't.calc_type as type_calc', 't.basis', 't.value as type_value', 't.eligible_only']);

        $names = [
            'Department' => DB::table('departments')->pluck('name', 'id'),
            'Grade'      => DB::table('pay_grades')->pluck('name', 'id'),
            'Step'       => DB::table('pay_steps as s')->join('pay_grades as g', 'g.id', '=', 's.pay_grade_id')
                ->selectRaw("s.id, CONCAT(g.name, ' / Step ', s.step_no) as label")->pluck('label', 'id'),
            'Employee'   => DB::table('employees')->selectRaw("id, CONCAT(name, ' (', COALESCE(employee_code, ''), ')') as label")->pluck('label', 'id'),
        ];

        // How many employees each assignment reaches and what it costs (from the live month lines).
        $lines = PayrollRunController::monthLines($month);

        return response()->json(['status' => true, 'data' => $rows->map(function ($r) use ($names, $lines) {
            $calc = $r->calc_type ?: $r->type_calc;
            $value = (float) ($r->value ?? $r->type_value);
            $r->who = $r->scope === 'All' ? 'All employees' : $r->scope . ': ' . ($names[$r->scope][$r->scope_id] ?? '#' . $r->scope_id);
            $r->rule = $calc === 'Percentage' ? rtrim(rtrim(number_format($value, 2), '0'), '.') . '% of ' . strtolower($r->basis) : number_format($value, 2) . ' fixed';
            // Only the employees this grant actually reaches (a more specific grant of the same bonus wins).
            $hits = collect($lines)->filter(fn ($l) => collect($l['bonus_detail'])->contains('assignment_id', $r->id));
            $r->employees = $hits->count();
            $r->total = round($hits->sum(fn ($l) => collect($l['bonus_detail'])->firstWhere('assignment_id', $r->id)['amount'] ?? 0), 2);

            return $r;
        })]);
    }

    public function saveAssignment(Request $request)
    {
        $id = $request->input('id') ? (int) $request->input('id') : null;
        $scope = $request->input('scope');
        $table = ['Department' => 'departments', 'Grade' => 'pay_grades', 'Step' => 'pay_steps', 'Employee' => 'employees'][$scope] ?? null;

        $validator = Validator::make($request->all(), [
            'bonus_type_id' => ['required', 'integer', Rule::exists('bonus_types', 'id')->where('deleted', 'No')],
            'month'         => ['required', 'date_format:Y-m'],
            'scope'         => ['required', Rule::in(PayrollRunController::SCOPES)],
            'scope_id'      => $table ? ['required', 'integer', Rule::exists($table, 'id')->where('deleted', 'No')] : ['nullable'],
            'calc_type'     => ['nullable', Rule::in(['Percentage', 'Amount'])],
            'value'         => ['nullable', 'numeric', 'min:0', 'required_with:calc_type'],
            'remarks'       => ['nullable', 'string', 'max:255'],
        ], [
            'scope_id.required'   => 'Select the ' . strtolower((string) $scope) . '.',
            'value.required_with' => 'Enter the bonus value for this override.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $data = [
                'bonus_type_id' => (int) $request->input('bonus_type_id'),
                'month'         => $this->month($request->input('month'))->toDateString(),
                'scope'         => $scope,
                'scope_id'      => $table ? (int) $request->input('scope_id') : null,
                'calc_type'     => $request->input('calc_type') ?: null,
                'value'         => $request->input('calc_type') ? round((float) $request->input('value'), 2) : null,
                'remarks'       => $this->nullIfBlank($request->input('remarks')),
            ];

            $duplicate = DB::table('bonus_assignments')->where('deleted', 'No')->where('bonus_type_id', $data['bonus_type_id'])
                ->where('month', $data['month'])->where('scope', $scope)->where(fn ($q) => $data['scope_id'] ? $q->where('scope_id', $data['scope_id']) : $q->whereNull('scope_id'))
                ->when($id, fn ($q) => $q->where('id', '<>', $id))->exists();
            if ($duplicate) {
                return response()->json(['status' => false, 'message' => 'This bonus is already granted to them for that month.'], 422);
            }

            if ($id) {
                DB::table('bonus_assignments')->where('id', $id)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
            } else {
                DB::table('bonus_assignments')->insert($data + ['created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }

            return response()->json(['status' => true, 'message' => 'Bonus granted for ' . Carbon::parse($data['month'])->format('F Y') . '.']);
        } catch (Throwable $e) {
            Log::error('Bonus assignment save error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the bonus.'], 500);
        }
    }

    public function deleteAssignment(int $id)
    {
        DB::table('bonus_assignments')->where('id', $id)->update($this->deletedColumns());

        return response()->json(['status' => true, 'message' => 'Bonus removed from that month.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Additions / deductions                                             */
    /* ------------------------------------------------------------------ */

    public function adjustments(Request $request)
    {
        $month = $this->month($request->query('month'));
        $processed = PayrollRunController::processedIds($month);

        $rows = BranchController::scope(DB::table('salary_adjustments as a'), 'e.branch_id')->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->where('a.month', $month->toDateString())->where('a.deleted', 'No')
            ->when($request->filled('employee_id'), fn ($q) => $q->where('a.employee_id', (int) $request->query('employee_id')))
            ->orderBy('e.name')->orderBy('a.adj_type')->orderBy('a.id')
            ->get(['a.*', 'e.name', 'e.employee_code', 'e.department'])
            ->map(function ($r) use ($processed) {
                $r->locked = in_array($r->employee_id, $processed);

                return $r;
            });

        return response()->json(['status' => true, 'data' => $rows, 'totals' => [
            'additions'  => round($rows->where('adj_type', 'Addition')->sum('amount'), 2),
            'deductions' => round($rows->where('adj_type', 'Deduction')->sum('amount'), 2),
        ]]);
    }

    /** Daily rate used for absence fines: basic or gross (Payroll setting) / days in the month. */
    public static function dailyRate(object $employee, Carbon $month): float
    {
        $basis = PayrollSetupController::salarySettings()->absence_basis ?? 'Gross';
        $salary = $basis === 'Basic' ? (float) $employee->basic_salary : (float) $employee->gross_salary;

        return round($salary / $month->daysInMonth, 2);
    }

    public function saveAdjustment(Request $request)
    {
        $id = $request->input('id') ? (int) $request->input('id') : null;
        $isAbsence = $request->input('adj_type') === 'Deduction' && $request->input('head') === 'Absence';

        $validator = Validator::make($request->all(), [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('deleted', 'No')],
            'month'       => ['required', 'date_format:Y-m'],
            'adj_type'    => ['required', Rule::in(['Addition', 'Deduction'])],
            'head'        => ['required', 'string', 'max:60'],
            'days'        => $isAbsence ? ['required', 'numeric', 'min:0.5', 'max:31'] : ['nullable'],
            'amount'      => $isAbsence ? ['nullable', 'numeric', 'min:0'] : ['required', 'numeric', 'min:0.01'],
            'remarks'     => ['nullable', 'string', 'max:255'],
        ], [
            'days.required'   => 'Enter the absent days.',
            'amount.required' => 'Enter the amount.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $month = $this->month($request->input('month'));
            $employeeId = (int) $request->input('employee_id');

            if (!self::ownEmployee($employeeId)) {
                return response()->json(['status' => false, 'message' => 'Select an employee of this branch.'], 422);
            }

            if (in_array($employeeId, PayrollRunController::processedIds($month))) {
                return response()->json(['status' => false, 'message' => 'Salary of ' . $month->format('F Y')
                    . ' is already processed for this employee. Reverse that run first, or enter it in the next month.'], 422);
            }

            // An entry already frozen into a processed month cannot be moved or changed either.
            if ($id) {
                $current = DB::table('salary_adjustments')->where('id', $id)->where('deleted', 'No')->first();
                if (!$current || !self::ownEmployee((int) $current->employee_id)) {
                    return response()->json(['status' => false, 'message' => 'That entry no longer exists. Reload the list.'], 404);
                }
                if (in_array((int) $current->employee_id, PayrollRunController::processedIds(Carbon::parse($current->month)))) {
                    return response()->json(['status' => false, 'message' => 'This entry is part of the processed salary of '
                        . Carbon::parse($current->month)->format('F Y') . '. Reverse that run first.'], 422);
                }
            }

            $amount = round((float) $request->input('amount'), 2);
            $days = null;
            if ($isAbsence) {
                $days = round((float) $request->input('days'), 1);
                // Blank amount = days × daily rate; a typed amount overrides it.
                if (!$request->filled('amount')) {
                    $amount = round(self::dailyRate(DB::table('employees')->where('id', $employeeId)->first(), $month) * $days, 2);
                }
            }

            $data = [
                'employee_id' => $employeeId,
                'month'       => $month->toDateString(),
                'adj_type'    => $request->input('adj_type'),
                'head'        => trim($request->input('head')),
                'days'        => $days,
                'amount'      => $amount,
                'remarks'     => $this->nullIfBlank($request->input('remarks')),
            ];

            if ($id) {
                DB::table('salary_adjustments')->where('id', $id)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
            } else {
                DB::table('salary_adjustments')->insert($data + ['created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }

            return response()->json(['status' => true, 'message' => $data['adj_type'] . ' of ' . number_format($amount, 2) . ' saved.']);
        } catch (Throwable $e) {
            Log::error('Salary adjustment save error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save.'], 500);
        }
    }

    public function deleteAdjustment(int $id)
    {
        $row = DB::table('salary_adjustments')->where('id', $id)->where('deleted', 'No')->first();
        if (!$row || !self::ownEmployee((int) $row->employee_id)) {
            return response()->json(['status' => false, 'message' => 'That entry no longer exists. Reload the list.'], 404);
        }
        if (in_array($row->employee_id, PayrollRunController::processedIds(Carbon::parse($row->month)))) {
            return response()->json(['status' => false, 'message' => 'That month is already processed for this employee.'], 422);
        }

        DB::table('salary_adjustments')->where('id', $id)->update($this->deletedColumns());

        return response()->json(['status' => true, 'message' => 'Removed.']);
    }

    /** The employee exists and belongs to the logged-in branch. */
    private static function ownEmployee(int $employeeId): bool
    {
        return BranchController::scope(DB::table('employees'), 'branch_id')->where('id', $employeeId)->where('deleted', 'No')->exists();
    }

    private function month(?string $value): Carbon
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $value) ? Carbon::createFromFormat('!Y-m', $value) : now()->startOfMonth();
    }
}
