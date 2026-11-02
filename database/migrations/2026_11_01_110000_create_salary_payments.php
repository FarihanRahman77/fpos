<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledgers used when salary is paid (remembered from the last payment).
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->foreignId('salary_expense_account_id')->nullable()->after('bonus_percent')->constrained('chart_of_accounts');
            $table->foreignId('pf_account_id')->nullable()->after('salary_expense_account_id')->constrained('chart_of_accounts');
            $table->foreignId('salary_payment_account_id')->nullable()->after('pf_account_id')->constrained('chart_of_accounts');
        });

        // One run of "Pay Salary" for a month: Dr salary expense, Cr cash/bank (paid) + PF payable (PF).
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 20)->unique();
            $table->date('month');                           // first day of the salary month
            $table->date('payment_date');
            $table->foreignId('payment_account_id')->constrained('chart_of_accounts');
            $table->foreignId('salary_expense_account_id')->constrained('chart_of_accounts');
            $table->foreignId('pf_account_id')->nullable()->constrained('chart_of_accounts');
            $table->enum('salary_structure', ['Scale', 'Contractual']);
            $table->enum('include_bonus', ['Yes', 'No'])->default('No');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_pf', 18, 2)->default(0);
            $table->decimal('total_lwp', 18, 2)->default(0);
            $table->decimal('total_bonus', 18, 2)->default(0);
            $table->decimal('total_paid', 18, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->enum('payment_status', ['Posted', 'Cancelled'])->default('Posted');
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->timestamps();
            $table->index(['month', 'payment_status']);
        });

        // What each employee was paid (a frozen copy of the salary sheet row).
        Schema::create('salary_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_payment_id')->constrained('salary_payments');
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('department', 100)->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('grade_step', 120)->nullable();
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('house_rent', 18, 2)->default(0);
            $table->decimal('compensation', 18, 2)->default(0);
            $table->decimal('medical_allowance', 18, 2)->default(0);
            $table->decimal('gross_salary', 18, 2)->default(0);
            $table->decimal('pf_amount', 18, 2)->default(0);
            $table->decimal('lwp_days', 6, 1)->default(0);
            $table->decimal('lwp_deduction', 18, 2)->default(0);
            $table->decimal('bonus_amount', 18, 2)->default(0);
            $table->decimal('net_salary', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->timestamps();
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payment_details');
        Schema::dropIfExists('salary_payments');
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropForeign(['salary_expense_account_id']);
            $table->dropForeign(['pf_account_id']);
            $table->dropForeign(['salary_payment_account_id']);
            $table->dropColumn(['salary_expense_account_id', 'pf_account_id', 'salary_payment_account_id']);
        });
    }
};
