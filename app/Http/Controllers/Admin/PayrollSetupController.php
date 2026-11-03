<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\SafeJsonErrors;
use App\Traits\VoucherHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Payroll setup on one tabbed page: payroll settings, departments, designations, pay grades
 * and pay steps. {type} in the routes selects the table; see TABLES.
 */
class PayrollSetupController extends Controller
{
    use VoucherHelperTrait, SafeJsonErrors;

    private const TABLES = [
        'departments'  => ['table' => 'departments', 'label' => 'Department'],
        'designations' => ['table' => 'designations', 'label' => 'Designation'],
        'grades'       => ['table' => 'pay_grades', 'label' => 'Pay grade'],
        'steps'        => ['table' => 'pay_steps', 'label' => 'Step'],
    ];

    public function index()
    {
        return view('admin.payroll.setup.index', [
            'settings'    => self::salarySettings(),
            'departments' => $this->activeRows('departments'),
            'grades'      => $this->activeRows('pay_grades', ['increment_percent']),
            // Active employees that cannot be paid on the Scale structure yet.
            'withoutStep' => DB::table('employees')->where('deleted', 'No')->where('status', 'Active')->whereNull('step_id')->count(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Payroll settings (one row, applies to the whole application)      */
    /* ------------------------------------------------------------------ */

    public function saveSettings(Request $request)
    {
        $percent = ['nullable', 'numeric', 'min:0', 'max:1000'];

        $validator = Validator::make($request->all(), [
            'salary_structure'     => ['required', Rule::in(self::SALARY_STRUCTURES)],
            'house_rent_percent'   => ['required_if:salary_structure,Scale', ...$percent],
            'compensation_percent' => ['required_if:salary_structure,Scale', ...$percent],
            'medical_percent'      => ['required_if:salary_structure,Scale', ...$percent],
            'pf_enabled'           => ['required', Rule::in(['Yes', 'No'])],
            'pf_percent'           => ['required_if:pf_enabled,Yes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'bonus_percent'        => ['required', 'numeric', 'min:0', 'max:1000'],
        ], [
            '*.required_if' => 'Enter the percentage.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $changed = DB::transaction(function () use ($request) {
                $current = self::salarySettings(true);
                $data = [
                    'salary_structure' => $request->input('salary_structure'),
                    'pf_enabled'       => $request->input('pf_enabled'),
                    'bonus_percent'    => round((float) $request->input('bonus_percent'), 2),
                    'updated_by'       => Auth::id(),
                    'updated_date'     => now(),
                    'updated_at'       => now(),
                ];

                // Fields hidden on the form keep their saved value.
                foreach (['house_rent_percent', 'compensation_percent', 'medical_percent', 'pf_percent'] as $col) {
                    $data[$col] = $request->filled($col) ? round((float) $request->input($col), 2) : $current->$col;
                }

                if ($current->id) {
                    DB::table('payroll_settings')->where('id', $current->id)->update($data);
                } else {
                    DB::table('payroll_settings')->insert($data + ['created_at' => now()]);
                }

                return self::recalculateSalaries(null, 'Payroll settings changed');
            });

            $message = 'Payroll settings saved.';
            if ($changed > 0) {
                $message .= ' Salary of ' . $changed . ' employee(s) recalculated.';
            }

            return response()->json(['status' => true, 'message' => $message]);
        } catch (Throwable $e) {
            Log::error('Payroll settings save error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the settings. Please try again.'], 500);
        }
    }

    /**
     * Annual increment (Scale): an active employee moves up to the next active step of the
     * same grade (and department scope) once a full year has passed since the last
     * increment, or since joining for the first one.
     */
    public function increment()
    {
        if (!self::isScale()) {
            return response()->json(['status' => false, 'message' => 'Annual increment applies to the Scale structure only.'], 422);
        }

        try {
            $result = DB::transaction(function () {
                $dueBefore = now()->subYear()->toDateString();
                $result = ['moved' => [], 'top' => 0, 'no_date' => 0];

                $employees = DB::table('employees as e')
                    ->join('pay_steps as ps', 'ps.id', '=', 'e.step_id')
                    ->where('e.deleted', 'No')->where('e.status', 'Active')
                    ->lockForUpdate()
                    ->get(['e.id', 'e.joining_date', 'e.last_increment_date', 'ps.pay_grade_id', 'ps.department_id', 'ps.step_no']);

                foreach ($employees as $emp) {
                    $since = $emp->last_increment_date ?: $emp->joining_date;

                    if (!$since) {
                        $result['no_date']++;
                        continue;
                    }
                    if ($since > $dueBefore) {
                        continue;
                    }

                    $next = DB::table('pay_steps')
                        ->where('pay_grade_id', $emp->pay_grade_id)
                        ->where(fn ($q) => $emp->department_id ? $q->where('department_id', $emp->department_id) : $q->whereNull('department_id'))
                        ->where('step_no', '>', $emp->step_no)
                        ->where('deleted', 'No')->where('status', 'Active')
                        ->orderBy('step_no')
                        ->value('id');

                    if (!$next) {
                        $result['top']++;
                        continue;
                    }

                    DB::table('employees')->where('id', $emp->id)->update([
                        'step_id' => $next, 'last_increment_date' => now()->toDateString(),
                        'updated_by' => Auth::id(), 'updated_date' => now(),
                    ]);
                    $result['moved'][] = $emp->id;
                }

                self::recalculateSalaries($result['moved'], 'Annual increment');

                return $result;
            });

            $message = count($result['moved']) . ' employee(s) moved to the next step.';
            if ($result['top'] > 0) {
                $message .= ' ' . $result['top'] . ' already on the last step.';
            }
            if ($result['no_date'] > 0) {
                $message .= ' ' . $result['no_date'] . ' skipped: no joining date.';
            }

            return response()->json(['status' => true, 'message' => $message]);
        } catch (Throwable $e) {
            Log::error('Payroll annual increment error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to run the increment. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable rows                                                    */
    /* ------------------------------------------------------------------ */

    public function list(Request $request, string $type)
    {
        try {
            $rows = match ($type) {
                'departments'  => $this->departmentRows(),
                'designations' => $this->designationRows(),
                'grades'       => $this->gradeRows(),
                'steps'        => $this->stepRows($request),
            };

            return response()->json(['data' => $rows]);
        } catch (Throwable $e) {
            Log::error('Payroll setup list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load the list.'], 500);
        }
    }

    private function departmentRows(): array
    {
        $designations = $this->countBy('designations', 'department_id');
        $employees    = $this->countBy('employees', 'department_id');

        return DB::table('departments')->where('deleted', 'No')->orderBy('name')->get()
            ->values()->map(fn ($r, $i) => [
                $i + 1,
                '<span class="fw-semibold">' . e($r->name) . '</span>' . $this->sub($r->description),
                e($r->code ?: '-'),
                $this->countBadge($designations[$r->id] ?? 0),
                $this->countBadge($employees[$r->id] ?? 0),
                $this->statusBadge('departments', $r),
                $this->actions('departments', $r->id),
            ])->all();
    }

    private function designationRows(): array
    {
        $employees = $this->countBy('employees', 'designation_id');

        return DB::table('designations as d')
            ->leftJoin('departments as dp', 'dp.id', '=', 'd.department_id')
            ->where('d.deleted', 'No')
            ->orderBy('d.name')
            ->get(['d.*', 'dp.name as department_name'])
            ->values()->map(fn ($r, $i) => [
                $i + 1,
                '<span class="fw-semibold">' . e($r->name) . '</span>' . $this->sub($r->description),
                e($r->department_name ?: 'All departments'),
                $this->countBadge($employees[$r->id] ?? 0),
                $this->statusBadge('designations', $r),
                $this->actions('designations', $r->id),
            ])->all();
    }

    private function gradeRows(): array
    {
        $steps = DB::table('pay_steps')->where('deleted', 'No')
            ->groupBy('pay_grade_id')
            ->selectRaw('pay_grade_id, COUNT(*) as steps, MIN(basic_salary) as min_basic, MAX(basic_salary) as max_basic')
            ->get()->keyBy('pay_grade_id');

        return DB::table('pay_grades')->where('deleted', 'No')->orderBy('name')->get()
            ->values()->map(function ($r, $i) use ($steps) {
                $s = $steps[$r->id] ?? null;

                return [
                    $i + 1,
                    '<span class="fw-semibold">' . e($r->name) . '</span>' . $this->sub($r->description),
                    '<span class="d-block text-end">' . number_format((float) $r->increment_percent, 2) . '%</span>',
                    $this->countBadge($s->steps ?? 0),
                    $s ? '<span class="d-block text-end">' . number_format((float) $s->min_basic, 2) . ' – ' . number_format((float) $s->max_basic, 2) . '</span>' : '<span class="text-muted">-</span>',
                    $this->statusBadge('grades', $r),
                    $this->actions('grades', $r->id),
                ];
            })->all();
    }

    private function stepRows(Request $request): array
    {
        $employees = $this->countBy('employees', 'step_id');
        $money = fn ($v) => '<span class="d-block text-end">' . number_format((float) $v, 2) . '</span>';

        return DB::table('pay_steps as s')
            ->join('pay_grades as g', 'g.id', '=', 's.pay_grade_id')
            ->leftJoin('departments as d', 'd.id', '=', 's.department_id')
            ->where('s.deleted', 'No')
            ->when($request->filled('grade_id'), fn ($q) => $q->where('s.pay_grade_id', (int) $request->query('grade_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('s.department_id', (int) $request->query('department_id')))
            ->orderBy('g.name')->orderBy('d.name')->orderBy('s.step_no')
            ->get(['s.*', 'g.name as grade_name', 'd.name as department_name'])
            ->values()->map(function ($r, $i) use ($employees, $money) {
                // Allowances follow the current payroll settings.
                $pay = self::salaryBreakdown((float) $r->basic_salary, false);

                return [
                    $i + 1,
                    e($r->grade_name),
                    $r->department_name ? e($r->department_name) : '<span class="text-muted">All departments</span>',
                    '<span class="badge bg-primary-subtle text-primary-emphasis">Step ' . (int) $r->step_no . '</span>',
                    $money($pay['basic_salary']),
                    $money($pay['house_rent']),
                    $money($pay['compensation']),
                    $money($pay['medical_allowance']),
                    '<span class="d-block text-end fw-bold">' . number_format($pay['gross_salary'], 2) . '</span>',
                    $this->countBadge($employees[$r->id] ?? 0),
                    $this->statusBadge('steps', $r),
                    $this->actions('steps', $r->id),
                ];
            })->all();
    }

    /** Active rows for dropdowns (refreshed after a save on another tab). */
    public function options(string $type)
    {
        $table = self::TABLES[$type]['table'];

        return response()->json(['status' => true, 'data' => $this->activeRows($table, $type === 'grades' ? ['increment_percent'] : [])]);
    }

    public function edit(string $type, int $id)
    {
        $row = DB::table(self::TABLES[$type]['table'])->where('id', $id)->where('deleted', 'No')->first();

        if (!$row) {
            return response()->json(['status' => false, 'message' => self::TABLES[$type]['label'] . ' not found.'], 404);
        }

        return response()->json(['status' => true, 'data' => $row]);
    }

    /* ------------------------------------------------------------------ */
    /*  Create / update                                                   */
    /* ------------------------------------------------------------------ */

    public function save(Request $request, string $type)
    {
        $table = self::TABLES[$type]['table'];
        $label = self::TABLES[$type]['label'];
        $id    = $request->filled('id') ? (int) $request->input('id') : null;

        $unique = fn (string $col) => Rule::unique($table, $col)->where('deleted', 'No')->ignore($id);
        $rules = [
            'id'          => ['nullable', 'integer'],
            'status'      => ['required', Rule::in(['Active', 'Inactive'])],
            'description' => ['nullable', 'string', 'max:500'],
        ];

        $rules += match ($type) {
            'departments' => [
                'name' => ['required', 'string', 'max:100', $unique('name')],
                'code' => ['nullable', 'string', 'max:20'],
            ],
            'designations' => [
                'name'          => ['required', 'string', 'max:100',
                    $unique('name')->where(fn ($q) => $request->filled('department_id')
                        ? $q->where('department_id', (int) $request->input('department_id'))
                        : $q->whereNull('department_id'))],
                'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('deleted', 'No')],
            ],
            'grades' => [
                'name'              => ['required', 'string', 'max:100', $unique('name')],
                'increment_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ],
            'steps' => [
                'pay_grade_id'  => ['required', 'integer', Rule::exists('pay_grades', 'id')->where('deleted', 'No')],
                'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('deleted', 'No')],
                // Step numbers are unique per grade and department (or per grade for "all departments").
                'step_no'       => ['required', 'integer', 'min:1', 'max:999',
                    $unique('step_no')->where('pay_grade_id', (int) $request->input('pay_grade_id'))
                        ->where(fn ($q) => $request->filled('department_id')
                            ? $q->where('department_id', (int) $request->input('department_id'))
                            : $q->whereNull('department_id'))],
                'basic_salary'  => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            ],
        };

        $validator = Validator::make($request->all(), $rules, [
            'name.unique'         => 'This name already exists.',
            'step_no.unique'      => 'This step already exists in the selected grade and department.',
            'pay_grade_id.required' => 'Select the pay grade.',
            'basic_salary.min'    => 'Basic salary must be greater than zero.',
        ], [
            'name'              => $label . ' name',
            'code'              => $label . ' code',
            'description'       => 'Description',
            'department_id'     => 'Department',
            'pay_grade_id'      => 'Pay grade',
            'step_no'           => 'Step number',
            'basic_salary'      => 'Basic salary',
            'increment_percent' => 'Yearly increment %',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $synced = DB::transaction(function () use ($request, $type, $table, $id, $label) {
                $data = [
                    'status'      => $request->input('status'),
                    'description' => $this->nullIfBlank($request->input('description')),
                ];

                if ($type === 'steps') {
                    unset($data['description']);
                    $data['pay_grade_id']  = (int) $request->input('pay_grade_id');
                    $data['department_id'] = $request->input('department_id') ?: null;
                    $data['step_no']       = (int) $request->input('step_no');
                    $data['basic_salary']  = round((float) $request->input('basic_salary'), 2);
                } else {
                    $data['name'] = trim($request->input('name'));
                }

                if ($type === 'departments') {
                    $data['code'] = $this->nullIfBlank($request->input('code'));
                }

                if ($type === 'designations') {
                    $data['department_id'] = $request->input('department_id') ?: null;
                }

                if ($type === 'grades') {
                    $data['increment_percent'] = round((float) $request->input('increment_percent', 0), 2);
                }

                if ($id) {
                    $exists = DB::table($table)->where('id', $id)->where('deleted', 'No')->lockForUpdate()->exists();
                    abort_unless($exists, 404, $label . ' not found.');

                    DB::table($table)->where('id', $id)->update($data + [
                        'updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    $id = DB::table($table)->insertGetId($data + [
                        'deleted' => 'No', 'created_by' => Auth::id(), 'created_date' => now(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                return $this->syncDenormalized($type, $id);
            });

            $message = $label . ($request->filled('id') ? ' updated' : ' created') . ' successfully.';
            if ($synced > 0) {
                $message .= ' ' . $synced . ' employee(s) updated.';
            }

            return response()->json(['status' => true, 'message' => $message]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Payroll setup save error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save. Please try again.'], 500);
        }
    }

    /**
     * Employees keep a copy of the department / designation name (used on ID cards and PDFs)
     * and their calculated salary. Keep those copies current.
     * Returns the number of employees touched.
     */
    private function syncDenormalized(string $type, int $id): int
    {
        $employees = DB::table('employees')->where('deleted', 'No');

        return match ($type) {
            'departments' => (clone $employees)->where('department_id', $id)
                ->update(['department' => DB::table('departments')->where('id', $id)->value('name')]),
            'designations' => (clone $employees)->where('designation_id', $id)
                ->update(['designation' => DB::table('designations')->where('id', $id)->value('name')]),
            'steps' => self::isScale()
                ? self::recalculateSalaries((clone $employees)->where('step_id', $id)->pluck('id')->all(), 'Pay step changed')
                : 0,
            default => 0,
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Status / delete                                                   */
    /* ------------------------------------------------------------------ */

    public function status(string $type, int $id)
    {
        $table = self::TABLES[$type]['table'];
        $row   = DB::table($table)->where('id', $id)->where('deleted', 'No')->first();

        if (!$row) {
            return response()->json(['status' => false, 'message' => self::TABLES[$type]['label'] . ' not found.'], 404);
        }

        $status = $row->status === 'Active' ? 'Inactive' : 'Active';
        DB::table($table)->where('id', $id)->update(['status' => $status, 'updated_by' => Auth::id(), 'updated_date' => now()]);

        return response()->json(['status' => true, 'message' => self::TABLES[$type]['label'] . ' is now ' . $status . '.']);
    }

    public function delete(string $type, int $id)
    {
        $table = self::TABLES[$type]['table'];
        $label = self::TABLES[$type]['label'];

        if (!DB::table($table)->where('id', $id)->where('deleted', 'No')->exists()) {
            return response()->json(['status' => false, 'message' => $label . ' not found.'], 404);
        }

        // Things that still point at this row.
        $usage = match ($type) {
            'departments'  => ['employees' => 'department_id', 'designations' => 'department_id', 'pay_steps' => 'department_id'],
            'designations' => ['employees' => 'designation_id'],
            'grades'       => ['pay_steps' => 'pay_grade_id'],
            'steps'        => ['employees' => 'step_id'],
        };

        foreach ($usage as $usedIn => $column) {
            $count = DB::table($usedIn)->where($column, $id)->where('deleted', 'No')->count();
            if ($count > 0) {
                $what = ['employees' => 'employee(s)', 'designations' => 'designation(s)', 'pay_steps' => 'step(s)'][$usedIn];

                return response()->json([
                    'status'  => false,
                    'message' => $label . ' is used by ' . $count . ' ' . $what . '. Change or remove those first, or set it Inactive.',
                ], 422);
            }
        }

        DB::table($table)->where('id', $id)->update($this->deletedColumns());

        return response()->json(['status' => true, 'message' => $label . ' deleted.']);
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    private function activeRows(string $table, array $extra = [])
    {
        return DB::table($table)->where('deleted', 'No')->where('status', 'Active')->orderBy('name')->get(['id', 'name', ...$extra]);
    }

    private function countBy(string $table, string $column)
    {
        return DB::table($table)->where('deleted', 'No')->whereNotNull($column)
            ->groupBy($column)->selectRaw("$column as k, COUNT(*) as c")->pluck('c', 'k');
    }

    private function countBadge(int $n): string
    {
        return '<span class="badge rounded-pill ' . ($n > 0 ? 'bg-info-subtle text-info-emphasis' : 'bg-light text-muted') . '">' . $n . '</span>';
    }

    private function sub(?string $text): string
    {
        return $text ? '<div class="text-muted small">' . e($text) . '</div>' : '';
    }

    private function statusBadge(string $type, object $row): string
    {
        return '<button type="button" class="btn btn-sm badge border-0 ' . ($row->status === 'Active' ? 'bg-success' : 'bg-secondary') . '"'
            . ' title="Click to change" onclick="Setup.toggleStatus(\'' . $type . '\', ' . (int) $row->id . ')">' . e($row->status) . '</button>';
    }

    private function actions(string $type, int $id): string
    {
        return action_dropdown([
            ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => "Setup.edit('$type', $id)"],
            ['divider' => true],
            ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => "Setup.remove('$type', $id)"],
        ]);
    }

    /* ================================================================== */
    /*  Salary rules (was app/Services/PayrollSalary.php)                 */
    /* ================================================================== */

    /**
 * Salary rules for both structures. The structure is chosen once in payroll_settings
 * and applies to every employee:
 *
 *  Scale        basic = the employee's pay step; house rent, compensation and medical are
 *               % of basic. PF = % of basic.
 *  Contractual  basic = the employee's contract salary, nothing else. PF = % of it.
 *
 *  Both         bonus = % of gross (eligible employees only); net = gross - PF.
 *
 * The results are stored on the employee, so recalculate() runs after anything that
 * changes them (settings, a step's basic, the employee itself).
     */
    public const SALARY_STRUCTURES = ['Scale', 'Contractual'];

    // Stored on employees; transport / other are no longer used and kept at 0.
    public const SALARY_COLUMNS = [
        'basic_salary', 'house_rent', 'compensation', 'medical_allowance', 'transport_allowance', 'other_allowance',
        'gross_salary', 'pf_amount', 'bonus_amount', 'net_salary',
    ];

    private static ?object $salarySettingsCache = null;

    public static function salarySettings(bool $fresh = false): object
    {
        if ($fresh || self::$salarySettingsCache === null) {
            self::$salarySettingsCache = DB::table('payroll_settings')->orderBy('id')->first() ?? (object) [
                'id' => null, 'salary_structure' => 'Contractual',
                'house_rent_percent' => 50, 'compensation_percent' => 30, 'medical_percent' => 20,
                'pf_enabled' => 'No', 'pf_percent' => 0, 'bonus_percent' => 0,
            ];
        }

        return self::$salarySettingsCache;
    }

    /* ------------------------------------------------------------------ */
    /*  Ledgers used by payroll postings (process / pay salary, loans)    */
    /* ------------------------------------------------------------------ */

    /**
     * Active 3rd-layer ledgers of one account type usable in the logged-in branch: shared ledgers plus
     * the branch's own (a branch-owned cash / bank ledger of another branch is never offered).
     * Rows: id, account_name, branch_id, group_name.
     */
    public static function payrollLedgers(string $type)
    {
        $branch = BranchController::activeId();

        return DB::table('chart_of_accounts as a')
            ->leftJoin('chart_of_accounts as g', 'g.id', '=', 'a.parent_id')
            ->where('a.layer_level', 3)->where('a.account_type', $type)
            ->where('a.deleted', 'No')->where('a.status', 'Active')
            ->where(fn ($q) => $q->whereNull('a.branch_id')->when($branch, fn ($b) => $b->orWhere('a.branch_id', $branch)))
            ->orderBy('a.account_code')
            ->get(['a.id', 'a.account_name', 'a.branch_id', 'g.account_name as group_name']);
    }

    /**
     * Asset ledgers suitable for employee loans / advance salary: names or groups that speak of
     * employees, staff, loans or salary advances (never fixed assets, depreciation, inventory, tax ...).
     * The Payroll Settings ledger is always included. Falls back to all non-fixed asset ledgers when none match.
     */
    public static function loanLedgers()
    {
        $all = self::payrollLedgers('Asset');
        $saved = (int) (self::salarySettings()->loan_account_id ?? 0);

        $fit = $all->filter(fn ($a) => (int) $a->id === $saved
            || (preg_match('/employee|staff|personnel|worker|loan|advance/i', $a->account_name)
                && !preg_match('/rent|tax|\bait\b|prepaid|deposit|supplier|vendor|fixed|depreciation/i', $a->account_name)));

        if ($fit->isEmpty()) {
            $fit = $all->reject(fn ($a) => preg_match('/fixed|depreciation|inventory|stock|vat|tax|receivable|deposit|prepaid/i',
                $a->account_name . ' ' . $a->group_name));
        }

        return $fit->values();
    }

    /**
     * Default ledgers for the payroll dialogs: the Payroll Settings ledger when it is still offered,
     * else the first ledger whose name matches. Keys: expense, payable, pf, loan, payment (ids or null).
     * PF payable never defaults to the salary payable ledger.
     */
    public static function payrollLedgerDefaults(): array
    {
        $s = self::salarySettings();
        $pick = function ($rows, $saved, array $patterns, array $not = []) {
            $rows = $rows->reject(fn ($a) => in_array((int) $a->id, $not, true));
            if ($saved && $rows->contains(fn ($a) => (int) $a->id === (int) $saved)) {
                return (int) $saved;
            }
            foreach ($patterns as $p) {
                $hit = $rows->first(fn ($a) => preg_match($p, $a->account_name));
                if ($hit) {
                    return (int) $hit->id;
                }
            }

            return null;
        };

        $expenses    = self::payrollLedgers('Expense');
        $liabilities = self::payrollLedgers('Liability');

        $d = [];
        $d['expense'] = $pick($expenses, $s->salary_expense_account_id ?? null,
            ['/^salar(y|ies)\b.*wage/i', '/^salar(y|ies)/i', '/salar(y|ies)|wage/i']);
        $d['payable'] = $pick($liabilities, $s->salary_payable_account_id ?? null,
            ['/salar(y|ies).*payable|payable.*salar/i', '/wages?.*payable/i']);
        $d['pf']      = $pick($liabilities, $s->pf_account_id ?? null,
            ['/provident/i', '/\bpf\b/i'], array_filter([$d['payable']]));
        $d['loan']    = $pick(self::loanLedgers(), $s->loan_account_id ?? null,
            ['/(employee|staff).*(loan|advance)/i', '/loan|advance/i']);

        // Paid from: the last used ledger when this branch may use it, else the branch's own cash / bank, else a shared cash ledger.
        $liquid = self::payrollLedgers('Liquid Asset');
        $branch = BranchController::activeId();
        $saved  = (int) ($s->salary_payment_account_id ?? 0);
        $d['payment'] = $liquid->contains(fn ($a) => (int) $a->id === $saved) ? $saved
            : ($liquid->first(fn ($a) => $branch && (int) $a->branch_id === (int) $branch)->id
                ?? $liquid->first(fn ($a) => preg_match('/cash/i', $a->group_name . ' ' . $a->account_name))->id
                ?? $liquid->first()->id ?? null);
        $d['payment'] = $d['payment'] ? (int) $d['payment'] : null;

        return $d;
    }

    public static function isScale(): bool
    {
        return self::salarySettings()->salary_structure === 'Scale';
    }

    /** Monthly breakdown for a basic (Scale step) or contract salary under the current settings. */
    public static function salaryBreakdown(float $base, bool $bonusEligible, ?object $s = null): array
    {
        $s = $s ?? self::salarySettings();
        $pct = fn ($amount, $percent) => round($amount * (float) $percent / 100, 2);

        $row = array_fill_keys(self::SALARY_COLUMNS, 0.0);
        $row['basic_salary'] = round($base, 2);

        if ($s->salary_structure === 'Scale') {
            $row['house_rent']        = $pct($base, $s->house_rent_percent);
            $row['compensation']      = $pct($base, $s->compensation_percent);
            $row['medical_allowance'] = $pct($base, $s->medical_percent);
        }

        $row['gross_salary'] = round($row['basic_salary'] + $row['house_rent'] + $row['compensation'] + $row['medical_allowance'], 2);
        $row['pf_amount']    = $s->pf_enabled === 'Yes' ? $pct($row['basic_salary'], $s->pf_percent) : 0.0;
        $row['bonus_amount'] = $bonusEligible ? $pct($row['gross_salary'], $s->bonus_percent) : 0.0;
        $row['net_salary']   = round($row['gross_salary'] - $row['pf_amount'], 2);

        return $row;
    }

    /** The base amount an employee row is paid on under the current structure. */
    public static function salaryBase(object $employee, ?float $stepBasic): float
    {
        return self::isScale() ? (float) $stepBasic : (float) $employee->contract_salary;
    }

    /**
     * Re-store the salary of employees (all when $employeeIds is null) under the current
     * settings. Each change is written to the salary history (the PF ledger's source) with
     * $reason, effective $effectiveDate (today when null). Returns the number of employees changed.
     */
    public static function recalculateSalaries(?array $employeeIds = null, string $reason = 'Salary updated', ?string $effectiveDate = null): int
    {
        $s = self::salarySettings(true);

        $rows = DB::table('employees as e')
            ->leftJoin('pay_steps as ps', 'ps.id', '=', 'e.step_id')
            ->leftJoin('pay_grades as pg', 'pg.id', '=', 'ps.pay_grade_id')
            ->where('e.deleted', 'No')
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('e.id', $employeeIds ?: [0]))
            ->get(['e.id', 'e.step_id', 'e.contract_salary', 'e.bonus_eligible', 'ps.basic_salary as step_basic',
                'ps.step_no', 'pg.name as grade_name', ...array_map(fn ($c) => "e.$c", self::SALARY_COLUMNS)]);

        $changed = 0;

        foreach ($rows as $emp) {
            $new = self::salaryBreakdown(self::salaryBase($emp, $emp->step_basic), $emp->bonus_eligible === 'Yes', $s);

            $differs = false;
            foreach (self::SALARY_COLUMNS as $col) {
                if (abs((float) $emp->$col - $new[$col]) >= 0.005) {
                    $differs = true;
                    break;
                }
            }

            if ($differs) {
                DB::table('employees')->where('id', $emp->id)->update($new);
                self::addSalaryHistory($emp, $new, $s, $reason, $effectiveDate);
                $changed++;
            }
        }

        return $changed;
    }

    private static function addSalaryHistory(object $emp, array $pay, object $s, string $reason, ?string $effectiveDate): void
    {
        $scale = $s->salary_structure === 'Scale';

        DB::table('employee_salary_histories')->insert([
            'employee_id'      => $emp->id,
            'effective_date'   => $effectiveDate ?: now()->toDateString(),
            'reason'           => $reason,
            'salary_structure' => $s->salary_structure,
            'step_id'          => $scale ? $emp->step_id : null,
            'grade_step'       => $scale && $emp->grade_name ? $emp->grade_name . ' / Step ' . (int) $emp->step_no : null,
            'basic_salary'     => $pay['basic_salary'],
            'gross_salary'     => $pay['gross_salary'],
            'pf_amount'        => $pay['pf_amount'],
            'net_salary'       => $pay['net_salary'],
            'created_by'       => auth()->id(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }
}
