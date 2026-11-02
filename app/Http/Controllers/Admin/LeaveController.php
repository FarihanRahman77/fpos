<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Leave applications (apply -> approve / reject / cancel) and the employee leave ledger
 * with its manual adjustments, plus the shared leave rules (days, balances, ledger) at the bottom.
 */
class LeaveController extends Controller
{
    use VoucherHelperTrait;

    public const STATUSES = ['Pending', 'Approved', 'Rejected', 'Cancelled'];

    /* ------------------------------------------------------------------ */
    /*  Applications                                                      */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        return view('admin.leave.applications', [
            'types'    => self::leaveTypes(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function list(Request $request)
    {
        try {
            $year = (int) $request->query('year', now()->year);

            $rows = BranchController::scope(DB::table('leave_applications as la'), 'e.branch_id')
                ->join('employees as e', 'e.id', '=', 'la.employee_id')
                ->join('leave_types as t', 't.id', '=', 'la.leave_type_id')
                ->where('la.deleted', 'No')
                // leaves that touch the year (one can run over New Year)
                ->where('la.from_date', '<=', "$year-12-31")->where('la.to_date', '>=', "$year-01-01")
                ->when($request->filled('employee_id'), fn ($q) => $q->where('la.employee_id', (int) $request->query('employee_id')))
                ->when($request->filled('leave_type_id'), fn ($q) => $q->where('la.leave_type_id', (int) $request->query('leave_type_id')))
                ->when(in_array($request->query('leave_status'), self::STATUSES, true), fn ($q) => $q->where('la.leave_status', $request->query('leave_status')))
                ->orderByDesc('la.from_date')->orderByDesc('la.id')
                ->get(['la.*', 'e.name as employee_name', 'e.employee_code', 'e.designation', 't.name as type_name', 't.is_paid']);

            $badge = ['Pending' => 'bg-warning text-dark', 'Approved' => 'bg-success', 'Rejected' => 'bg-danger', 'Cancelled' => 'bg-secondary'];

            $data = $rows->values()->map(function ($r, $i) use ($badge) {
                $id = (int) $r->id;
                $items = [['label' => 'View', 'icon' => 'fas fa-eye', 'onclick' => "Leave.view($id)"]];
                if ($r->leave_status === 'Pending') {
                    $items[] = ['label' => 'Edit', 'icon' => 'fas fa-edit', 'onclick' => "Leave.edit($id)"];
                    $items[] = ['label' => 'Approve', 'icon' => 'fas fa-check', 'class' => 'text-success', 'onclick' => "Leave.decide($id, 'approve')"];
                    $items[] = ['label' => 'Reject', 'icon' => 'fas fa-times', 'class' => 'text-danger', 'onclick' => "Leave.decide($id, 'reject')"];
                }
                if (in_array($r->leave_status, ['Pending', 'Approved'], true)) {
                    $items[] = ['label' => 'Cancel', 'icon' => 'fas fa-ban', 'onclick' => "Leave.decide($id, 'cancel')"];
                }
                if ($r->leave_status !== 'Approved') {
                    $items[] = ['divider' => true];
                    $items[] = ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => "Leave.remove($id)"];
                }
                $from = Carbon::parse($r->from_date);

                return [
                    $i + 1,
                    '<span class="fw-semibold">' . e($r->application_no) . '</span>',
                    '<span class="fw-semibold">' . e($r->employee_name) . '</span><div class="text-muted small">' . e($r->employee_code) . ($r->designation ? ' · ' . e($r->designation) : '') . '</div>',
                    e($r->type_name) . ($r->is_paid === 'No' ? ' <span class="badge bg-danger-subtle text-danger-emphasis">Unpaid</span>' : ''),
                    '<span class="d-none">' . $from->format('Y-m-d') . '</span>' . e($from->format('d M Y'))
                        . ($r->to_date !== $r->from_date ? ' – ' . e(Carbon::parse($r->to_date)->format('d M Y')) : '')
                        . ($r->half_day === 'Yes' ? ' <span class="text-muted small">(half day)</span>' : ''),
                    '<span class="d-block text-end fw-semibold">' . rtrim(rtrim(number_format((float) $r->days, 1), '0'), '.') . '</span>',
                    e($r->reason ?: '-'),
                    '<span class="badge ' . $badge[$r->leave_status] . '">' . $r->leave_status . '</span>',
                    action_dropdown($items),
                ];
            });

            return response()->json([
                'data'    => $data,
                'summary' => [
                    'pending'  => $rows->where('leave_status', 'Pending')->count(),
                    'approved' => $rows->where('leave_status', 'Approved')->count(),
                    'days'     => (float) $rows->where('leave_status', 'Approved')->sum('days'),
                    'today'    => BranchController::scope(DB::table('leave_applications as la'), 'e.branch_id')->join('employees as e', 'e.id', '=', 'la.employee_id')
                        ->where('la.deleted', 'No')->where('la.leave_status', 'Approved')
                        ->where('la.from_date', '<=', today()->toDateString())->where('la.to_date', '>=', today()->toDateString())->distinct()->count('la.employee_id'),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Leave list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load leave applications.'], 500);
        }
    }

    public function show(int $id)
    {
        $row = BranchController::scope(DB::table('leave_applications as la'), 'e.branch_id')
            ->join('employees as e', 'e.id', '=', 'la.employee_id')
            ->join('leave_types as t', 't.id', '=', 'la.leave_type_id')
            ->leftJoin('users as u', 'u.id', '=', 'la.decided_by')
            ->where('la.id', $id)->where('la.deleted', 'No')
            ->first(['la.*', 'e.name as employee_name', 'e.employee_code', 't.name as type_name', 'u.name as decided_by_name']);

        return $row
            ? response()->json(['status' => true, 'data' => $row])
            : response()->json(['status' => false, 'message' => 'Leave application not found.'], 404);
    }

    /** Days of the chosen period and the employee's balances, shown while filling the form. */
    public function preview(Request $request)
    {
        $employee = self::employees()->where('id', (int) $request->query('employee_id'))->where('deleted', 'No')->first();

        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'Select the employee.'], 422);
        }

        $from = $request->query('from_date');
        $to   = $request->query('to_date') ?: $from;
        $year = $from ? (int) substr($from, 0, 4) : now()->year;
        $days = $from && $to >= $from ? self::countDays($from, $to, $request->boolean('half_day')) : 0;

        $balances = self::balances($employee, $year, $request->filled('id') ? (int) $request->query('id') : null);

        return response()->json([
            'status'   => true,
            'year'     => $year,
            'days'     => $days,
            'group'    => $employee->leave_group_id ? DB::table('leave_groups')->where('id', $employee->leave_group_id)->value('name') : null,
            'balances' => array_values(array_map(fn ($b) => [
                'id' => $b['type']->id, 'name' => $b['type']->name, 'paid' => $b['paid'],
                'entitled' => $b['entitled'] + $b['adjusted'], 'taken' => $b['taken'], 'pending' => $b['pending'], 'balance' => $b['balance'],
            ], $balances)),
        ]);
    }

    public function store(Request $request)
    {
        $id = $request->filled('id') ? (int) $request->input('id') : null;

        $validator = Validator::make($request->all(), [
            'id'            => ['nullable', 'integer'],
            'employee_id'   => ['required', 'integer', Rule::exists('employees', 'id')->where('deleted', 'No')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('deleted', 'No')->where('status', 'Active')],
            'from_date'     => ['required', 'date'],
            'to_date'       => ['required', 'date', 'after_or_equal:from_date'],
            'half_day'      => ['nullable', 'boolean'],
            'reason'        => ['nullable', 'string', 'max:500'],
        ], [
            'employee_id.required'   => 'Select the employee.',
            'leave_type_id.required' => 'Select the leave type.',
            'to_date.after_or_equal' => 'End date cannot be before the start date.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $application = DB::transaction(function () use ($request, $id) {
                $from = $request->input('from_date');
                $to   = $request->input('to_date');
                $half = $request->boolean('half_day') && $from === $to;
                $days = self::countDays($from, $to, $half);

                abort_if($days <= 0, 422, 'The selected dates are weekends or holidays only.');

                $employee = self::employees()->where('id', (int) $request->input('employee_id'))->first();
                abort_unless($employee, 422, 'Select an employee of this branch.');
                $this->checkOverlap($employee->id, $from, $to, $id);
                $this->checkBalance($employee, (int) $request->input('leave_type_id'), $from, $to, $half, $id);

                $data = [
                    'employee_id'   => $employee->id,
                    'leave_type_id' => (int) $request->input('leave_type_id'),
                    'from_date'     => $from,
                    'to_date'       => $to,
                    'half_day'      => $half ? 'Yes' : 'No',
                    'days'          => $days,
                    'reason'        => $this->nullIfBlank($request->input('reason')),
                ];

                if ($id) {
                    $current = DB::table('leave_applications')->where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();
                    abort_unless($current && self::employees()->where('id', $current->employee_id)->exists(), 404, 'Leave application not found.');
                    abort_unless($current->leave_status === 'Pending', 422, 'Only a pending application can be edited.');
                    DB::table('leave_applications')->where('id', $id)->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);

                    return DB::table('leave_applications')->where('id', $id)->first();
                }

                $last = DB::table('leave_applications')->where('application_no', 'like', 'LV-%')->lockForUpdate()->max('application_no');
                $next = $last ? ((int) substr($last, 3)) + 1 : 1;

                $newId = DB::table('leave_applications')->insertGetId($data + [
                    'application_no' => 'LV-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                    'leave_status'   => 'Pending',
                    'deleted' => 'No', 'status' => 'Active',
                    'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);

                return DB::table('leave_applications')->where('id', $newId)->first();
            });

            return response()->json([
                'status'  => true,
                'message' => 'Leave ' . $application->application_no . ' (' . (float) $application->days . ' day(s)) ' . ($id ? 'updated.' : 'submitted.'),
            ]);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Leave store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the leave. Please try again.'], 500);
        }
    }

    /** approve | reject | cancel */
    public function decide(Request $request, int $id)
    {
        $action = $request->input('action');
        $map = ['approve' => 'Approved', 'reject' => 'Rejected', 'cancel' => 'Cancelled'];

        if (!isset($map[$action])) {
            return response()->json(['status' => false, 'message' => 'Unknown action.'], 422);
        }

        try {
            $status = DB::transaction(function () use ($request, $id, $action, $map) {
                $leave = DB::table('leave_applications')->where('id', $id)->where('deleted', 'No')->lockForUpdate()->first();
                abort_unless($leave && self::employees()->where('id', $leave->employee_id)->exists(), 404, 'Leave application not found.');

                $allowed = $action === 'cancel' ? ['Pending', 'Approved'] : ['Pending'];
                abort_unless(in_array($leave->leave_status, $allowed, true), 422, 'This application is already ' . strtolower($leave->leave_status) . '.');

                if ($action === 'approve') {
                    // The calendar or the balance may have changed since it was applied.
                    $employee = DB::table('employees')->where('id', $leave->employee_id)->first();
                    $this->checkBalance($employee, $leave->leave_type_id, $leave->from_date, $leave->to_date, $leave->half_day === 'Yes', $leave->id);
                }
                if ($action === 'approve' || ($action === 'cancel' && $leave->leave_status === 'Approved')) {
                    $this->checkProcessedMonths($leave);
                }

                DB::table('leave_applications')->where('id', $id)->update([
                    'leave_status'  => $map[$action],
                    'decided_by'    => Auth::id(),
                    'decided_at'    => now(),
                    'decision_note' => $this->nullIfBlank($request->input('note')),
                    'updated_at'    => now(),
                ]);

                return $map[$action];
            });

            return response()->json(['status' => true, 'message' => 'Leave ' . strtolower($status) . '.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Leave decide error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to update the leave.'], 500);
        }
    }

    public function delete(int $id)
    {
        $leave = DB::table('leave_applications')->where('id', $id)->where('deleted', 'No')->first();

        if (!$leave || !self::employees()->where('id', $leave->employee_id)->exists()) {
            return response()->json(['status' => false, 'message' => 'Leave application not found.'], 404);
        }
        if ($leave->leave_status === 'Approved') {
            return response()->json(['status' => false, 'message' => 'Cancel an approved leave instead of deleting it.'], 422);
        }

        DB::table('leave_applications')->where('id', $id)->update($this->deletedColumns());

        return response()->json(['status' => true, 'message' => 'Leave application deleted.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Ledger                                                            */
    /* ------------------------------------------------------------------ */

    public function ledger(Request $request)
    {
        $selected = $request->filled('employee_id')
            ? self::employees()->where('id', (int) $request->query('employee_id'))->where('deleted', 'No')->first(['id', 'name', 'employee_code'])
            : null;

        return view('admin.leave.ledger', ['selected' => $selected, 'types' => self::leaveTypes(), 'year' => now()->year]);
    }

    public function ledgerData(Request $request)
    {
        $employee = self::employees()->where('id', (int) $request->query('employee_id'))->where('deleted', 'No')->first();

        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'Select the employee.'], 422);
        }

        $year = (int) $request->query('year', now()->year);

        return response()->json([
            'status' => true,
            'html'   => view('admin.leave.partials.ledger-table', [
                'employee' => $employee,
                'year'     => $year,
                'group'    => $employee->leave_group_id ? DB::table('leave_groups')->where('id', $employee->leave_group_id)->value('name') : null,
                'balances' => self::balances($employee, $year),
                'ledger'   => self::leaveLedger($employee, $year),
            ])->render(),
        ]);
    }

    public function saveAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id'   => ['required', 'integer', Rule::exists('employees', 'id')->where('deleted', 'No')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('deleted', 'No')],
            'year'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'entry_date'    => ['required', 'date'],
            'days'          => ['required', 'numeric', 'between:-366,366', 'not_in:0'],
            'remarks'       => ['required', 'string', 'max:255'],
        ], [
            'days.not_in'      => 'Days cannot be zero.',
            'remarks.required' => 'Write why the balance is adjusted (e.g. carry forward from last year).',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }
        if (!self::employees()->where('id', (int) $request->input('employee_id'))->exists()) {
            return response()->json(['status' => false, 'message' => 'Select an employee of this branch.'], 422);
        }

        DB::table('leave_adjustments')->insert([
            'employee_id'   => (int) $request->input('employee_id'),
            'leave_type_id' => (int) $request->input('leave_type_id'),
            'year'          => (int) $request->input('year'),
            'entry_date'    => $request->input('entry_date'),
            'days'          => round((float) $request->input('days') * 2) / 2,
            'remarks'       => trim($request->input('remarks')),
            'deleted' => 'No', 'status' => 'Active',
            'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['status' => true, 'message' => 'Leave balance adjusted.']);
    }

    public function deleteAdjustment(int $id)
    {
        if (!DB::table('leave_adjustments')->where('id', $id)->where('deleted', 'No')
            ->whereIn('employee_id', self::employees()->select('id'))->update($this->deletedColumns())) {
            return response()->json(['status' => false, 'message' => 'Adjustment not found.'], 404);
        }

        return response()->json(['status' => true, 'message' => 'Adjustment removed.']);
    }

    /* ------------------------------------------------------------------ */

    /** Employees of the logged-in branch. */
    private static function employees()
    {
        return BranchController::scope(DB::table('employees'), 'branch_id')->where('deleted', 'No');
    }

    /**
     * Unpaid leave is deducted on the salary sheet, which is frozen once a month is processed.
     * Approving or cancelling unpaid leave inside such a month would leave the salary wrong.
     */
    private function checkProcessedMonths(object $leave): void
    {
        if (DB::table('leave_types')->where('id', $leave->leave_type_id)->value('is_paid') !== 'No') {
            return;
        }

        $month = Carbon::parse($leave->from_date)->startOfMonth();
        $end = Carbon::parse($leave->to_date)->startOfMonth();
        for (; $month->lte($end); $month->addMonth()) {
            abort_if(in_array((int) $leave->employee_id, array_map('intval', PayrollRunController::processedIds($month)), true), 422,
                'Salary of ' . $month->format('F Y') . ' is already processed for this employee, so this unpaid leave cannot change it. '
                . 'Reverse that processed salary first.');
        }
    }

    private function checkOverlap(int $employeeId, string $from, string $to, ?int $exceptId): void
    {
        $clash = DB::table('leave_applications')->where('employee_id', $employeeId)->where('deleted', 'No')
            ->whereIn('leave_status', ['Pending', 'Approved'])
            ->when($exceptId, fn ($q) => $q->where('id', '<>', $exceptId))
            ->where('from_date', '<=', $to)->where('to_date', '>=', $from)
            ->value('application_no');

        abort_if($clash, 422, 'These dates overlap leave ' . $clash . '.');
    }

    /**
     * Paid leave cannot go beyond the balance; pending applications count as used. A leave over
     * New Year is checked against each year's balance for the days falling in that year.
     */
    private function checkBalance(object $employee, int $typeId, string $from, string $to, bool $halfDay, ?int $exceptId): void
    {
        foreach (self::daysByYear($from, $to, $halfDay) as $year => $days) {
            $b = self::balances($employee, $year, $exceptId)[$typeId] ?? null;

            if (!$b || !$b['paid']) {
                return;
            }

            $available = $b['balance'] - $b['pending'];
            abort_if($days > $available, 422, sprintf(
                'Not enough %s in %d: %s day(s) needed, %s available (%s entitled, %s taken, %s pending).',
                $b['type']->name, $year, $days + 0, $available + 0, $b['entitled'] + $b['adjusted'], $b['taken'] + 0, $b['pending'] + 0
            ));
        }
    }

    /* ================================================================== */
    /*  Leave rules (was app/Services/LeaveService.php)                   */
    /* ================================================================== */

    /**
 * Leave rules on top of the yearly holiday calendar.
 *
 *  - A leave day is a working day: weekends of that year's calendar and holidays are not counted.
 *  - Entitlement: the employee's leave group has days per leave type per year (leave_group_rules).
 *  - Ledger per employee / year / type: entitlement + adjustments - approved leave = balance.
 *  - Unpaid leave types need no balance; their approved days are deducted on the salary sheet.
     */
    public const DEFAULT_WEEKEND = [5];   // Friday
    public const DAY_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    private static array $weekends = [];

    /** ISO day numbers (1 = Mon ... 7 = Sun) that are weekend in $year. */
    public static function weekendDays(int $year): array
    {
        if (!isset(self::$weekends[$year])) {
            $days = DB::table('work_calendars')->where('year', $year)->value('weekend_days');
            self::$weekends[$year] = $days === null
                ? self::DEFAULT_WEEKEND
                : array_values(array_filter(array_map('intval', explode(',', $days))));
        }

        return self::$weekends[$year];
    }

    public static function forgetWeekends(): void
    {
        self::$weekends = [];
    }

    /** Holiday titles keyed by Y-m-d for the dates between $from and $to. */
    public static function holidayMap(string $from, string $to): array
    {
        $map = [];
        $rows = DB::table('holidays')->where('deleted', 'No')->where('status', 'Active')
            ->where('from_date', '<=', $to)->where('to_date', '>=', $from)
            ->orderBy('from_date')->get(['title', 'from_date', 'to_date', 'type']);

        foreach ($rows as $h) {
            $start = max($h->from_date, $from);
            $end   = min($h->to_date, $to);
            foreach (CarbonPeriod::create($start, $end) as $day) {
                $map[$day->toDateString()] ??= ['title' => $h->title, 'type' => $h->type];
            }
        }

        return $map;
    }

    /** Working dates (Y-m-d) between two dates, inclusive. */
    public static function workingDates(string $from, string $to): array
    {
        $holidays = self::holidayMap($from, $to);
        $dates = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            if (!in_array($day->dayOfWeekIso, self::weekendDays($day->year), true) && !isset($holidays[$day->toDateString()])) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    /** Leave days for an application: working days, or 0.5 for a half day. */
    public static function countDays(string $from, string $to, bool $halfDay = false): float
    {
        $days = count(self::workingDates($from, $to));

        return $halfDay && $from === $to ? min($days, 1) * 0.5 : (float) $days;
    }

    /* ------------------------------------------------------------------ */
    /*  Balances and ledger                                               */
    /* ------------------------------------------------------------------ */

    public static function leaveTypes(bool $activeOnly = true)
    {
        return DB::table('leave_types')->where('deleted', 'No')
            ->when($activeOnly, fn ($q) => $q->where('status', 'Active'))
            ->orderByRaw("is_paid = 'No'")->orderBy('name')
            ->get(['id', 'name', 'code', 'is_paid', 'status']);
    }

    /** [leave_type_id => days] of the group rules for $year (full year, not prorated). */
    public static function entitlements(?int $groupId, int $year): array
    {
        if (!$groupId) {
            return [];
        }

        return DB::table('leave_group_rules')->where('leave_group_id', $groupId)->where('year', $year)
            ->pluck('days', 'leave_type_id')->map(fn ($d) => (float) $d)->all();
    }

    /**
     * Share of the year an employee is entitled to: 1 for a full year, months left / 12 for the
     * joining year (the joining month counts), 0 for years before joining.
     */
    public static function serviceFactor(object $employee, int $year): float
    {
        if (empty($employee->joining_date)) {
            return 1.0;
        }

        $joined = Carbon::parse($employee->joining_date);
        if ($joined->year > $year) {
            return 0.0;
        }

        return $joined->year === $year ? (13 - $joined->month) / 12 : 1.0;
    }

    /** The group entitlement of one employee for $year, prorated to whole half days. */
    public static function entitlementsFor(object $employee, int $year): array
    {
        $factor = self::serviceFactor($employee, $year);

        return array_map(fn ($days) => round($days * $factor * 2) / 2, self::entitlements($employee->leave_group_id, $year));
    }

    /** Leave days of an application that fall in $year (a leave can run over New Year). */
    public static function daysInYear(object $application, int $year): float
    {
        $from = max($application->from_date, "$year-01-01");
        $to   = min($application->to_date, "$year-12-31");

        if ($from > $to) {
            return 0.0;
        }
        if ($application->half_day === 'Yes') {
            return (float) $application->days;   // half days are single-day leaves
        }

        return (float) count(self::workingDates($from, $to));
    }

    /** [year => days] of a leave period, for checking each year's balance. */
    public static function daysByYear(string $from, string $to, bool $halfDay = false): array
    {
        $out = [];
        for ($y = (int) substr($from, 0, 4); $y <= (int) substr($to, 0, 4); $y++) {
            $out[$y] = $halfDay && $from === $to
                ? self::countDays($from, $to, true)
                : (float) count(self::workingDates(max($from, "$y-01-01"), min($to, "$y-12-31")));
        }

        return array_filter($out);
    }

    /** Approved / pending applications of an employee that touch $year. */
    private static function applicationsInYear(object $employee, int $year, array $statuses, ?int $exceptApplicationId = null)
    {
        return DB::table('leave_applications')->where('employee_id', $employee->id)->where('deleted', 'No')
            ->whereIn('leave_status', $statuses)
            ->where('from_date', '<=', "$year-12-31")->where('to_date', '>=', "$year-01-01")
            ->when($exceptApplicationId, fn ($q) => $q->where('id', '<>', $exceptApplicationId))
            ->orderBy('from_date')
            ->get();
    }

    /**
     * Per leave type for one employee and year:
     * entitled (prorated), adjusted, taken (approved), pending, balance (null for unpaid types).
     * Only the days of a leave that fall in $year count. $exceptApplicationId leaves one
     * application out (when it is being edited).
     */
    public static function balances(object $employee, int $year, ?int $exceptApplicationId = null): array
    {
        $entitled = self::entitlementsFor($employee, $year);

        $adjusted = DB::table('leave_adjustments')->where('employee_id', $employee->id)->where('year', $year)->where('deleted', 'No')
            ->groupBy('leave_type_id')->selectRaw('leave_type_id, SUM(days) as d')->pluck('d', 'leave_type_id');

        $used = [];   // [type][status] => days in this year
        foreach (self::applicationsInYear($employee, $year, ['Approved', 'Pending'], $exceptApplicationId) as $a) {
            $used[$a->leave_type_id][$a->leave_status] = ($used[$a->leave_type_id][$a->leave_status] ?? 0) + self::daysInYear($a, $year);
        }

        $out = [];
        foreach (self::leaveTypes() as $t) {
            $taken   = (float) ($used[$t->id]['Approved'] ?? 0);
            $pending = (float) ($used[$t->id]['Pending'] ?? 0);
            $ent     = $entitled[$t->id] ?? 0.0;
            $adj     = (float) ($adjusted[$t->id] ?? 0);

            $out[$t->id] = [
                'type'     => $t,
                'paid'     => $t->is_paid === 'Yes',
                'entitled' => $ent,
                'adjusted' => $adj,
                'taken'    => $taken,
                'pending'  => $pending,
                'balance'  => $t->is_paid === 'Yes' ? round($ent + $adj - $taken, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * Ledger lines per leave type for one employee and year, in date order:
     * the group entitlement on 1 January, adjustments and approved leave, with running balance.
     */
    public static function leaveLedger(object $employee, int $year): array
    {
        $entitled = self::entitlementsFor($employee, $year);
        $factor = self::serviceFactor($employee, $year);
        $groupName = $employee->leave_group_id ? DB::table('leave_groups')->where('id', $employee->leave_group_id)->value('name') : null;
        $start = $factor < 1 && $factor > 0 ? Carbon::parse($employee->joining_date)->startOfMonth()->toDateString() : "$year-01-01";
        $note = $factor < 1 && $factor > 0 ? ' (pro-rata ' . round($factor * 12) . '/12 months, joined ' . Carbon::parse($employee->joining_date)->format('d M') . ')' : '';

        $lines = [];
        foreach (self::leaveTypes() as $t) {
            $lines[$t->id] = [];
            if (isset($entitled[$t->id])) {
                $lines[$t->id][] = ['date' => $start, 'kind' => 'Entitlement', 'text' => 'Yearly entitlement — ' . $groupName . $note, 'days' => $entitled[$t->id]];
            }
        }

        foreach (DB::table('leave_adjustments')->where('employee_id', $employee->id)->where('year', $year)->where('deleted', 'No')->orderBy('entry_date')->get() as $a) {
            $lines[$a->leave_type_id][] = ['date' => $a->entry_date, 'kind' => 'Adjustment', 'text' => $a->remarks ?: 'Adjustment', 'days' => (float) $a->days, 'adjustment_id' => $a->id];
        }

        foreach (self::applicationsInYear($employee, $year, ['Approved']) as $l) {
            $days = self::daysInYear($l, $year);
            $period = Carbon::parse($l->from_date)->format('d M Y') . ($l->to_date !== $l->from_date ? ' – ' . Carbon::parse($l->to_date)->format('d M Y') : '');
            $split = abs($days - (float) $l->days) > 0.001 ? ' (' . rtrim(rtrim(number_format($days, 1), '0'), '.') . ' of ' . rtrim(rtrim(number_format((float) $l->days, 1), '0'), '.') . ' days fall in ' . $year . ')' : '';
            $lines[$l->leave_type_id][] = [
                'date' => max($l->from_date, "$year-01-01"), 'kind' => 'Leave', 'days' => -$days,
                'text' => $l->application_no . ' · ' . $period . ($l->half_day === 'Yes' ? ' (half day)' : '') . $split . ($l->reason ? ' · ' . $l->reason : ''),
            ];
        }

        $out = [];
        foreach (self::leaveTypes() as $t) {
            $rows = collect($lines[$t->id] ?? [])->sortBy('date')->values();
            $balance = 0.0;
            $out[] = [
                'type' => $t,
                'rows' => $rows->map(function ($r) use (&$balance) {
                    $balance += $r['days'];

                    return $r + ['balance' => round($balance, 1)];
                })->all(),
            ];
        }

        return $out;
    }

    /**
     * Approved unpaid leave days of each employee falling inside the month: [employee_id => days].
     */
    public static function unpaidDays(Carbon $month): array
    {
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        $rows = DB::table('leave_applications as la')
            ->join('leave_types as lt', 'lt.id', '=', 'la.leave_type_id')
            ->where('la.deleted', 'No')->where('la.leave_status', 'Approved')->where('lt.is_paid', 'No')
            ->where('la.from_date', '<=', $to)->where('la.to_date', '>=', $from)
            ->get(['la.employee_id', 'la.from_date', 'la.to_date', 'la.half_day']);

        $out = [];
        foreach ($rows as $r) {
            $days = $r->half_day === 'Yes'
                ? self::countDays($r->from_date, $r->to_date, true)
                : count(self::workingDates(max($r->from_date, $from), min($r->to_date, $to)));
            $out[$r->employee_id] = ($out[$r->employee_id] ?? 0) + $days;
        }

        return $out;
    }
}
