<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AccountingReportController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SuspenseController;
use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\AccountingHelpers;
use Tests\Concerns\BranchHelpers;

/**
 * Investors and the Suspense module, with the owner's two examples:
 *  - an employee takes 5,000 for an office trip, spends 3,000, returns 2,000 → Paid 5,000, Adjustment 3,000, Return 2,000:
 *    outstanding 0, expense 3,000, cash −3,000;
 *  - an investor invests 500,000, withdraws 300,000, 250,000 is adjusted, 50,000 returned: outstanding 0,
 *    capital 500,000, 250,000 in the chosen ledger, cash +250,000.
 * Both reach the Cash & Bank Book, the daily cash closing and the statements (TB / BS / P&L stay balanced).
 */
class SuspenseTest extends AppTestCase
{
    use AccountingHelpers, BranchHelpers;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->day = now()->toDateString();
    }

    private function entry(string $personType, int $personId, string $type, float $amount, array $extra = [])
    {
        return $this->ajaxPost(route('admin.suspense.store'), $extra + [
            'person_type' => $personType, 'person_id' => $personId, 'txn_type' => $type, 'amount' => $amount, 'txn_date' => $this->day,
        ]);
    }

    private function investor(string $name = 'Owner One'): int
    {
        $this->ajaxPost(route('party.store'), ['type' => 'Investor', 'name' => $name, 'phone' => '01710000001', 'nid' => '1990-123',
            'share_percent' => 60, 'status' => 'Active'])->assertOk();

        return (int) DB::table('parties')->where('type', 'Investor')->where('name', $name)->value('id');
    }

    private function cashBook(): array
    {
        self::resetAppStatics();
        $ctx = (object) ['filters' => [], 'branches' => [$this->branch('main')]];

        return (new ReportController())->liquidAssetMovements($ctx, now()->startOfDay(), now()->endOfDay())
            ->groupBy('type')->map(fn ($g) => round($g->sum('in') - $g->sum('out'), 2))->all();
    }

    private function assertStatementsBalance(): void
    {
        $b = $this->balancesAt();
        $this->assertEqualsWithDelta(0, array_sum($b['acc']), 0.01, 'Trial balance must balance');
        $this->assertEmpty($b['suspense'], 'Unexpected unmatched documents: ' . json_encode($b['suspense']));

        $tb = $this->ajaxGet(route('admin.accounting.trial-balance.data', ['as_at' => $this->day]))->assertOk()->json('summary');
        $this->assertEqualsWithDelta($this->summaryValue($tb, 'Total Debit'), $this->summaryValue($tb, 'Total Credit'), 0.01);

        $bs = $this->ajaxGet(route('admin.accounting.balance-sheet.data', ['as_at' => $this->day]))->assertOk()->json('summary');
        $this->assertEqualsWithDelta($this->summaryValue($bs, 'Total assets'),
            $this->summaryValue($bs, 'Total liabilities') + $this->summaryValue($bs, 'Total equity'), 0.01, 'Assets must equal liabilities + equity');
    }

    private function closingHtml(): string
    {
        return (string) $this->ajaxGet('/admin/business-reports/cash-closing/data?date=' . $this->day)->assertOk()->json('html');
    }

    public function test_employee_office_trip_paid_adjusted_returned(): void
    {
        $this->signIn('superadmin', 'main');
        $this->fundCash(10000);
        $emp = $this->employee('main', 'E-1', 'Trip Taker');

        $this->entry('Employee', $emp, 'Paid', 5000, ['account_id' => $this->coa('cash-main'), 'purpose' => 'Office trip'])->assertOk()->assertJson(['status' => true]);
        $this->entry('Employee', $emp, 'Adjustment', 3000, ['adjust_account_id' => $this->coa('utilities-expense'), 'purpose' => 'Trip costs'])->assertOk();
        $this->entry('Employee', $emp, 'Return', 2000, ['account_id' => $this->coa('cash-main')])->assertOk();

        self::resetAppStatics();
        $t = SuspenseController::totals('Employee', $emp);
        $this->assertSame([5000.0, 2000.0, 3000.0, 0.0], [$t['paid'], $t['returned'], $t['adjusted'], $t['outstanding']]);
        $this->assertSame(['SUS-000001', 'SUS-000002', 'SUS-000003'], DB::table('suspense_transactions')->orderBy('id')->pluck('voucher_no')->all());
        $this->assertSame([$this->branch('main')], DB::table('suspense_transactions')->distinct()->pluck('branch_id')->map(fn ($v) => (int) $v)->all());

        // ledger: expense 3,000, suspense 0, cash −3,000 (from the 10,000 opening)
        $acc = $this->balancesAt()['acc'];
        $this->assertEquals(3000, $this->acc($acc, 'utilities-expense'));
        $this->assertEquals(7000, $this->acc($acc, 'cash-main'));
        $this->assertEquals(0, round($acc['a:' . SuspenseController::ledgers()['suspense']] ?? 0, 2));
        self::resetAppStatics();
        $this->assertSame(7000.0, ReportController::cashAvailable($this->coa('cash-main')));
        self::resetAppStatics();
        $pl = AccountingReportController::profitLossFigures($this->day, $this->day, AccountingReportController::allBranches());
        $this->assertEqualsWithDelta(3000, array_sum($pl['opex']), 0.001);

        // Cash & Bank Book and the daily cash closing
        $this->assertEquals(['Suspense Paid' => -5000.0, 'Suspense Return' => 2000.0], $this->cashBook());
        $html = $this->closingHtml();
        $this->assertStringContainsString('Suspense Paid (−)', $html);
        $this->assertStringContainsString('Suspense Return (+)', $html);

        $this->assertStatementsBalance();

        // statement: running balance back to 0
        $s = $this->ajaxGet(route('admin.suspense.statement', ['person_type' => 'Employee', 'person_id' => $emp]))->assertOk()->json('data');
        $this->assertSame([5000, 2000, 0], array_map(fn ($l) => (int) $l['balance'], $s['lines']));
        $this->get(route('admin.suspense.statement.pdf', ['person_type' => 'Employee', 'person_id' => $emp]))->assertOk();
        $this->get(route('admin.suspense.statement.excel', ['person_type' => 'Employee', 'person_id' => $emp]))->assertOk();
    }

    public function test_investor_invests_withdraws_adjusts_and_returns(): void
    {
        $this->signIn('superadmin', 'main');
        $inv = $this->investor();
        $this->assertStringStartsWith('INVR-', (string) DB::table('parties')->where('id', $inv)->value('code'));

        $this->entry('Investor', $inv, 'Investment', 500000, ['account_id' => $this->coa('cash-main')])->assertOk()->assertJson(['status' => true]);
        $this->entry('Investor', $inv, 'Withdraw', 300000, ['account_id' => $this->coa('cash-main')])->assertOk();
        $drawings = SuspenseController::ledgers()['drawings'];
        $this->assertNotNull($drawings, 'Owner\'s Drawings is added to the chart when missing');
        $this->entry('Investor', $inv, 'Adjustment', 250000, ['adjust_account_id' => $drawings, 'purpose' => 'Personal use'])->assertOk();
        $this->entry('Investor', $inv, 'Return', 50000, ['account_id' => $this->coa('cash-main')])->assertOk();

        self::resetAppStatics();
        $t = SuspenseController::totals('Investor', $inv);
        $this->assertEquals(0, $t['outstanding']);
        $this->assertEquals(500000, $t['capital']);
        $this->assertEquals(250000, $t['adjusted']);

        $acc = $this->balancesAt()['acc'];
        $this->assertEquals(-500000, $this->acc($acc, 'owner-capital'));            // credit
        $this->assertEquals(250000, round($acc['a:' . $drawings] ?? 0, 2));         // debit
        $this->assertEquals(250000, $this->acc($acc, 'cash-main'));
        $this->assertEquals(0, round($acc['a:' . SuspenseController::ledgers()['suspense']] ?? 0, 2));
        self::resetAppStatics();
        $pl = AccountingReportController::profitLossFigures($this->day, $this->day, AccountingReportController::allBranches());
        $this->assertEqualsWithDelta(0, array_sum($pl['opex']), 0.001, 'Drawings are not an expense');

        $this->assertEquals(['Investment' => 500000.0, 'Suspense Paid' => -300000.0, 'Suspense Return' => 50000.0], $this->cashBook());
        $html = $this->closingHtml();
        $this->assertStringContainsString('Investment (+)', $html);
        $this->assertStringContainsString('Suspense Paid (−)', $html);

        $this->assertStatementsBalance();

        // investor list: capital and outstanding; not a customer / supplier
        $row = collect($this->ajaxGet('/admin/parties/list/Investor')->assertOk()->json('data'))->firstWhere('id', $inv);
        $this->assertEquals(500000, $row['capital']);
        $this->assertEquals(0, $row['outstanding']);
        $this->assertFalse(collect($this->ajaxGet('/admin/parties/list/Customer')->json('data'))->contains('id', $inv));
        $this->ajaxPost(route('admin.payment.store'), ['type' => 'Payment', 'payment_date' => $this->day, 'party_id' => $inv, 'amount' => 10,
            'payment_method' => $this->coa('cash'), 'payment_method_account' => $this->coa('cash-main')])->assertStatus(422);
        $this->ajaxPost(route('party.delete'), ['id' => $inv])->assertStatus(422);

        // capital withdrawal (permanent) lowers capital, not the outstanding
        $this->entry('Investor', $inv, 'Capital Withdrawal', 100000, ['account_id' => $this->coa('cash-main')])->assertOk();
        self::resetAppStatics();
        $this->assertEquals(400000, SuspenseController::totals('Investor', $inv)['capital']);
        $this->assertEquals(-400000, $this->acc($this->balancesAt()['acc'], 'owner-capital'));
        $this->assertStatementsBalance();
    }

    public function test_opening_capital_counts_in_capital_and_statements(): void
    {
        $this->signIn('superadmin', 'main');
        $this->ajaxPost(route('party.store'), ['type' => 'Investor', 'name' => 'Old Owner', 'phone' => '01710000002', 'opening_capital' => 200000,
            'status' => 'Active'])->assertOk();
        $inv = (int) DB::table('parties')->where('name', 'Old Owner')->value('id');
        $this->assertEquals(0, (float) DB::table('parties')->where('id', $inv)->value('opening_balance'));

        self::resetAppStatics();
        $this->assertEquals(200000, SuspenseController::totals('Investor', $inv)['capital']);
        $this->assertEquals(-200000, $this->acc($this->balancesAt()['acc'], 'owner-capital'));
        $this->assertStatementsBalance();
    }

    public function test_refusals(): void
    {
        $this->signIn('manager', 'main');
        $this->fundCash(1000);
        $emp = $this->employee('main', 'E-2', 'Careful Clerk');
        $cash = ['account_id' => $this->coa('cash-main')];

        // nothing outstanding yet
        $this->entry('Employee', $emp, 'Return', 10, $cash)->assertStatus(422)->assertJson(['status' => false]);
        // cash drawer
        $r = $this->entry('Employee', $emp, 'Paid', 1500, $cash)->assertStatus(422);
        $this->assertStringContainsString('Not enough cash in Cash Main', (string) $r->json('message'));
        $this->assertSame(0, DB::table('suspense_transactions')->count());

        $this->entry('Employee', $emp, 'Paid', 800, $cash)->assertOk();
        $paid = $this->latestId('suspense_transactions');
        $r = $this->entry('Employee', $emp, 'Return', 800.01, $cash)->assertStatus(422);
        $this->assertStringContainsString('more than Careful Clerk\'s outstanding 800.00', (string) $r->json('message'));
        $this->entry('Employee', $emp, 'Adjustment', 900, ['adjust_account_id' => $this->coa('rent-expense')])->assertStatus(422);
        $this->entry('Employee', $emp, 'Adjustment', 500, ['adjust_account_id' => $this->coa('rent-expense')])->assertOk();
        // the payout cannot go while its adjustment stays
        $this->ajaxPost(route('admin.suspense.delete'), ['id' => $paid])->assertStatus(422);
        // types / ledgers that do not fit the person
        $this->entry('Employee', $emp, 'Investment', 100, $cash)->assertStatus(422);
        $this->entry('Employee', $emp, 'Adjustment', 100, ['adjust_account_id' => $this->coa('owner-capital')])->assertStatus(422);
        // another branch's cash drawer
        $this->entry('Employee', $emp, 'Return', 100, ['account_id' => $this->coa('cash-two')])->assertStatus(422);

        // other branch: visible, but changes are refused
        $this->signIn('manager', 'two');
        $r = $this->entry('Employee', $emp, 'Paid', 100, $cash + ['id' => $paid])->assertForbidden();
        $this->assertStringContainsString('Sign in to Main Branch', (string) $r->json('message'));
        $this->ajaxPost(route('admin.suspense.delete'), ['id' => $paid])->assertForbidden();
        $this->ajaxGet(route('admin.suspense.list'))->assertOk()->assertJsonCount(2, 'data');
        $this->assertNoSqlLeak($r);
    }

    public function test_edit_and_delete_restore_the_ledger(): void
    {
        $this->signIn('superadmin', 'main');
        $this->fundCash(10000);
        $emp = $this->employee('main', 'E-3', 'Editor');
        $before = $this->balancesAt()['acc'];

        $this->entry('Employee', $emp, 'Paid', 5000, ['account_id' => $this->coa('cash-main')])->assertOk();
        $id = $this->latestId('suspense_transactions');
        $this->entry('Employee', $emp, 'Paid', 4000, ['id' => $id, 'account_id' => $this->coa('bank-account')])->assertOk();
        $acc = $this->balancesAt()['acc'];
        $this->assertEquals(10000, $this->acc($acc, 'cash-main'));
        $this->assertEquals(-4000, $this->acc($acc, 'bank-account'));
        $this->assertEquals(-4000.0, (float) DB::table('chart_of_accounts')->where('id', $this->coa('bank-account'))->value('current_balance'));

        $this->ajaxPost(route('admin.suspense.delete'), ['id' => $id])->assertOk()->assertJson(['status' => true]);
        $after = array_filter($this->balancesAt()['acc'], fn ($v) => abs($v) >= 0.005);
        $this->assertEquals(array_filter($before, fn ($v) => abs($v) >= 0.005), $after);
        $this->assertEquals(0.0, (float) DB::table('chart_of_accounts')->where('id', $this->coa('bank-account'))->value('current_balance'));
        $this->assertSame('Yes', DB::table('suspense_transactions')->where('id', $id)->value('deleted'));
    }

    public function test_permissions(): void
    {
        $this->signIn('cashier', 'two');
        $this->ajaxGet(route('admin.suspense.list'))->assertForbidden();
        $this->ajaxGet(route('party.list', 'Investor'))->assertForbidden();
        $this->ajaxGet(route('party.list', 'Customer'))->assertOk();

        $this->signIn('accountant', 'main');
        $this->get(route('admin.suspense.index'))->assertOk()->assertSee('Suspense');
        $this->get(route('party.index', 'Investor'))->assertOk()->assertSee('Investors');
        $this->ajaxPost(route('party.store'), ['type' => 'Investor', 'name' => 'Nope', 'phone' => '01710000003', 'status' => 'Active'])->assertForbidden();
    }
}
