<?php

use App\Http\Controllers\Admin\SettingController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings > Theme Settings: the PDF template (+ optional PDF accent), and theme_colors moves from the old flat
 * light map ({"primary": "#..."}) to {"light": {...}, "dark": {...}} (SettingController::themeStored()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'pdf_template')) {
                $table->string('pdf_template', 30)->default('classic')->after('theme_colors');
            }
            if (!Schema::hasColumn('settings', 'pdf_accent')) {
                $table->string('pdf_accent', 7)->nullable()->after('pdf_template');
            }
        });

        foreach (DB::table('settings')->whereNotNull('theme_colors')->get(['id', 'theme_colors']) as $row) {
            $stored = json_decode((string) $row->theme_colors, true);
            if (!is_array($stored) || isset($stored['light']) || isset($stored['dark'])) {
                continue;
            }
            $new = SettingController::legacyThemeColors($stored);
            DB::table('settings')->where('id', $row->id)->update([
                'theme_colors' => $new['light'] || $new['dark'] ? json_encode($new) : null,
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('settings')->whereNotNull('theme_colors')->get(['id', 'theme_colors']) as $row) {
            $stored = json_decode((string) $row->theme_colors, true);
            if (!is_array($stored) || !isset($stored['light'])) {
                continue;
            }
            $flat = [];
            foreach (['accent' => 'primary', 'button' => 'button', 'sidebar' => 'sidebar_bg', 'sidebar_text' => 'sidebar_text',
                'sidebar_active' => 'sidebar_active', 'topbar' => 'topbar_bg', 'topbar_text' => 'topbar_text', 'bg' => 'page_bg',
                'surface' => 'surface', 'text' => 'text', 'text_muted' => 'text_muted', 'border' => 'border',
                'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'] as $new => $old) {
                if (isset($stored['light'][$new])) {
                    $flat[$old] = $stored['light'][$new];
                }
            }
            DB::table('settings')->where('id', $row->id)->update(['theme_colors' => $flat ? json_encode($flat) : null]);
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['pdf_template', 'pdf_accent']);
        });
    }
};
