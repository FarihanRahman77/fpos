<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Payroll setup on one tabbed page: departments, designations, pay grades and pay steps.
 * {type} in the routes selects the table; see TABLES.
 */
class PayrollSetupController extends Controller
{
    use VoucherHelperTrait;

    private const TABLES = [
        'departments'  => ['table' => 'departments', 'label' => 'Department'],
        'designations' => ['table' => 'designations', 'label' => 'Designation'],
        'grades'       => ['table' => 'pay_grades', 'label' => 'Pay grade'],
        'steps'        => ['table' => 'pay_steps', 'label' => 'Step'],
    ];

    public const SALARY_PARTS = ['basic_salary', 'house_rent', 'medical_allowance', 'transport_allowance', 'other_allowance'];

    public function index()
    {
        return view('admin.payroll.setup.index', [
            'departments' => $this->activeRows('departments'),
            'grades'      => $this->activeRows('pay_grades'),
        ]);
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
            ->selectRaw('pay_grade_id, COUNT(*) as steps, MIN(gross_salary) as min_gross, MAX(gross_salary) as max_gross')
            ->get()->keyBy('pay_grade_id');

        return DB::table('pay_grades')->where('deleted', 'No')->orderBy('name')->get()
            ->values()->map(function ($r, $i) use ($steps) {
                $s = $steps[$r->id] ?? null;

                return [
                    $i + 1,
                    '<span class="fw-semibold">' . e($r->name) . '</span>' . $this->sub($r->description),
                    $this->countBadge($s->steps ?? 0),
                    $s ? '<span class="d-block text-end">' . number_format((float) $s->min_gross, 2) . ' – ' . number_format((float) $s->max_gross, 2) . '</span>' : '<span class="text-muted">-</span>',
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
            ->where('s.deleted', 'No')
            ->when($request->filled('grade_id'), fn ($q) => $q->where('s.pay_grade_id', (int) $request->query('grade_id')))
            ->orderBy('g.name')->orderBy('s.step_no')
            ->get(['s.*', 'g.name as grade_name'])
            ->values()->map(fn ($r, $i) => [
                $i + 1,
                e($r->grade_name),
                '<span class="badge bg-primary-subtle text-primary-emphasis">Step ' . (int) $r->step_no . '</span>',
                $money($r->basic_salary),
                $money($r->house_rent),
                $money($r->medical_allowance),
                $money($r->transport_allowance),
                $money($r->other_allowance),
                '<span class="d-block text-end fw-bold">' . number_format((float) $r->gross_salary, 2) . '</span>',
                $this->countBadge($employees[$r->id] ?? 0),
                $this->statusBadge('steps', $r),
                $this->actions('steps', $r->id),
            ])->all();
    }

    /** Active rows for dropdowns (refreshed after a save on another tab). */
    public function options(string $type)
    {
        $table = self::TABLES[$type]['table'];

        return response()->json(['status' => true, 'data' => $this->activeRows($table)]);
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
        $money  = ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'];

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
                'name' => ['required', 'string', 'max:100', $unique('name')],
            ],
            'steps' => [
                'pay_grade_id' => ['required', 'integer', Rule::exists('pay_grades', 'id')->where('deleted', 'No')],
                'step_no'      => ['required', 'integer', 'min:1', 'max:999',
                    $unique('step_no')->where('pay_grade_id', (int) $request->input('pay_grade_id'))],
                'basic_salary' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
                'house_rent'          => $money,
                'medical_allowance'   => $money,
                'transport_allowance' => $money,
                'other_allowance'     => $money,
            ],
        };

        $validator = Validator::make($request->all(), $rules, [
            'name.unique'         => 'This name already exists.',
            'step_no.unique'      => 'This step already exists in the selected grade.',
            'pay_grade_id.required' => 'Select the pay grade.',
            'basic_salary.min'    => 'Basic salary must be greater than zero.',
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
                    $data['pay_grade_id'] = (int) $request->input('pay_grade_id');
                    $data['step_no']      = (int) $request->input('step_no');
                    $data['gross_salary'] = 0;
                    foreach (self::SALARY_PARTS as $col) {
                        $data[$col] = round((float) $request->input($col, 0), 2);
                        $data['gross_salary'] += $data[$col];
                    }
                    $data['gross_salary'] = round($data['gross_salary'], 2);
                } else {
                    $data['name'] = trim($request->input('name'));
                }

                if ($type === 'departments') {
                    $data['code'] = $this->nullIfBlank($request->input('code'));
                }

                if ($type === 'designations') {
                    $data['department_id'] = $request->input('department_id') ?: null;
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
     * and, on the Scale structure, a copy of their step's salary. Keep those copies current.
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
            'steps' => (clone $employees)->where('salary_structure', 'Scale')->where('step_id', $id)
                ->update((array) DB::table('pay_steps')->where('id', $id)
                    ->first([...self::SALARY_PARTS, 'gross_salary'])),
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
            'departments'  => ['employees' => 'department_id', 'designations' => 'department_id'],
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

    private function activeRows(string $table)
    {
        return DB::table($table)->where('deleted', 'No')->where('status', 'Active')->orderBy('name')->get(['id', 'name']);
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
}
