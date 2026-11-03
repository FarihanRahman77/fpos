<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;

/**
 * Daily cash closing chain (BusinessReportController saveClosing / clearClosing, cash_day_closings):
 * opening = last saved day's closing + net cash of the unsaved days in between (the books before the first save),
 * closing = opening + today; saving / clearing a day re-flows every later saved day.
 *
 * Fixture: Cash Main ledger opening balance 1,000; cash movements in the main branch
 *   1 Oct +500 (receipt), 2 Oct −200 (payment), 3 Oct +300, 4 Oct +100.
 */
class CashDayClosingChainTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('chart_of_accounts')->where('id', $this->coa('cash-main'))->update(['opening_balance' => 1000]);
        $this->signIn('superadmin', 'main');

        $this->cash('2026-10-01 10:00:00', 500);
        $this->cash('2026-10-02 10:00:00', -200);
        $this->cash('2026-10-03 10:00:00', 300);
        $this->cash('2026-10-04 10:00:00', 100);
    }

    /** A cash receipt (+) or a payment (−) on a cash ledger. */
    private function cash(string $when, float $amount, string $branch = 'main', string $ledger = 'cash-main'): void
    {
        static $n = 0;
        DB::table('payments')->insert([
            'branch_id' => $this->branch($branch), 'party_id' => $amount > 0 ? $this->ids['customer'] : $this->ids['supplier'],
            'party_type' => $amount > 0 ? 'Customer' : 'Supplier', 'code' => 'VO-CC' . (++$n) . random_int(1000, 9999),
            'payment_type' => $amount > 0 ? 'Payment Receive' : 'Payment', 'datetime' => $when,
            'debit' => $amount > 0 ? $amount : 0, 'credit' => $amount < 0 ? -$amount : 0,
            'payment_method' => 'Cash', 'payment_method_id' => $this->coa('cash'),
            'payment_method_account' => 'Cash', 'payment_method_account_id' => $this->coa($ledger),
            'status' => 'Active', 'deleted' => 'No', 'created_datetime' => now(),
        ]);
    }

    private function save(string $day)
    {
        return $this->ajaxPost(route('admin.business-reports.closing.save'), ['closing_date' => $day]);
    }

    private function row(string $day, string $branch = 'main'): ?object
    {
        return DB::table('cash_day_closings')->where('branch_id', $this->branch($branch))->where('closing_date', $day)->first();
    }

    private function assertDay(string $day, float $opening, float $today, float $closing, string $branch = 'main'): void
    {
        $r = $this->row($day, $branch);
        $this->assertNotNull($r, "No saved closing for $day");
        $this->assertEqualsWithDelta($opening, (float) $r->opening_balance, 0.001, "$day opening");
        $this->assertEqualsWithDelta($today, (float) $r->today_amount, 0.001, "$day today");
        $this->assertEqualsWithDelta($closing, (float) $r->closing_amount, 0.001, "$day closing");
    }

    public function test_first_saved_day_opens_from_the_books(): void
    {
        $this->save('2026-10-01')->assertOk()->assertJson(['status' => true]);

        $this->assertDay('2026-10-01', 1000, 500, 1500);
        $this->assertEquals($this->ids['user.superadmin'], $this->row('2026-10-01')->saved_by);
    }

    public function test_day_without_previous_save_opens_from_all_earlier_movements(): void
    {
        $this->save('2026-10-03')->assertOk();

        $this->assertDay('2026-10-03', 1300, 300, 1600);   // 1000 + 500 − 200
    }

    public function test_opening_is_last_saved_closing_plus_unsaved_days(): void
    {
        $this->save('2026-10-01')->assertOk();
        $this->save('2026-10-03')->assertOk();   // 2 Oct not saved: its −200 is added
        $this->save('2026-10-04')->assertOk();

        $this->assertDay('2026-10-01', 1000, 500, 1500);
        $this->assertDay('2026-10-03', 1300, 300, 1600);
        $this->assertDay('2026-10-04', 1600, 100, 1700);
    }

    public function test_resaving_an_earlier_day_reflows_later_days(): void
    {
        $this->save('2026-10-01')->assertOk();
        $this->save('2026-10-03')->assertOk();
        $this->save('2026-10-04')->assertOk();

        // a late receipt dated 1 Oct, then 1 Oct is saved again
        $this->cash('2026-10-01 18:00:00', 50);
        $this->save('2026-10-01')->assertOk();

        $this->assertDay('2026-10-01', 1000, 550, 1550);
        $this->assertDay('2026-10-03', 1350, 300, 1650);
        $this->assertDay('2026-10-04', 1650, 100, 1750);
    }

    public function test_clearing_a_day_reflows_the_days_after_it(): void
    {
        $this->save('2026-10-01')->assertOk();
        $this->save('2026-10-02')->assertOk();
        $this->save('2026-10-04')->assertOk();
        // the saved 2 Oct closing is overwritten by hand to prove the reflow recomputes from the books
        DB::table('cash_day_closings')->where('closing_date', '2026-10-02')->update(['closing_amount' => 9999]);
        $this->save('2026-10-03')->assertOk();
        $this->assertDay('2026-10-03', 9999, 300, 10299);

        $this->ajaxPost(route('admin.business-reports.closing.clear'), ['closing_date' => '2026-10-02'])->assertOk()->assertJson(['status' => true]);

        $this->assertNull($this->row('2026-10-02'));
        $this->assertDay('2026-10-03', 1300, 300, 1600);   // 1 Oct closing 1500 + unsaved 2 Oct −200
        $this->assertDay('2026-10-04', 1600, 100, 1700);
    }

    public function test_clearing_a_day_that_was_not_saved_is_404(): void
    {
        $this->ajaxPost(route('admin.business-reports.closing.clear'), ['closing_date' => '2026-10-02'])
            ->assertNotFound()->assertJson(['status' => false]);
    }

    public function test_future_day_cannot_be_closed(): void
    {
        $this->save(now()->addDay()->toDateString())->assertStatus(422)->assertJsonValidationErrors('closing_date');
        $this->assertSame(0, DB::table('cash_day_closings')->count());
    }

    public function test_bank_movements_do_not_change_the_cash_closing(): void
    {
        DB::table('payments')->insert([
            'branch_id' => $this->branch('main'), 'party_id' => $this->ids['customer'], 'party_type' => 'Customer', 'code' => 'VO-BANK1',
            'payment_type' => 'Payment Receive', 'datetime' => '2026-10-01 11:00:00', 'debit' => 7000, 'credit' => 0,
            'payment_method' => 'Bank', 'payment_method_id' => $this->coa('bank'), 'payment_method_account_id' => $this->coa('bank-account'),
            'status' => 'Active', 'deleted' => 'No',
        ]);

        $this->save('2026-10-01')->assertOk();

        $this->assertDay('2026-10-01', 1000, 500, 1500);
        $details = json_decode($this->row('2026-10-01')->details, true);
        $this->assertArrayHasKey('Bank', $details['others']);
    }

    public function test_each_branch_has_its_own_chain(): void
    {
        $this->cash('2026-10-01 09:00:00', 800, 'two', 'cash-two');

        $this->save('2026-10-01')->assertOk();
        $this->signIn('manager', 'two');   // branch manager may also clear a day (cash_closing.delete)
        $this->save('2026-10-01')->assertOk();

        $this->assertDay('2026-10-01', 1000, 500, 1500, 'main');
        $this->assertDay('2026-10-01', 0, 800, 800, 'two');   // Branch Two: no opening, only its own cash

        // clearing in Branch Two leaves the main branch's day alone
        $this->ajaxPost(route('admin.business-reports.closing.clear'), ['closing_date' => '2026-10-01'])->assertOk();
        $this->assertNull($this->row('2026-10-01', 'two'));
        $this->assertNotNull($this->row('2026-10-01', 'main'));
    }

    public function test_cash_closing_report_shows_the_saved_figures(): void
    {
        $this->save('2026-10-01')->assertOk();

        $res = $this->ajaxGet(route('admin.business-reports.data', ['report' => 'cash-closing', 'date' => '2026-10-02']))->assertOk();
        $res->assertJson(['status' => true]);
        $cards = $res->json('cards');
        $this->assertStringContainsString('1,500.00', $cards);   // opening of 2 Oct = 1 Oct closing
        $this->assertStringContainsString('1,300.00', $cards);   // closing 1500 − 200
    }
}
