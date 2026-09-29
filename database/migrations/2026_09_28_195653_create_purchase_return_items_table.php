<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->BigInteger('purchase_return_id');

            $table->BigInteger('purchase_id');

            $table->BigInteger('purchase_item_id');

            $table->BigInteger('product_id');

            $table->BigInteger('product_variant_id')->nullable();

            $table->decimal('qty', 15, 4)->default(0);

            $table->decimal('purchase_price', 15, 2)->default(0);

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('discount_type', 15, 2)->nullable();

            $table->decimal('discount_value', 15, 2)->default(0);

            $table->decimal('discount_amount', 15, 2)->default(0);

            $table->decimal('total', 15, 2)->default(0);

            $table->enum('deleted', ['Yes', 'No'])->default('No');

            $table->BigInteger('deleted_by')->nullable();

            $table->dateTime('created_datetime')->nullable();

            $table->BigInteger('created_by')->nullable();

            $table->enum('status', ['Active', 'Inactive'])->default('Active');

            $table->BigInteger('updated_by')->nullable();

            $table->dateTime('updated_datetime')->nullable();
            
            $table->dateTime('deleted_datetime')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
