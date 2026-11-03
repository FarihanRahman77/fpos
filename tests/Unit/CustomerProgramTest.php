<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\CustomerProgramController as CP;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\AppTestCase;

/** VAT, loyalty points and credit limits (CustomerProgramController rules used by SaleController::store). */
class CustomerProgramTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('superadmin', 'main');
    }

    public function test_vat_disabled_returns_zero(): void
    {
        $vat = CP::vat([['product_id' => $this->ids['product.plain'], 'total' => 1000]], 0);

        $this->assertSame(0.0, $vat['amount']);
        $this->assertSame([], $vat['lines']);
    }

    public function test_vat_added_on_top_uses_default_rate(): void
    {
        $this->setSettings(['vat_enabled' => '1', 'vat_inclusive' => '0', 'vat_rate' => '15']);

        $vat = CP::vat([['product_id' => $this->ids['product.plain'], 'total' => 1000]], 0);

        $this->assertFalse($vat['inclusive']);
        $this->assertEqualsWithDelta(150.00, $vat['amount'], 0.001);
        $this->assertEqualsWithDelta(15.0, $vat['lines'][0]['rate'], 0.001);
        $this->assertEqualsWithDelta(150.00, $vat['lines'][0]['vat'], 0.001);
    }

    public function test_vat_inclusive_extracts_vat_from_price(): void
    {
        $this->setSettings(['vat_enabled' => '1', 'vat_inclusive' => '1', 'vat_rate' => '15']);

        // 1150 including 15% → 150 VAT
        $vat = CP::vat([['product_id' => $this->ids['product.plain'], 'total' => 1150]], 0);

        $this->assertTrue($vat['inclusive']);
        $this->assertEqualsWithDelta(150.00, $vat['amount'], 0.001);
    }

    public function test_product_vat_percent_overrides_default_rate(): void
    {
        $this->setSettings(['vat_enabled' => '1', 'vat_inclusive' => '0', 'vat_rate' => '15']);
        DB::table('products')->where('id', $this->ids['product.variant'])->update(['vat_percent' => 5]);
        DB::table('products')->where('id', $this->ids['product.batch'])->update(['vat_percent' => 0]);

        $vat = CP::vat([
            ['product_id' => $this->ids['product.plain'], 'total' => 100],   // default 15% → 15
            ['product_id' => $this->ids['product.variant'], 'total' => 200], // 5% → 10
            ['product_id' => $this->ids['product.batch'], 'total' => 300],   // 0% (not null) → 0
        ], 0);

        $this->assertEqualsWithDelta(15.0, $vat['lines'][0]['vat'], 0.001);
        $this->assertEqualsWithDelta(5.0, $vat['lines'][1]['rate'], 0.001);
        $this->assertEqualsWithDelta(10.0, $vat['lines'][1]['vat'], 0.001);
        $this->assertEqualsWithDelta(0.0, $vat['lines'][2]['rate'], 0.001);
        $this->assertEqualsWithDelta(0.0, $vat['lines'][2]['vat'], 0.001);
        $this->assertEqualsWithDelta(25.0, $vat['amount'], 0.001);
    }

    public function test_sale_discount_is_spread_over_lines_before_vat(): void
    {
        $this->setSettings(['vat_enabled' => '1', 'vat_inclusive' => '0', 'vat_rate' => '10']);

        // lines 600 + 400 = 1000, sale discount 200 → each line keeps 80%: 480 and 320 → VAT 48 + 32
        $vat = CP::vat([
            ['product_id' => $this->ids['product.plain'], 'total' => 600],
            ['product_id' => $this->ids['product.variant'], 'total' => 400],
        ], 200);

        $this->assertEqualsWithDelta(48.0, $vat['lines'][0]['vat'], 0.001);
        $this->assertEqualsWithDelta(32.0, $vat['lines'][1]['vat'], 0.001);
        $this->assertEqualsWithDelta(80.0, $vat['amount'], 0.001);
    }

    public function test_discount_larger_than_subtotal_gives_zero_vat(): void
    {
        $this->setSettings(['vat_enabled' => '1', 'vat_rate' => '15']);

        $vat = CP::vat([['product_id' => $this->ids['product.plain'], 'total' => 100]], 150);

        $this->assertEqualsWithDelta(0.0, $vat['amount'], 0.001);
    }

    public function test_earn_points(): void
    {
        $this->assertSame(0.0, CP::earnPoints(1000)); // loyalty off by default

        $this->setSettings(['loyalty_enabled' => '1', 'loyalty_earn_amount' => '100', 'loyalty_earn_points' => '2']);

        $this->assertEqualsWithDelta(18.0, CP::earnPoints(999.99), 0.001); // floor(9.9999) * 2
        $this->assertEqualsWithDelta(0.0, CP::earnPoints(99), 0.001);
        $this->assertEqualsWithDelta(0.0, CP::earnPoints(-500), 0.001);
    }

    public function test_redeem_value_disabled_is_zero(): void
    {
        $this->assertSame([0.0, 0.0], CP::redeemValue($this->ids['customer'], 100, 1000));
    }

    public function test_redeem_value_point_value_and_cap(): void
    {
        $this->setSettings(['loyalty_enabled' => '1', 'loyalty_point_value' => '2', 'loyalty_min_redeem' => '10', 'loyalty_max_percent' => '50']);
        DB::table('parties')->where('id', $this->ids['customer'])->update(['loyalty_points' => 500]);

        // 100 points × 2 = 200 money, cap 50% of 1000 = 500 → full
        [$used, $money] = CP::redeemValue($this->ids['customer'], 100, 1000);
        $this->assertEqualsWithDelta(100.0, $used, 0.001);
        $this->assertEqualsWithDelta(200.0, $money, 0.001);

        // 400 points × 2 = 800, cap 50% of 600 = 300 → only 150 points used
        [$used, $money] = CP::redeemValue($this->ids['customer'], 400, 600);
        $this->assertEqualsWithDelta(150.0, $used, 0.001);
        $this->assertEqualsWithDelta(300.0, $money, 0.001);
    }

    public function test_redeem_more_than_balance_is_refused(): void
    {
        $this->setSettings(['loyalty_enabled' => '1', 'loyalty_min_redeem' => '10']);
        DB::table('parties')->where('id', $this->ids['customer'])->update(['loyalty_points' => 40]);

        try {
            CP::redeemValue($this->ids['customer'], 50, 1000);
            $this->fail('Expected a 422');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('only 40.00 points', $e->getMessage());
        }
    }

    public function test_redeem_below_minimum_is_refused(): void
    {
        $this->setSettings(['loyalty_enabled' => '1', 'loyalty_min_redeem' => '50']);
        DB::table('parties')->where('id', $this->ids['customer'])->update(['loyalty_points' => 500]);

        try {
            CP::redeemValue($this->ids['customer'], 20, 1000);
            $this->fail('Expected a 422');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('At least 50 points', $e->getMessage());
        }
    }

    public function test_add_points_writes_transaction_and_balance(): void
    {
        CP::addPoints($this->ids['customer'], 25, 'Earn', null, 2500, 'test earn');
        CP::addPoints($this->ids['customer'], -10, 'Redeem', null, 10, 'test redeem');
        CP::addPoints($this->ids['customer'], 0.001, 'Earn', null, 0); // below threshold: ignored

        $this->assertEqualsWithDelta(15.0, (float) DB::table('parties')->where('id', $this->ids['customer'])->value('loyalty_points'), 0.001);
        $rows = DB::table('loyalty_transactions')->where('party_id', $this->ids['customer'])->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Earn', $rows[0]->txn_type);
        $this->assertEqualsWithDelta(25.0, (float) $rows[0]->balance_after, 0.001);
        $this->assertEqualsWithDelta(-10.0, (float) $rows[1]->points, 0.001);
        $this->assertEqualsWithDelta(15.0, (float) $rows[1]->balance_after, 0.001);
        $this->assertEquals($this->branch('main'), $rows[0]->branch_id);
    }

    public function test_credit_check_within_and_over_limit(): void
    {
        DB::table('parties')->where('id', $this->ids['customer'])->update(['credit_limit' => 1000]);
        $this->insertDue(600);

        $this->assertNull(CP::creditCheck($this->ids['customer'], 400));          // 600 + 400 = 1000 ≤ limit
        $message = CP::creditCheck($this->ids['customer'], 401);
        $this->assertNotNull($message);
        $this->assertStringContainsString('1,001.00', $message);
        $this->assertStringContainsString('now owes 600.00', $message);
    }

    public function test_credit_check_off_or_no_limit(): void
    {
        $this->insertDue(5000);
        $this->assertNull(CP::creditCheck($this->ids['customer'], 100)); // no limit set

        DB::table('parties')->where('id', $this->ids['customer'])->update(['credit_limit' => 100]);
        $this->setSettings(['credit_mode' => 'off']);
        $this->assertNull(CP::creditCheck($this->ids['customer'], 100));
    }

    public function test_credit_check_counts_advance_opening_balance_as_money_held(): void
    {
        DB::table('parties')->where('id', $this->ids['customer'])->update([
            'credit_limit' => 1000, 'opening_balance' => 500, 'opening_balance_type' => 'Advance',
        ]);

        // In the main branch: current = -500, so a 1500 due ends at 1000 (allowed), 1501 is over.
        $this->assertNull(CP::creditCheck($this->ids['customer'], 1500));
        $this->assertNotNull(CP::creditCheck($this->ids['customer'], 1501));

        DB::table('parties')->where('id', $this->ids['customer'])->update(['opening_balance_type' => 'Due']);
        $this->assertNotNull(CP::creditCheck($this->ids['customer'], 501)); // 500 due + 501 > 1000
    }

    public function test_credit_check_counts_only_logged_in_branch_payments(): void
    {
        DB::table('parties')->where('id', $this->ids['customer'])->update(['credit_limit' => 1000]);
        $this->insertDue(900, $this->branch('two'));

        // Main branch sees none of Branch Two's due.
        $this->assertNull(CP::creditCheck($this->ids['customer'], 1000));
    }

    private function insertDue(float $amount, ?int $branchId = null): void
    {
        DB::table('payments')->insert([
            'branch_id' => $branchId ?? $this->branch('main'), 'party_id' => $this->ids['customer'], 'party_type' => 'Customer',
            'code' => 'VO-T' . random_int(10000, 99999), 'payment_type' => 'Wholesale', 'datetime' => now(),
            'debit' => 0, 'credit' => $amount, 'status' => 'Active', 'deleted' => 'No',
        ]);
    }
}
