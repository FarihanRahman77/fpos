<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\PayrollHelpers;

/**
 * Payroll end to end in the main branch, month 2026-10 (31 days):
 *   Alice: gross 31,000, PF 1,550, a 6,000 loan in 3 installments of 2,000 from 2026-10  → net 27,450.00
 *   Bob:   gross 20,000, PF 1,000, joined 2026-10-16 (16 of 31 days employed)
 *          not employed 15 days = 20000/31*15 = 9,677.42; PF 1000*16/31 = 516.13      → net  9,806.45
 * Run totals: gross 51,000, deductions 9,677.42, PF 2,066.13, loan 2,000, net 37,256.45;
 * posting Dr salary expense 41,322.58 / Cr salary payable 37,256.45, PF payable 2,066.13, employee loans 2,000.
 */
class PayrollWorkflowTest extends AppTestCase
{
    use PayrollHelpers;

    private int $alice;
    private int $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('superadmin', 'main');
        $this->fundCash(100000);   // loans and salary are paid from the main drawer (its opening balance)
        $this->seedPayrollSettings();
        $this->seedGradeStep();
        $this->alice = $this->makeEmployee('EMP-001', 'Alice Rahman', 31000, 1550);
        $this->bob = $this->makeEmployee('EMP-002', 'Bob Karim', 20000, 1000, ['joining_date' => '2026-10-16']);
    }

    private function issueLoan(): void
    {
        $this->ajaxPost(route('admin.employee-loans.store'), [
            'employee_id' => $this->alice, 'loan_type' => 'Loan', 'amount' => 6000, 'issue_date' => '2026-10-05',
            'payment_account_id' => $this->coa('cash-main'), 'loan_account_id' => $this->coa('employee-loans'),
            'installments' => 3, 'start_month' => '2026-10',
        ])->assertOk()->assertJson(['status' => true]);
    }

    private function process(): object
    {
        $this->ajaxPost(route('admin.payroll-runs.store'), $this->runPayload('2026-10'))
            ->assertOk()->assertJson(['status' => true]);

        return DB::table('payroll_runs')->where('month', '2026-10-01')->where('run_status', 'Posted')->first();
    }

    private function item(object $run, int $employeeId): object
    {
        return DB::table('payroll_run_items')->where('payroll_run_id', $run->id)->where('employee_id', $employeeId)->first();
    }

    public function test_loan_is_issued_with_installments_and_posted(): void
    {
        $this->issueLoan();

        $loan = DB::table('employee_loans')->where('employee_id', $this->alice)->first();
        $this->assertSame('Running', $loan->loan_status);
        $this->assertEquals(2000, (float) $loan->installment_amount);
        $this->assertSame('2026-10-01', $loan->start_month);
        $this->assertSame($this->branch('main'), (int) $loan->branch_id, 'branch_id filled by the trigger from @fpos_branch');
        $this->assertDatabaseHas('employee_loan_transactions', ['employee_loan_id' => $loan->id, 'txn_type' => 'Disbursement', 'amount' => 6000]);
        $this->assertEquals(6000, $this->balanceOf('employee-loans'));
        $this->assertEquals(-6000, $this->balanceOf('cash-main'));
    }

    public function test_process_month_freezes_month_lines_recovers_loan_and_posts(): void
    {
        $this->issueLoan();
        $live = PayrollRunController::monthLines(Carbon::create(2026, 10, 1));

        $run = $this->process();

        $this->assertNotNull($run);
        $this->assertSame($this->branch('main'), (int) $run->branch_id);
        $this->assertSame(2, (int) $run->employee_count);
        $this->assertEquals(51000, (float) $run->total_gross);
        $this->assertEquals(9677.42, (float) $run->total_deductions);
        $this->assertEquals(2066.13, (float) $run->total_pf);
        $this->assertEquals(2000, (float) $run->total_loan);
        $this->assertEquals(37256.45, (float) $run->total_net);
        $this->assertSame($this->coa('pf-payable'), (int) $run->pf_account_id);
        $this->assertSame($this->coa('employee-loans'), (int) $run->loan_account_id);

        $a = $this->item($run, $this->alice);
        $b = $this->item($run, $this->bob);
        $this->assertEquals(27450, (float) $a->net_payable);
        $this->assertEquals(1550, (float) $a->pf_amount);
        $this->assertEquals(2000, (float) $a->loan_deduction);
        $this->assertEquals(9806.45, (float) $b->net_payable);
        $this->assertEquals(516.13, (float) $b->pf_amount);
        $this->assertEquals(9677.42, (float) $b->deductions);
        $this->assertStringContainsString('Not employed (15 days)', $b->deductions_detail);
        // frozen lines equal the live monthLines() figures
        foreach ([$a, $b] as $it) {
            $this->assertEquals($live[$it->employee_id]['net_payable'], (float) $it->net_payable);
            $this->assertEquals($live[$it->employee_id]['pf_amount'], (float) $it->pf_amount);
            $this->assertEquals($live[$it->employee_id]['loan_deduction'], (float) $it->loan_deduction);
        }

        $loan = DB::table('employee_loans')->where('employee_id', $this->alice)->first();
        $this->assertEquals(2000, (float) $loan->recovered);
        $this->assertSame('Running', $loan->loan_status);
        $this->assertDatabaseHas('employee_loan_transactions', ['employee_loan_id' => $loan->id, 'txn_type' => 'Salary Deduction', 'amount' => 2000, 'payroll_run_id' => $run->id, 'txn_status' => 'Posted']);

        $this->assertEquals(41322.58, $this->balanceOf('salary-expense'));
        $this->assertEquals(37256.45, $this->balanceOf('salary-payable'));
        $this->assertEquals(2066.13, $this->balanceOf('pf-payable'));
        $this->assertEquals(4000, $this->balanceOf('employee-loans'));

        // processed employees drop off: a second run of the month is refused
        $this->ajaxPost(route('admin.payroll-runs.store'), $this->runPayload('2026-10'))
            ->assertStatus(422)->assertJson(['status' => false]);
        $this->assertSame(1, DB::table('payroll_runs')->where('month', '2026-10-01')->count());
    }

    public function test_process_requires_ledgers(): void
    {
        $this->ajaxPost(route('admin.payroll-runs.store'), ['month' => '2026-10', 'run_date' => '2026-10-28'])
            ->assertStatus(422)->assertJson(['status' => false, 'message' => 'Select the salary expense ledger.']);

        // PF is deducted, so a PF ledger is required
        $this->ajaxPost(route('admin.payroll-runs.store'), $this->runPayload('2026-10', ['pf_account_id' => null]))
            ->assertStatus(422)->assertJsonFragment(['status' => false]);
        $this->assertSame(0, DB::table('payroll_runs')->count());
    }

    public function test_partial_then_full_salary_payment(): void
    {
        $this->issueLoan();
        $run = $this->process();
        $a = $this->item($run, $this->alice);
        $b = $this->item($run, $this->bob);

        // 1st payment: part of Alice, all of Bob
        $this->ajaxPost(route('admin.salary-payments.store'), [
            'payment_date' => '2026-10-30', 'payment_account_id' => $this->coa('cash-main'),
            'items' => [$a->id => 10000, $b->id => 9806.45],
        ])->assertOk()->assertJson(['status' => true]);

        $p1 = DB::table('salary_payments')->where('payroll_run_id', $run->id)->first();
        $this->assertEquals(19806.45, (float) $p1->total_paid);
        $this->assertSame(2, (int) $p1->employee_count);
        $this->assertSame($this->branch('main'), (int) $p1->branch_id);
        $this->assertSame(2, DB::table('salary_payment_details')->where('salary_payment_id', $p1->id)->count());
        $this->assertEquals(10000, (float) $this->item($run, $this->alice)->paid_amount);
        $this->assertEquals(19806.45, (float) DB::table('payroll_runs')->where('id', $run->id)->value('total_paid'));
        $this->assertEquals(17450.00, round($this->balanceOf('salary-payable'), 2));

        // more than the due is refused, nothing changes
        $this->ajaxPost(route('admin.salary-payments.store'), [
            'payment_date' => '2026-10-31', 'payment_account_id' => $this->coa('cash-main'), 'items' => [$a->id => 17450.01],
        ])->assertStatus(422)->assertJsonFragment(['message' => 'Alice Rahman: only 17,450.00 is due.']);
        $this->assertSame(1, DB::table('salary_payments')->count());

        // 2nd payment completes Alice
        $this->ajaxPost(route('admin.salary-payments.store'), [
            'payment_date' => '2026-10-31', 'payment_account_id' => $this->coa('cash-main'), 'items' => [$a->id => 17450],
        ])->assertOk();

        $this->assertEquals(27450, (float) $this->item($run, $this->alice)->paid_amount);
        $this->assertEquals(37256.45, (float) DB::table('payroll_runs')->where('id', $run->id)->value('total_paid'));
        $this->assertEquals(0, $this->balanceOf('salary-payable'));
        $this->assertEquals(-6000 - 37256.45, $this->balanceOf('cash-main'));
        $this->assertSame([], PayrollRunController::unpaidMonths(), 'nothing left unpaid');

        // the run cannot be reversed while it has payments
        $this->ajaxPost(route('admin.payroll-runs.reverse', $run->id), ['reason' => 'Mistake'])
            ->assertStatus(422)->assertJsonFragment(['status' => false]);
        $this->assertSame('Posted', DB::table('payroll_runs')->where('id', $run->id)->value('run_status'));
    }

    public function test_payment_from_another_branch_cash_ledger_is_refused(): void
    {
        $run = $this->process();
        $a = $this->item($run, $this->alice);

        $this->ajaxPost(route('admin.salary-payments.store'), [
            'payment_date' => '2026-10-30', 'payment_account_id' => $this->coa('cash-two'), 'items' => [$a->id => 100],
        ])->assertStatus(422)->assertJson(['status' => false, 'message' => 'Select a cash / bank account of this branch.']);
        $this->assertSame(0, DB::table('salary_payments')->count());
    }

    public function test_cancelled_payment_makes_salary_due_again(): void
    {
        $run = $this->process();
        $a = $this->item($run, $this->alice);
        $this->ajaxPost(route('admin.salary-payments.store'), [
            'payment_date' => '2026-10-30', 'payment_account_id' => $this->coa('cash-main'), 'items' => [$a->id => 5000],
        ])->assertOk();
        $pay = DB::table('salary_payments')->first();

        $this->ajaxPost(route('admin.salary-payments.cancel', $pay->id), ['reason' => 'Wrong amount'])->assertOk();

        $this->assertSame('Cancelled', DB::table('salary_payments')->where('id', $pay->id)->value('payment_status'));
        $this->assertEquals(0, (float) $this->item($run, $this->alice)->paid_amount);
        $this->assertEquals(0, $this->balanceOf('cash-main'));
    }

    public function test_reverse_run_without_payments_restores_loan_and_postings(): void
    {
        $this->issueLoan();
        $run = $this->process();

        $this->ajaxPost(route('admin.payroll-runs.reverse', $run->id), [])->assertStatus(422); // reason required
        $this->ajaxPost(route('admin.payroll-runs.reverse', $run->id), ['reason' => 'Wrong month'])
            ->assertOk()->assertJson(['status' => true]);

        $this->assertSame('Reversed', DB::table('payroll_runs')->where('id', $run->id)->value('run_status'));
        $loan = DB::table('employee_loans')->where('employee_id', $this->alice)->first();
        $this->assertEquals(0, (float) $loan->recovered);
        $this->assertSame('Running', $loan->loan_status);
        $this->assertSame('Reversed', DB::table('employee_loan_transactions')->where('payroll_run_id', $run->id)->value('txn_status'));
        $this->assertEquals(0, $this->balanceOf('salary-expense'));
        $this->assertEquals(0, $this->balanceOf('salary-payable'));
        $this->assertEquals(0, $this->balanceOf('pf-payable'));
        $this->assertEquals(6000, $this->balanceOf('employee-loans'));

        // the employees can be processed again
        $again = $this->process();
        $this->assertNotEquals($run->id, $again->id);
        $this->assertEquals(37256.45, (float) $again->total_net);
    }

    public function test_payslip_pdf_and_list(): void
    {
        $this->issueLoan();
        $run = $this->process();
        $a = $this->item($run, $this->alice);

        $list = $this->ajaxGet(route('admin.payslips.list', ['month' => '2026-10']))->assertOk()->json();
        $this->assertCount(2, $list['data']);
        $row = collect($list['data'])->firstWhere('id', $a->id);
        $this->assertSame('Alice Rahman', $row['name']);
        $this->assertEquals(27450, $row['net']);
        $this->assertEquals(27450, $row['due']);
        $this->assertSame($run->run_no, $row['run_no']);

        $pdf = $this->get(route('admin.payslips.pdf', $a->id));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get(route('admin.payslips.pdf', 999999))->assertNotFound();
    }

    /** A user permitted only in Branch Two cannot open a Main Branch payslip by id, nor see it listed. */
    public function test_payslip_of_another_branch_is_not_found_for_single_branch_user(): void
    {
        $run = $this->process();
        $a = $this->item($run, $this->alice);
        $hr2 = $this->makeUser('hr.two@example.test', 'HR Two', 'hr', 'two', ['two']);

        $this->signIn(User::find($hr2), 'two');
        $this->get(route('admin.payslips.pdf', $a->id))->assertNotFound();
        $this->assertCount(0, $this->ajaxGet(route('admin.payslips.list', ['month' => '2026-10']))->json('data'));
    }

    /**
     * Multi-branch rule: a user permitted in several branches, signed in to Two, sees Main's processed salary
     * (combined read) but cannot pay or reverse it (changes only in the signed-in branch).
     */
    public function test_multi_branch_user_sees_but_cannot_pay_other_branch_salary(): void
    {
        $run = $this->process();
        $a = $this->item($run, $this->alice);

        $this->signIn('superadmin', 'two');
        $this->assertCount(2, $this->ajaxGet(route('admin.payslips.list', ['month' => '2026-10']))->json('data'));

        $this->ajaxPost(route('admin.salary-payments.store'), [
            'payment_date' => '2026-10-30', 'payment_account_id' => $this->coa('cash-two'), 'items' => [$a->id => 100],
        ])->assertStatus(422);
        $this->assertSame(0, DB::table('salary_payments')->count());
        $this->assertEquals(0, (float) $this->item($run, $this->alice)->paid_amount);

        $this->ajaxPost(route('admin.payroll-runs.reverse', $run->id), ['reason' => 'x'])->assertForbidden();
        $this->assertSame('Posted', DB::table('payroll_runs')->where('id', $run->id)->value('run_status'));
    }

    /**
     * A run is saved to the signed-in branch, so it must contain only that branch's employees, even for a user who
     * sees several branches (the superadmin signed in to Branch Two, with Main's Alice and Bob visible).
     */
    public function test_processing_in_a_branch_takes_only_that_branch_employees(): void
    {
        $dina = $this->makeEmployee('EMP-020', 'Dina Two', 12000, 0, [], 'two');
        $this->signIn('superadmin', 'two');

        $this->ajaxPost(route('admin.payroll-runs.store'), $this->runPayload('2026-10'))->assertOk();

        $run = DB::table('payroll_runs')->where('month', '2026-10-01')->first();
        $this->assertSame($this->branch('two'), (int) $run->branch_id);
        $this->assertSame([$dina], DB::table('payroll_run_items')->where('payroll_run_id', $run->id)->pluck('employee_id')->map(fn ($v) => (int) $v)->all(),
            'a Branch Two run must not freeze Main Branch employees');
        $this->assertEquals(12000, (float) $run->total_net);
    }

    public function test_hr_user_can_open_payroll_runs(): void
    {
        $run = $this->process();

        $this->signIn('hr', 'main');
        $res = $this->ajaxGet(route('admin.payroll-runs.index', ['month' => '2026-10']))->assertOk()->assertJson(['status' => true]);
        $this->assertSame($run->run_no, $res->json('data.0.run_no'));
    }

    public function test_cashier_cannot_process_payroll(): void
    {
        $this->signIn('cashier', 'two');

        $this->ajaxPost(route('admin.payroll-runs.store'), $this->runPayload('2026-10'))
            ->assertForbidden()->assertJson(['status' => false]);
        $this->assertSame(0, DB::table('payroll_runs')->count());

        $this->get(route('admin.payroll-runs.index'))->assertForbidden();
    }
}
