<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The column already exists on databases built from the SQL dump, so only add it when missing.
     * It holds a chart_of_accounts id.
     */
    public function up(): void
    {
        if (Schema::hasColumn('sales', 'payment_method_account')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_method_account')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op: on dump-built databases this column predates the migration.
     */
    public function down(): void
    {
        //
    }
};
