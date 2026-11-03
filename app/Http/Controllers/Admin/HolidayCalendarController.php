<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\SafeJsonErrors;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Yearly holiday calendar: the weekend days of each year and the holidays.
 * Leave days are counted against it (see LeaveController::workingDates()).
 */
class HolidayCalendarController extends Controller
{
    use VoucherHelperTrait, SafeJsonErrors;

    public const TYPES = ['Public', 'Festival', 'Company', 'Optional'];

    public function index(Request $request)
    {
        $year = $this->year($request);

        return view('admin.leave.calendar', [
            'year'     => $year,
            'types'    => self::TYPES,
            'dayNames' => LeaveController::DAY_NAMES,
            'note'     => DB::table('work_calendars')->where('year', $year)->value('note'),
        ]);
    }

    /** Everything the 12-month grid needs for one year. */
    public function data(Request $request)
    {
        $year = $this->year($request);
        $from = "$year-01-01";
        $to   = "$year-12-31";

        $holidays = DB::table('holidays')->where('deleted', 'No')
            ->where('from_date', '<=', $to)->where('to_date', '>=', $from)
            ->orderBy('from_date')->get(['id', 'title', 'from_date', 'to_date', 'type', 'description', 'status']);

        // People on approved leave per date.
        $onLeave = [];
        $leaves = BranchController::scope(DB::table('leave_applications as la'), 'e.branch_id')->join('employees as e', 'e.id', '=', 'la.employee_id')
            ->where('la.deleted', 'No')->where('la.leave_status', 'Approved')
            ->where('la.from_date', '<=', $to)->where('la.to_date', '>=', $from)
            ->get(['la.from_date', 'la.to_date', 'e.name']);
        foreach ($leaves as $l) {
            foreach (CarbonPeriod::create(max($l->from_date, $from), min($l->to_date, $to)) as $day) {
                $onLeave[$day->toDateString()][] = $l->name;
            }
        }

        $weekend  = LeaveController::weekendDays($year);
        $map      = LeaveController::holidayMap($from, $to);
        $working  = count(LeaveController::workingDates($from, $to));

        return response()->json([
            'status'   => true,
            'year'     => $year,
            'weekend'  => $weekend,
            'map'      => $map,
            'on_leave' => $onLeave,
            'holidays' => $holidays->map(fn ($h) => (array) $h + [
                'days'  => Carbon::parse($h->from_date)->diffInDays(Carbon::parse($h->to_date)) + 1,
                'label' => Carbon::parse($h->from_date)->format('d M') . ($h->to_date !== $h->from_date ? ' – ' . Carbon::parse($h->to_date)->format('d M') : ''),
            ]),
            'summary'  => [
                'days'     => Carbon::create($year)->daysInYear,
                'working'  => $working,
                'holidays' => count($map),
                'weekend'  => implode(', ', array_map(fn ($d) => LeaveController::DAY_NAMES[$d], $weekend)) ?: 'None',
            ],
        ]);
    }

    public function saveWeekend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year'           => ['required', 'integer', 'min:2000', 'max:2100'],
            'weekend_days'   => ['nullable', 'array', 'max:6'],
            'weekend_days.*' => ['integer', 'between:1,7'],
            'note'           => ['nullable', 'string', 'max:255'],
        ], ['weekend_days.max' => 'At least one day of the week must be a working day.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $days = collect($request->input('weekend_days', []))->map(fn ($d) => (int) $d)->unique()->sort()->implode(',');

        DB::table('work_calendars')->updateOrInsert(
            ['year' => (int) $request->input('year')],
            ['weekend_days' => $days, 'note' => $this->nullIfBlank($request->input('note')), 'updated_by' => Auth::id(), 'updated_at' => now(), 'created_at' => now()]
        );
        LeaveController::forgetWeekends();

        return response()->json(['status' => true, 'message' => 'Weekend of ' . $request->input('year') . ' saved.']);
    }

    public function saveHoliday(Request $request)
    {
        $id = $request->filled('id') ? (int) $request->input('id') : null;

        $validator = Validator::make($request->all(), [
            'id'          => ['nullable', 'integer'],
            'title'       => ['required', 'string', 'max:150'],
            'from_date'   => ['required', 'date'],
            'to_date'     => ['required', 'date', 'after_or_equal:from_date'],
            'type'        => ['required', Rule::in(self::TYPES)],
            'description' => ['nullable', 'string', 'max:500'],
            'status'      => ['required', Rule::in(['Active', 'Inactive'])],
        ], [
            'title.required'         => 'Enter the holiday name.',
            'to_date.after_or_equal' => 'End date cannot be before the start date.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = [
            'title'       => trim($request->input('title')),
            'from_date'   => $request->input('from_date'),
            'to_date'     => $request->input('to_date'),
            'type'        => $request->input('type'),
            'description' => $this->nullIfBlank($request->input('description')),
            'status'      => $request->input('status'),
        ];

        if ($id) {
            $updated = DB::table('holidays')->where('id', $id)->where('deleted', 'No')
                ->update($data + ['updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now()]);
            if (!$updated && !DB::table('holidays')->where('id', $id)->where('deleted', 'No')->exists()) {
                return response()->json(['status' => false, 'message' => 'Holiday not found.'], 404);
            }
        } else {
            DB::table('holidays')->insert($data + [
                'deleted' => 'No', 'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => true, 'message' => 'Holiday ' . ($id ? 'updated' : 'added') . '.']);
    }

    public function deleteHoliday(int $id)
    {
        if (!DB::table('holidays')->where('id', $id)->where('deleted', 'No')->update($this->deletedColumns())) {
            return response()->json(['status' => false, 'message' => 'Holiday not found.'], 404);
        }

        return response()->json(['status' => true, 'message' => 'Holiday deleted.']);
    }

    /** Copies the weekend and the holidays of one year into another (same day and month). */
    public function copy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_year' => ['required', 'integer', 'min:2000', 'max:2100', 'different:year'],
            'year'      => ['required', 'integer', 'min:2000', 'max:2100'],
        ], ['from_year.different' => 'Choose another year to copy from.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $fromYear = (int) $request->input('from_year');
        $year     = (int) $request->input('year');

        try {
            $copied = DB::transaction(function () use ($fromYear, $year) {
                if ($weekend = DB::table('work_calendars')->where('year', $fromYear)->first()) {
                    DB::table('work_calendars')->updateOrInsert(['year' => $year], [
                        'weekend_days' => $weekend->weekend_days, 'updated_by' => Auth::id(), 'updated_at' => now(), 'created_at' => now(),
                    ]);
                }

                $shift = fn ($date) => Carbon::parse($date)->addYears($year - $fromYear)->toDateString();
                $copied = 0;

                foreach (DB::table('holidays')->where('deleted', 'No')->whereYear('from_date', $fromYear)->get() as $h) {
                    $from = $shift($h->from_date);
                    if (DB::table('holidays')->where('deleted', 'No')->where('title', $h->title)->where('from_date', $from)->exists()) {
                        continue;
                    }

                    DB::table('holidays')->insert([
                        'title' => $h->title, 'from_date' => $from, 'to_date' => $shift($h->to_date), 'type' => $h->type,
                        'description' => $h->description, 'status' => $h->status, 'deleted' => 'No',
                        'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $copied++;
                }

                return $copied;
            });
            LeaveController::forgetWeekends();

            return response()->json(['status' => true, 'message' => $copied . ' holiday(s) copied from ' . $fromYear . '. Check the dates of moving (lunar) holidays.']);
        } catch (Throwable $e) {
            Log::error('Holiday copy error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to copy the calendar.'], 500);
        }
    }

    private function year(Request $request): int
    {
        $year = (int) $request->query('year', now()->year);

        return $year >= 2000 && $year <= 2100 ? $year : now()->year;
    }
}
