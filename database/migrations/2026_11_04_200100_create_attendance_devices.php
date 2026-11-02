<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZKTeco (and compatible) attendance devices and the raw punch log.
 * Punches from a device keep the device's user id; imported punches use device_id 0
 * and device_user_id "emp:<employee id>". employee_id is filled when the user id is mapped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('ip', 64);
            $table->unsignedInteger('port')->default(4370);
            $table->unsignedInteger('comm_key')->default(0);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->string('last_message', 255)->nullable();
            $table->enum('deleted', ['Yes', 'No'])->default('No');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->dateTime('deleted_datetime')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->default(0);          // 0 = imported from a file
            $table->string('device_user_id', 30);
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->dateTime('punched_at');
            $table->enum('source', ['Device', 'Import'])->default('Device');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['device_id', 'device_user_id', 'punched_at'], 'attendance_punches_unique');
            $table->index(['employee_id', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('attendance_devices');
    }
};
