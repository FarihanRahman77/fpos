<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row each time an employee's salary changes (joining, increment, step / settings change).
        // The PF ledger is built from it.
        Schema::create('employee_salary_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('effective_date');
            $table->string('reason', 50);
            $table->enum('salary_structure', ['Scale', 'Contractual']);
            $table->foreignId('step_id')->nullable()->constrained('pay_steps');
            $table->string('grade_step', 120)->nullable();   // snapshot, e.g. "Grade-10 / 3"
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('gross_salary', 18, 2)->default(0);
            $table->decimal('pf_amount', 18, 2)->default(0);
            $table->decimal('net_salary', 18, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_date']);
        });

        $structure = DB::table('payroll_settings')->value('salary_structure') ?? 'Contractual';

        foreach (DB::table('employees')->where('deleted', 'No')->get() as $emp) {
            DB::table('employee_salary_histories')->insert([
                'employee_id'      => $emp->id,
                'effective_date'   => $emp->joining_date ?: substr((string) ($emp->created_date ?: now()), 0, 10),
                'reason'           => 'Opening',
                'salary_structure' => $structure,
                'step_id'          => $emp->step_id,
                'basic_salary'     => $emp->basic_salary,
                'gross_salary'     => $emp->gross_salary,
                'pf_amount'        => $emp->pf_amount,
                'net_salary'       => $emp->net_salary,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_histories');
    }
};
