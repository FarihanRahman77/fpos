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
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->string('return_no')->unique();

            $table->BigInteger('purchase_id');
            $table->BigInteger('supplier_id');

            $table->datetime('datetime');

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->enum('discount_type', ['Percentage', 'Amount'])
                ->default('Amount');

            $table->decimal('discount_value', 15, 2)->default(0);

            $table->decimal('discount_amount', 15, 2)->default(0);

            $table->decimal('grand_total', 15, 2)->default(0);

            $table->longtext('remarks')->nullable();

            $table->enum('deleted', ['Yes', 'No'])->default('No');

            $table->BigInteger('deleted_by')->nullable();

            $table->dateTime('created_datetime')->nullable();

            $table->BigInteger('created_by')->nullable();

            $table->enum('status', ['Active', 'Inactive'])->default('Active');

            $table->BigInteger('updated_by')->nullable();

            $table->dateTime('updated_datetime')->nullable();


            $table->timestamps();
                });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
