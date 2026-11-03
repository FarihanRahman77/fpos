<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sale orders become real sales.
 *
 * Before: saving an order charged the customer the WHOLE order total (a payments row of type 'Sale Order'), and a
 * delivery only moved stock out (stocks.reference_type 'Order'), so delivered goods never showed up as sales
 * (dashboard, Sales Report, Product Profit, P&L) and undelivered goods were still owed.
 *
 * After: an order charges nothing. The advance taken with it stays a customer receipt ('Sale Order Payment Receive').
 * Every delivery is a Wholesale sale invoice (sales.sale_order_id → the order, sale_items.sale_order_item_id → the
 * order line) for the delivered lines only; it moves the stock out and charges the delivered value, and the order's
 * advance is applied to it (sales.advance_applied, counted in paid_amount).
 *
 * Data: for every live order that was charged under the old rule (has an active 'Sale Order' payments row):
 *   1. its deliveries so far (the active 'Order' stock-out rows, one invoice per delivery date) become sale invoices
 *      for exactly the delivered quantities at the order's line prices / discounts (order discount spread pro rata);
 *      the stock rows are re-pointed to the new invoice (reference 'Sale'), so stock does not move twice;
 *   2. each invoice charges the customer its value ('Wholesale' payments row) and takes the order's advance first;
 *   3. the old 'Sale Order' charge rows are switched off (deleted = Yes, remark says why).
 * So afterwards each such order adds (delivered value − advance) to the customer's due, instead of (order total −
 * advance). Orders saved before the charge rule existed (no 'Sale Order' row) had no effect on dues and are left
 * as they were. Order SO-000003 was delivered through invoice INV-000004 by an older version; that invoice is linked.
 *
 * Other schema: order VAT (vat_percent = a quotation's fixed rate, else the VAT settings), quotation conversion
 * links + 'Converted' status, receipts applied to an invoice (payments.applied_sale_id), refund choice on sale
 * returns, fractional delivery quantities (sale_order_items.delivery_qty was an integer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $t) {
            if (!Schema::hasColumn('sales', 'sale_order_id')) {
                $t->unsignedBigInteger('sale_order_id')->nullable()->after('customer_id')->index();
            }
            if (!Schema::hasColumn('sales', 'sale_quotation_id')) {
                $t->unsignedBigInteger('sale_quotation_id')->nullable()->after('sale_order_id')->index();
            }
            if (!Schema::hasColumn('sales', 'advance_applied')) {
                $t->decimal('advance_applied', 15, 2)->default(0)->after('paid_amount');
            }
        });
        Schema::table('sale_items', function (Blueprint $t) {
            if (!Schema::hasColumn('sale_items', 'sale_order_item_id')) {
                $t->unsignedBigInteger('sale_order_item_id')->nullable()->after('sale_id')->index();
            }
        });
        DB::statement('UPDATE sale_order_items SET delivery_qty = 0 WHERE delivery_qty IS NULL');
        DB::statement('ALTER TABLE sale_order_items MODIFY delivery_qty DECIMAL(15,4) NOT NULL DEFAULT 0');
        Schema::table('sale_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('sale_orders', 'vat_amount')) {
                $t->decimal('vat_percent', 6, 2)->nullable()->after('discount_amount');
                $t->decimal('vat_amount', 15, 2)->default(0)->after('vat_percent');
                $t->enum('vat_inclusive', ['Yes', 'No'])->default('No')->after('vat_amount');
                $t->unsignedBigInteger('sale_quotation_id')->nullable()->after('customer_id')->index();
            }
        });
        DB::statement("ALTER TABLE sale_quotations MODIFY quotation_status ENUM('Draft','Sent','Accepted','Rejected','Expired','Converted') NOT NULL DEFAULT 'Draft'");
        Schema::table('sale_quotations', function (Blueprint $t) {
            if (!Schema::hasColumn('sale_quotations', 'converted_order_id')) {
                $t->unsignedBigInteger('converted_order_id')->nullable()->after('quotation_status');
                $t->unsignedBigInteger('converted_sale_id')->nullable()->after('converted_order_id');
            }
        });
        Schema::table('payments', function (Blueprint $t) {
            if (!Schema::hasColumn('payments', 'applied_sale_id')) {
                $t->unsignedBigInteger('applied_sale_id')->nullable()->after('sale_id')->index();
            }
        });
        Schema::table('sale_returns', function (Blueprint $t) {
            if (!Schema::hasColumn('sale_returns', 'refund_mode')) {
                $t->enum('refund_mode', ['Credit', 'Cash'])->default('Credit')->after('grand_total');
                $t->decimal('refund_amount', 15, 2)->default(0)->after('refund_mode');
            }
        });

        DB::transaction(fn () => $this->moveOrderCharges());
    }

    private function moveOrderCharges(): void
    {
        $now = now();

        // Old delivery through a sale invoice (remark "Delivered from Sale Order SO-...").
        foreach (DB::table('sales')->whereNull('sale_order_id')->where('remarks', 'like', 'Delivered from Sale Order SO-%')->get(['id', 'remarks']) as $s) {
            $orderId = DB::table('sale_orders')->where('order_no', trim(substr($s->remarks, strlen('Delivered from Sale Order '))))->value('id');
            if ($orderId) {
                DB::table('sales')->where('id', $s->id)->update(['sale_order_id' => $orderId]);
            }
        }

        $orders = DB::table('sale_orders as o')->where('o.deleted', 'No')
            ->whereExists(fn ($q) => $q->from('payments as p')->whereColumn('p.order_id', 'o.id')
                ->where('p.payment_type', 'Sale Order')->where('p.deleted', 'No'))
            ->orderBy('o.id')->get();

        foreach ($orders as $order) {
            $party = DB::table('parties')->where('id', $order->customer_id)->first(['type']);
            $partyType = $party->type ?? 'Customer';
            $lines = DB::table('sale_order_items')->where('sale_order_id', $order->id)->where('deleted', 'No')->orderBy('id')->get();
            $left = [];   // per order line: delivered qty not yet put on an invoice
            foreach ($lines as $l) {
                $left[$l->id] = (float) $l->delivery_qty;
            }

            $stockRows = DB::table('stocks')->where('order_id', $order->id)->where('reference_type', 'Order')
                ->where('deleted', 'No')->whereNull('sale_id')->orderBy('date')->orderBy('id')->get();
            $advanceLeft = (float) $order->advance_paid;

            foreach ($stockRows->groupBy(fn ($r) => substr((string) $r->date, 0, 10)) as $day => $rows) {
                // Delivered quantity of this day per order line (rows matched by product + variant).
                $qty = [];
                foreach ($rows as $r) {
                    $need = (float) $r->out_qty;
                    foreach ($lines as $l) {
                        if ($need <= 0.00005) {
                            break;
                        }
                        if ((int) $l->product_id !== (int) $r->product_id || (int) $l->product_variant_id !== (int) $r->product_variant_id || $left[$l->id] <= 0.00005) {
                            continue;
                        }
                        $take = min($need, $left[$l->id]);
                        $qty[$l->id] = ($qty[$l->id] ?? 0) + $take;
                        $left[$l->id] -= $take;
                        $need -= $take;
                    }
                }
                if (!$qty) {
                    continue;
                }

                $items = [];
                $subtotal = 0.0;
                foreach ($lines as $l) {
                    if (empty($qty[$l->id])) {
                        continue;
                    }
                    $q = $qty[$l->id];
                    $sub = $q * (float) $l->sale_price;
                    $disc = (float) $l->qty > 0 ? round((float) $l->discount_amount * $q / (float) $l->qty, 2) : 0;
                    $disc = min($disc, $sub);
                    $items[] = ['line' => $l, 'qty' => $q, 'sub' => $sub, 'disc' => $disc, 'total' => $sub - $disc];
                    $subtotal += $sub - $disc;
                }
                $headerDisc = (float) $order->subtotal > 0 ? round((float) $order->discount_amount * $subtotal / (float) $order->subtotal, 2) : 0;
                $headerDisc = min($headerDisc, $subtotal);
                $grand = round($subtotal - $headerDisc, 2);
                $applied = round(min($advanceLeft, $grand), 2);
                $advanceLeft -= $applied;

                $last = DB::table('sales')->where('invoice_no', 'like', 'INV-%')->lockForUpdate()->max('invoice_no');
                $invoiceNo = 'INV-' . str_pad((string) ((int) substr((string) $last, 4) + 1), 6, '0', STR_PAD_LEFT);
                $at = $day . ' ' . substr((string) ($rows->first()->created_date ?? $day . ' 12:00:00'), 11, 8);

                $saleId = DB::table('sales')->insertGetId([
                    'branch_id' => $order->branch_id, 'invoice_no' => $invoiceNo, 'sale_type' => 'Wholesale',
                    'customer_id' => $order->customer_id, 'sale_order_id' => $order->id, 'sale_date' => $day,
                    'subtotal' => round($subtotal, 2), 'discount_type' => 'Amount', 'discount_value' => $headerDisc,
                    'discount_amount' => $headerDisc, 'vat_amount' => 0, 'vat_inclusive' => 'No', 'grand_total' => $grand,
                    'paid_amount' => $applied, 'advance_applied' => $applied, 'due_amount' => round($grand - $applied, 2),
                    'remarks' => 'Delivered from Sale Order ' . $order->order_no, 'deleted' => 'No', 'status' => 'Active',
                    'created_by' => $order->created_by, 'created_date' => $at, 'created_at' => $now, 'updated_at' => $now,
                ]);

                foreach ($items as $it) {
                    $l = $it['line'];
                    DB::table('sale_items')->insert([
                        'sale_id' => $saleId, 'sale_order_item_id' => $l->id,
                        'product_id' => $l->product_id, 'product_variant_id' => $l->product_variant_id, 'qty' => $it['qty'],
                        'sale_price' => $l->sale_price, 'purchase_price' => (float) DB::table('products')->where('id', $l->product_id)->value('purchase_price'),
                        'discount_type' => 'Amount', 'discount_value' => $it['disc'], 'discount_amount' => $it['disc'],
                        'subtotal' => round($it['sub'], 2), 'total' => round($it['total'], 2), 'vat_percent' => 0, 'vat_amount' => 0,
                        'deleted' => 'No', 'status' => 'Active', 'created_by' => $order->created_by, 'created_date' => $at,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }

                DB::table('stocks')->whereIn('id', $rows->pluck('id')->all())->update([
                    'sale_id' => $saleId, 'reference_type' => 'Sale', 'reference_id' => $saleId,
                    'remarks' => 'Sale: ' . $invoiceNo . ' (order ' . $order->order_no . ')',
                ]);

                $lastCode = DB::table('payments')->where('code', 'like', 'VO-%')->lockForUpdate()->max('code');
                DB::table('payments')->insert([
                    'branch_id' => $order->branch_id, 'party_id' => $order->customer_id, 'party_type' => $partyType,
                    'code' => 'VO-' . str_pad((string) ((int) substr((string) $lastCode, 3) + 1), 6, '0', STR_PAD_LEFT),
                    'payment_type' => 'Wholesale', 'datetime' => $at, 'debit' => 0, 'credit' => $grand, 'sale_id' => $saleId,
                    'remarks' => 'Delivery of ' . $order->order_no, 'status' => 'Active', 'deleted' => 'No',
                    'created_by' => $order->created_by, 'created_datetime' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('payments')->where('order_id', $order->id)->where('payment_type', 'Sale Order')->where('deleted', 'No')->update([
                'deleted' => 'Yes', 'status' => 'Inactive', 'deleted_datetime' => $now, 'updated_datetime' => $now,
                'remarks' => DB::raw("CONCAT(COALESCE(remarks, ''), ' [order charge replaced by delivery invoices, 2026-11-09 migration]')"),
            ]);
        }
    }

    public function down(): void
    {
        // The data move is not reversed (delivery invoices are real sales now); only unused columns could be dropped.
    }
};
