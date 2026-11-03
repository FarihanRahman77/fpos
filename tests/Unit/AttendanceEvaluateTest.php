<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\AttendanceController;
use Tests\AppTestCase;

/** AttendanceController::evaluate(): status, late / overtime / worked minutes against a shift. */
class AttendanceEvaluateTest extends AppTestCase
{
    private function day(array $over = []): object
    {
        return (object) ($over + ['start_time' => '09:00:00', 'end_time' => '17:00:00', 'grace_minutes' => 10,
            'half_day_after_minutes' => 0, 'overtime_after_minutes' => 30]);
    }

    private function night(): object
    {
        return (object) ['start_time' => '22:00:00', 'end_time' => '06:00:00', 'grace_minutes' => 10,
            'half_day_after_minutes' => 0, 'overtime_after_minutes' => 30];
    }

    private function ev(object $shift, ?string $in, ?string $out, ?string $dayType = null, ?string $forced = null): array
    {
        return AttendanceController::evaluate($shift, '2026-11-02', $in, $out, $dayType, $forced);
    }

    public function test_on_time_within_grace_is_present(): void
    {
        $this->assertSame(['attendance_status' => 'Present', 'late_minutes' => 0, 'overtime_minutes' => 0, 'worked_minutes' => 475],
            $this->ev($this->day(), '09:05', '17:00'));
        $this->assertSame('Present', $this->ev($this->day(), '09:10', '17:00')['attendance_status']); // exactly the grace
    }

    public function test_late_after_grace_counts_all_late_minutes(): void
    {
        $r = $this->ev($this->day(), '09:15', '17:00');

        $this->assertSame('Late', $r['attendance_status']);
        $this->assertSame(15, $r['late_minutes']);
        $this->assertSame(465, $r['worked_minutes']);
    }

    public function test_very_late_is_half_day_when_configured(): void
    {
        $r = $this->ev($this->day(['half_day_after_minutes' => 120]), '11:00', '17:00');

        $this->assertSame('Half Day', $r['attendance_status']);
        $this->assertSame(120, $r['late_minutes']);
    }

    public function test_early_leave_under_half_the_shift_is_half_day(): void
    {
        $r = $this->ev($this->day(), '09:00', '12:00');

        $this->assertSame('Half Day', $r['attendance_status']);
        $this->assertSame(180, $r['worked_minutes']);
        $this->assertSame(0, $r['overtime_minutes']);
    }

    public function test_overtime_only_after_the_threshold(): void
    {
        $r = $this->ev($this->day(), '09:00', '18:00');
        $this->assertSame('Present', $r['attendance_status']);
        $this->assertSame(60, $r['overtime_minutes']);
        $this->assertSame(540, $r['worked_minutes']);

        $this->assertSame(0, $this->ev($this->day(), '09:00', '17:20')['overtime_minutes']); // 20 < 30
        $this->assertSame(30, $this->ev($this->day(), '09:00', '17:30')['overtime_minutes']);
    }

    public function test_no_check_in_is_absent(): void
    {
        $this->assertSame(['attendance_status' => 'Absent', 'late_minutes' => 0, 'overtime_minutes' => 0, 'worked_minutes' => 0],
            $this->ev($this->day(), null, null));
        $this->assertSame('Absent', $this->ev($this->day(), '09:00', '17:00', 'n/a')['attendance_status']);
    }

    public function test_work_on_holiday_or_weekly_off_is_all_overtime_but_not_on_leave(): void
    {
        $h = $this->ev($this->day(), '10:00', '14:00', 'Holiday');
        $this->assertSame('Holiday', $h['attendance_status']);
        $this->assertSame(240, $h['overtime_minutes']);

        $this->assertSame(240, $this->ev($this->day(), '10:00', '14:00', 'Weekly Off')['overtime_minutes']);

        $l = $this->ev($this->day(), '10:00', '14:00', 'Leave');
        $this->assertSame('Leave', $l['attendance_status']);
        $this->assertSame(0, $l['overtime_minutes']);
    }

    public function test_forced_status_keeps_the_minutes(): void
    {
        $r = $this->ev($this->day(), '09:40', '17:00', null, 'Present');

        $this->assertSame('Present', $r['attendance_status']);
        $this->assertSame(40, $r['late_minutes']);
    }

    public function test_night_shift_crossing_midnight(): void
    {
        $this->assertSame(840, AttendanceController::nightCutoff($this->night())); // 06:00 + (22:00 - 06:00) / 2 = 14:00
        $this->assertNull(AttendanceController::nightCutoff($this->day()));

        $ok = $this->ev($this->night(), '22:05', '06:00');
        $this->assertSame('Present', $ok['attendance_status']);
        $this->assertSame(475, $ok['worked_minutes']);
        $this->assertSame(0, $ok['overtime_minutes']);
    }

    public function test_night_shift_check_in_after_midnight_is_late_and_morning_out_is_overtime(): void
    {
        $late = $this->ev($this->night(), '00:30', '06:10');
        $this->assertSame('Late', $late['attendance_status']);
        $this->assertSame(150, $late['late_minutes']);
        $this->assertSame(340, $late['worked_minutes']);
        $this->assertSame(0, $late['overtime_minutes']);

        $ot = $this->ev($this->night(), '22:00', '07:00');
        $this->assertSame('Present', $ot['attendance_status']);
        $this->assertSame(60, $ot['overtime_minutes']);
        $this->assertSame(540, $ot['worked_minutes']);
    }
}
