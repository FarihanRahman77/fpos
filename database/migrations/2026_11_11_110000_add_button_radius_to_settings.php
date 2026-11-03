<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings > Theme Settings > Button corner radius: 5 (default), 10 or 15 px (SettingController::BUTTON_RADII). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('settings', 'button_radius')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->unsignedTinyInteger('button_radius')->default(5)->after('pdf_accent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('settings', 'button_radius')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('button_radius');
            });
        }
    }
};
