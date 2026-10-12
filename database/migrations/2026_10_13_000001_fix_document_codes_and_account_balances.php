<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Code columns that must be unique. Sales, sale orders, sale returns and expenses already are.
     */
    private array $codeColumns = [
        'purchases'        => 'invoice_no',
        'purchase_returns' => 'return_no',
        'payments'         => 'code',
        'parties'          => 'code',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            // 1. Purchase numbers were reused after deletes. Keep the live row's number and
            //    suffix the soft-deleted duplicate with its id (e.g. PUR-000001-D6).
            foreach ($this->codeColumns as $table => $column) {
                $duplicates = DB::table($table)
                    ->select($column)
                    ->whereNotNull($column)
                    ->groupBy($column)
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck($column);

                foreach ($duplicates as $code) {
                    $rows = DB::table($table)
                        ->where($column, $code)
                        ->orderByRaw("deleted = 'No' DESC")
                        ->orderBy('id')
                        ->get(['id', 'deleted']);

                    // First row (live if one exists) keeps the number.
                    foreach ($rows->slice(1) as $row) {
                        if ($row->deleted !== 'Yes') {
                            throw new RuntimeException("Two live rows in {$table} share {$column} {$code}; fix manually.");
                        }
                        DB::table($table)->where('id', $row->id)->update([$column => $code . '-D' . $row->id]);
                    }
                }
            }

            // 2. Normal balances: Income is credit-normal, Expense is debit-normal.
            DB::table('chart_of_accounts')->where('account_type', 'Income')->update(['balance' => 'Credit']);
            DB::table('chart_of_accounts')->where('account_type', 'Expense')->update(['balance' => 'Debit']);

            // 3. Purchase stock rows were saved without a reference type.
            DB::table('stocks')
                ->whereNotNull('purchase_id')
                ->whereNull('purchase_return_id')
                ->whereNull('reference_type')
                ->update(['reference_type' => 'Purchase', 'reference_id' => DB::raw('purchase_id')]);

            // 4. Soft-deleted rows left behind when sale return #3 was removed outside the app.
            DB::table('sale_return_items')
                ->where('deleted', 'Yes')
                ->whereNotIn('sale_return_id', DB::table('sale_returns')->select('id'))
                ->delete();
            DB::table('stocks')
                ->where('deleted', 'Yes')
                ->whereNotNull('sale_return_id')
                ->whereNotIn('sale_return_id', DB::table('sale_returns')->select('id'))
                ->delete();
        });

        // DDL commits implicitly, so it runs after the data transaction.
        foreach ($this->codeColumns as $table => $column) {
            if (!$this->hasIndex($table, "{$table}_{$column}_unique")) {
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$table}_{$column}_unique` (`{$column}`)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->codeColumns as $table => $column) {
            if ($this->hasIndex($table, "{$table}_{$column}_unique")) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$table}_{$column}_unique`");
            }
        }
        // Data fixes (renamed duplicates, balances, cleanup) are not reverted.
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
