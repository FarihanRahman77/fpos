<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\SalesHelpers;

/** POS / sale: stock out, payments, invoice PDF, customer due, receipt, oversell guard, VAT, loyalty, delete. */
class SaleWorkflowTest extends AppTestCase
{
    use SalesHelpers;

    public function test_wholesale_sale_writes_sale_items_stock_and_payments_then_receipt_clears_due(): void
    {
        $this->signIn('superadmin', 'main');
        $plain = $this->ids['product.plain'];
        $this->addStock($plain, 20);

        $res = $this->postSale([$this->line($plain, 5, 80)], ['paid_amount' => 100]);

        $res->assertOk()->assertJson(['status' => true]);
        $sale = DB::table('sales')->where('id', $res->json('sale_id'))->first();
        $this->assertNotNull($sale);
        $this->assertSame($this->branch('main'), (int) $sale->branch_id);
        $this->assertEquals(400.00, (float) $sale->grand_total);
        $this->assertEquals(100.00, (float) $sale->paid_amount);
        $this->assertEquals(300.00, (float) $sale->due_amount);
        $this->assertSame((int) $this->ids['customer'], (int) $sale->customer_id);

        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_id' => $plain, 'qty' => 5, 'purchase_price' => 50, 'total' => 400]);

        $stock = DB::table('stocks')->where('sale_id', $sale->id)->get();
        $this->assertCount(1, $stock);
        $this->assertEquals(5, (float) $stock[0]->out_qty);
        $this->assertSame('Sale', $stock[0]->reference_type);
        $this->assertSame($this->branch('main'), (int) $stock[0]->branch_id);
        $this->assertEquals(15, $this->onHand($plain));

        $payments = DB::table('payments')->where('sale_id', $sale->id)->where('deleted', 'No')->get()->keyBy('payment_type');
        $this->assertEquals(400, (float) $payments['Wholesale']->credit);
        $this->assertEquals(100, (float) $payments['Wholesale Payment Receive']->debit);
        $this->assertSame($this->coa('cash-main'), (int) $payments['Wholesale Payment Receive']->payment_method_account_id);

        $this->assertEquals(300.0, $this->customerDue($this->ids['customer']));

        $pdf = $this->get(route('sales.invoice.pdf', $sale->id));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');

        // Receipt against the invoice brings the due (and the invoice due) to zero.
        $this->postPayment('Payment Receive', $this->ids['customer'], 300, ['sale_id' => $sale->id])
            ->assertOk()->assertJson(['status' => true]);
        $this->assertEquals(0.0, $this->customerDue($this->ids['customer']));
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'paid_amount' => 400, 'due_amount' => 0]);
        $this->assertDatabaseHas('payments', ['party_id' => $this->ids['customer'], 'payment_type' => 'Payment Receive', 'debit' => 300,
            'applied_sale_id' => $sale->id, 'branch_id' => $this->branch('main')]);
    }

    public function test_receipt_more_than_invoice_due_is_refused(): void
    {
        $this->signIn('superadmin', 'main');
        $this->addStock($this->ids['product.plain'], 20);
        $saleId = $this->postSale([$this->line($this->ids['product.plain'], 1, 80)])->assertOk()->json('sale_id');

        $this->postPayment('Payment Receive', $this->ids['customer'], 81, ['sale_id' => $saleId])->assertStatus(422);
        $this->assertDatabaseMissing('payments', ['payment_type' => 'Payment Receive']);
        $this->assertEquals(80.0, $this->customerDue($this->ids['customer']));
    }

    public function test_walk_in_sale_by_phone_with_items_as_json_string_creates_walk_in_customer(): void
    {
        $this->signIn('cashier', 'two');
        $variant = $this->ids['product.variant'];
        $this->addStock($variant, 4, 'two', $this->ids['variant.red']);

        $res = $this->ajaxPost(route('sales.store'), [
            'sale_type' => 'Walk-In', 'customer_phone' => '01555000111', 'customer_name' => 'Walk Buyer',
            'sale_date' => now()->toDateString(), 'discount_type' => 'Amount', 'discount_value' => 0,
            'paid_amount' => 700, 'payment_method' => $this->coa('cash'), 'payment_method_account' => $this->coa('cash-two'),
            'items' => json_encode([$this->line($variant, 2, 350, $this->ids['variant.red'])]),
        ]);

        $res->assertOk()->assertJson(['status' => true]);
        $party = DB::table('parties')->where('phone', '01555000111')->first();
        $this->assertNotNull($party);
        $this->assertSame('Walk-In Customer', $party->type);
        $this->assertSame('Walk Buyer', $party->name);

        $sale = DB::table('sales')->find($res->json('sale_id'));
        $this->assertSame('Walk-In', $sale->sale_type);
        $this->assertSame($this->branch('two'), (int) $sale->branch_id);
        $this->assertEquals(700, (float) $sale->grand_total);
        $this->assertEquals(0, (float) $sale->due_amount);
        $this->assertEquals(2, $this->onHand($variant, 'two', $this->ids['variant.red']));
        $this->assertDatabaseHas('stocks', ['sale_id' => $sale->id, 'product_variant_id' => $this->ids['variant.red'], 'out_qty' => 2, 'branch_id' => $this->branch('two')]);
        $this->assertEquals(0.0, $this->customerDue($party->id));

        // A second sale to the same phone reuses the customer.
        $this->ajaxPost(route('sales.store'), [
            'sale_type' => 'Walk-In', 'customer_phone' => '01555000111', 'sale_date' => now()->toDateString(), 'discount_type' => 'Amount',
            'items' => [$this->line($variant, 1, 350, $this->ids['variant.red'])],
        ])->assertOk();
        $this->assertSame(1, DB::table('parties')->where('phone', '01555000111')->count());
        $this->assertEquals(350.0, $this->customerDue($party->id));
    }

    public function test_oversell_is_refused_with_422_and_nothing_is_written(): void
    {
        $this->signIn('superadmin', 'main');
        $plain = $this->ids['product.plain'];
        $this->addStock($plain, 10);
        $this->addStock($plain, 50, 'two'); // other branch stock must not count

        $res = $this->ajaxPost(route('sales.store'), [
            'sale_type' => 'Walk-In', 'customer_phone' => '01666000222', 'sale_date' => now()->toDateString(),
            'discount_type' => 'Amount', 'items' => [$this->line($plain, 11, 80)],
        ]);

        $res->assertStatus(422)->assertJson(['status' => false]);
        $this->assertStringContainsString('Not enough stock', $res->json('message'));
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('stocks')->where('reference_type', 'Sale')->count());
        $this->assertDatabaseMissing('parties', ['phone' => '01666000222']);
        $this->assertEquals(10, $this->onHand($plain));
    }

    public function test_two_lines_of_the_same_product_are_summed_for_the_stock_check(): void
    {
        $this->signIn('superadmin', 'main');
        $plain = $this->ids['product.plain'];
        $this->addStock($plain, 10);

        $res = $this->postSale([$this->line($plain, 6, 80), $this->line($plain, 6, 75)]);

        $res->assertStatus(422);
        $this->assertStringContainsString('required: 12', $res->json('message'));
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertEquals(10, $this->onHand($plain));

        // each alone fits
        $this->postSale([$this->line($plain, 6, 80)])->assertOk();
        $this->assertEquals(4, $this->onHand($plain));
    }

    public function test_vat_added_on_top_and_loyalty_points_earned(): void
    {
        $this->setSettings(['vat_enabled' => '1', 'vat_rate' => '10', 'vat_inclusive' => '0',
            'loyalty_enabled' => '1', 'loyalty_earn_amount' => '100', 'loyalty_earn_points' => '1']);
        $this->signIn('superadmin', 'main');
        $plain = $this->ids['product.plain'];
        $this->addStock($plain, 20);

        $res = $this->postSale([$this->line($plain, 10, 80)], ['discount_value' => 0]);

        $res->assertOk();
        $sale = DB::table('sales')->find($res->json('sale_id'));
        $this->assertEquals(800, (float) $sale->subtotal);
        $this->assertEquals(80, (float) $sale->vat_amount);
        $this->assertSame('No', $sale->vat_inclusive);
        $this->assertEquals(880, (float) $sale->grand_total);
        $this->assertEquals(8, (float) $sale->loyalty_points_earned);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'vat_percent' => 10, 'vat_amount' => 80]);
        $this->assertEquals(8, (float) DB::table('parties')->where('id', $this->ids['customer'])->value('loyalty_points'));
        $this->assertDatabaseHas('loyalty_transactions', ['sale_id' => $sale->id, 'txn_type' => 'Earn', 'points' => 8, 'party_id' => $this->ids['customer']]);
        $this->assertEquals(880.0, $this->customerDue($this->ids['customer']));
    }

    public function test_vat_inclusive_does_not_raise_the_total_and_sale_discount_is_spread(): void
    {
        $this->setSettings(['vat_enabled' => '1', 'vat_rate' => '15', 'vat_inclusive' => '1']);
        $this->signIn('superadmin', 'main');
        $plain = $this->ids['product.plain'];
        $this->addStock($plain, 20);

        $res = $this->postSale([$this->line($plain, 10, 115)], ['discount_value' => 150]);

        $res->assertOk();
        $sale = DB::table('sales')->find($res->json('sale_id'));
        $this->assertEquals(1000, (float) $sale->grand_total);   // 1150 - 150, VAT inside
        $this->assertEquals(130.43, (float) $sale->vat_amount);  // 1000 - 1000/1.15
        $this->assertSame('Yes', $sale->vat_inclusive);
    }

    public function test_redeeming_loyalty_points_lowers_the_total(): void
    {
        $this->setSettings(['loyalty_enabled' => '1', 'loyalty_point_value' => '1', 'loyalty_min_redeem' => '10', 'loyalty_max_percent' => '50',
            'loyalty_earn_amount' => '100', 'loyalty_earn_points' => '1']);
        DB::table('parties')->where('id', $this->ids['customer'])->update(['loyalty_points' => 100]);
        $this->signIn('superadmin', 'main');
        $this->addStock($this->ids['product.plain'], 20);

        $res = $this->postSale([$this->line($this->ids['product.plain'], 1, 80)], ['redeem_points' => 60]);

        $res->assertOk();
        $sale = DB::table('sales')->find($res->json('sale_id'));
        // capped at 50% of 80 = 40 points / 40 money
        $this->assertEquals(40, (float) $sale->loyalty_discount);
        $this->assertEquals(40, (float) $sale->loyalty_points_redeemed);
        $this->assertEquals(40, (float) $sale->grand_total);
        $this->assertEquals(60, (float) DB::table('parties')->where('id', $this->ids['customer'])->value('loyalty_points'));
        $this->assertDatabaseHas('loyalty_transactions', ['sale_id' => $sale->id, 'txn_type' => 'Redeem', 'points' => -40]);
    }

    public function test_deleting_a_sale_restores_stock_and_removes_its_payments(): void
    {
        $this->signIn('superadmin', 'main');
        $plain = $this->ids['product.plain'];
        $this->addStock($plain, 10);
        $saleId = $this->postSale([$this->line($plain, 4, 80)], ['paid_amount' => 100])->assertOk()->json('sale_id');
        $this->assertEquals(6, $this->onHand($plain));

        $this->ajaxPost(route('sales.delete', $saleId))->assertOk()->assertJson(['status' => true]);

        $this->assertEquals(10, $this->onHand($plain));
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'deleted' => 'Yes']);
        $this->assertSame(0, DB::table('payments')->where('sale_id', $saleId)->where('deleted', 'No')->count());
        $this->assertEquals(0.0, $this->customerDue($this->ids['customer']));
    }

    public function test_sale_validation_errors_are_422_with_a_message(): void
    {
        $this->signIn('superadmin', 'main');

        $r = $this->postSale([]);
        $r->assertStatus(422)->assertJsonPath('message', 'Add at least one product to the sale.');

        $r = $this->postSale([$this->line($this->ids['product.plain'], -1, 80)]);
        $r->assertStatus(422)->assertJsonPath('message', 'Quantity must be more than zero.');
        $this->assertNoSqlLeak($r);

        $r = $this->ajaxPost(route('sales.store'), ['sale_type' => 'Walk-In', 'sale_date' => now()->toDateString(), 'discount_type' => 'Amount',
            'items' => 'not-json']);
        $r->assertStatus(422);
        $this->assertSame(0, DB::table('sales')->count());
    }
}
