<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Background image of the login page's brand panel, and how strongly the brand color covers it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('login_background')->nullable()->after('watermark');
            $table->unsignedTinyInteger('login_overlay')->default(70)->after('login_background'); // % of brand color over the image
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['login_background', 'login_overlay']);
        });
    }
};
