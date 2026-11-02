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
 * Leave setup on one tabbed page: leave types, leave groups and the yearly rules
 * (days of each leave type per group per year). {type} = types | groups.
 */
class LeaveSetupController extends Controller
{
    use VoucherHelperTrait;

    private const TABLES = [
        'types'  => ['table' => 'leave_types', 'label' => 'Leave type'],
        'groups' => ['table' => 'leave_groups', 'label' => 'Leave group'],
    ];

    public function index(Request $request)
    {
        return view('admin.leave.setup', ['year' => (int) $request->query('year', now()->year)]);
    }

    public function list(string $type)
    {
        try {
            $rows = $type === 'types' ? $this->typeRows() : $this->groupRows();

            return response()->json(['data' => $rows]);
        } catch (Throwable $e) {
            Log::error('Leave setup list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load the list.'], 500);
        }
    }

    private function typeRows(): array
    {
        $used = DB::table('leave_applications')->where('deleted', 'No')->groupBy('leave_type_id')
            ->selectRaw('leave_type_id, COUNT(*) c')->pluck('c', 'leave_type_id');

        return DB::table('leave_types')->where('deleted', 'No')->orderByRaw("is_paid = 'No'")->orderBy('name')->get()
            ->values()->map(fn ($r, $i) => [
                $i + 1,
                '<span class="fw-semibold">' . e($r->name) . '</span>' . ($r->description ? '<div class="text-muted small">' . e($r->description) . '</div>' : ''),
                e($r->code ?: '-'),
                $r->is_paid === 'Yes'
                    ? '<span class="badge bg-success-subtle text-success-emphasis">Paid</span>'
                    : '<span class="badge bg-danger-subtle text-danger-emphasis">Unpaid — deducted from salary</span>',
                $this->countBadge($used[$r->id] ?? 0),
                $this->statusBadge('types', $r),
                $this->actions('types', $r->id),
            ])->all();
    }

    private function groupRows(): array
    {
        $employees = DB::table('employees')->where('deleted', 'No')->whereNotNull('leave_group_id')
            ->groupBy('leave_group_id')->selectRaw('leave_group_id, COUNT(*) c')->pluck('c', 'leave_group_id');
        $year = now()->year;
        $days = DB::table('leave_group_rules as r')->join('leave_types as t', 't.id', '=', 'r.leave_type_id')
            ->where('r.year', $year)->where('r.days', '>', 0)->where('t.deleted', 'No')
            ->orderBy('t.name')->get(['r.leave_group_id', 't.code', 't.name', 'r.days'])->groupBy('leave_group_id');

        return DB::table('leave_groups')->where('deleted', 'No')->orderBy('name')->get()
            ->values()->map(fn ($r, $i) => [
                $i + 1,
                '<span class="fw-semibold">' . e($r->name) . '</span>' . ($r->description ? '<div class="text-muted small">' . e($r->description) . '</div>' : ''),
                isset($days[$r->id])
                    ? $days[$r->id]->map(fn ($d) => '<span class="badge bg-light text-dark border me-1">' . e($d->code ?: $d->name) . ' ' . (float) $d->days . '</span>')->implode('')
                    : '<span class="text-muted small">No rules for ' . $year . '</span>',
                $this->countBadge($employees[$r->id] ?? 0),
                $this->statusBadge('groups', $r),
                $this->actions('groups', $r->id),
            ])->all();
    }

    public function edit(string $type, int $id)
    {
        $row = DB::table(self::TABLES[$type]['table'])->where('id', $id)->where('deleted', 'No')->first();

        return $row
            ? response()->json(['status' => true, 'data' => $row])
            : response()->json(['status' => false, 'message' => self::TABLES[$type]['label'] . ' not found.'], 404);
    }

    public function save(Request $request, string $type)
    {
        $table = self::TABLES[$type]['table'];
        $label = self::TABLES[$type]['label'];
        $id    = $request->filled('id') ? (int) $request->input('id') : null;

        $rules = [
            'id'          => ['nullable', 'integer'],
            'name'        => ['required', 'string', 'max:100', Rule::unique($table, 'name')->where('deleted', 'No')->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'status'      => ['required', Rule::in(['Active', 'Inactive'])],
        ];
        if ($type === 'types') {
            $rules['code']    = ['nullable', 'string', 'max:20'];
            $rules['is_paid'] = ['required', Rule::in(['Yes', 'No'])];
        }

        $validator = Validator::make($request->all(), $rules, ['name.unique' => 'This name already exists.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = [
            'name'        => trim($request->input('name')),
            'description' => $this->nullIfBlank($request->input('description')),
            'status'      => $request->input('status'),
        ];
        if ($type === 'types') {
            $data['code']    = $this->nullIfBlank($request->input('code'));
            $data['is_paid'] = $request->input('is_paid');
        }

        if ($id) {
            if (!DB::table($table)->where('id', $id)->where('deleted', 'No')->exists()) {
                return response()->json(['status' => false, 'message' => $label . ' not found.'], 404);
            }
            DB::table($table)->where('id', $id)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
        } else {
            DB::table($table)->insert($data + ['deleted' => 'No', 'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['status' => true, 'message' => $label . ($id ? ' updated.' : ' created.')]);
    }

    public function status(string $type, int $id)
    {
        $table = self::TABLES[$type]['table'];
        $row = DB::table($table)->where('id', $id)->where('deleted', 'No')->first();

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

        $usage = $type === 'types'
            ? DB::table('leave_applications')->where('leave_type_id', $id)->where('deleted', 'No')->count()
                + DB::table('leave_adjustments')->where('leave_type_id', $id)->where('deleted', 'No')->count()
            : DB::table('employees')->where('leave_group_id', $id)->where('deleted', 'No')->count();

        if ($usage > 0) {
            return response()->json([
                'status'  => false,
                'message' => $label . ' is used by ' . $usage . ($type === 'types' ? ' leave record(s)' : ' employee(s)') . '. Set it Inactive instead.',
            ], 422);
        }

        if (!DB::table($table)->where('id', $id)->where('deleted', 'No')->update($this->deletedColumns())) {
            return response()->json(['status' => false, 'message' => $label . ' not found.'], 404);
        }
        DB::table('leave_group_rules')->where($type === 'types' ? 'leave_type_id' : 'leave_group_id', $id)->delete();

        return response()->json(['status' => true, 'message' => $label . ' deleted.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Yearly rules: group x leave type matrix                           */
    /* ------------------------------------------------------------------ */

    public function rules(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        $values = DB::table('leave_group_rules')->where('year', $year)->get(['leave_group_id', 'leave_type_id', 'days'])
            ->mapWithKeys(fn ($r) => [$r->leave_group_id . '_' . $r->leave_type_id => (float) $r->days]);

        return response()->json([
            'status' => true,
            'year'   => $year,
            'types'  => LeaveController::leaveTypes(),
            'groups' => DB::table('leave_groups')->where('deleted', 'No')->where('status', 'Active')->orderBy('name')->get(['id', 'name']),
            'values' => $values,
        ]);
    }

    public function saveRules(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year'      => ['required', 'integer', 'min:2000', 'max:2100'],
            'days'      => ['nullable', 'array'],
            'days.*'    => ['array'],
            'days.*.*'  => ['nullable', 'numeric', 'min:0', 'max:366'],
        ], ['days.*.*.numeric' => 'Days must be a number.', 'days.*.*.max' => 'Days cannot be more than 366.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $year     = (int) $request->input('year');
        $groupIds = DB::table('leave_groups')->where('deleted', 'No')->pluck('id')->all();
        $typeIds  = DB::table('leave_types')->where('deleted', 'No')->pluck('id')->all();

        DB::transaction(function () use ($request, $year, $groupIds, $typeIds) {
            foreach ((array) $request->input('days', []) as $groupId => $types) {
                if (!in_array((int) $groupId, $groupIds)) {
                    continue;
                }
                foreach ((array) $types as $typeId => $days) {
                    if (!in_array((int) $typeId, $typeIds)) {
                        continue;
                    }
                    $key = ['leave_group_id' => (int) $groupId, 'leave_type_id' => (int) $typeId, 'year' => $year];

                    if ($days === null || $days === '') {
                        DB::table('leave_group_rules')->where($key)->delete();
                    } else {
                        DB::table('leave_group_rules')->updateOrInsert($key, [
                            'days' => round((float) $days * 2) / 2, 'updated_by' => Auth::id(), 'updated_at' => now(), 'created_at' => now(),
                        ]);
                    }
                }
            }
        });

        return response()->json(['status' => true, 'message' => 'Leave rules of ' . $year . ' saved. They apply to every employee of each group.']);
    }

    public function copyRules(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_year' => ['required', 'integer', 'min:2000', 'max:2100', 'different:year'],
            'year'      => ['required', 'integer', 'min:2000', 'max:2100'],
        ], ['from_year.different' => 'Choose another year to copy from.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $rows = DB::table('leave_group_rules')->where('year', (int) $request->input('from_year'))->get();

        foreach ($rows as $r) {
            DB::table('leave_group_rules')->updateOrInsert(
                ['leave_group_id' => $r->leave_group_id, 'leave_type_id' => $r->leave_type_id, 'year' => (int) $request->input('year')],
                ['days' => $r->days, 'updated_by' => Auth::id(), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return response()->json(['status' => true, 'message' => $rows->count() . ' rule(s) copied from ' . $request->input('from_year') . '.']);
    }

    /* ------------------------------------------------------------------ */

    private function countBadge(int $n): string
    {
        return '<span class="badge rounded-pill ' . ($n > 0 ? 'bg-info-subtle text-info-emphasis' : 'bg-light text-muted') . '">' . $n . '</span>';
    }

    private function statusBadge(string $type, object $row): string
    {
        return '<button type="button" class="btn btn-sm badge border-0 ' . ($row->status === 'Active' ? 'bg-success' : 'bg-secondary') . '"'
            . ' title="Click to change" onclick="LeaveSetup.toggleStatus(\'' . $type . '\', ' . (int) $row->id . ')">' . e($row->status) . '</button>';
    }

    private function actions(string $type, int $id): string
    {
        return action_dropdown([
            ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => "LeaveSetup.edit('$type', $id)"],
            ['divider' => true],
            ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => "LeaveSetup.remove('$type', $id)"],
        ]);
    }
}
