<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Http\Controllers\Admin\PayrollSetupController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;

/**
 * PayrollRunController::monthLines(): gross + bonuses + additions − deductions − unpaid leave − PF − loan installments,
 * with mid-month joiners prorated. Month under test: November 2026 (30 days).
 * Standard employee: basic 20,000 + house rent 10,000 + compensation 6,000 + medical 4,000 = gross 40,000; PF 2,000.
 */
class PayrollMonthLinesTest extends AppTestCase
{
    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('superadmin', 'main');
        $this->month = Carbon::parse('2026-11-01');
    }

    private function employee(string $code, array $extra = []): int
    {
        return DB::table('employees')->insertGetId($extra + [
            'branch_id' => $this->branch('main'), 'employee_code' => $code, 'name' => 'Emp ' . $code, 'department' => 'Sales',
            'joining_date' => '2025-01-01', 'basic_salary' => 20000, 'house_rent' => 10000, 'compensation' => 6000, 'medical_allowance' => 4000,
            'gross_salary' => 40000, 'pf_amount' => 2000, 'net_salary' => 38000, 'bonus_eligible' => 'Yes', 'deleted' => 'No', 'status' => 'Active',
        ]);
    }

    private function lines(): array
    {
        self::resetAppStatics();

        return PayrollRunController::monthLines($this->month);
    }

    private function loan(int $emp, float $amount, float $installment, float $recovered = 0, string $start = '2026-10-01', string $status = 'Running'): int
    {
        static $n = 0;

        return DB::table('employee_loans')->insertGetId([
            'branch_id' => $this->branch('main'), 'loan_no' => 'LN-T' . (++$n), 'employee_id' => $emp, 'loan_type' => 'Loan', 'amount' => $amount,
            'issue_date' => '2026-09-15', 'payment_account_id' => $this->coa('cash-main'), 'loan_account_id' => $this->coa('employee-loans'),
            'installments' => 5, 'installment_amount' => $installment, 'start_month' => $start, 'recovered' => $recovered,
            'loan_status' => $status, 'deleted' => 'No', 'status' => 'Active',
        ]);
    }

    public function test_plain_line_is_gross_minus_pf(): void
    {
        $id = $this->employee('E001');

        $l = $this->lines()[$id];

        $this->assertEqualsWithDelta(40000, $l['gross_salary'], 0.001);
        $this->assertEqualsWithDelta(2000, $l['pf_amount'], 0.001);
        $this->assertEqualsWithDelta(0, $l['lwp_deduction'], 0.001);
        $this->assertEqualsWithDelta(0, $l['loan_deduction'], 0.001);
        $this->assertEqualsWithDelta(38000, $l['net_payable'], 0.001);
    }

    public function test_unpaid_leave_is_deducted_at_the_daily_gross_rate(): void
    {
        $id = $this->employee('E001');
        $lwp = DB::table('leave_types')->insertGetId(['name' => 'LWP', 'is_paid' => 'No', 'deleted' => 'No', 'status' => 'Active']);
        // Mon 2 – Wed 4 Nov: 3 working days
        DB::table('leave_applications')->insert(['application_no' => 'LA-1', 'employee_id' => $id, 'leave_type_id' => $lwp, 'from_date' => '2026-11-02',
            'to_date' => '2026-11-04', 'days' => 3, 'leave_status' => 'Approved', 'deleted' => 'No', 'status' => 'Active']);

        $l = $this->lines()[$id];

        $this->assertEqualsWithDelta(3, $l['lwp_days'], 0.001);
        $this->assertEqualsWithDelta(4000, $l['lwp_deduction'], 0.001);   // 40000 / 30 × 3
        $this->assertEqualsWithDelta(34000, $l['net_payable'], 0.001);    // 40000 − 4000 − 2000
    }

    public function test_bonus_additions_and_deductions(): void
    {
        $a = $this->employee('E001');
        $b = $this->employee('E002', ['bonus_eligible' => 'No']);
        $c = $this->employee('E003');

        $festival = DB::table('bonus_types')->insertGetId(['name' => 'Festival', 'calc_type' => 'Percentage', 'basis' => 'Basic', 'value' => 50,
            'eligible_only' => 'Yes', 'deleted' => 'No', 'status' => 'Active']);
        DB::table('bonus_assignments')->insert([
            ['bonus_type_id' => $festival, 'month' => '2026-11-01', 'scope' => 'All', 'scope_id' => null, 'calc_type' => null, 'value' => null, 'deleted' => 'No', 'status' => 'Active'],
            // the employee-scope assignment is the most specific and replaces "All" for E003
            ['bonus_type_id' => $festival, 'month' => '2026-11-01', 'scope' => 'Employee', 'scope_id' => $c, 'calc_type' => 'Amount', 'value' => 1234, 'deleted' => 'No', 'status' => 'Active'],
            // other month: ignored
            ['bonus_type_id' => $festival, 'month' => '2026-10-01', 'scope' => 'All', 'scope_id' => null, 'calc_type' => 'Amount', 'value' => 999, 'deleted' => 'No', 'status' => 'Active'],
        ]);
        DB::table('salary_adjustments')->insert([
            ['employee_id' => $a, 'month' => '2026-11-01', 'adj_type' => 'Addition', 'head' => 'Overtime', 'amount' => 1500, 'deleted' => 'No', 'status' => 'Active'],
            ['employee_id' => $a, 'month' => '2026-11-01', 'adj_type' => 'Deduction', 'head' => 'Fine', 'amount' => 500, 'deleted' => 'No', 'status' => 'Active'],
            ['employee_id' => $a, 'month' => '2026-11-01', 'adj_type' => 'Deduction', 'head' => 'Deleted', 'amount' => 7777, 'deleted' => 'Yes', 'status' => 'Active'],
        ]);

        $lines = $this->lines();

        $this->assertEqualsWithDelta(10000, $lines[$a]['bonus_amount'], 0.001);     // 50% of basic 20000
        $this->assertEqualsWithDelta(1500, $lines[$a]['additions'], 0.001);
        $this->assertEqualsWithDelta(500, $lines[$a]['deductions'], 0.001);
        $this->assertEqualsWithDelta(49000, $lines[$a]['net_payable'], 0.001);      // 40000 + 10000 + 1500 − 500 − 2000
        $this->assertEqualsWithDelta(0, $lines[$b]['bonus_amount'], 0.001);         // not bonus eligible
        $this->assertEqualsWithDelta(1234, $lines[$c]['bonus_amount'], 0.001);      // employee assignment only
        $this->assertCount(1, $lines[$c]['bonus_detail']);
    }

    public function test_loan_installment_is_capped_by_what_is_left_and_by_the_salary(): void
    {
        $a = $this->employee('E001');
        $this->loan($a, 10000, 3000, 8500);                 // only 1500 left
        $this->loan($a, 5000, 1000, 0, '2026-12-01');       // starts next month: ignored
        $this->loan($a, 5000, 1000, 0, '2026-10-01', 'Closed');

        $b = $this->employee('E002');
        $this->loan($b, 100000, 50000);                     // more than the salary
        DB::table('salary_adjustments')->insert(['employee_id' => $b, 'month' => '2026-11-01', 'adj_type' => 'Deduction', 'head' => 'Damage', 'amount' => 30000, 'deleted' => 'No', 'status' => 'Active']);

        $lines = $this->lines();

        $this->assertEqualsWithDelta(1500, $lines[$a]['loan_deduction'], 0.001);
        $this->assertEqualsWithDelta(36500, $lines[$a]['net_payable'], 0.001);      // 38000 − 1500
        $this->assertCount(1, $lines[$a]['loan_detail']);

        // 40000 − 30000 − 2000 = 8000 room → installment 8000, net 0 (never below zero)
        $this->assertEqualsWithDelta(8000, $lines[$b]['loan_deduction'], 0.001);
        $this->assertEqualsWithDelta(0, $lines[$b]['net_payable'], 0.001);
    }

    public function test_mid_month_joiner_is_prorated_including_pf(): void
    {
        $id = $this->employee('E001', ['joining_date' => '2026-11-16']);   // employed 16–30 Nov = 15 of 30 days

        $l = $this->lines()[$id];

        $this->assertEqualsWithDelta(20000, $l['deductions'], 0.001);      // 40000 / 30 × 15
        $this->assertStringContainsString('Not employed (15 days)', $l['deductions_detail'][0]['name']);
        $this->assertEqualsWithDelta(1000, $l['pf_amount'], 0.001);        // 2000 × 15 / 30
        $this->assertEqualsWithDelta(19000, $l['net_payable'], 0.001);
    }

    public function test_mid_month_leaver_is_prorated(): void
    {
        $id = $this->employee('E001', ['resign_date' => '2026-11-10']);    // employed 1–10 Nov = 10 days, 20 off

        $l = $this->lines()[$id];

        $this->assertEqualsWithDelta(26666.67, $l['deductions'], 0.001);   // 40000 / 30 × 20
        $this->assertEqualsWithDelta(666.67, $l['pf_amount'], 0.001);      // 2000 × 10 / 30
        $this->assertEqualsWithDelta(12666.66, $l['net_payable'], 0.001);
    }

    public function test_only_active_employees_employed_in_the_month_and_branch_are_listed(): void
    {
        $in = $this->employee('E001');
        $joinedLater = $this->employee('E002', ['joining_date' => '2026-12-01']);
        $leftBefore = $this->employee('E003', ['resign_date' => '2026-10-31']);
        $inactive = $this->employee('E004', ['status' => 'Inactive']);
        $deleted = $this->employee('E005', ['deleted' => 'Yes']);
        $otherBranch = $this->employee('E006', ['branch_id' => $this->branch('two')]);

        // HR works only in the main branch: Branch Two's employee is not in its month lines.
        $this->signIn('hr', 'main');
        $this->assertSame([$in], array_keys($this->lines()));
    }

    public function test_salary_breakdown_scale_structure(): void
    {
        $s = (object) ['salary_structure' => 'Scale', 'house_rent_percent' => 50, 'compensation_percent' => 30, 'medical_percent' => 20,
            'pf_enabled' => 'Yes', 'pf_percent' => 10, 'bonus_percent' => 10];

        $r = PayrollSetupController::salaryBreakdown(20000, true, $s);

        $this->assertEqualsWithDelta(10000, $r['house_rent'], 0.001);
        $this->assertEqualsWithDelta(6000, $r['compensation'], 0.001);
        $this->assertEqualsWithDelta(4000, $r['medical_allowance'], 0.001);
        $this->assertEqualsWithDelta(40000, $r['gross_salary'], 0.001);
        $this->assertEqualsWithDelta(2000, $r['pf_amount'], 0.001);
        $this->assertEqualsWithDelta(4000, $r['bonus_amount'], 0.001);
        $this->assertEqualsWithDelta(38000, $r['net_salary'], 0.001);

        $this->assertEqualsWithDelta(0, PayrollSetupController::salaryBreakdown(20000, false, $s)['bonus_amount'], 0.001);
    }

    public function test_salary_breakdown_contractual_without_pf(): void
    {
        $s = (object) ['salary_structure' => 'Contractual', 'house_rent_percent' => 50, 'compensation_percent' => 30, 'medical_percent' => 20,
            'pf_enabled' => 'No', 'pf_percent' => 10, 'bonus_percent' => 0];

        $r = PayrollSetupController::salaryBreakdown(25000, true, $s);

        $this->assertEqualsWithDelta(0, $r['house_rent'], 0.001);
        $this->assertEqualsWithDelta(25000, $r['gross_salary'], 0.001);
        $this->assertEqualsWithDelta(0, $r['pf_amount'], 0.001);
        $this->assertEqualsWithDelta(25000, $r['net_salary'], 0.001);
    }
}
