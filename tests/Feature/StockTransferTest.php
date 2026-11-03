<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AppTestCase;
use Tests\Concerns\SalesHelpers;

/** Branch-to-branch stock transfers (send / receive / cancel) and stock adjustments. */
class StockTransferTest extends AppTestCase
{
    use SalesHelpers;

    private function send(float $qty, string $to = 'two', array $extra = [])
    {
        return $this->ajaxPost(route('admin.stock.transfers.store'), $extra + [
            'to_branch_id' => $this->branch($to),
            'transfer_date' => now()->toDateString(),
            'items' => [['product_id' => $this->ids['product.plain'], 'qty' => $qty]],
        ]);
    }

    public function test_transfer_out_then_receive_moves_stock_between_branches(): void
    {
        $plain = $this->ids['product.plain'];
        $this->signIn('superadmin', 'main');
        $this->addStock($plain, 20);

        $this->send(8)->assertOk()->assertJson(['status' => true]);

        $t = DB::table('stock_transfers')->first();
        $this->assertSame($this->branch('main'), (int) $t->from_branch_id);
        $this->assertSame($this->branch('two'), (int) $t->to_branch_id);
        $this->assertSame('In Transit', $t->transfer_status);
        $this->assertEquals(8, (float) $t->total_qty);
        $this->assertEquals(400, (float) $t->total_value);
        $this->assertDatabaseHas('stocks', ['reference_type' => 'Transfer Out', 'reference_id' => $t->id, 'branch_id' => $this->branch('main'), 'out_qty' => 8]);
        $this->assertEquals(12, $this->onHand($plain, 'main'));
        $this->assertEquals(0, $this->onHand($plain, 'two'));   // in transit

        // the sending branch cannot receive it
        $this->ajaxPost(route('admin.stock.transfers.receive', $t->id))->assertStatus(403);
        $this->assertSame('In Transit', DB::table('stock_transfers')->where('id', $t->id)->value('transfer_status'));

        // receiving branch confirms; the In row carries the destination branch explicitly (not @fpos_branch)
        $this->signIn('manager', 'two');
        $this->ajaxPost(route('admin.stock.transfers.receive', $t->id), ['received_date' => now()->toDateString()])->assertOk();

        $this->assertDatabaseHas('stocks', ['reference_type' => 'Transfer In', 'reference_id' => $t->id, 'branch_id' => $this->branch('two'), 'in_qty' => 8]);
        $this->assertSame('Received', DB::table('stock_transfers')->where('id', $t->id)->value('transfer_status'));
        $this->assertEquals(8, $this->onHand($plain, 'two'));
        $this->assertEquals(12, $this->onHand($plain, 'main'));
        $this->assertEquals(8, (float) DB::table('stock_transfer_items')->where('stock_transfer_id', $t->id)->value('received_qty'));

        // the received stock can be sold in Branch Two
        $this->postSale([$this->line($plain, 8, 80)], [], 'cash-two')->assertOk();
        $this->assertEquals(0, $this->onHand($plain, 'two'));

        // a received transfer can no longer be cancelled
        $this->signIn('superadmin', 'main');
        $this->ajaxPost(route('admin.stock.transfers.cancel', $t->id))->assertStatus(422);
    }

    public function test_cancel_puts_stock_back_at_the_sending_branch(): void
    {
        $plain = $this->ids['product.plain'];
        $this->signIn('superadmin', 'main');
        $this->addStock($plain, 10);
        $this->send(6)->assertOk();
        $t = DB::table('stock_transfers')->first();
        $this->assertEquals(4, $this->onHand($plain, 'main'));

        $this->ajaxPost(route('admin.stock.transfers.cancel', $t->id))->assertOk();

        $this->assertSame('Cancelled', DB::table('stock_transfers')->where('id', $t->id)->value('transfer_status'));
        $this->assertEquals(10, $this->onHand($plain, 'main'));
        $this->assertEquals(0, $this->onHand($plain, 'two'));
        $this->assertSame(0, DB::table('stocks')->where('reference_type', 'Transfer In')->count());

        // and it cannot then be received
        $this->signIn('manager', 'two');
        $this->ajaxPost(route('admin.stock.transfers.receive', $t->id))->assertStatus(422);
        $this->assertEquals(0, $this->onHand($plain, 'two'));
    }

    public function test_cannot_transfer_more_than_on_hand_or_to_own_branch(): void
    {
        $plain = $this->ids['product.plain'];
        $this->signIn('superadmin', 'main');
        $this->addStock($plain, 5);
        $this->addStock($plain, 100, 'two');   // other branch's stock does not count

        $r = $this->send(6);
        $r->assertStatus(422);
        $this->assertStringContainsString('only 5 in stock', $r->json('message'));
        $this->assertSame(0, DB::table('stock_transfers')->count());
        $this->assertSame(0, DB::table('stocks')->where('reference_type', 'Transfer Out')->count());

        $this->send(1, 'main')->assertStatus(422)->assertJsonPath('message', 'Send to a different branch.');
        $this->send(0)->assertStatus(422);
        $this->assertEquals(5, $this->onHand($plain, 'main'));
    }

    public function test_batch_tracked_transfer_keeps_the_batches_fefo(): void
    {
        $para = $this->ids['product.batch'];
        $this->signIn('superadmin', 'main');
        $early = $this->makeBatch($para, 'E1', now()->addMonth()->toDateString());
        $late = $this->makeBatch($para, 'L1', now()->addYear()->toDateString());
        $this->addStock($para, 3, 'main', null, $late);
        $this->addStock($para, 3, 'main', null, $early);

        $this->ajaxPost(route('admin.stock.transfers.store'), ['to_branch_id' => $this->branch('two'), 'transfer_date' => now()->toDateString(),
            'items' => [['product_id' => $para, 'qty' => 4]]])->assertOk();
        $t = DB::table('stock_transfers')->first();
        $this->signIn('manager', 'two');
        $this->ajaxPost(route('admin.stock.transfers.receive', $t->id))->assertOk();

        $this->assertEquals(3, $this->onHand($para, 'two', null, $early));
        $this->assertEquals(1, $this->onHand($para, 'two', null, $late));
        $this->assertEquals(0, $this->onHand($para, 'main', null, $early));
        $this->assertEquals(2, $this->onHand($para, 'main', null, $late));
    }

    public function test_stock_adjustments_post_to_stock(): void
    {
        $plain = $this->ids['product.plain'];
        $this->signIn('superadmin', 'main');
        $this->addStock($plain, 20);

        $this->ajaxPost(route('admin.stock.adjustments.store'), [
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'Damage', 'post' => 1,
            'items' => [['product_id' => $plain, 'qty' => 3, 'note' => 'broken bag']],
        ])->assertOk()->assertJson(['status' => true]);

        $adj = DB::table('stock_adjustments')->first();
        $this->assertSame('Posted', $adj->adjustment_status);
        $this->assertSame($this->branch('main'), (int) $adj->branch_id);
        $this->assertEquals(-150, (float) $adj->value_change);
        $this->assertDatabaseHas('stocks', ['adjustment_id' => $adj->id, 'reference_type' => 'Adjustment', 'out_qty' => 3, 'branch_id' => $this->branch('main')]);
        $this->assertEquals(17, $this->onHand($plain));

        // stock count: counted 15 of 17 → −2
        $this->ajaxPost(route('admin.stock.adjustments.store'), [
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'Stock Count', 'post' => 1,
            'items' => [['product_id' => $plain, 'counted_qty' => 15]],
        ])->assertOk();
        $this->assertEquals(15, $this->onHand($plain));
        $this->assertDatabaseHas('stock_adjustment_items', ['product_id' => $plain, 'system_qty' => 17, 'counted_qty' => 15, 'difference' => -2]);

        // cannot take out more than on hand
        $r = $this->ajaxPost(route('admin.stock.adjustments.store'), [
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'Lost', 'post' => 1,
            'items' => [['product_id' => $plain, 'qty' => 16]],
        ]);
        $r->assertStatus(422);
        $this->assertSame(2, DB::table('stock_adjustments')->count());
        $this->assertEquals(15, $this->onHand($plain));

        // a draft does not move stock
        $this->ajaxPost(route('admin.stock.adjustments.store'), [
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'Found',
            'items' => [['product_id' => $plain, 'qty' => 5]],
        ])->assertOk();
        $this->assertSame('Draft', DB::table('stock_adjustments')->orderByDesc('id')->value('adjustment_status'));
        $this->assertEquals(15, $this->onHand($plain));
    }
}
