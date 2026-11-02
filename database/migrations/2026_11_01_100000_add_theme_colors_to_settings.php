<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customized admin colors (see SettingController::themeCss()); NULL = default theme.
        Schema::table('settings', function (Blueprint $table) {
            $table->json('theme_colors')->nullable()->after('time_format');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('theme_colors');
        });
    }
};
