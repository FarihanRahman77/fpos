<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll processing:
 *  - bonus types (festival / annual / special ...) and the months + employees they apply to;
 *  - per-employee monthly additions and deductions (incl. absence fines);
 *  - advance salary / loans repaid by monthly installments;
 *  - payroll runs: a month's salary is processed once (Dr expense / Cr salary payable, PF payable,
 *    employee loans) and then paid in any number of partial payments from any cash / bank account
 *    (Dr salary payable / Cr cash-bank).
 *
 * salary_payments / salary_payment_details are rebuilt as payments against a run (they were empty).
 */
return new class extends Migration
{
    private function audit(Blueprint $table): void
    {
        $table->enum('deleted', ['Yes', 'No'])->default('No');
        $table->enum('status', ['Active', 'Inactive'])->default('Active');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->unsignedBigInteger('deleted_by')->nullable();
        $table->dateTime('created_date')->nullable();
        $table->dateTime('updated_date')->nullable();
        $table->dateTime('deleted_datetime')->nullable();
        $table->timestamps();
    }

    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->foreignId('salary_payable_account_id')->nullable()->after('salary_payment_account_id')->constrained('chart_of_accounts');
            $table->foreignId('loan_account_id')->nullable()->after('salary_payable_account_id')->constrained('chart_of_accounts');
            // Absence fine per day = basic or gross / days in the month.
            $table->enum('absence_basis', ['Basic', 'Gross'])->default('Gross')->after('loan_account_id');
        });

        Schema::create('bonus_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('calc_type', ['Percentage', 'Amount'])->default('Percentage');
            $table->enum('basis', ['Basic', 'Gross'])->default('Basic');   // for Percentage
            $table->decimal('value', 18, 2)->default(0);
            $table->enum('eligible_only', ['Yes', 'No'])->default('Yes');   // only employees marked bonus eligible
            $table->string('description', 255)->nullable();
            $this->audit($table);
        });

        // A bonus type granted for one month to everyone / a department / grade / step / employee.
        Schema::create('bonus_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_type_id')->constrained('bonus_types');
            $table->date('month');                                          // first day of the month
            $table->enum('scope', ['All', 'Department', 'Grade', 'Step', 'Employee'])->default('All');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->enum('calc_type', ['Percentage', 'Amount'])->nullable();   // null = the type's own
            $table->decimal('value', 18, 2)->nullable();
            $table->string('remarks', 255)->nullable();
            $this->audit($table);
            $table->index(['month', 'deleted']);
        });

        // One-off additions (overtime, arrear, incentive ...) and deductions (fine, absence ...).
        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('month');
            $table->enum('adj_type', ['Addition', 'Deduction']);
            $table->string('head', 60);
            $table->decimal('days', 6, 1)->nullable();                      // absence days
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('remarks', 255)->nullable();
            $this->audit($table);
            $table->index(['month', 'employee_id', 'deleted']);
        });

        // Advance salary / loan, repaid by monthly salary installments (or in cash).
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_no', 20)->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->enum('loan_type', ['Advance Salary', 'Loan']);
            $table->decimal('amount', 18, 2);
            $table->date('issue_date');
            $table->foreignId('payment_account_id')->constrained('chart_of_accounts');   // paid out from
            $table->foreignId('loan_account_id')->constrained('chart_of_accounts');      // employee loans (asset)
            $table->unsignedInteger('installments');
            $table->decimal('installment_amount', 18, 2);
            $table->date('start_month');
            $table->decimal('recovered', 18, 2)->default(0);
            $table->enum('loan_status', ['Running', 'Closed', 'Cancelled'])->default('Running');
            $table->string('remarks', 255)->nullable();
            $this->audit($table);
        });

        Schema::create('employee_loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans');
            $table->date('txn_date');
            $table->enum('txn_type', ['Disbursement', 'Salary Deduction', 'Cash Repayment', 'Reschedule', 'Cancel']);
            $table->decimal('amount', 18, 2)->default(0);
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->foreignId('payment_account_id')->nullable()->constrained('chart_of_accounts');
            $table->string('voucher_no', 20)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->enum('txn_status', ['Posted', 'Reversed'])->default('Posted');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->timestamps();
            $table->index(['employee_loan_id', 'txn_status']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_no', 20)->unique();
            $table->date('month');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->date('run_date');
            $table->enum('salary_structure', ['Scale', 'Contractual']);
            $table->enum('include_bonus', ['Yes', 'No'])->default('No');   // default bonus % from Payroll Setup
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->foreignId('payable_account_id')->constrained('chart_of_accounts');
            $table->foreignId('pf_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('loan_account_id')->nullable()->constrained('chart_of_accounts');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_bonus', 18, 2)->default(0);
            $table->decimal('total_additions', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('total_lwp', 18, 2)->default(0);
            $table->decimal('total_pf', 18, 2)->default(0);
            $table->decimal('total_loan', 18, 2)->default(0);
            $table->decimal('total_net', 18, 2)->default(0);
            $table->decimal('total_paid', 18, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->enum('run_status', ['Posted', 'Reversed'])->default('Posted');
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->dateTime('reversed_at')->nullable();
            $table->string('reverse_reason', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->timestamps();
            $table->index(['month', 'run_status']);
        });

        // A frozen salary sheet row.
        Schema::create('payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs');
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('department', 100)->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('grade_step', 120)->nullable();
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('house_rent', 18, 2)->default(0);
            $table->decimal('compensation', 18, 2)->default(0);
            $table->decimal('medical_allowance', 18, 2)->default(0);
            $table->decimal('gross_salary', 18, 2)->default(0);
            $table->decimal('bonus_amount', 18, 2)->default(0);
            $table->json('bonus_detail')->nullable();
            $table->decimal('additions', 18, 2)->default(0);
            $table->json('additions_detail')->nullable();
            $table->decimal('deductions', 18, 2)->default(0);
            $table->json('deductions_detail')->nullable();
            $table->decimal('lwp_days', 6, 1)->default(0);
            $table->decimal('lwp_deduction', 18, 2)->default(0);
            $table->decimal('pf_amount', 18, 2)->default(0);
            $table->decimal('loan_deduction', 18, 2)->default(0);
            $table->json('loan_detail')->nullable();
            $table->decimal('net_payable', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->timestamps();
            $table->index('employee_id');
        });

        // Old direct-payment tables (empty) are replaced by payments against a run.
        abort_if(DB::table('salary_payments')->exists(), 500, 'salary_payments has rows; migrate them before running this migration.');
        Schema::drop('salary_payment_details');
        Schema::drop('salary_payments');

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 20)->unique();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs');
            $table->date('month');
            $table->date('payment_date');
            $table->foreignId('payment_account_id')->constrained('chart_of_accounts');
            $table->unsignedInteger('employee_count')->default(0);
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

        Schema::create('salary_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_payment_id')->constrained('salary_payments');
            $table->foreignId('payroll_run_item_id')->constrained('payroll_run_items');
            $table->foreignId('employee_id')->constrained('employees');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->index('employee_id');
        });

        DB::table('bonus_types')->insert(collect([
            ['Festival Bonus', 'Percentage', 'Basic', 100],
            ['Annual Bonus', 'Percentage', 'Gross', 50],
            ['Special Bonus', 'Amount', 'Basic', 0],
        ])->map(fn ($b) => [
            'name' => $b[0], 'calc_type' => $b[1], 'basis' => $b[2], 'value' => $b[3], 'eligible_only' => 'Yes',
            'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payment_details');
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('payroll_run_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_loan_transactions');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('salary_adjustments');
        Schema::dropIfExists('bonus_assignments');
        Schema::dropIfExists('bonus_types');
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropForeign(['salary_payable_account_id']);
            $table->dropForeign(['loan_account_id']);
            $table->dropColumn(['salary_payable_account_id', 'loan_account_id', 'absence_basis']);
        });
        // The old salary_payments layout comes back with: php artisan migrate:refresh --path=...2026_11_01_110000_create_salary_payments.php
    }
};
