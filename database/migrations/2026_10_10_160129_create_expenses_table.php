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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->string('expense_no', 30)->nullable()->unique();
            $table->date('expense_date');

            // Both point to 3rd layer accounts of chart_of_accounts.
            $table->unsignedBigInteger('expense_account_id');   // account_type = Expense
            $table->unsignedBigInteger('payment_account_id');   // account_type = Liquid Asset (Bank / Cash ...)

            $table->string('payee', 150)->nullable();
            $table->string('payment_method', 30);
            $table->string('reference_no', 100)->nullable();

            $table->decimal('amount', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2);

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
        Schema::dropIfExists('expenses');
    }
};
