<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\LeaveController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;

/** LeaveController::workingDates() / countDays() / unpaidDays(): weekends of the year's calendar and holidays are not leave days. */
class LeaveWorkingDatesTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('superadmin', 'main');
    }

    private function holiday(string $from, string $to, array $extra = []): void
    {
        DB::table('holidays')->insert($extra + ['title' => 'Holiday ' . $from, 'from_date' => $from, 'to_date' => $to, 'type' => 'Public',
            'deleted' => 'No', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function calendar(int $year, string $weekend): void
    {
        DB::table('work_calendars')->insert(['year' => $year, 'weekend_days' => $weekend, 'created_at' => now(), 'updated_at' => now()]);
        LeaveController::forgetWeekends();
    }

    public function test_default_weekend_is_friday(): void
    {
        // Sun 1 Nov 2026 .. Sat 7 Nov 2026: Friday 6th is off
        $this->assertSame(
            ['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-07'],
            LeaveController::workingDates('2026-11-01', '2026-11-07')
        );
    }

    public function test_calendar_weekend_days_of_the_year(): void
    {
        $this->calendar(2026, '5,6'); // Friday + Saturday

        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'], LeaveController::workingDates('2026-11-01', '2026-11-07'));
    }

    public function test_holidays_are_excluded_and_clipped_to_the_range(): void
    {
        $this->holiday('2026-11-03', '2026-11-03');
        $this->holiday('2026-10-28', '2026-11-02');                       // starts before the range
        $this->holiday('2026-11-05', '2026-11-05', ['deleted' => 'Yes']); // deleted: ignored
        $this->holiday('2026-11-04', '2026-11-04', ['status' => 'Inactive']); // inactive: ignored

        $this->assertSame(['2026-11-04', '2026-11-05', '2026-11-07'], LeaveController::workingDates('2026-11-01', '2026-11-07'));
        $this->assertSame(3.0, LeaveController::countDays('2026-11-01', '2026-11-07'));
    }

    public function test_range_over_new_year_uses_each_years_calendar(): void
    {
        $this->calendar(2027, '6'); // 2027: Saturday off (2026 keeps the Friday default)

        // Wed 30 Dec, Thu 31 Dec, Fri 1 Jan 2027 (working in 2027), Sat 2 Jan (off in 2027)
        $this->assertSame(['2026-12-30', '2026-12-31', '2027-01-01'], LeaveController::workingDates('2026-12-30', '2027-01-02'));
    }

    public function test_half_day_counts(): void
    {
        $this->assertSame(0.5, LeaveController::countDays('2026-11-02', '2026-11-02', true));
        $this->assertSame(0.0, LeaveController::countDays('2026-11-06', '2026-11-06', true)); // half day on a Friday
        $this->assertSame(2.0, LeaveController::countDays('2026-11-02', '2026-11-03', true)); // half day only for a single date
    }

    public function test_unpaid_days_counts_approved_unpaid_leave_in_the_month(): void
    {
        $unpaid = DB::table('leave_types')->insertGetId(['name' => 'Leave Without Pay', 'code' => 'LWP', 'is_paid' => 'No', 'deleted' => 'No', 'status' => 'Active']);
        $paid = DB::table('leave_types')->insertGetId(['name' => 'Casual', 'code' => 'CL', 'is_paid' => 'Yes', 'deleted' => 'No', 'status' => 'Active']);
        $emp = DB::table('employees')->insertGetId(['employee_code' => 'E-LV', 'name' => 'Leave Person', 'branch_id' => $this->branch('main'), 'deleted' => 'No', 'status' => 'Active']);
        $app = function (int $type, string $from, string $to, string $status = 'Approved', string $half = 'No', float $days = 1) use ($emp) {
            static $n = 0;
            DB::table('leave_applications')->insert(['application_no' => 'LA-T' . (++$n), 'employee_id' => $emp, 'leave_type_id' => $type,
                'from_date' => $from, 'to_date' => $to, 'half_day' => $half, 'days' => $days, 'leave_status' => $status, 'deleted' => 'No', 'status' => 'Active']);
        };

        $app($unpaid, '2026-10-29', '2026-11-03');           // Nov part: 1,2,3 = 3 working days
        $app($unpaid, '2026-11-10', '2026-11-10', 'Approved', 'Yes', 0.5); // half day
        $app($unpaid, '2026-11-11', '2026-11-12', 'Pending'); // not approved
        $app($paid, '2026-11-15', '2026-11-16');              // paid type

        $this->assertSame([$emp => 3.5], array_map('floatval', LeaveController::unpaidDays(Carbon::parse('2026-11-01'))));
    }
}
