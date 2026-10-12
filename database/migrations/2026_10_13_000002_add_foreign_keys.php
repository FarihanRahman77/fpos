<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * [child table, child column, parent table]. Parent key is always `id`.
     *
     * Deliberately not constrained:
     *  - payments.party_id: points to parties OR employees depending on party_type.
     *  - created_by / updated_by / deleted_by: users can be deleted from the profile page.
     *  - stocks.damage_id / adjustment_id, payments.bill_id: no parent tables yet.
     */
    private array $foreignKeys = [
        ['products', 'brand_id', 'brands'],
        ['products', 'category_id', 'categories'],
        ['products', 'unit_id', 'units'],
        ['product_variants', 'product_id', 'products'],
        ['product_variants', 'attribute_type_id', 'attribute_types'],
        ['product_variants', 'attribute_id', 'attributes'],
        ['product_attributes', 'product_id', 'products'],
        ['product_attributes', 'attribute_type_id', 'attribute_types'],
        ['product_attributes', 'attribute_id', 'attributes'],
        ['product_specs', 'product_id', 'products'],
        ['attributes', 'attribute_type_id', 'attribute_types'],

        ['purchases', 'supplier_id', 'parties'],
        ['purchases', 'payment_method_account', 'chart_of_accounts'],
        ['purchase_items', 'purchase_id', 'purchases'],
        ['purchase_items', 'product_id', 'products'],
        ['purchase_items', 'product_variant_id', 'product_variants'],
        ['purchase_returns', 'purchase_id', 'purchases'],
        ['purchase_returns', 'supplier_id', 'parties'],
        ['purchase_return_items', 'purchase_return_id', 'purchase_returns'],
        ['purchase_return_items', 'purchase_id', 'purchases'],
        ['purchase_return_items', 'purchase_item_id', 'purchase_items'],
        ['purchase_return_items', 'product_id', 'products'],
        ['purchase_return_items', 'product_variant_id', 'product_variants'],

        ['sales', 'customer_id', 'parties'],
        ['sales', 'payment_method_account', 'chart_of_accounts'],
        ['sale_items', 'sale_id', 'sales'],
        ['sale_items', 'product_id', 'products'],
        ['sale_items', 'product_variant_id', 'product_variants'],
        ['sale_returns', 'sale_id', 'sales'],
        ['sale_returns', 'customer_id', 'parties'],
        ['sale_return_items', 'sale_return_id', 'sale_returns'],
        ['sale_return_items', 'sale_id', 'sales'],
        ['sale_return_items', 'sale_item_id', 'sale_items'],
        ['sale_return_items', 'product_id', 'products'],
        ['sale_return_items', 'product_variant_id', 'product_variants'],
        ['sale_orders', 'customer_id', 'parties'],
        ['sale_order_items', 'sale_order_id', 'sale_orders'],
        ['sale_order_items', 'product_id', 'products'],
        ['sale_order_items', 'product_variant_id', 'product_variants'],

        ['stocks', 'product_id', 'products'],
        ['stocks', 'product_variant_id', 'product_variants'],
        ['stocks', 'purchase_id', 'purchases'],
        ['stocks', 'sale_id', 'sales'],
        ['stocks', 'purchase_return_id', 'purchase_returns'],
        ['stocks', 'sale_return_id', 'sale_returns'],
        ['stocks', 'order_id', 'sale_orders'],

        ['expenses', 'employee_id', 'employees'],
        ['expenses', 'payment_group_id', 'chart_of_accounts'],
        ['expenses', 'payment_account_id', 'chart_of_accounts'],
        ['expense_details', 'expense_id', 'expenses'],
        ['expense_details', 'expense_group_id', 'chart_of_accounts'],
        ['expense_details', 'expense_account_id', 'chart_of_accounts'],

        ['payments', 'sale_id', 'sales'],
        ['payments', 'purchase_id', 'purchases'],
        ['payments', 'sale_return_id', 'sale_returns'],
        ['payments', 'purchase_return_id', 'purchase_returns'],
        ['payments', 'expense_id', 'expenses'],
        ['payments', 'order_id', 'sale_orders'],
        ['payments', 'payment_method_id', 'chart_of_accounts'],
        ['payments', 'payment_method_account_id', 'chart_of_accounts'],

        ['chart_of_accounts', 'parent_id', 'chart_of_accounts'],
        ['users', 'role_id', 'roles'],
        ['role_permissions', 'role_id', 'roles'],
        ['role_permissions', 'permission_id', 'permissions'],
    ];

    public function up(): void
    {
        // 1. Refuse to start if any reference is broken, so nothing is half-applied.
        $problems = [];
        foreach ($this->foreignKeys as [$table, $column, $parent]) {
            $orphans = DB::table("{$table} as c")
                ->whereNotNull("c.{$column}")
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from("{$parent} as p")->whereColumn('p.id', "c.{$column}"))
                ->pluck('c.id');

            if ($orphans->isNotEmpty()) {
                $problems[] = "{$table}.{$column} -> {$parent}: ids " . $orphans->implode(', ');
            }
        }
        if ($problems) {
            throw new RuntimeException("Fix these broken references before migrating:\n" . implode("\n", $problems));
        }

        // 2. Keys must be BIGINT UNSIGNED on both sides. purchase_returns.id is signed;
        //    several child columns are INT or signed BIGINT.
        foreach (array_unique(array_column($this->foreignKeys, 2)) as $parent) {
            if ($this->column($parent, 'id')->COLUMN_TYPE !== 'bigint(20) unsigned') {
                DB::statement("ALTER TABLE `{$parent}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
            }
        }

        foreach ($this->foreignKeys as [$table, $column]) {
            $col = $this->column($table, $column);
            if ($col->COLUMN_TYPE !== 'bigint(20) unsigned') {
                $null = $col->IS_NULLABLE === 'YES' ? 'NULL DEFAULT NULL' : 'NOT NULL';
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED {$null}");
            }
        }

        // Stored as DECIMAL by mistake; every other discount_type is this enum.
        DB::statement("ALTER TABLE `purchase_return_items` MODIFY `discount_type` ENUM('Percentage','Amount') NULL DEFAULT NULL");

        // 3. Constraints. Default ON DELETE RESTRICT: rows are soft-deleted, so a hard delete
        //    of a referenced parent is a mistake that should fail.
        foreach ($this->foreignKeys as [$table, $column, $parent]) {
            $name = "{$table}_{$column}_foreign";
            if (!$this->hasConstraint($table, $name)) {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$parent}` (`id`)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->foreignKeys as [$table, $column]) {
            $name = "{$table}_{$column}_foreign";
            if ($this->hasConstraint($table, $name)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            }
        }
        // Column type widening is kept: it is compatible with the old data.
    }

    private function column(string $table, string $column): object
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->first(['COLUMN_TYPE', 'IS_NULLABLE']);
    }

    private function hasConstraint(string $table, string $name): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $name)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
