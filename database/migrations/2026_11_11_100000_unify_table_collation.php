<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Four tables were created with utf8mb4_general_ci while the app (config/database.php) and every other table use
     * utf8mb4_unicode_ci. Comparing text across them (e.g. a barcode join between product_variants and products) fails
     * with "Illegal mix of collations" (1267). Same character set, so the data is unchanged; triggers are kept.
     */
    private const TABLES = ['product_variants', 'purchases', 'purchase_items', 'roles'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        // New tables created outside migrations get the same default.
        DB::statement('ALTER DATABASE `' . DB::getDatabaseName() . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        }
    }
};
