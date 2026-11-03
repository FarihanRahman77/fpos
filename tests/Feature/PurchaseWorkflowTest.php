<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\SalesHelpers;

/** Purchases: stock in (incl. batches), supplier due, supplier payment, purchase returns. */
class PurchaseWorkflowTest extends AppTestCase
{
    use SalesHelpers;

    private function creditPurchase(): object
    {
        $this->signIn('superadmin', 'main');
        $this->fundCash(100000);   // paid purchases / supplier payments come out of the main drawer
        $this->postPurchase([
            ['product_id' => $this->ids['product.plain'], 'qty' => 10, 'purchase_price' => 50],
            ['product_id' => $this->ids['product.batch'], 'qty' => 100, 'purchase_price' => 2, 'batch_no' => 'B-100', 'expiry_date' => '2027-06-30'],
        ])->assertOk()->assertJson(['status' => true]);

        return $this->lastPurchase();
    }

    public function test_purchase_stocks_in_and_creates_supplier_due_then_payment_clears_it(): void
    {
        $purchase = $this->creditPurchase();

        $this->assertSame($this->branch('main'), (int) $purchase->branch_id);
        $this->assertEquals(700, (float) $purchase->grand_total);
        $this->assertEquals(0, (float) $purchase->paid_amount);
        $this->assertEquals(700, (float) $purchase->due_amount);
        $this->assertSame(2, DB::table('purchase_items')->where('purchase_id', $purchase->id)->count());

        // stock in, in the logged-in branch
        $this->assertEquals(10, $this->onHand($this->ids['product.plain']));
        $this->assertEquals(0, $this->onHand($this->ids['product.plain'], 'two'));
        $batch = DB::table('product_batches')->where('product_id', $this->ids['product.batch'])->where('batch_no', 'B-100')->first();
        $this->assertNotNull($batch);
        $this->assertSame('2027-06-30', $batch->expiry_date);
        $this->assertEquals(2, (float) $batch->unit_cost);
        $this->assertSame((int) $purchase->id, (int) $batch->purchase_id);
        $this->assertEquals(100, $this->onHand($this->ids['product.batch'], 'main', null, $batch->id));
        $this->assertDatabaseHas('stocks', ['purchase_id' => $purchase->id, 'reference_type' => 'Purchase', 'batch_id' => $batch->id,
            'in_qty' => 100, 'branch_id' => $this->branch('main')]);
        $this->assertDatabaseHas('purchase_items', ['purchase_id' => $purchase->id, 'product_id' => $this->ids['product.batch'], 'batch_id' => $batch->id]);

        $this->assertDatabaseHas('payments', ['purchase_id' => $purchase->id, 'payment_type' => 'Purchase', 'debit' => 700, 'party_id' => $this->ids['supplier']]);
        $this->assertEquals(700.0, $this->supplierDue($this->ids['supplier']));

        $this->postPayment('Payment', $this->ids['supplier'], 300)->assertOk()->assertJson(['status' => true]);
        $this->assertEquals(400.0, $this->supplierDue($this->ids['supplier']));
        $this->postPayment('Payment', $this->ids['supplier'], 400)->assertOk();
        $this->assertEquals(0.0, $this->supplierDue($this->ids['supplier']));
        $this->assertSame(2, DB::table('payments')->where('party_id', $this->ids['supplier'])->where('payment_type', 'Payment')
            ->where('payment_method_account_id', $this->coa('cash-main'))->count());

        $this->get(route('purchases.invoice.pdf', $purchase->id))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_paid_purchase_has_no_due_and_records_the_payment(): void
    {
        $this->signIn('superadmin', 'main');
        $this->fundCash(100000);   // paid purchases / supplier payments come out of the main drawer
        $this->postPurchase([['product_id' => $this->ids['product.plain'], 'qty' => 4, 'purchase_price' => 50]], ['paid_amount' => 200])
            ->assertOk();
        $p = $this->lastPurchase();

        $this->assertEquals(200, (float) $p->paid_amount);
        $this->assertEquals(0, (float) $p->due_amount);
        $this->assertDatabaseHas('payments', ['purchase_id' => $p->id, 'payment_type' => 'Purchase Payment', 'credit' => 200,
            'payment_method_account_id' => $this->coa('cash-main')]);
        $this->assertEquals(0.0, $this->supplierDue($this->ids['supplier']));
    }

    public function test_purchase_validation(): void
    {
        $this->signIn('superadmin', 'main');
        $this->fundCash(100000);   // paid purchases / supplier payments come out of the main drawer

        // variant product without a variant
        $r = $this->postPurchase([['product_id' => $this->ids['product.variant'], 'qty' => 1, 'purchase_price' => 100]]);
        $r->assertStatus(422);
        $this->assertStringContainsString('Choose a variant', $r->json('message'));

        // batch-tracked product without expiry
        $r = $this->postPurchase([['product_id' => $this->ids['product.batch'], 'qty' => 1, 'purchase_price' => 2]]);
        $r->assertStatus(422);
        $this->assertStringContainsString('expiry date', $r->json('message'));

        // paid without an account
        $r = $this->ajaxPost(route('purchases.store'), ['supplier_id' => $this->ids['supplier'], 'purchase_date' => now()->toDateString(),
            'discount_type' => 'Amount', 'paid_amount' => 10,
            'items' => [['product_id' => $this->ids['product.plain'], 'qty' => 1, 'purchase_price' => 50, 'discount_type' => 'Amount']]]);
        $r->assertStatus(422)->assertJsonPath('message', 'Choose the payment method for the paid amount.');

        // another branch's cash ledger cannot pay a main-branch purchase
        $r = $this->postPurchase([['product_id' => $this->ids['product.plain'], 'qty' => 1, 'purchase_price' => 50]], ['paid_amount' => 50], 'cash-two');
        $r->assertStatus(422);

        // a customer is not a supplier
        $r = $this->postPurchase([['product_id' => $this->ids['product.plain'], 'qty' => 1, 'purchase_price' => 50]], ['supplier_id' => $this->ids['customer']]);
        $r->assertStatus(422)->assertJsonPath('message', 'Invalid supplier.');
        $this->assertNoSqlLeak($r);

        $this->assertSame(0, DB::table('purchases')->count());
        $this->assertSame(0, DB::table('stocks')->count());
    }

    private function postPurchaseReturn(object $purchase, int $productId, float $qty)
    {
        $item = DB::table('purchase_items')->where('purchase_id', $purchase->id)->where('product_id', $productId)->first();

        return $this->ajaxPost(route('purchases.return.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => now()->toDateString(),
            'items' => [['purchase_item_id' => $item->id, 'qty' => $qty]],
        ]);
    }

    public function test_purchase_return_takes_stock_out_and_lowers_supplier_due(): void
    {
        $purchase = $this->creditPurchase();
        $this->ajaxGet(route('purchases.return.data', $purchase->id))->assertOk()->assertJson(['status' => true]);

        $this->postPurchaseReturn($purchase, $this->ids['product.plain'], 4)->assertOk()->assertJson(['status' => true]);

        $ret = DB::table('purchase_returns')->where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($ret);
        $this->assertEquals(200, (float) $ret->grand_total);
        $this->assertSame($this->branch('main'), (int) $ret->branch_id);
        $this->assertEquals(6, $this->onHand($this->ids['product.plain']));
        $this->assertDatabaseHas('stocks', ['purchase_return_id' => $ret->id, 'reference_type' => 'Purchase Return', 'out_qty' => 4]);
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'grand_total' => 500, 'due_amount' => 500]);
        $this->assertEquals(500.0, $this->supplierDue($this->ids['supplier']));

        $this->ajaxGet(route('purchases.return.list'))->assertOk();
    }

    /** Regression: the return used to be booked on the wrong side, raising the supplier due instead of lowering it. */
    public function test_regression_purchase_return_lowers_supplier_due(): void
    {
        $purchase = $this->creditPurchase();
        $before = $this->supplierDue($this->ids['supplier']);

        $this->postPurchaseReturn($purchase, $this->ids['product.batch'], 30)->assertOk();

        $after = $this->supplierDue($this->ids['supplier']);
        $this->assertEquals($before - 60, $after, 'A purchase return of 60 must lower the supplier due by 60.');
        $this->assertDatabaseHas('payments', ['purchase_id' => $purchase->id, 'payment_type' => 'Purchase Payment', 'credit' => 60, 'debit' => 0]);
        // the batch the goods came in with goes down
        $batch = DB::table('product_batches')->where('batch_no', 'B-100')->value('id');
        $this->assertEquals(70, $this->onHand($this->ids['product.batch'], 'main', null, $batch));
    }

    public function test_return_of_paid_purchase_refunds_and_keeps_due_zero(): void
    {
        $this->signIn('superadmin', 'main');
        $this->fundCash(100000);   // paid purchases / supplier payments come out of the main drawer
        $this->postPurchase([['product_id' => $this->ids['product.plain'], 'qty' => 10, 'purchase_price' => 50]], ['paid_amount' => 500])->assertOk();
        $purchase = $this->lastPurchase();

        $this->postPurchaseReturn($purchase, $this->ids['product.plain'], 2)->assertOk();

        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'grand_total' => 400, 'paid_amount' => 400, 'due_amount' => 0]);
        $this->assertDatabaseHas('payments', ['purchase_id' => $purchase->id, 'payment_type' => 'Payment', 'debit' => 100]);
        $this->assertEquals(0.0, $this->supplierDue($this->ids['supplier']));
    }

    public function test_cannot_return_goods_already_sold(): void
    {
        $purchase = $this->creditPurchase();
        $this->postSale([$this->line($this->ids['product.plain'], 8, 80)])->assertOk();

        $r = $this->postPurchaseReturn($purchase, $this->ids['product.plain'], 4);

        $r->assertStatus(422);
        $this->assertSame(0, DB::table('purchase_returns')->count());
        $this->assertEquals(2, $this->onHand($this->ids['product.plain']));
        $this->assertEquals(700.0, $this->supplierDue($this->ids['supplier']));
    }

    public function test_deleting_a_purchase_whose_goods_were_sold_is_refused(): void
    {
        $purchase = $this->creditPurchase();
        $this->postSale([$this->line($this->ids['product.plain'], 3, 80)])->assertOk();

        $this->ajaxPost(route('purchases.delete', $purchase->id))->assertStatus(422);
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'deleted' => 'No']);
        $this->assertEquals(7, $this->onHand($this->ids['product.plain']));
    }
}
