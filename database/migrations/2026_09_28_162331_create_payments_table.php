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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('code',55)->nullable();
            $table->string('payment_type',55)->nullable();
            $table->datetime('datetime')->nullable();
            $table->decimal('debit',10,2)->nullable();
            $table->decimal('credit',10,2)->nullable();
            $table->bigInteger('purchase_id')->nullable();
            $table->bigInteger('purchase_return_id')->nullable();
            $table->bigInteger('sale_id')->nullable();
            $table->bigInteger('sale_return_id')->nullable();
            $table->bigInteger('expense_id')->nullable();
            $table->bigInteger('bill_id')->nullable();
            $table->string('payment_method',191)->nullable();
            $table->bigInteger('payment_method_id')->nullable();
            $table->string('payment_method_source',191)->nullable();
            $table->bigInteger('payment_method_source_id')->nullable();
            $table->string('payment_method_account',191)->nullable();
            $table->bigInteger('payment_method_account_id')->nullable();

            //common fields start
            $table->enum('deleted',['Yes','No'])->nullable();
            $table->enum('status',['Active','Inactive'])->nullable();

            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->bigInteger('deleted_by')->nullable();

            $table->datetime('created_by_datetime')->nullable();
            $table->datetime('updated_by_datetime')->nullable();
            $table->datetime('deleted_by_datetime')->nullable();
            //common fields end

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
