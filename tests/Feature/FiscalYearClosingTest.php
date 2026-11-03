<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\AccountingHelpers;

/**
 * Year-end closing: a closed year moves its profit to retained earnings, and BlockClosedPeriod / closedDateMessage()
 * refuse any save, edit or delete dated inside it until the year is reopened.
 */
class FiscalYearClosingTest extends AppTestCase
{
    use AccountingHelpers;

    private int $yearId;
    private int $oldExpenseId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('superadmin', 'main');
        $this->fundCash(100000);   // the drawer the expenses pay from (its opening balance)

        // an expense inside the year, saved while it is still open
        $this->postExpense('2025-06-15', 100)->assertOk();
        $this->oldExpenseId = $this->latestId('expenses');

        $this->ajaxPost(route('admin.accounting.fiscal-years.store'), ['name' => 'FY 2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31'])
            ->assertOk()->assertJson(['status' => true]);
        $this->yearId = (int) DB::table('fiscal_years')->where('name', 'FY 2025')->value('id');
    }

    private function closeYear()
    {
        return $this->ajaxPost(route('admin.accounting.fiscal-years.close', $this->yearId), ['retained_earnings_account_id' => $this->coa('retained-earnings')]);
    }

    public function test_closing_moves_the_year_result_to_retained_earnings(): void
    {
        $this->closeYear()->assertOk()->assertJson(['status' => true]);

        $year = DB::table('fiscal_years')->find($this->yearId);
        $this->assertSame('Closed', $year->status);
        $this->assertEquals(-100, (float) $year->net_profit);
        $this->assertSame($this->coa('retained-earnings'), (int) $year->retained_earnings_account_id);
        $this->assertGreaterThan(0, DB::table('fiscal_year_balances')->where('fiscal_year_id', $this->yearId)->count());

        // after the year end: rent expense is closed into retained earnings (a loss = debit)
        $after = $this->balancesAt('2026-01-15');
        $this->assertEquals(0, $this->acc($after['acc'], 'rent-expense'));
        $this->assertEquals(100, $this->acc($after['acc'], 'retained-earnings'));
        $this->assertEqualsWithDelta(0, array_sum($after['acc']), 0.01);
        // at the year end itself the year is still shown before closing
        $this->assertEquals(100, $this->acc($this->balancesAt('2025-12-31')['acc'], 'rent-expense'));
    }

    public function test_back_dated_expense_in_a_closed_year_is_refused(): void
    {
        $this->closeYear()->assertOk();
        $before = DB::table('expenses')->count();

        $r = $this->postExpense('2025-07-01', 40)->assertStatus(422)->assertJson(['status' => false]);
        $this->assertStringContainsString('The books are closed for the year that includes 2025-07-01', $r->json('message'));
        $this->assertArrayHasKey('expense_date', $r->json('errors'));
        $this->assertSame($before, DB::table('expenses')->count());
        $this->assertSame(0, DB::table('expenses')->where('expense_date', '2025-07-01')->count());
    }

    public function test_back_dated_sale_in_a_closed_year_is_refused_and_writes_nothing(): void
    {
        $this->addStock($this->ids['product.plain'], 10, 'main', null, null, '2025-01-01 00:00:00');
        $this->closeYear()->assertOk();
        $stockRows = DB::table('stocks')->count();

        $r = $this->postSale('2025-11-30', 1, 80, 80)->assertStatus(422);
        $this->assertStringContainsString('The books are closed', $r->json('message'));
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(0, DB::table('payments')->whereNotNull('sale_id')->count());
        $this->assertSame($stockRows, DB::table('stocks')->count());
    }

    public function test_deleting_a_document_dated_in_a_closed_year_is_refused(): void
    {
        $this->closeYear()->assertOk();

        $r = $this->ajaxPost(route('admin.expense.delete'), ['id' => $this->oldExpenseId])->assertStatus(422);
        $this->assertStringContainsString('cannot be changed or deleted', $r->json('message'));
        $this->assertDatabaseHas('expenses', ['id' => $this->oldExpenseId, 'deleted' => 'No']);
        $this->assertDatabaseHas('payments', ['expense_id' => $this->oldExpenseId, 'deleted' => 'No']);
    }

    /** Moving a closed-year document to an open date is still an edit of the closed year. */
    public function test_editing_a_closed_year_document_to_a_new_date_is_refused(): void
    {
        $this->closeYear()->assertOk();

        $r = $this->ajaxPost(route('admin.expense.store'), [
            'id' => $this->oldExpenseId, 'expense_date' => now()->toDateString(),
            'payment_group_id' => $this->coa('cash'), 'payment_account_id' => $this->coa('cash-main'),
            'details' => [['expense_group_id' => $this->coa('operating-expenses'), 'expense_account_id' => $this->coa('rent-expense'), 'amount' => 5]],
        ])->assertStatus(422);
        $this->assertStringContainsString('cannot be changed or deleted', $r->json('message'));
        $this->assertDatabaseHas('expenses', ['id' => $this->oldExpenseId, 'expense_date' => '2025-06-15', 'total_amount' => 100]);
    }

    public function test_saves_after_the_closed_year_still_work(): void
    {
        $this->closeYear()->assertOk();

        $this->postExpense(now()->toDateString(), 25)->assertOk()->assertJson(['status' => true]);
        $this->assertDatabaseHas('expenses', ['expense_date' => now()->toDateString(), 'total_amount' => 25]);
    }

    public function test_reopening_lifts_the_block(): void
    {
        $this->closeYear()->assertOk();
        $this->ajaxPost(route('admin.accounting.fiscal-years.reopen', $this->yearId))->assertOk()->assertJson(['status' => true]);

        $this->assertDatabaseHas('fiscal_years', ['id' => $this->yearId, 'status' => 'Open', 'net_profit' => null]);
        $this->assertSame(0, DB::table('fiscal_year_balances')->where('fiscal_year_id', $this->yearId)->count());

        $this->postExpense('2025-07-01', 40)->assertOk()->assertJson(['status' => true]);
        $this->ajaxPost(route('admin.expense.delete'), ['id' => $this->oldExpenseId])->assertOk();
        $this->assertDatabaseHas('expenses', ['id' => $this->oldExpenseId, 'deleted' => 'Yes']);
        $this->assertEquals(40, $this->acc($this->balancesAt('2026-01-15')['acc'], 'rent-expense'));
    }

    public function test_a_year_that_has_not_ended_cannot_be_closed(): void
    {
        $this->ajaxPost(route('admin.accounting.fiscal-years.store'), ['name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])->assertOk();
        $id = (int) DB::table('fiscal_years')->where('name', 'FY 2026')->value('id');

        $r = $this->ajaxPost(route('admin.accounting.fiscal-years.close', $id), ['retained_earnings_account_id' => $this->coa('retained-earnings')])
            ->assertStatus(422);
        $this->assertStringContainsString('has not ended yet', $r->json('message'));
        $this->assertDatabaseHas('fiscal_years', ['id' => $id, 'status' => 'Open']);
    }

    public function test_overlapping_year_and_closed_year_delete_are_refused(): void
    {
        $r = $this->ajaxPost(route('admin.accounting.fiscal-years.store'), ['name' => 'FY X', 'start_date' => '2025-06-01', 'end_date' => '2026-05-31'])
            ->assertStatus(422);
        $this->assertSame('These dates overlap FY 2025.', $r->json('message'));

        $this->closeYear()->assertOk();
        $this->ajaxPost(route('admin.accounting.fiscal-years.delete', $this->yearId))->assertStatus(422)
            ->assertJson(['message' => 'Reopen the year before deleting it.']);
        $this->assertDatabaseHas('fiscal_years', ['id' => $this->yearId, 'deleted' => 'No', 'status' => 'Closed']);
    }

    public function test_closing_requires_an_equity_ledger(): void
    {
        $r = $this->ajaxPost(route('admin.accounting.fiscal-years.close', $this->yearId), ['retained_earnings_account_id' => $this->coa('rent-expense')])
            ->assertStatus(422);
        $this->assertSame('Select an Equity ledger (3rd layer).', $r->json('message'));
        $this->assertDatabaseHas('fiscal_years', ['id' => $this->yearId, 'status' => 'Open']);
    }
}
