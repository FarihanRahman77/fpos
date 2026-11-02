<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance:
 *  - work shifts (times, grace, half-day / overtime thresholds, weekly offs) and employees.work_shift_id
 *    (the old free-text employees.work_shift stays as it is);
 *  - one attendance row per employee and date (manual, imported or from a device);
 *  - attendance_settings: the default shift and the rules used when attendance is sent to the salary sheet.
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
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');                                          // before start_time = ends next day
            $table->unsignedSmallInteger('grace_minutes')->default(10);         // late after start + grace
            $table->unsignedSmallInteger('half_day_after_minutes')->default(0); // 0 = off; arriving this late = half day
            $table->unsignedSmallInteger('overtime_after_minutes')->default(30);// overtime counts once out this long after end
            $table->json('weekly_offs')->nullable();                            // ["Friday"]; empty = company calendar weekend
            $this->audit($table);
        });

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'work_shift_id')) {
                $table->unsignedBigInteger('work_shift_id')->nullable()->after('work_shift');
            }
            if (!Schema::hasColumn('employees', 'device_user_id')) {
                $table->string('device_user_id', 30)->nullable()->after('employee_code');   // user id on the attendance device
                $table->index('device_user_id');
            }
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->enum('attendance_status', ['Present', 'Late', 'Half Day', 'Absent', 'Leave', 'Holiday', 'Weekly Off'])->default('Present');
            $table->enum('status_override', ['Yes', 'No'])->default('No');     // Yes = status picked by hand, not worked out
            $table->unsignedBigInteger('work_shift_id')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->enum('source', ['Manual', 'Import', 'Device'])->default('Manual');
            $table->string('remarks', 255)->nullable();
            $this->audit($table);
            $table->unique(['employee_id', 'date']);
            $table->index('date');
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('default_shift_id')->nullable();
            $table->enum('absence_deduction', ['Yes', 'No'])->default('Yes');
            $table->unsignedTinyInteger('lates_per_day')->default(3);          // N lates = 1 day fine; 0 = no late fine
            $table->enum('overtime_pay', ['Yes', 'No'])->default('Yes');
            $table->decimal('overtime_multiplier', 5, 2)->default(2.00);      // × hourly rate (gross / days / 8)
            $table->unsignedTinyInteger('hours_per_day')->default(8);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $shiftId = DB::table('work_shifts')->insertGetId([
            'name' => 'General (9 AM - 6 PM)', 'start_time' => '09:00:00', 'end_time' => '18:00:00',
            'grace_minutes' => 10, 'half_day_after_minutes' => 120, 'overtime_after_minutes' => 30,
            'weekly_offs' => json_encode(['Friday']), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attendance_settings')->insert(['default_shift_id' => $shiftId, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
        Schema::dropIfExists('attendances');
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'device_user_id')) {
                $table->dropIndex(['device_user_id']);
                $table->dropColumn('device_user_id');
            }
            if (Schema::hasColumn('employees', 'work_shift_id')) {
                $table->dropColumn('work_shift_id');
            }
        });
        Schema::dropIfExists('work_shifts');
    }
};
