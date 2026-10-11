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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sale_id');

            $table->unsignedBigInteger('product_id');

            $table->unsignedBigInteger('product_variant_id')->nullable();

            $table->decimal('qty', 15, 4)->default(0);

            $table->decimal('sale_price', 15, 2)->default(0);

            $table->decimal('purchase_price', 15, 2)->default(0);

            $table->string('discount_type', 30)->nullable();

            $table->decimal('discount_value', 15, 2)->default(0);

            $table->decimal('discount_amount', 15, 2)->default(0);

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('total', 15, 2)->default(0);
            
            $table->enum('deleted', ['Yes','No'])->default('No');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->enum('status', ['Active','Inactive'])->default('Active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
