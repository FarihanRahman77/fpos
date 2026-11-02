<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_logs')) {
            return;
        }
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 30);
            $table->text('message');
            $table->string('type', 50)->default('General')->index();
            $table->unsignedBigInteger('party_id')->nullable()->index();
            $table->enum('status', ['Sent', 'Failed'])->index();
            $table->text('response')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
