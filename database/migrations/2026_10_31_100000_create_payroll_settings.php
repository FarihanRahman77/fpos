<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row. The salary structure chosen here applies to every employee.
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('salary_structure', ['Scale', 'Contractual'])->default('Contractual');
            // Scale only: allowances as a percentage of the basic salary.
            $table->decimal('house_rent_percent', 6, 2)->default(50);
            $table->decimal('compensation_percent', 6, 2)->default(30);
            $table->decimal('medical_percent', 6, 2)->default(20);
            // Both structures. PF: % of basic (Scale) / contract salary (Contractual). Bonus: % of gross.
            $table->enum('pf_enabled', ['Yes', 'No'])->default('No');
            $table->decimal('pf_percent', 6, 2)->default(0);
            $table->decimal('bonus_percent', 6, 2)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->timestamps();
        });

        DB::table('payroll_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        // Yearly increment of the grade: suggests the next step's basic salary.
        Schema::table('pay_grades', function (Blueprint $table) {
            $table->decimal('increment_percent', 6, 2)->default(0)->after('name');
        });

        // A step now holds only the basic salary; the allowances come from the payroll settings.
        // NULL department = the step applies to all departments.
        Schema::table('pay_steps', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('pay_grade_id')->constrained('departments');
            $table->dropColumn(['house_rent', 'medical_allowance', 'transport_allowance', 'other_allowance', 'gross_salary']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('salary_structure');
            $table->decimal('contract_salary', 18, 2)->default(0)->after('step_id');
            $table->enum('bonus_eligible', ['Yes', 'No'])->default('Yes')->after('contract_salary');
            $table->date('last_increment_date')->nullable()->after('bonus_eligible');
            $table->decimal('compensation', 18, 2)->default(0)->after('house_rent');
            $table->decimal('pf_amount', 18, 2)->default(0)->after('gross_salary');
            $table->decimal('bonus_amount', 18, 2)->default(0)->after('pf_amount');
            $table->decimal('net_salary', 18, 2)->default(0)->after('bonus_amount');
        });

        // Existing employees were all on a fixed salary: keep it as their contract salary.
        DB::table('employees')->update([
            'contract_salary' => DB::raw('gross_salary'),
            'net_salary'      => DB::raw('gross_salary'),
        ]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['contract_salary', 'bonus_eligible', 'last_increment_date', 'compensation', 'pf_amount', 'bonus_amount', 'net_salary']);
            $table->enum('salary_structure', ['Scale', 'Contractual'])->default('Contractual')->after('card_valid_until');
        });

        Schema::table('pay_steps', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
            $table->decimal('house_rent', 18, 2)->default(0);
            $table->decimal('medical_allowance', 18, 2)->default(0);
            $table->decimal('transport_allowance', 18, 2)->default(0);
            $table->decimal('other_allowance', 18, 2)->default(0);
            $table->decimal('gross_salary', 18, 2)->default(0);
        });

        Schema::table('pay_grades', function (Blueprint $table) {
            $table->dropColumn('increment_percent');
        });

        Schema::dropIfExists('payroll_settings');
    }
};
