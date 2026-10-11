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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_no', 100)->unique();

            $table->enum('sale_type',['Wholesale','Walk-In']);

            $table->unsignedBigInteger('customer_id')->nullable();

            $table->date('sale_date');

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->string('discount_type', 30)->nullable();

            $table->decimal('discount_value', 15, 2)->default(0);

            $table->decimal('discount_amount', 15, 2)->default(0);

            $table->decimal('grand_total', 15, 2)->default(0);

            $table->decimal('paid_amount', 15, 2)->default(0);

            $table->decimal('due_amount', 15, 2)->default(0);

            $table->string('payment_method', 50)->nullable();

            $table->longtext('remarks')->nullable();
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
        Schema::dropIfExists('sales');
    }
};
