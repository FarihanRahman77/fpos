<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\SalesHelpers;

/** Sale returns: stock back, VAT and loyalty reversed in proportion, customer due / credit. */
class SaleReturnTest extends AppTestCase
{
    use SalesHelpers;

    /** 10 × 80 with 10% VAT added = 880, 8 loyalty points earned. */
    private function vatLoyaltySale(float $paid): object
    {
        $this->setSettings(['vat_enabled' => '1', 'vat_rate' => '10', 'vat_inclusive' => '0',
            'loyalty_enabled' => '1', 'loyalty_earn_amount' => '100', 'loyalty_earn_points' => '1']);
        $this->signIn('superadmin', 'main');
        $this->addStock($this->ids['product.plain'], 20);
        $id = $this->postSale([$this->line($this->ids['product.plain'], 10, 80)], ['paid_amount' => $paid])->assertOk()->json('sale_id');

        return DB::table('sales')->find($id);
    }

    private function postReturn(object $sale, float $qty, array $data = [])
    {
        $item = DB::table('sale_items')->where('sale_id', $sale->id)->first();

        return $this->ajaxPost(route('sales.return.store'), $data + [
            'sale_id' => $sale->id,
            'datetime' => now()->format('Y-m-d H:i:s'),
            'items' => [['sale_item_id' => $item->id, 'qty' => $qty]],
        ]);
    }

    public function test_partial_return_of_partly_paid_sale_restocks_and_reverses_vat_and_loyalty(): void
    {
        $sale = $this->vatLoyaltySale(400);
        $this->assertEquals(480.0, $this->customerDue($this->ids['customer']));
        $this->assertEquals(10, $this->onHand($this->ids['product.plain']));

        $this->postReturn($sale, 5)->assertOk()->assertJson(['status' => true]);

        $ret = DB::table('sale_returns')->where('sale_id', $sale->id)->first();
        $this->assertNotNull($ret);
        $this->assertEquals(440, (float) $ret->grand_total);      // 5 × (80 + 8 VAT)
        $this->assertEquals(40, (float) $ret->vat_amount);        // half of the 80 VAT
        $this->assertEquals(4, (float) $ret->loyalty_points_reversed);
        $this->assertSame('Credit', $ret->refund_mode);
        $this->assertSame($this->branch('main'), (int) $ret->branch_id);
        $this->assertDatabaseHas('sale_return_items', ['sale_return_id' => $ret->id, 'qty' => 5, 'vat_amount' => 40]);

        $this->assertEquals(15, $this->onHand($this->ids['product.plain']));
        $this->assertDatabaseHas('stocks', ['sale_return_id' => $ret->id, 'in_qty' => 5, 'reference_type' => 'Sale Return', 'branch_id' => $this->branch('main')]);

        $this->assertDatabaseHas('payments', ['sale_return_id' => $ret->id, 'payment_type' => 'Wholesale Return', 'debit' => 440]);
        $this->assertEquals(40.0, $this->customerDue($this->ids['customer']));  // 480 - 440

        $this->assertEquals(4, (float) DB::table('parties')->where('id', $this->ids['customer'])->value('loyalty_points'));
        $this->assertDatabaseHas('loyalty_transactions', ['sale_return_id' => $ret->id, 'txn_type' => 'Reverse', 'points' => -4]);
    }

    public function test_return_of_fully_paid_sale_leaves_customer_credit(): void
    {
        $sale = $this->vatLoyaltySale(880);
        $this->assertEquals(0.0, $this->customerDue($this->ids['customer']));

        $this->postReturn($sale, 5)->assertOk();

        $this->assertEquals(-440.0, $this->customerDue($this->ids['customer']));  // credit kept on the account
        $this->assertEquals(15, $this->onHand($this->ids['product.plain']));
    }

    public function test_cash_refund_settles_the_return(): void
    {
        $sale = $this->vatLoyaltySale(880);

        $this->postReturn($sale, 5, ['refund_mode' => 'Cash', 'payment_method' => $this->coa('cash'), 'payment_method_account' => $this->coa('cash-main')])
            ->assertOk();

        $ret = DB::table('sale_returns')->where('sale_id', $sale->id)->first();
        $this->assertSame('Cash', $ret->refund_mode);
        $this->assertEquals(440, (float) $ret->refund_amount);
        $this->assertDatabaseHas('payments', ['sale_return_id' => $ret->id, 'payment_type' => 'Payment', 'credit' => 440,
            'payment_method_account_id' => $this->coa('cash-main')]);
        $this->assertEquals(0.0, $this->customerDue($this->ids['customer']));
    }

    public function test_returning_everything_gives_back_exactly_the_grand_total_and_all_vat(): void
    {
        $sale = $this->vatLoyaltySale(0);
        $this->postReturn($sale, 3)->assertOk();
        $this->postReturn($sale, 7)->assertOk();

        $this->assertEquals(880, (float) DB::table('sale_returns')->where('sale_id', $sale->id)->sum('grand_total'));
        $this->assertEquals(80, (float) DB::table('sale_returns')->where('sale_id', $sale->id)->sum('vat_amount'));
        $this->assertEquals(0.0, $this->customerDue($this->ids['customer']));
        $this->assertEquals(0, (float) DB::table('parties')->where('id', $this->ids['customer'])->value('loyalty_points'));
        $this->assertEquals(20, $this->onHand($this->ids['product.plain']));
    }

    public function test_cannot_return_more_than_sold(): void
    {
        $sale = $this->vatLoyaltySale(0);

        $r = $this->postReturn($sale, 11);

        $r->assertStatus(422);
        $this->assertStringContainsString('more than what is left to return', $r->json('message'));
        $this->assertSame(0, DB::table('sale_returns')->count());
        $this->assertEquals(10, $this->onHand($this->ids['product.plain']));
        $this->assertNoSqlLeak($r);
    }

    public function test_sale_with_returns_cannot_be_deleted(): void
    {
        $sale = $this->vatLoyaltySale(0);
        $this->postReturn($sale, 1)->assertOk();

        $this->ajaxPost(route('sales.delete', $sale->id))->assertStatus(422);
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'deleted' => 'No']);
    }

    /**
     * Regression: the restock query joins product_batches (which also has product_id / product_variant_id), so the
     * unqualified columns were ambiguous and the return failed with a SQL error. Covers return data, store and list
     * on a batch-tracked product.
     */
    public function test_regression_sale_return_has_no_ambiguous_sql(): void
    {
        $this->signIn('superadmin', 'main');
        $para = $this->ids['product.batch'];
        $b1 = $this->makeBatch($para, 'B-EARLY', now()->addMonths(2)->toDateString());
        $b2 = $this->makeBatch($para, 'B-LATE', now()->addMonths(8)->toDateString());
        $this->addStock($para, 5, 'main', null, $b1);
        $this->addStock($para, 5, 'main', null, $b2);
        $saleId = $this->postSale([$this->line($para, 7, 5)])->assertOk()->json('sale_id');
        $sale = DB::table('sales')->find($saleId);
        // FEFO: 5 from the early batch, 2 from the late one
        $this->assertDatabaseHas('stocks', ['sale_id' => $saleId, 'batch_id' => $b1, 'out_qty' => 5]);
        $this->assertDatabaseHas('stocks', ['sale_id' => $saleId, 'batch_id' => $b2, 'out_qty' => 2]);

        $data = $this->ajaxGet(route('sales.return.data', $saleId));
        $data->assertOk()->assertJsonPath('items.0.remaining_qty', 7);
        $this->assertNoSqlLeak($data);

        $store = $this->postReturn($sale, 2);
        $this->assertNoSqlLeak($store);
        $store->assertOk()->assertJson(['status' => true]);
        $ret = DB::table('sale_returns')->where('sale_id', $saleId)->first();
        // restocked into a batch the goods were sold from (latest expiry first)
        $this->assertDatabaseHas('stocks', ['sale_return_id' => $ret->id, 'in_qty' => 2, 'batch_id' => $b2]);

        $list = $this->ajaxGet(route('sales.returns.list'));
        $list->assertOk()->assertJson(['status' => true]);
        $this->assertStringContainsString($ret->return_no, $list->json('html'));
        $this->assertNoSqlLeak($list);

        $this->get(route('sales.return.view', $ret->id))->assertOk();
        $this->get(route('sales.return.invoice.pdf', $ret->id))->assertOk();
    }

    public function test_deleting_a_return_takes_the_stock_back_out(): void
    {
        $sale = $this->vatLoyaltySale(0);
        $this->postReturn($sale, 4)->assertOk();
        $ret = DB::table('sale_returns')->where('sale_id', $sale->id)->first();
        $this->assertEquals(14, $this->onHand($this->ids['product.plain']));

        $this->ajaxPost(route('sales.return.delete', $ret->id))->assertOk();

        $this->assertDatabaseHas('sale_returns', ['id' => $ret->id, 'deleted' => 'Yes']);
        $this->assertEquals(10, $this->onHand($this->ids['product.plain']));
        $this->assertEquals(880.0, $this->customerDue($this->ids['customer']));
        $this->assertEquals(8, (float) DB::table('parties')->where('id', $this->ids['customer'])->value('loyalty_points'));
    }
}
