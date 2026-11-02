<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Yearly holiday calendar ----
        // Weekend days per year as ISO day numbers (1 = Mon ... 7 = Sun), e.g. "5" or "5,6".
        Schema::create('work_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('weekend_days', 20)->default('5');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->date('from_date');
            $table->date('to_date');
            $table->enum('type', ['Public', 'Festival', 'Company', 'Optional'])->default('Public');
            $table->string('description', 500)->nullable();
            $this->auditColumns($table);
            $table->index(['from_date', 'to_date']);
        });

        // ---- Leave rules ----
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            // Unpaid leave (LWP) needs no balance and is deducted on the salary sheet.
            $table->enum('is_paid', ['Yes', 'No'])->default('Yes');
            $table->string('description', 500)->nullable();
            $this->auditColumns($table);
        });

        Schema::create('leave_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $this->auditColumns($table);
        });

        // Days of each leave type per year for a group; every employee of the group gets them.
        Schema::create('leave_group_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_group_id')->constrained('leave_groups');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 5, 1)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['leave_group_id', 'leave_type_id', 'year']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('leave_group_id')->nullable()->after('department_id')->constrained('leave_groups');
        });

        // ---- Per employee ----
        // Ledger adjustments of one employee: extra (+) or fewer (-) days, carry forward, encashment.
        Schema::create('leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->unsignedSmallInteger('year');
            $table->date('entry_date');
            $table->decimal('days', 5, 1);
            $table->string('remarks', 255)->nullable();
            $this->auditColumns($table);
            $table->index(['employee_id', 'year']);
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 20)->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('from_date');
            $table->date('to_date');
            $table->enum('half_day', ['Yes', 'No'])->default('No');
            // Working days only (weekends and holidays of the calendar are not counted).
            $table->decimal('days', 5, 1);
            $table->string('reason', 500)->nullable();
            $table->enum('leave_status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $this->auditColumns($table);
            $table->index(['employee_id', 'from_date']);
        });

        $now = now();
        foreach ([
            ['Casual Leave', 'CL', 'Yes'], ['Sick Leave', 'SL', 'Yes'], ['Earned Leave', 'EL', 'Yes'],
            ['Maternity Leave', 'ML', 'Yes'], ['Leave Without Pay', 'LWP', 'No'],
        ] as [$name, $code, $paid]) {
            DB::table('leave_types')->insert([
                'name' => $name, 'code' => $code, 'is_paid' => $paid, 'deleted' => 'No', 'status' => 'Active',
                'created_date' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_adjustments');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['leave_group_id']);
            $table->dropColumn('leave_group_id');
        });
        Schema::dropIfExists('leave_group_rules');
        Schema::dropIfExists('leave_groups');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('work_calendars');
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
