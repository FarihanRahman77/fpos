<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAT (sales output VAT, purchase input VAT), loyalty points and the columns a sale needs for them.
 * Settings (rates, rules) live in system_settings (SystemSettingController::get / put).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('vat_percent', 6, 2)->nullable()->after('discount');   // null = default VAT rate
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('vat_amount', 18, 2)->default(0)->after('discount_amount');
            $table->enum('vat_inclusive', ['Yes', 'No'])->default('No')->after('vat_amount');
            $table->decimal('loyalty_points_redeemed', 18, 2)->default(0)->after('vat_inclusive');
            $table->decimal('loyalty_discount', 18, 2)->default(0)->after('loyalty_points_redeemed');
            $table->decimal('loyalty_points_earned', 18, 2)->default(0)->after('loyalty_discount');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('vat_percent', 6, 2)->default(0)->after('total');
            $table->decimal('vat_amount', 18, 2)->default(0)->after('vat_percent');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('vat_percent', 6, 2)->default(0)->after('discount_amount');
            $table->decimal('vat_amount', 18, 2)->default(0)->after('vat_percent');
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->decimal('loyalty_points', 18, 2)->default(0)->after('credit_limit');
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('party_id')->constrained('parties');
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->enum('txn_type', ['Earn', 'Redeem', 'Adjust', 'Reverse']);
            $table->decimal('points', 18, 2);              // + earned / − redeemed
            $table->decimal('balance_after', 18, 2);
            $table->decimal('amount', 18, 2)->default(0);  // sale amount (earn) or discount given (redeem)
            $table->string('remarks', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->timestamps();
            $table->index(['party_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::table('parties', fn (Blueprint $t) => $t->dropColumn('loyalty_points'));
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn(['vat_percent', 'vat_amount']));
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropColumn(['vat_percent', 'vat_amount']));
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn(['vat_amount', 'vat_inclusive', 'loyalty_points_redeemed', 'loyalty_discount', 'loyalty_points_earned']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('vat_percent'));
    }
};
