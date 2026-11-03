<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AccountingReportController;
use App\Http\Controllers\Admin\ChartOfAccountController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\AppTestCase;
use Tests\Concerns\AccountingHelpers;
use Tests\Concerns\PayrollHelpers;

/**
 * No overdrawn cash drawer: money out of a Cash-method ledger beyond what the working branch holds in it is refused
 * (422 "Not enough cash in <ledger>: available ..., needed ..."), on every money-out path; bank ledgers may go
 * negative. Also: a deadlocked save is retried, and the Chart of Accounts shows the Trial Balance figures.
 *
 * Every test starts with Cash Main (main branch) holding 1,000.00: a customer's advance received in cash.
 */
class CashDrawerGuardTest extends AppTestCase
{
    use AccountingHelpers, PayrollHelpers;

    private const SHORT = 'Not enough cash in Cash Main: available ৳1,000.00, needed ৳1,500.00.';

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('superadmin', 'main');
        $this->day = now()->toDateString();
        $this->postReceipt($this->day, 1000)->assertOk();
        $this->assertSame(1000.0, $this->drawer());
    }

    private function drawer(): float
    {
        self::resetAppStatics();

        return ReportController::cashAvailable($this->coa('cash-main'));
    }

    private function assertShort($response, string $message = self::SHORT): void
    {
        $response->assertStatus(422)->assertJson(['status' => false, 'message' => $message]);
        $this->assertNoSqlLeak($response);
    }

    private function payment(string $type, int $party, float $amount, string $account = 'cash-main', string $method = 'cash')
    {
        return $this->ajaxPost(route('admin.payment.store'), [
            'type' => $type, 'payment_date' => $this->day, 'party_id' => $party, 'amount' => $amount,
            'payment_method' => $this->coa($method), 'payment_method_account' => $this->coa($account),
        ]);
    }

    public function test_cash_available_reads_the_cash_book_and_bank_is_unlimited(): void
    {
        $this->assertNull(ReportController::cashAvailable($this->coa('bank-account')));
        // another branch's drawer is empty for the main branch
        $this->assertSame(0.0, ReportController::cashAvailable($this->coa('cash-two')));
    }

    public function test_expense_refused_beyond_the_drawer_allowed_from_bank_exact_and_unchanged_edit(): void
    {
        $this->assertShort($this->postExpense($this->day, 1500));
        $this->assertSame(0, DB::table('expenses')->count());

        $this->postExpense($this->day, 1500, 'rent-expense', 'bank', 'bank-account')->assertOk()->assertJson(['status' => true]);
        $this->postExpense($this->day, 1000)->assertOk()->assertJson(['status' => true]);   // the exact balance
        $this->assertSame(0.0, $this->drawer());

        $id = (int) DB::table('expenses')->where('payment_account_id', $this->coa('cash-main'))->value('id');
        $edit = fn (float $amount) => $this->ajaxPost(route('admin.expense.store'), [
            'id' => $id, 'expense_date' => $this->day, 'payment_group_id' => $this->coa('cash'), 'payment_account_id' => $this->coa('cash-main'),
            'details' => [['expense_group_id' => $this->coa('operating-expenses'), 'expense_account_id' => $this->coa('rent-expense'), 'amount' => $amount]],
        ]);
        $edit(1000)->assertOk();                                                                // same amount, drawer at 0
        $this->assertShort($edit(1000.01), 'Not enough cash in Cash Main: available ৳1,000.00, needed ৳1,000.01.');
        $edit(400)->assertOk();
        $this->assertSame(600.0, $this->drawer());
    }

    public function test_bill_payment(): void
    {
        $billId = (int) $this->postBill($this->day, [['utilities-expense', 1, 5000]])->assertOk()->json('id');

        $this->assertShort($this->postBillPayment($billId, $this->day, 1500));
        $this->postBillPayment($billId, $this->day, 1500, 'bank', 'bank-account')->assertOk()->assertJson(['status' => true]);
        $this->postBillPayment($billId, $this->day, 1000)->assertOk();
        $this->assertSame(0.0, $this->drawer());
    }

    public function test_supplier_payment_and_customer_credit_paid_back(): void
    {
        $this->assertShort($this->payment('Payment', $this->ids['supplier'], 1500));
        $this->payment('Payment', $this->ids['supplier'], 1500, 'bank-account', 'bank')->assertOk()->assertJson(['status' => true]);

        // the customer has 1,000 credit + 5,000 received in the bank
        $this->payment('Payment Receive', $this->ids['customer'], 5000, 'bank-account', 'bank')->assertOk();
        $this->assertShort($this->payment('Payment', $this->ids['customer'], 1500));
        $this->payment('Payment', $this->ids['customer'], 1500, 'bank-account', 'bank')->assertOk()->assertJson(['status' => true]);
        $this->payment('Payment', $this->ids['customer'], 1000)->assertOk();
        $this->assertSame(0.0, $this->drawer());
    }

    public function test_amount_transfer_from_side_only(): void
    {
        $this->assertShort($this->postTransfer($this->day, 1500));
        $this->postTransfer($this->day, 1500, 'bank-account', 'bank', 'cash-main', 'cash')->assertOk();   // bank may go negative
        $this->assertSame(2500.0, $this->drawer());
        $this->postTransfer($this->day, 2500)->assertOk();
        $this->assertSame(0.0, $this->drawer());
    }

    public function test_journal_credit_on_a_cash_ledger(): void
    {
        $this->assertShort($this->postJournal($this->day, [['rent-expense', 'operating-expenses', 1500, 0], ['cash-main', 'cash', 0, 1500]]));
        $this->postJournal($this->day, [['rent-expense', 'operating-expenses', 1500, 0], ['bank-account', 'bank', 0, 1500]])->assertOk();
        // debit and credit on the same drawer net out: only 500 leaves it
        $this->postJournal($this->day, [['cash-main', 'cash', 1000, 0], ['rent-expense', 'operating-expenses', 500, 0], ['cash-main', 'cash', 0, 1500]])->assertOk();
        $this->assertSame(500.0, $this->drawer());
    }

    public function test_purchase_payment(): void
    {
        $this->assertShort($this->postPurchase($this->day, 10, 200, 1500));
        $this->assertSame(0, DB::table('purchases')->count());

        $this->ajaxPost(route('purchases.store'), [
            'supplier_id' => $this->ids['supplier'], 'purchase_date' => $this->day, 'discount_type' => 'Amount', 'discount_value' => 0,
            'paid_amount' => 1500, 'payment_method' => $this->coa('bank'), 'payment_method_account' => $this->coa('bank-account'),
            'items' => [['product_id' => $this->ids['product.plain'], 'product_variant_id' => null, 'qty' => 10, 'purchase_price' => 200, 'discount_type' => 'Amount', 'discount_value' => 0]],
        ])->assertOk()->assertJson(['status' => true]);
        $this->postPurchase($this->day, 10, 200, 1000)->assertOk();
        $this->assertSame(0.0, $this->drawer());
    }

    public function test_sale_return_cash_refund(): void
    {
        $this->addStock($this->ids['product.plain'], 50);
        $saleId = (int) $this->postSale($this->day, 30, 100, 0)->assertOk()->json('sale_id');   // 3,000 on credit
        $item = DB::table('sale_items')->where('sale_id', $saleId)->first();
        $return = fn (string $account, string $method) => $this->ajaxPost(route('sales.return.store'), [
            'sale_id' => $saleId, 'datetime' => now()->format('Y-m-d H:i:s'), 'items' => [['sale_item_id' => $item->id, 'qty' => 15]],
            'refund_mode' => 'Cash', 'refund_amount' => 1500, 'payment_method' => $this->coa($method), 'payment_method_account' => $this->coa($account),
        ]);

        $this->assertShort($return('cash-main', 'cash'));
        $this->assertSame(0, DB::table('sale_returns')->count());
        $return('bank-account', 'bank')->assertOk()->assertJson(['status' => true]);
    }

    public function test_salary_payment_and_loan_disbursement(): void
    {
        $this->seedPayrollSettings();
        $this->seedGradeStep();
        $emp = $this->makeEmployee('EMP-001', 'Alice Rahman', 31000, 1550);

        $loan = fn (string $account) => $this->ajaxPost(route('admin.employee-loans.store'), [
            'employee_id' => $emp, 'loan_type' => 'Advance Salary', 'amount' => 1500, 'issue_date' => $this->day,
            'payment_account_id' => $this->coa($account), 'loan_account_id' => $this->coa('employee-loans'), 'installments' => 1,
            'start_month' => now()->format('Y-m'),
        ]);
        $this->assertShort($loan('cash-main'));
        $loan('bank-account')->assertOk()->assertJson(['status' => true]);

        $month = now()->format('Y-m');
        $this->ajaxPost(route('admin.payroll-runs.store'), ['run_date' => $this->day] + $this->runPayload($month))->assertOk();
        $item = DB::table('payroll_run_items')->where('employee_id', $emp)->first();
        $pay = fn (string $account, float $amount) => $this->ajaxPost(route('admin.salary-payments.store'), [
            'payment_date' => $this->day, 'payment_account_id' => $this->coa($account), 'items' => [$item->id => $amount],
        ]);
        $this->assertShort($pay('cash-main', 1500));
        $pay('bank-account', 1500)->assertOk()->assertJson(['status' => true]);
        $pay('cash-main', 1000)->assertOk();
        $this->assertSame(0.0, $this->drawer());
    }

    public function test_a_deadlocked_save_is_retried_and_posts_once(): void
    {
        $thrown = 0;
        DB::listen(function ($q) use (&$thrown) {
            if ($thrown === 0 && str_starts_with(strtolower($q->sql), 'insert into `expenses`')) {
                $thrown++;
                $pdo = new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');
                $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];
                throw new QueryException('mysql', $q->sql, [], $pdo);
            }
        });

        $this->postExpense($this->day, 300)->assertOk()->assertJson(['status' => true]);
        $this->assertSame(1, $thrown);
        $this->assertSame(1, DB::table('expenses')->count());
        $this->assertSame(1, DB::table('expense_details')->where('deleted', 'No')->count());
        $this->assertSame(700.0, $this->drawer());
    }

    public function test_retry_helper_gives_up_after_the_attempts_and_ignores_other_errors(): void
    {
        $deadlock = fn () => new QueryException('mysql', 'update x', [], new PDOException('Deadlock found when trying to get lock; try restarting transaction'));

        $calls = 0;
        try {
            Controller::retryOnDeadlock(function () use (&$calls, $deadlock) { $calls++; throw $deadlock(); });
            $this->fail('expected the deadlock to be thrown');
        } catch (QueryException $e) {
            $this->assertSame(Controller::SAVE_ATTEMPTS, $calls);
        }

        $calls = 0;
        try {
            Controller::retryOnDeadlock(function () use (&$calls) { $calls++; throw new \RuntimeException('other'); });
        } catch (\RuntimeException $e) {
            $this->assertSame(1, $calls);
        }
        $this->assertTrue(Controller::isConcurrencyError(new PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction')));
    }

    public function test_chart_of_accounts_shows_the_trial_balance_figures(): void
    {
        // money in several ledgers, including control ledgers the TB fills from party rows
        $this->postExpense($this->day, 250)->assertOk();
        $this->postTransfer($this->day, 4000, 'bank-account', 'bank', 'cash-main', 'cash')->assertOk();
        $this->postPurchase($this->day, 10, 200, 0)->assertOk();
        DB::table('chart_of_accounts')->where('id', $this->coa('bank-account'))->update(['current_balance' => 13033275.07]);   // stale, never shown

        $rows = $this->ajaxGet(route('admin.chart-of-accounts.index'))->assertOk()->json('data');
        $shown = [];
        foreach ($rows as $r) {
            preg_match('/>(\d+)</', $r[1], $code);
            $shown[$code[1]] = trim(strip_tags($r[8]));
        }

        self::resetAppStatics();
        $acc = AccountingReportController::balances($this->day, AccountingReportController::allBranches())['acc'];
        $ledgers = DB::table('chart_of_accounts')->where('layer_level', 3)->where('deleted', 'No')->get(['id', 'account_code']);
        foreach ($ledgers as $l) {
            $this->assertSame(ChartOfAccountController::balanceText((float) ($acc['a:' . $l->id] ?? 0)), $shown[$l->account_code], 'ledger ' . $l->account_code);
        }
        $this->assertSame('4,000.00 Cr', $shown['60201000']);              // City Bank: −4,000 (not 13,033,275.07)
        $this->assertSame('4,750.00 Dr', $shown['60101000']);              // Cash Main: 1,000 − 250 + 4,000
        $this->assertSame('2,000.00 Cr', $shown['20101000']);              // Accounts Payable from the purchase
        $this->assertSame('4,750.00 Dr', $shown['60100000']);              // the Cash group = its ledgers summed
        $this->assertSame('750.00 Dr', $shown['60000000']);                // Liquid Asset root = cash 4,750 − bank 4,000
    }
}
