<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Payroll fixtures for tests extending Tests\AppTestCase (uses SeedsApp ids / coa()).
 * Employees are inserted directly (the employee wizard is heavy); payroll settings point at the seeded ledgers.
 */
trait PayrollHelpers
{
    protected function seedPayrollSettings(array $extra = []): void
    {
        DB::table('payroll_settings')->insert($extra + [
            'salary_structure' => 'Contractual', 'house_rent_percent' => 50, 'compensation_percent' => 30, 'medical_percent' => 20,
            'pf_enabled' => 'Yes', 'pf_percent' => 10, 'bonus_percent' => 0,
            'salary_expense_account_id' => $this->coa('salary-expense'), 'pf_account_id' => $this->coa('pf-payable'),
            'salary_payment_account_id' => $this->coa('cash-main'), 'salary_payable_account_id' => $this->coa('salary-payable'),
            'loan_account_id' => $this->coa('employee-loans'), 'absence_basis' => 'Gross',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A pay grade with one step (Scale structure data; the run reads it for grade_step). */
    protected function seedGradeStep(float $basic = 15500): int
    {
        $dept = DB::table('departments')->insertGetId(['name' => 'Sales', 'code' => 'SAL', 'status' => 'Active', 'deleted' => 'No', 'created_date' => now()]);
        $this->ids['department'] = $dept;
        $grade = DB::table('pay_grades')->insertGetId(['name' => 'Grade 5', 'increment_percent' => 5, 'status' => 'Active', 'deleted' => 'No', 'created_date' => now()]);

        return $this->ids['step'] = DB::table('pay_steps')->insertGetId([
            'pay_grade_id' => $grade, 'department_id' => $dept, 'step_no' => 1, 'basic_salary' => $basic,
            'status' => 'Active', 'deleted' => 'No', 'created_date' => now(),
        ]);
    }

    /** Employee with an explicit branch (default main) and salary; also a salary-history row (PF ledger reads it). */
    protected function makeEmployee(string $code, string $name, float $gross, float $pf, array $extra = [], string $branch = 'main'): int
    {
        $basic = round($gross / 2, 2);
        $id = DB::table('employees')->insertGetId($extra + [
            'branch_id' => $this->branch($branch), 'employee_code' => $code, 'name' => $name, 'email' => strtolower($code) . '@example.test',
            'designation' => 'Sales Officer', 'department' => 'Sales', 'department_id' => $this->ids['department'] ?? null,
            'employment_type' => 'Permanent', 'joining_date' => '2025-01-01', 'step_id' => $this->ids['step'] ?? null,
            'basic_salary' => $basic, 'house_rent' => round($basic * 0.5, 2), 'compensation' => round($basic * 0.3, 2),
            'medical_allowance' => round($gross - $basic - round($basic * 0.5, 2) - round($basic * 0.3, 2), 2),
            'gross_salary' => $gross, 'pf_amount' => $pf, 'net_salary' => $gross - $pf, 'salary_payment_mode' => 'Cash',
            'status' => 'Active', 'deleted' => 'No', 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employee_salary_histories')->insert([
            'employee_id' => $id, 'effective_date' => $extra['joining_date'] ?? '2025-01-01', 'reason' => 'Joining',
            'salary_structure' => 'Contractual', 'basic_salary' => $basic, 'gross_salary' => $gross, 'pf_amount' => $pf,
            'net_salary' => $gross - $pf, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    protected function runPayload(string $month, array $extra = []): array
    {
        return $extra + [
            'month' => $month, 'run_date' => $month . '-28',
            'expense_account_id' => $this->coa('salary-expense'), 'payable_account_id' => $this->coa('salary-payable'),
            'pf_account_id' => $this->coa('pf-payable'), 'loan_account_id' => $this->coa('employee-loans'),
        ];
    }

    protected function balanceOf(string $slug): float
    {
        return (float) DB::table('chart_of_accounts')->where('id', $this->coa($slug))->value('current_balance');
    }
}
