<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the branch-scoped list/report queries (every read is "branch_id = ? AND <date> BETWEEN ..."
 * or "party_id = ? AND branch_id = ?"), plus wider payments.debit/credit so a single entry can hold the same
 * amounts as the documents it mirrors (purchases/sales totals are decimal(15..18,2), payments were decimal(10,2)).
 * Non-breaking: adds indexes only when missing and only widens columns.
 */
return new class extends Migration
{
    private array $indexes = [
        // table => [index name => columns]
        'payments'      => [
            'payments_branch_datetime_index' => ['branch_id', 'datetime'],
            'payments_party_branch_index'    => ['party_id', 'branch_id'],
        ],
        'sales'         => ['sales_branch_sale_date_index' => ['branch_id', 'sale_date']],
        'purchases'     => ['purchases_branch_purchase_date_index' => ['branch_id', 'purchase_date']],
        'expenses'      => ['expenses_branch_expense_date_index' => ['branch_id', 'expense_date']],
        'journals'      => ['journals_branch_journal_date_index' => ['branch_id', 'journal_date']],
        'stocks'        => [
            'stocks_branch_product_variant_index' => ['branch_id', 'product_id', 'product_variant_id'],
            'stocks_reference_index'              => ['reference_type', 'reference_id'],
        ],
        'activity_logs' => ['activity_logs_branch_created_index' => ['branch_id', 'created_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $list) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($list as $name => $columns) {
                if (!$this->hasIndex($table, $name) && Schema::hasColumns($table, $columns)) {
                    Schema::table($table, fn ($t) => $t->index($columns, $name));
                }
            }
        }

        if (Schema::hasTable('payments') && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `payments` MODIFY `debit` DECIMAL(18,2) NULL DEFAULT NULL, MODIFY `credit` DECIMAL(18,2) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $list) {
            foreach ($list as $name => $columns) {
                if (Schema::hasTable($table) && $this->hasIndex($table, $name)) {
                    Schema::table($table, fn ($t) => $t->dropIndex($name));
                }
            }
        }
        // payments.debit/credit stay decimal(18,2): narrowing could truncate saved amounts.
    }

    private function hasIndex(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
