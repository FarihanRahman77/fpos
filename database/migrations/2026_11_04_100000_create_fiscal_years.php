<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accounting years. Closing moves the year's Income / Expense ledger balances into a Retained Earnings ledger.
        // Company-wide (not per branch): chart_of_accounts balances are company-wide.
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['Open', 'Closed'])->default('Open');
            $table->decimal('net_profit', 18, 2)->nullable();          // posted to retained earnings (ledger based)
            $table->decimal('report_net_profit', 18, 2)->nullable();   // Profit & Loss statement figure, for reference
            $table->foreignId('retained_earnings_account_id')->nullable()->constrained('chart_of_accounts');
            $table->string('remarks', 500)->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->dateTime('reopened_at')->nullable();
            $table->enum('deleted', ['Yes', 'No'])->default('No');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->dateTime('deleted_datetime')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
        });

        // Snapshot of every 3rd layer ledger when a year is closed, and the amount the closing posted to it.
        Schema::create('fiscal_year_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->string('account_code', 30)->nullable();
            $table->string('account_name', 150)->nullable();
            $table->string('account_type', 30)->nullable();
            $table->enum('normal_side', ['Debit', 'Credit']);
            $table->decimal('balance', 18, 2)->default(0);          // as at end_date, before closing (normal-side sign)
            $table->decimal('closing_delta', 18, 2)->default(0);    // change the closing made (normal-side sign)
            $table->decimal('closed_balance', 18, 2)->default(0);   // balance + closing_delta
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_balances');
        Schema::dropIfExists('fiscal_years');
    }
};
