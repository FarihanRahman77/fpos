<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\AccountingHelpers;

/**
 * Daily cash closing (Reports Center › cash-closing): one row per branch per day; opening = last saved day's closing
 * (+ unsaved days in between, or the books before the first save), closing = opening + today; saving or clearing a
 * day re-flows every later saved day.
 *
 * Fixture: Cash Main opening balance 1000; cash expenses D1 100, D2 50, D3 30.
 */
class CashDayClosingTest extends AppTestCase
{
    use AccountingHelpers;

    private string $d1;
    private string $d2;
    private string $d3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->d1 = now()->subDays(5)->toDateString();
        $this->d2 = now()->subDays(4)->toDateString();
        $this->d3 = now()->subDays(3)->toDateString();

        // set before the first request: the cash engine is built once per branch per process
        DB::table('chart_of_accounts')->where('id', $this->coa('cash-main'))->update(['opening_balance' => 1000]);
        $this->signIn('superadmin', 'main');

        $this->postExpense($this->d1, 100)->assertOk();
        $this->postExpense($this->d2, 50)->assertOk();
        $this->postExpense($this->d3, 30)->assertOk();
    }

    private function save(string $day)
    {
        return $this->ajaxPost(route('admin.business-reports.closing.save'), ['closing_date' => $day, 'remarks' => 'Counted']);
    }

    private function clear(string $day)
    {
        return $this->ajaxPost(route('admin.business-reports.closing.clear'), ['closing_date' => $day]);
    }

    /** [opening, today, closing] of the saved row. */
    private function row(string $day, string $branch = 'main'): ?array
    {
        $r = DB::table('cash_day_closings')->where('closing_date', $day)->where('branch_id', $this->branch($branch))->first();

        return $r ? [(float) $r->opening_balance, (float) $r->today_amount, (float) $r->closing_amount] : null;
    }

    public function test_first_saved_day_opens_from_the_books(): void
    {
        $r = $this->save($this->d2)->assertOk()->assertJson(['status' => true]);
        $this->assertStringContainsString('Closing amount 850.00', $r->json('message'));

        $this->assertSame([900.0, -50.0, 850.0], $this->row($this->d2));
        $saved = DB::table('cash_day_closings')->where('closing_date', $this->d2)->first();
        $this->assertSame($this->branch('main'), (int) $saved->branch_id);
        $this->assertSame($this->ids['user.superadmin'], (int) $saved->saved_by);
        $this->assertSame('Counted', $saved->remarks);
        $details = json_decode($saved->details, true);
        $expense = collect($details['cash'])->firstWhere('label', 'Expense (−)');
        $this->assertEquals(50, $expense['amount']);
    }

    public function test_next_day_opens_from_the_saved_closing_and_report_shows_it(): void
    {
        $this->save($this->d2)->assertOk();
        $this->save($this->d3)->assertOk();

        $this->assertSame([850.0, -30.0, 820.0], $this->row($this->d3));

        $r = $this->ajaxGet(route('admin.business-reports.data', ['report' => 'cash-closing', 'date' => $this->d3]))->assertOk()->assertJson(['status' => true]);
        $this->assertStringContainsString('850.00', $r->json('cards'));
        $this->assertStringContainsString('820.00', $r->json('cards'));
        $this->assertStringContainsString('Saved', $r->json('cards'));
    }

    public function test_unsaved_days_in_between_are_added_to_the_opening(): void
    {
        $this->save($this->d1)->assertOk();
        $this->save($this->d3)->assertOk();   // D2 not saved: 900 - 50

        $this->assertSame([1000.0, -100.0, 900.0], $this->row($this->d1));
        $this->assertSame([850.0, -30.0, 820.0], $this->row($this->d3));
    }

    public function test_regression_cash_closing_reflows_later_days(): void
    {
        $this->save($this->d1)->assertOk();
        $this->save($this->d2)->assertOk();
        $this->save($this->d3)->assertOk();
        $this->assertSame([850.0, -30.0, 820.0], $this->row($this->d3));

        // a late entry on D1, then D1 is saved again: D2 and D3 must follow
        $this->postExpense($this->d1, 40)->assertOk();
        $this->assertSame([1000.0, -100.0, 900.0], $this->row($this->d1), 'saved rows only change when a day is saved');

        $this->save($this->d1)->assertOk();
        $this->assertSame([1000.0, -140.0, 860.0], $this->row($this->d1));
        $this->assertSame([860.0, -50.0, 810.0], $this->row($this->d2));
        $this->assertSame([810.0, -30.0, 780.0], $this->row($this->d3));
    }

    public function test_clearing_a_day_reflows_the_later_days(): void
    {
        $this->save($this->d1)->assertOk();
        $this->save($this->d2)->assertOk();
        $this->save($this->d3)->assertOk();
        $this->postExpense($this->d1, 40)->assertOk();   // D1's saved closing (900) is now stale

        $this->clear($this->d1)->assertOk()->assertJson(['status' => true]);

        $this->assertNull($this->row($this->d1));
        // D2 now opens from the books before D2: 1000 - 140
        $this->assertSame([860.0, -50.0, 810.0], $this->row($this->d2));
        $this->assertSame([810.0, -30.0, 780.0], $this->row($this->d3));
    }

    public function test_clearing_a_day_that_was_not_saved_is_404(): void
    {
        $this->clear($this->d2)->assertStatus(404)->assertJson(['status' => false, 'message' => 'There is no saved report for that day.']);
    }

    public function test_saving_twice_updates_the_same_row(): void
    {
        $this->save($this->d2)->assertOk();
        $this->save($this->d2)->assertOk();

        $this->assertSame(1, DB::table('cash_day_closings')->where('closing_date', $this->d2)->count());
    }

    public function test_future_day_cannot_be_closed(): void
    {
        $r = $this->save(now()->addDay()->toDateString())->assertStatus(422);
        $this->assertSame('A day can be closed only once it has started — not a future date.', $r->json('message'));
        $this->assertSame(0, DB::table('cash_day_closings')->count());
    }

    public function test_closings_are_per_branch(): void
    {
        $this->save($this->d2)->assertOk();

        // Branch Two: its own cash ledger, its own closing row, no effect from Main's
        $this->signIn('superadmin', 'two');
        $this->fundCash(70, 'cash-two');   // a drawer never pays out more than it holds
        $this->postExpense($this->d2, 70, 'rent-expense', 'cash', 'cash-two')->assertOk();
        $this->save($this->d2)->assertOk();

        $this->assertSame([70.0, -70.0, 0.0], $this->row($this->d2, 'two'));
        $this->assertSame([900.0, -50.0, 850.0], $this->row($this->d2, 'main'));

        // clearing in Branch Two does not touch Main's row
        $this->clear($this->d2)->assertOk();
        $this->assertNull($this->row($this->d2, 'two'));
        $this->assertNotNull($this->row($this->d2, 'main'));
    }

    public function test_cashier_may_save_but_not_clear(): void
    {
        $this->signIn('cashier_main', 'main');

        $this->save($this->d2)->assertOk();
        $this->assertNotNull($this->row($this->d2));

        $this->clear($this->d2)->assertStatus(403)->assertJson(['status' => false]);
        $this->assertNotNull($this->row($this->d2));
    }
}
