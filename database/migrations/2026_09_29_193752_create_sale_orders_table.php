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
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 100)->unique();

            $table->unsignedBigInteger('customer_id')->nullable();

            $table->date('order_date');

            $table->date('delivery_date')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->string('discount_type', 30)->nullable();

            $table->decimal('discount_value', 15, 2)->default(0);

            $table->decimal('discount_amount', 15, 2)->default(0);

            $table->decimal('grand_total', 15, 2)->default(0);

            $table->decimal('advance_paid', 15, 2)->default(0);

            $table->decimal('due_amount', 15, 2)->default(0);

            $table->string('order_status', 30)->default('Pending');



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
        Schema::dropIfExists('sale_orders');
    }
};
