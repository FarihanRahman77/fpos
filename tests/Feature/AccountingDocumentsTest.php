<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AccountingReportController;
use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\AccountingHelpers;

/**
 * Expense, bill (+ payment), journal and amount transfer: each saved through its endpoint, then checked in its own
 * tables and in the general ledger the statements read (AccountingReportController::gl / balances / profitLossFigures).
 */
class AccountingDocumentsTest extends AppTestCase
{
    use AccountingHelpers;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->day = now()->subDays(3)->toDateString();
        $this->signIn('superadmin', 'main');
        $this->fundCash(self::FUND);   // the drawer the documents pay from (its opening balance)
    }

    private const FUND = 100000;

    public function test_expense_posts_dr_expense_cr_cash_and_shows_in_opex(): void
    {
        $this->postExpense($this->day, 300)->assertOk()->assertJson(['status' => true]);

        $expense = DB::table('expenses')->latest('id')->first();
        $this->assertSame($this->branch('main'), (int) $expense->branch_id, 'branch_id comes from the @fpos_branch trigger');
        $this->assertEquals(300, (float) $expense->total_amount);
        $this->assertSame($this->coa('cash-main'), (int) $expense->payment_account_id);
        $this->assertDatabaseHas('expense_details', ['expense_id' => $expense->id, 'expense_account_id' => $this->coa('rent-expense'), 'amount' => 300, 'deleted' => 'No']);
        $this->assertDatabaseHas('payments', ['expense_id' => $expense->id, 'payment_type' => 'Expense', 'credit' => 300, 'deleted' => 'No']);

        $acc = $this->gl()['acc'];
        $this->assertEquals(300, $this->acc($acc, 'rent-expense'));
        $this->assertEquals(self::FUND - 300, $this->acc($acc, 'cash-main'));

        self::resetAppStatics();
        $pl = AccountingReportController::profitLossFigures($this->day, $this->day, AccountingReportController::allBranches());
        $this->assertEquals(300, $pl['opex']['a:' . $this->coa('rent-expense')] ?? null);
        $this->assertEquals(300, $pl['opexTotal']);
        $this->assertEquals(-300, $pl['net']);
    }

    public function test_expense_delete_reverses_the_ledger(): void
    {
        $this->postExpense($this->day, 120)->assertOk();
        $id = $this->latestId('expenses');

        $this->ajaxPost(route('admin.expense.delete'), ['id' => $id])->assertOk()->assertJson(['status' => true]);

        $this->assertDatabaseHas('expenses', ['id' => $id, 'deleted' => 'Yes']);
        $this->assertDatabaseHas('payments', ['expense_id' => $id, 'deleted' => 'Yes']);
        $acc = $this->gl()['acc'];
        $this->assertEquals(0, $this->acc($acc, 'rent-expense'));
        $this->assertEquals(self::FUND, $this->acc($acc, 'cash-main'));
    }

    public function test_expense_validation_rejects_negative_amount_and_wrong_group(): void
    {
        $r = $this->postExpense($this->day, -5)->assertStatus(422);
        $this->assertSame('Amount must be greater than zero.', $r->json('message'));

        // a ledger that does not sit under the chosen group
        $r = $this->ajaxPost(route('admin.expense.store'), [
            'expense_date' => $this->day, 'payment_group_id' => $this->coa('cash'), 'payment_account_id' => $this->coa('bank-account'),
            'details' => [['expense_group_id' => $this->coa('operating-expenses'), 'expense_account_id' => $this->coa('rent-expense'), 'amount' => 10]],
        ])->assertStatus(422);
        $this->assertSame('Please select a valid account of the selected payment group.', $r->json('message'));
        $this->assertNoSqlLeak($r);
        $this->assertSame(0, DB::table('expenses')->count());
    }

    public function test_bill_raises_supplier_payable_and_payment_settles_it(): void
    {
        $r = $this->postBill($this->day, [['utilities-expense', 2, 150], ['rent-expense', 1, 200]], 50)->assertOk()->assertJson(['status' => true]);
        $billId = (int) $r->json('id');

        $bill = DB::table('bills')->find($billId);
        $this->assertEquals(500, (float) $bill->subtotal);
        $this->assertEquals(450, (float) $bill->grand_total);
        $this->assertEquals(450, (float) $bill->due_amount);
        $this->assertSame('Unpaid', $bill->payment_status);
        $this->assertDatabaseHas('payments', ['bill_id' => $billId, 'payment_type' => 'Bill', 'debit' => 450, 'party_id' => $this->ids['supplier']]);

        // expense lines net of the discount (factor 0.9), supplier owes 450 in Accounts Payable
        $b = $this->balancesAt();
        $this->assertEquals(270, $this->acc($b['acc'], 'utilities-expense'));
        $this->assertEquals(180, $this->acc($b['acc'], 'rent-expense'));
        $this->assertEquals(-450, $this->acc($b['acc'], 'accounts-payable'));
        $this->assertEmpty($b['suspense']);

        // partial payment from cash
        $this->postBillPayment($billId, $this->day, 200)->assertOk()->assertJson(['status' => true]);
        $bill = DB::table('bills')->find($billId);
        $this->assertEquals(200, (float) $bill->paid_amount);
        $this->assertEquals(250, (float) $bill->due_amount);
        $this->assertSame('Partial', $bill->payment_status);
        $this->assertDatabaseHas('bill_payments', ['bill_id' => $billId, 'amount' => 200, 'payment_account_id' => $this->coa('cash-main')]);

        // more than the due is refused
        $r = $this->postBillPayment($billId, $this->day, 300)->assertStatus(422);
        $this->assertStringContainsString('more than the bill due', $r->json('message'));

        $this->postBillPayment($billId, $this->day, 250)->assertOk();
        $bill = DB::table('bills')->find($billId);
        $this->assertEquals(0, (float) $bill->due_amount);
        $this->assertSame('Paid', $bill->payment_status);

        $b = $this->balancesAt();
        $this->assertEquals(0, $this->acc($b['acc'], 'accounts-payable'));
        $this->assertEquals(self::FUND - 450, $this->acc($b['acc'], 'cash-main'));
        $this->assertEqualsWithDelta(0, array_sum($b['acc']), 0.01);
    }

    public function test_bill_with_payments_cannot_be_deleted(): void
    {
        $billId = (int) $this->postBill($this->day, [['rent-expense', 1, 100]])->json('id');
        $this->postBillPayment($billId, $this->day, 40)->assertOk();

        $r = $this->ajaxPost(route('admin.bill.delete'), ['id' => $billId])->assertStatus(422);
        $this->assertSame('This bill has payments. Delete its payments first.', $r->json('message'));
        $this->assertDatabaseHas('bills', ['id' => $billId, 'deleted' => 'No']);
    }

    public function test_balanced_journal_posts_both_sides(): void
    {
        $this->postJournal($this->day, [
            ['rent-expense', 'operating-expenses', 500, 0],
            ['owner-capital', 'owner-s-equity', 0, 500],
        ])->assertOk()->assertJson(['status' => true]);

        $j = DB::table('journals')->latest('id')->first();
        $this->assertSame($this->branch('main'), (int) $j->branch_id);
        $this->assertSame(2, DB::table('journal_details')->where('journal_id', $j->id)->count());
        $this->assertEquals(500, (float) DB::table('journal_details')->where('journal_id', $j->id)->sum('debit'));
        $this->assertEquals(500, (float) DB::table('journal_details')->where('journal_id', $j->id)->sum('credit'));

        $acc = $this->gl()['acc'];
        $this->assertEquals(500, $this->acc($acc, 'rent-expense'));
        $this->assertEquals(-500, $this->acc($acc, 'owner-capital'));
    }

    public function test_unbalanced_journal_is_refused(): void
    {
        $r = $this->postJournal($this->day, [
            ['rent-expense', 'operating-expenses', 500, 0],
            ['owner-capital', 'owner-s-equity', 0, 400],
        ])->assertStatus(422)->assertJson(['status' => false]);

        $this->assertSame('Total debit (500.00) must equal total credit (400.00).', $r->json('message'));
        $this->assertSame(0, DB::table('journals')->count());
        $this->assertSame(0, DB::table('journal_details')->count());
    }

    public function test_journal_line_with_both_debit_and_credit_is_refused(): void
    {
        $r = $this->postJournal($this->day, [
            ['rent-expense', 'operating-expenses', 100, 100],
            ['owner-capital', 'owner-s-equity', 0, 0],
        ])->assertStatus(422);
        $this->assertSame('Enter either a debit or a credit, not both.', $r->json('message'));
        $this->assertSame(0, DB::table('journals')->count());
    }

    public function test_amount_transfer_moves_cash_to_bank(): void
    {
        $this->postTransfer($this->day, 750)->assertOk()->assertJson(['status' => true]);

        $this->assertDatabaseHas('internal_amount_transfers', [
            'from_account_id' => $this->coa('cash-main'), 'to_account_id' => $this->coa('bank-account'), 'amount' => 750,
            'branch_id' => $this->branch('main'), 'deleted' => 'No',
        ]);

        $acc = $this->gl()['acc'];
        $this->assertEquals(self::FUND - 750, $this->acc($acc, 'cash-main'));
        $this->assertEquals(750, $this->acc($acc, 'bank-account'));

        // no profit or loss from moving money
        self::resetAppStatics();
        $pl = AccountingReportController::profitLossFigures($this->day, $this->day, AccountingReportController::allBranches());
        $this->assertEquals(0, $pl['net']);
    }

    public function test_amount_transfer_to_the_same_account_is_refused(): void
    {
        $r = $this->postTransfer($this->day, 10, 'cash-main', 'cash', 'cash-main', 'cash')->assertStatus(422);
        $this->assertSame('From and To accounts must be different.', $r->json('message'));
        $this->assertSame(0, DB::table('internal_amount_transfers')->count());
    }
}
