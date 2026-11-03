<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\PayrollHelpers;

/** Regression tests for payroll defects fixed in this project. */
class PayrollRegressionTest extends AppTestCase
{
    use PayrollHelpers;

    private int $emp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('superadmin', 'main');
        $this->fundCash(100000);   // loans and salary are paid from the main drawer (its opening balance)
        $this->seedPayrollSettings();
        $this->seedGradeStep();
        $this->emp = $this->makeEmployee('EMP-010', 'Carol Das', 30000, 1500);
    }

    /** The PF ledger report used a column that does not exist; it must build (page, data, PDF) without SQL errors. */
    public function test_regression_pf_ledger_report_has_no_column_error(): void
    {
        $this->ajaxPost(route('admin.payroll-runs.store'), $this->runPayload('2026-10'))->assertOk();

        $this->get(route('admin.reports.pf-ledger', ['employee_id' => $this->emp]))->assertOk();

        $data = $this->ajaxGet(route('admin.reports.pf-ledger.data', ['employee_id' => $this->emp, 'from' => '2026-09', 'to' => '2026-10']));
        $data->assertOk()->assertJson(['status' => true]);
        $this->assertNoSqlLeak($data);
        $this->assertStringContainsString('1,500.00', $data->json('html'), 'October PF deducted in the run shows in the ledger');
        $this->assertSame('1,500.00', $data->json('summary.closing'), 'balance counts deducted PF only');

        $pdf = $this->get(route('admin.reports.pf-ledger.pdf', ['employee_id' => $this->emp]));
        $pdf->assertOk();
        $this->assertNoSqlLeak($pdf);

        $this->ajaxGet(route('admin.reports.pf-ledger.data'))->assertStatus(422)->assertJson(['message' => 'Select the employee.']);
    }

    public function test_regression_employee_with_running_loan_cannot_be_deleted(): void
    {
        $this->ajaxPost(route('admin.employee-loans.store'), [
            'employee_id' => $this->emp, 'loan_type' => 'Advance Salary', 'amount' => 3000, 'issue_date' => '2026-10-05',
            'payment_account_id' => $this->coa('cash-main'), 'loan_account_id' => $this->coa('employee-loans'),
            'installments' => 3, 'start_month' => '2026-11',
        ])->assertOk();

        $res = $this->ajaxPost(route('admin.employee.delete'), ['id' => $this->emp]);

        $res->assertStatus(422)->assertJson(['status' => false]);
        $this->assertStringContainsString('still owes 3,000.00', $res->json('message'));
        $this->assertDatabaseHas('employees', ['id' => $this->emp, 'deleted' => 'No']);
    }

    public function test_employee_with_unpaid_processed_salary_cannot_be_deleted(): void
    {
        $this->ajaxPost(route('admin.payroll-runs.store'), $this->runPayload('2026-10'))->assertOk();

        $res = $this->ajaxPost(route('admin.employee.delete'), ['id' => $this->emp]);

        $res->assertStatus(422);
        $this->assertStringContainsString('still unpaid', $res->json('message'));
        $this->assertDatabaseHas('employees', ['id' => $this->emp, 'deleted' => 'No']);
    }

    public function test_employee_without_loan_is_soft_deleted(): void
    {
        $this->ajaxPost(route('admin.employee.delete'), ['id' => $this->emp])
            ->assertOk()->assertJson(['status' => true, 'message' => 'Employee deleted.']);

        $row = DB::table('employees')->where('id', $this->emp)->first();
        $this->assertSame('Yes', $row->deleted);
        $this->assertSame('Inactive', $row->status);
        $this->assertNotNull($row->deleted_datetime);
    }

    /** Superadmin signed in to Main sees Branch Two's employee but may not delete it there (403 + branch message). */
    public function test_employee_of_another_branch_cannot_be_deleted(): void
    {
        $other = $this->makeEmployee('EMP-020', 'Dina Two', 10000, 0, [], 'two');

        $this->ajaxPost(route('admin.employee.delete'), ['id' => $other])
            ->assertForbidden()->assertJsonFragment(['message' => 'This record belongs to Branch Two. Sign in to Branch Two to change it.']);
        $this->assertDatabaseHas('employees', ['id' => $other, 'deleted' => 'No']);
    }
}
