<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A drawer opens with what was counted at the previous closing; the opening is kept with each closing. */
    public function up(): void
    {
        Schema::table('cash_closings', function (Blueprint $table) {
            $table->decimal('opening_cash', 18, 2)->default(0)->after('cashier_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_closings', function (Blueprint $table) {
            $table->dropColumn('opening_cash');
        });
    }
};
