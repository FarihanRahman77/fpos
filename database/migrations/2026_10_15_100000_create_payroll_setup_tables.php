<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->string('description', 500)->nullable();
            $this->auditColumns($table);
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->string('description', 500)->nullable();
            $this->auditColumns($table);
        });

        // Pay scale grade, e.g. "Grade-10"; each grade has salary steps.
        Schema::create('pay_grades', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $this->auditColumns($table);
        });

        Schema::create('pay_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_grade_id')->constrained('pay_grades');
            $table->unsignedSmallInteger('step_no');
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('house_rent', 18, 2)->default(0);
            $table->decimal('medical_allowance', 18, 2)->default(0);
            $table->decimal('transport_allowance', 18, 2)->default(0);
            $table->decimal('other_allowance', 18, 2)->default(0);
            $table->decimal('gross_salary', 18, 2)->default(0);
            $this->auditColumns($table);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('designation_id')->nullable()->after('designation')->constrained('designations');
            $table->foreignId('department_id')->nullable()->after('department')->constrained('departments');
            // Scale: salary comes from the pay step. Contractual: fixed salary entered manually.
            $table->enum('salary_structure', ['Scale', 'Contractual'])->default('Contractual')->after('card_valid_until');
            $table->foreignId('step_id')->nullable()->after('salary_structure')->constrained('pay_steps');
        });

        $this->linkExistingEmployees();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['designation_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['step_id']);
            $table->dropColumn(['designation_id', 'department_id', 'salary_structure', 'step_id']);
        });

        Schema::dropIfExists('pay_steps');
        Schema::dropIfExists('pay_grades');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }

    /** Turn the old free-text department / designation values into records and link them. */
    private function linkExistingEmployees(): void
    {
        $now = now();

        $departmentIds = [];
        foreach (DB::table('employees')->whereNotNull('department')->where('department', '<>', '')->distinct()->pluck('department') as $name) {
            $departmentIds[$name] = DB::table('departments')->insertGetId([
                'name' => $name, 'deleted' => 'No', 'status' => 'Active',
                'created_date' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $designationIds = [];
        foreach (DB::table('employees')->whereNotNull('designation')->where('designation', '<>', '')->distinct()->pluck('designation') as $name) {
            $designationIds[$name] = DB::table('designations')->insertGetId([
                'name' => $name, 'deleted' => 'No', 'status' => 'Active',
                'created_date' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (DB::table('employees')->get(['id', 'department', 'designation']) as $emp) {
            DB::table('employees')->where('id', $emp->id)->update([
                'department_id'  => $departmentIds[$emp->department] ?? null,
                'designation_id' => $designationIds[$emp->designation] ?? null,
            ]);
        }
    }

    private function auditColumns(Blueprint $table): void
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
};
