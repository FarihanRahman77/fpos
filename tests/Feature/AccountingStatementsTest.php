<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\AccountingHelpers;

/**
 * Trial Balance and Balance Sheet after a mixed set of documents posted through the endpoints:
 * opening stock, a part-paid purchase, a part-paid sale, a customer receipt, an expense, a journal and a transfer.
 */
class AccountingStatementsTest extends AppTestCase
{
    use AccountingHelpers;

    private function postMixedDocuments(): void
    {
        $d = fn (int $ago) => now()->subDays($ago)->toDateString();
        $this->addStock($this->ids['product.plain'], 100, 'main', null, null, now()->subDays(20)->toDateTimeString());
        $this->signIn('superadmin', 'main');
        $this->fundCash(1000);   // the drawer the purchase / expense / transfer pay from (its opening balance)

        $this->postPurchase($d(10), 20, 50, 400)->assertOk()->assertJson(['status' => true]);   // 1000, paid 400
        $this->postSale($d(8), 10, 80, 300)->assertOk()->assertJson(['status' => true]);        // 800, paid 300
        $this->postReceipt($d(6), 200)->assertOk()->assertJson(['status' => true]);
        $this->postExpense($d(5), 100)->assertOk()->assertJson(['status' => true]);
        $this->postJournal($d(4), [['rent-expense', 'operating-expenses', 50, 0], ['owner-capital', 'owner-s-equity', 0, 50]])
            ->assertOk()->assertJson(['status' => true]);
        $this->postTransfer($d(3), 100)->assertOk()->assertJson(['status' => true]);
    }

    public function test_trial_balance_debits_equal_credits_after_mixed_documents(): void
    {
        $this->postMixedDocuments();

        // the documents landed where expected
        $this->assertSame(1, DB::table('purchases')->where('deleted', 'No')->count());
        $this->assertSame(1, DB::table('sales')->where('deleted', 'No')->count());
        $this->assertEquals(500, (float) DB::table('sales')->value('due_amount'));

        $r = $this->ajaxGet(route('admin.accounting.trial-balance.data', ['as_at' => now()->toDateString()]))
            ->assertOk()->assertJson(['status' => true]);
        $summary = $r->json('summary');
        $debit = $this->summaryValue($summary, 'Total Debit');
        $credit = $this->summaryValue($summary, 'Total Credit');
        $this->assertGreaterThan(0, $debit);
        $this->assertEqualsWithDelta($debit, $credit, 0.01, 'Trial balance debits must equal credits');
        $this->assertEqualsWithDelta(0, $this->summaryValue($summary, 'Difference'), 0.001);
        $this->assertStringNotContainsString('Suspense', $r->json('html'));

        // the ledger itself: balanced, nothing in suspense, and the key balances
        $b = $this->balancesAt();
        $this->assertEqualsWithDelta(0, array_sum($b['acc']), 0.01);
        $this->assertEmpty($b['suspense'], 'Unexpected suspense: ' . json_encode($b['suspense']));
        $this->assertEquals(300, $this->acc($b['acc'], 'accounts-receivable'));     // 800 - 300 - 200
        $this->assertEquals(-600, $this->acc($b['acc'], 'accounts-payable'));       // 1000 - 400
        $this->assertEquals(-800, $this->acc($b['acc'], 'sales-revenue'));
        $this->assertEquals(150, $this->acc($b['acc'], 'rent-expense'));            // expense 100 + journal 50
        $this->assertEquals(100, $this->acc($b['acc'], 'bank-account'));
        $this->assertEquals(1000 - 400 + 300 + 200 - 100 - 100, $this->acc($b['acc'], 'cash-main'));
        $this->assertEquals(110 * 50, $this->acc($b['acc'], 'inventory'));         // 100 + 20 - 10 at cost 50
    }

    public function test_balance_sheet_balances_after_mixed_documents(): void
    {
        $this->postMixedDocuments();

        $r = $this->ajaxGet(route('admin.accounting.balance-sheet.data', ['as_at' => now()->toDateString()]))
            ->assertOk()->assertJson(['status' => true]);
        $summary = $r->json('summary');
        $assets = $this->summaryValue($summary, 'Total assets');
        $liabilities = $this->summaryValue($summary, 'Total liabilities');
        $equity = $this->summaryValue($summary, 'Total equity');
        $profit = $this->summaryValue($summary, 'Current year profit');

        $this->assertEqualsWithDelta($assets, $liabilities + $equity, 0.01, 'Assets must equal liabilities + equity');
        // profit = sales 800 - COGS 10 x 50 - rent 150
        $this->assertEqualsWithDelta(800 - 500 - 150, $profit, 0.01);
        $this->assertEqualsWithDelta(600, $liabilities, 0.01);
        $this->assertStringContainsString('Assets = Liabilities + Equity', $r->json('html'));
    }

    public function test_profit_and_loss_matches_the_ledger(): void
    {
        $this->postMixedDocuments();

        $r = $this->ajaxGet(route('admin.accounting.profit-loss.data', ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->assertJson(['status' => true]);
        $summary = $r->json('summary');
        $this->assertEqualsWithDelta(800, $this->summaryValue($summary, 'Net revenue'), 0.01);
        $this->assertEqualsWithDelta(300, $this->summaryValue($summary, 'Gross profit'), 0.01);
        $this->assertEqualsWithDelta(150, $this->summaryValue($summary, 'Operating expenses'), 0.01);
        $this->assertEqualsWithDelta(150, $this->summaryValue($summary, 'Net profit'), 0.01);
    }

    public function test_statements_are_main_branch_only(): void
    {
        $this->signIn('manager', 'two');

        foreach (['admin.accounting.trial-balance.data', 'admin.accounting.balance-sheet.data'] as $route) {
            $r = $this->ajaxGet(route($route))->assertStatus(403)->assertJson(['status' => false]);
            $this->assertStringContainsString('only available when signed in to Main Branch', $r->json('message'));
        }
        $this->get(route('admin.accounting.trial-balance'))->assertStatus(403)->assertSee('Main Branch');
    }

    public function test_cashier_without_statement_permission_is_refused(): void
    {
        $this->signIn('cashier_main', 'main');

        $this->ajaxGet(route('admin.accounting.trial-balance.data'))->assertStatus(403)->assertJson(['status' => false]);
    }

    public function test_statement_rejects_bad_dates_without_leaking_sql(): void
    {
        $this->signIn('superadmin', 'main');

        $r = $this->ajaxGet(route('admin.accounting.profit-loss.data', ['from' => '2026-05-10', 'to' => '2026-05-01']))->assertStatus(422);
        $this->assertSame('End date cannot be before the start date.', $r->json('message'));
        $r = $this->ajaxGet(route('admin.accounting.trial-balance.data', ['as_at' => "2026-01-01' OR 1=1 --"]))->assertStatus(422);
        $this->assertNoSqlLeak($r);
    }
}
