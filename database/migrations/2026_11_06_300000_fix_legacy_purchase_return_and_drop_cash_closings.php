<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. Purchase returns saved before the sign fix stored the return as a debit, which raised the supplier's due;
     *    a return lowers it, so it is a credit (as PurchaseController now writes it).
     * 2. The per-cashier cash_closings table was replaced by cash_day_closings (one closing per branch per day).
     */
    public function up(): void
    {
        DB::table('payments')->whereNotNull('purchase_return_id')->where('payment_type', 'Purchase Payment')
            ->where('debit', '>', 0)->where('credit', 0)
            ->update(['credit' => DB::raw('debit'), 'debit' => 0, 'updated_at' => now()]);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_cash_closings_branch');
        Schema::dropIfExists('cash_closings');
    }

    public function down(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->date('closing_date');
            $table->foreignId('cashier_id')->constrained('users');
            $table->decimal('opening_cash', 18, 2)->default(0);
            $table->decimal('expected_cash', 18, 2)->default(0);
            $table->decimal('counted_cash', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->string('remarks', 255)->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'closing_date', 'cashier_id']);
        });
    }
};
