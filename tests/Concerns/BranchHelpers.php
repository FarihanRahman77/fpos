<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Documents created directly in a given branch (explicit branch_id, bypassing the @fpos_branch trigger), each with
 * a unique, searchable number so list responses can be checked with assertSee / assertDontSee.
 * Used by the branch isolation tests; requires SeedsApp.
 */
trait BranchHelpers
{
    protected function sale(string $branch, string $invoiceNo, float $total, ?int $customerId = null, float $paid = 0): int
    {
        $customerId ??= $this->ids['customer'];
        $id = DB::table('sales')->insertGetId([
            'branch_id' => $this->branch($branch), 'invoice_no' => $invoiceNo, 'sale_type' => 'Wholesale', 'customer_id' => $customerId,
            'sale_date' => now()->toDateString(), 'subtotal' => $total, 'grand_total' => $total, 'paid_amount' => $paid, 'due_amount' => $total - $paid,
            'deleted' => 'No', 'status' => 'Active', 'created_by' => $this->ids['user.superadmin'], 'created_date' => now(),
        ]);
        DB::table('sale_items')->insert([
            'sale_id' => $id, 'product_id' => $this->ids['product.plain'], 'qty' => 1, 'sale_price' => $total, 'purchase_price' => 50,
            'subtotal' => $total, 'total' => $total, 'deleted' => 'No', 'status' => 'Active',
        ]);
        $this->payment($branch, 'SAL-' . $invoiceNo, 'Wholesale', 0, $total, $customerId, 'Customer', ['sale_id' => $id]);
        DB::table('stocks')->insert([
            'branch_id' => $this->branch($branch), 'product_id' => $this->ids['product.plain'], 'in_qty' => 0, 'out_qty' => 1, 'sale_id' => $id,
            'reference_type' => 'Sale', 'reference_id' => $id, 'date' => now(), 'deleted' => 'No', 'status' => 'Active',
        ]);

        return $id;
    }

    protected function purchase(string $branch, string $invoiceNo, float $total): int
    {
        $id = DB::table('purchases')->insertGetId([
            'branch_id' => $this->branch($branch), 'supplier_id' => $this->ids['supplier'], 'invoice_no' => $invoiceNo,
            'purchase_date' => now()->toDateString(), 'subtotal' => $total, 'grand_total' => $total, 'due_amount' => $total,
            'deleted' => 'No', 'status' => 'Active', 'created_by' => $this->ids['user.superadmin'],
        ]);
        DB::table('purchase_items')->insert([
            'purchase_id' => $id, 'product_id' => $this->ids['product.plain'], 'qty' => 2, 'purchase_price' => $total / 2,
            'subtotal' => $total, 'total' => $total, 'deleted' => 'No', 'status' => 'Active',
        ]);
        $this->payment($branch, 'PUR-' . $invoiceNo, 'Purchase', 0, $total, $this->ids['supplier'], 'Supplier', ['purchase_id' => $id]);

        return $id;
    }

    /** A payments row (debit / credit as the PaymentTrait stores them). */
    protected function payment(string $branch, string $code, string $type, float $debit, float $credit, ?int $partyId = null, ?string $partyType = 'Customer', array $extra = []): int
    {
        return DB::table('payments')->insertGetId($extra + [
            'branch_id' => $this->branch($branch), 'party_id' => $partyId ?? $this->ids['customer'], 'party_type' => $partyType, 'code' => $code,
            'payment_type' => $type, 'datetime' => now()->toDateTimeString(), 'debit' => $debit, 'credit' => $credit,
            'payment_method' => 'Cash', 'payment_method_id' => $this->coa('cash'),
            'payment_method_account' => 'Cash', 'payment_method_account_id' => $this->coa('cash-' . $branch),
            'deleted' => 'No', 'status' => 'Active', 'created_by' => $this->ids['user.superadmin'], 'created_datetime' => now(),
        ]);
    }

    protected function receipt(string $branch, string $code, float $amount): int
    {
        return $this->payment($branch, $code, 'Payment Receive', $amount, 0);
    }

    protected function expense(string $branch, string $expenseNo, float $amount): int
    {
        $id = DB::table('expenses')->insertGetId([
            'branch_id' => $this->branch($branch), 'expense_no' => $expenseNo, 'expense_date' => now()->toDateString(),
            'payment_group_id' => $this->coa('cash'), 'payment_account_id' => $this->coa('cash-' . $branch), 'total_amount' => $amount,
            'remarks' => 'Expense ' . $expenseNo, 'deleted' => 'No', 'status' => 'Active', 'created_by' => $this->ids['user.superadmin'],
        ]);
        DB::table('expense_details')->insert([
            'expense_id' => $id, 'expense_group_id' => $this->coa('operating-expenses'), 'expense_account_id' => $this->coa('rent-expense'),
            'amount' => $amount, 'deleted' => 'No', 'status' => 'Active',
        ]);

        return $id;
    }

    protected function employee(string $branch, string $code, string $name): int
    {
        return DB::table('employees')->insertGetId([
            'branch_id' => $this->branch($branch), 'employee_code' => $code, 'name' => $name, 'joining_date' => now()->subYear()->toDateString(),
            'basic_salary' => 10000, 'status' => 'Active', 'deleted' => 'No', 'created_by' => $this->ids['user.superadmin'],
        ]);
    }
}
