<?php

namespace Tests\Concerns;

use App\Http\Controllers\Admin\AccountingReportController;
use Illuminate\Support\Facades\DB;

/**
 * Posting documents through their endpoints, and reading the general ledger the statements are built from.
 * Used by the accounting feature tests (expects Tests\AppTestCase + SeedsApp).
 */
trait AccountingHelpers
{
    protected function postExpense(string $date, float $amount, string $ledger = 'rent-expense', string $payGroup = 'cash', string $payAccount = 'cash-main')
    {
        return $this->ajaxPost(route('admin.expense.store'), [
            'expense_date' => $date,
            'payment_group_id' => $this->coa($payGroup),
            'payment_account_id' => $this->coa($payAccount),
            'note' => 'Test expense',
            'details' => [[
                'expense_group_id' => $this->coa('operating-expenses'),
                'expense_account_id' => $this->coa($ledger),
                'description' => 'Line',
                'amount' => $amount,
            ]],
        ]);
    }

    protected function postBill(string $date, array $lines, float $discount = 0)
    {
        return $this->ajaxPost(route('admin.bill.store'), [
            'bill_date' => $date,
            'party_id' => $this->ids['supplier'],
            'discount_amount' => $discount,
            'details' => array_map(fn ($l) => [
                'account_group_id' => $this->coa('operating-expenses'),
                'account_id' => $this->coa($l[0]),
                'description' => 'Bill line',
                'qty' => $l[1],
                'rate' => $l[2],
            ], $lines),
        ]);
    }

    protected function postBillPayment(int $billId, string $date, float $amount, string $payGroup = 'cash', string $payAccount = 'cash-main')
    {
        return $this->ajaxPost(route('admin.bill.payment.store'), [
            'bill_id' => $billId,
            'payment_date' => $date,
            'payment_group_id' => $this->coa($payGroup),
            'payment_account_id' => $this->coa($payAccount),
            'amount' => $amount,
        ]);
    }

    /** $lines: [[slug, group slug, debit, credit], ...] */
    protected function postJournal(string $date, array $lines)
    {
        return $this->ajaxPost(route('admin.journal.store'), [
            'journal_date' => $date,
            'remarks' => 'Test journal',
            'details' => array_map(fn ($l) => [
                'account_group_id' => $this->coa($l[1]),
                'account_id' => $this->coa($l[0]),
                'debit' => $l[2],
                'credit' => $l[3],
            ], $lines),
        ]);
    }

    protected function postTransfer(string $date, float $amount, string $from = 'cash-main', string $fromGroup = 'cash', string $to = 'bank-account', string $toGroup = 'bank')
    {
        return $this->ajaxPost(route('admin.transfer.store'), [
            'transfer_date' => $date,
            'from_group_id' => $this->coa($fromGroup),
            'from_account_id' => $this->coa($from),
            'to_group_id' => $this->coa($toGroup),
            'to_account_id' => $this->coa($to),
            'amount' => $amount,
        ]);
    }

    /** Wholesale sale of the plain product to the seeded customer, part paid in cash (main branch). */
    protected function postSale(string $date, float $qty, float $price, float $paid)
    {
        return $this->ajaxPost(route('sales.store'), [
            'sale_type' => 'Wholesale',
            'customer_id' => $this->ids['customer'],
            'sale_date' => $date,
            'discount_type' => 'Amount',
            'discount_value' => 0,
            'paid_amount' => $paid,
            'payment_method' => $this->coa('cash'),
            'payment_method_account' => $this->coa('cash-main'),
            'items' => [[
                'product_id' => $this->ids['product.plain'],
                'qty' => $qty,
                'sale_price' => $price,
                'discount_type' => 'Amount',
                'discount_value' => 0,
            ]],
        ]);
    }

    protected function postPurchase(string $date, float $qty, float $price, float $paid)
    {
        return $this->ajaxPost(route('purchases.store'), [
            'supplier_id' => $this->ids['supplier'],
            'purchase_date' => $date,
            'discount_type' => 'Amount',
            'discount_value' => 0,
            'paid_amount' => $paid,
            'payment_method' => $paid > 0 ? $this->coa('cash') : null,
            'payment_method_account' => $paid > 0 ? $this->coa('cash-main') : null,
            'items' => [[
                'product_id' => $this->ids['product.plain'],
                'qty' => $qty,
                'purchase_price' => $price,
                'discount_type' => 'Amount',
                'discount_value' => 0,
            ]],
        ]);
    }

    protected function postReceipt(string $date, float $amount)
    {
        return $this->ajaxPost(route('admin.payment.store'), [
            'type' => 'Payment Receive',
            'payment_date' => $date,
            'party_id' => $this->ids['customer'],
            'amount' => $amount,
            'payment_method' => $this->coa('cash'),
            'payment_method_account' => $this->coa('cash-main'),
        ]);
    }

    /** General ledger (all branches) up to $to, with fresh per-request caches. */
    protected function gl(?string $from = null, ?string $to = null): array
    {
        self::resetAppStatics();

        return AccountingReportController::gl($from, $to ?? now()->toDateString(), AccountingReportController::allBranches());
    }

    protected function balancesAt(?string $asAt = null): array
    {
        self::resetAppStatics();

        return AccountingReportController::balances($asAt ?? now()->toDateString(), AccountingReportController::allBranches());
    }

    protected function acc(array $acc, string $slug): float
    {
        return round((float) ($acc['a:' . $this->coa($slug)] ?? 0), 2);
    }

    /** "1,234.50" → 1234.5 for the formatted summary values the statement endpoints return. */
    protected function money(string $formatted): float
    {
        return (float) str_replace(',', '', $formatted);
    }

    protected function summaryValue(array $summary, string $label): float
    {
        foreach ($summary as $s) {
            if (str_starts_with($s['label'], $label)) {
                return $this->money($s['value']);
            }
        }
        $this->fail("No summary card '$label' in " . json_encode($summary));
    }

    protected function latestId(string $table): int
    {
        return (int) DB::table($table)->max('id');
    }
}
