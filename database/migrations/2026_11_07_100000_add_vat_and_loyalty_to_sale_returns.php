<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sale returns carry the returned share of the sale's VAT (per item and in total) so the VAT report can
     * subtract it, and the loyalty points taken back from the customer, so deleting the return can give them back.
     * loyalty_transactions.sale_return_id ties those point movements to the return.
     * Existing returns get their VAT share filled in (points are not touched retroactively).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('sale_returns', 'vat_amount')) {
            Schema::table('sale_returns', function (Blueprint $table) {
                $table->decimal('vat_amount', 15, 2)->default(0)->after('subtotal');
                $table->decimal('loyalty_points_reversed', 18, 2)->default(0)->after('grand_total');
            });
        }
        if (!Schema::hasColumn('sale_return_items', 'vat_amount')) {
            Schema::table('sale_return_items', function (Blueprint $table) {
                $table->decimal('vat_amount', 15, 2)->default(0)->after('subtotal');
            });
        }
        if (!Schema::hasColumn('loyalty_transactions', 'sale_return_id')) {
            Schema::table('loyalty_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('sale_return_id')->nullable()->after('sale_id')->index();
            });
        }

        // Backfill: each return line's share of its sale line's VAT.
        DB::statement("UPDATE sale_return_items ri JOIN sale_items si ON si.id = ri.sale_item_id
            SET ri.vat_amount = ROUND(si.vat_amount * ri.qty / si.qty, 2)
            WHERE si.qty > 0 AND si.vat_amount > 0");
        DB::statement("UPDATE sale_returns r SET r.vat_amount = (SELECT COALESCE(SUM(ri.vat_amount), 0) FROM sale_return_items ri
            WHERE ri.sale_return_id = r.id AND ri.deleted = 'No')");
    }

    public function down(): void
    {
        if (Schema::hasColumn('loyalty_transactions', 'sale_return_id')) {
            Schema::table('loyalty_transactions', function (Blueprint $table) {
                $table->dropIndex(['sale_return_id']);
                $table->dropColumn('sale_return_id');
            });
        }
        if (Schema::hasColumn('sale_return_items', 'vat_amount')) {
            Schema::table('sale_return_items', fn (Blueprint $table) => $table->dropColumn('vat_amount'));
        }
        if (Schema::hasColumn('sale_returns', 'vat_amount')) {
            Schema::table('sale_returns', fn (Blueprint $table) => $table->dropColumn(['vat_amount', 'loyalty_points_reversed']));
        }
    }
};
