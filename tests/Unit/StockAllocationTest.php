<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\StockController;
use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;

/** StockController shared rules: onHand(), allocate() (FEFO), batchFor(), move(). */
class StockAllocationTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn('superadmin', 'main');
    }

    public function test_on_hand_is_per_branch_variant_and_batch(): void
    {
        $plain = $this->ids['product.plain'];
        $this->addStock($plain, 10, 'main');
        $this->addStock($plain, -3, 'main');
        $this->addStock($plain, 7, 'two');

        $this->assertEqualsWithDelta(7.0, StockController::onHand($plain, null, $this->branch('main')), 0.0001);
        $this->assertEqualsWithDelta(7.0, StockController::onHand($plain, null, $this->branch('two')), 0.0001);
        $this->assertEqualsWithDelta(0.0, StockController::onHand($plain, null, $this->branch('three')), 0.0001);

        $tshirt = $this->ids['product.variant'];
        $this->addStock($tshirt, 4, 'main', $this->ids['variant.red']);
        $this->addStock($tshirt, 6, 'main', $this->ids['variant.blue']);
        $this->assertEqualsWithDelta(4.0, StockController::onHand($tshirt, $this->ids['variant.red'], $this->branch('main')), 0.0001);
        $this->assertEqualsWithDelta(6.0, StockController::onHand($tshirt, $this->ids['variant.blue'], $this->branch('main')), 0.0001);
        // no-variant query does not count variant rows
        $this->assertEqualsWithDelta(0.0, StockController::onHand($tshirt, null, $this->branch('main')), 0.0001);

        $para = $this->ids['product.batch'];
        $b1 = $this->makeBatch($para, 'B1', '2027-01-31');
        $b2 = $this->makeBatch($para, 'B2', '2026-12-31');
        $this->addStock($para, 5, 'main', null, $b1);
        $this->addStock($para, 8, 'main', null, $b2);
        $this->assertEqualsWithDelta(5.0, StockController::onHand($para, null, $this->branch('main'), $b1), 0.0001);
        $this->assertEqualsWithDelta(13.0, StockController::onHand($para, null, $this->branch('main')), 0.0001);
    }

    public function test_deleted_stock_rows_are_ignored(): void
    {
        $plain = $this->ids['product.plain'];
        $this->addStock($plain, 10, 'main');
        DB::table('stocks')->where('product_id', $plain)->update(['deleted' => 'Yes']);

        $this->assertEqualsWithDelta(0.0, StockController::onHand($plain, null, $this->branch('main')), 0.0001);
    }

    public function test_untracked_product_allocates_one_line_without_batch(): void
    {
        $this->assertSame([[null, 5.0]], StockController::allocate($this->ids['product.plain'], null, $this->branch('main'), 5.0));
    }

    public function test_allocate_takes_earliest_expiry_first_and_undated_last(): void
    {
        $para = $this->ids['product.batch'];
        $late = $this->makeBatch($para, 'LATE', '2027-06-30');
        $none = $this->makeBatch($para, 'NOEXP', null);
        $early = $this->makeBatch($para, 'EARLY', '2026-12-31');
        $this->addStock($para, 4, 'main', null, $late);
        $this->addStock($para, 3, 'main', null, $none);
        $this->addStock($para, 2, 'main', null, $early);

        $this->assertSame([[$early, 2.0], [$late, 3.0]], StockController::allocate($para, null, $this->branch('main'), 5));
        $this->assertSame([[$early, 2.0], [$late, 4.0], [$none, 3.0]], StockController::allocate($para, null, $this->branch('main'), 9));
    }

    public function test_allocate_skips_empty_batches_and_other_branches(): void
    {
        $para = $this->ids['product.batch'];
        $early = $this->makeBatch($para, 'EARLY', '2026-12-31');
        $late = $this->makeBatch($para, 'LATE', '2027-06-30');
        $this->addStock($para, 2, 'main', null, $early);
        $this->addStock($para, -2, 'main', null, $early);   // used up
        $this->addStock($para, 5, 'main', null, $late);
        $this->addStock($para, 50, 'two', null, $early);    // other branch

        $this->assertSame([[$late, 3.0]], StockController::allocate($para, null, $this->branch('main'), 3));
    }

    public function test_allocate_shortfall_rest_comes_without_batch(): void
    {
        $para = $this->ids['product.batch'];
        $b = $this->makeBatch($para, 'ONLY', '2026-12-31');
        $this->addStock($para, 2, 'main', null, $b);

        $this->assertSame([[$b, 2.0], [null, 1.5]], StockController::allocate($para, null, $this->branch('main'), 3.5));
    }

    public function test_batch_for_reuses_existing_batch_and_fills_missing_expiry(): void
    {
        $para = $this->ids['product.batch'];
        $id = StockController::batchFor($para, null, 'LOT-1', null, null, 2.5, null);
        $again = StockController::batchFor($para, null, 'LOT-1', '2027-03-31', null, 9.99, null);

        $this->assertSame($id, $again);
        $this->assertSame(1, DB::table('product_batches')->where('product_id', $para)->where('batch_no', 'LOT-1')->count());
        $row = DB::table('product_batches')->find($id);
        $this->assertSame('2027-03-31', $row->expiry_date);
        $this->assertEqualsWithDelta(2.5, (float) $row->unit_cost, 0.001); // cost of the first purchase kept

        // an existing expiry is not overwritten
        StockController::batchFor($para, null, 'LOT-1', '2028-01-01', null, 1, null);
        $this->assertSame('2027-03-31', DB::table('product_batches')->where('id', $id)->value('expiry_date'));

        // same batch number for another variant is a different batch
        $other = StockController::batchFor($this->ids['product.variant'], $this->ids['variant.red'], 'LOT-1', null, null, 1, null);
        $this->assertNotSame($id, $other);
    }

    public function test_move_writes_explicit_branch_regardless_of_session(): void
    {
        StockController::move(['branch_id' => $this->branch('three'), 'product_id' => $this->ids['product.plain'], 'qty' => -4,
            'reference_type' => 'Transfer Out', 'date' => now()->toDateTimeString()]);

        $row = DB::table('stocks')->latest('id')->first();
        $this->assertEquals($this->branch('three'), $row->branch_id);
        $this->assertEqualsWithDelta(0.0, (float) $row->in_qty, 0.001);
        $this->assertEqualsWithDelta(4.0, (float) $row->out_qty, 0.001);
        $this->assertSame('Transfer Out', $row->reference_type);
    }
}
