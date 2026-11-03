<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Request shapes for the sale / purchase / payment screens (as the POS and purchase pages post them).
 * Used by the sales, purchase and stock feature tests.
 */
trait SalesHelpers
{
    /** One cart line (no line discount unless given). */
    protected function line(int $productId, float $qty, float $price, ?int $variantId = null, array $extra = []): array
    {
        return $extra + [
            'product_id' => $productId, 'product_variant_id' => $variantId, 'qty' => $qty, 'sale_price' => $price,
            'discount_type' => 'Amount', 'discount_value' => 0,
        ];
    }

    /** POST sales.store. Wholesale sale to the seeded customer unless $data says otherwise; paid in cash of the branch. */
    protected function postSale(array $items, array $data = [], string $cashLedger = 'cash-main')
    {
        $paid = (float) ($data['paid_amount'] ?? 0);

        return $this->ajaxPost(route('sales.store'), $data + [
            'sale_type' => 'Wholesale',
            'customer_id' => $this->ids['customer'],
            'sale_date' => now()->toDateString(),
            'discount_type' => 'Amount',
            'discount_value' => 0,
            'paid_amount' => $paid,
            'payment_method' => $paid > 0 ? $this->coa('cash') : null,
            'payment_method_account' => $paid > 0 ? $this->coa($cashLedger) : null,
            'items' => $items,
        ]);
    }

    /** POST purchases.store (supplier = seeded supplier). Lines: product_id, qty, purchase_price (+ batch_no / expiry_date). */
    protected function postPurchase(array $items, array $data = [], string $cashLedger = 'cash-main')
    {
        $paid = (float) ($data['paid_amount'] ?? 0);
        $items = array_map(fn ($i) => $i + ['product_variant_id' => null, 'discount_type' => 'Amount', 'discount_value' => 0], $items);

        return $this->ajaxPost(route('purchases.store'), $data + [
            'supplier_id' => $this->ids['supplier'],
            'purchase_date' => now()->toDateString(),
            'discount_type' => 'Amount',
            'discount_value' => 0,
            'paid_amount' => $paid,
            'payment_method' => $paid > 0 ? $this->coa('cash') : null,
            'payment_method_account' => $paid > 0 ? $this->coa($cashLedger) : null,
            'items' => $items,
        ]);
    }

    /** POST admin.payment.store: type 'Payment Receive' (from a customer) or 'Payment' (to a supplier), cash. */
    protected function postPayment(string $type, int $partyId, float $amount, array $data = [], string $cashLedger = 'cash-main')
    {
        return $this->ajaxPost(route('admin.payment.store'), $data + [
            'type' => $type,
            'payment_date' => now()->toDateString(),
            'party_id' => $partyId,
            'amount' => $amount,
            'payment_method' => $this->coa('cash'),
            'payment_method_account' => $this->coa($cashLedger),
        ]);
    }

    protected function lastSale(): object
    {
        return DB::table('sales')->orderByDesc('id')->first();
    }

    protected function lastPurchase(): object
    {
        return DB::table('purchases')->orderByDesc('id')->first();
    }

    protected function onHand(int $productId, string $branch = 'main', ?int $variantId = null, ?int $batchId = null): float
    {
        return \App\Http\Controllers\Admin\StockController::onHand($productId, $variantId, $this->branch($branch), $batchId);
    }

    /** Due as the screen shows it (string, 2 decimals) for a customer / supplier. */
    protected function customerDue(int $partyId): float
    {
        $r = $this->ajaxGet(route('sales.customer.due', $partyId))->assertOk();

        return (float) $r->json('due');
    }

    protected function supplierDue(int $partyId): float
    {
        $r = $this->ajaxGet(route('purchases.supplier.due', $partyId))->assertOk();

        return (float) $r->json('due');
    }
}
