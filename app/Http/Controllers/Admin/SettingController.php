<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SettingController extends Controller
{
    public function index()
    {
        $setting = Setting::first();

        if (!$setting) {
            $setting = new Setting();
        }

        return view('admin.setting.index', ['setting' => $setting, 'themeFields' => self::THEME_FIELDS]);
    }


    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_short_name' => 'nullable|string|max:255',
            'company_tagline' => 'nullable|string|max:255',

            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'logo_dark' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,ico|max:1024',
            'signature' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'watermark' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'login_background' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'login_overlay' => 'nullable|integer|min:0|max:95',
            'remove_login_background' => 'nullable|boolean',

            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',

            'phone' => 'nullable|string|max:50',
            'phone_two' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'email_two' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',

            'trade_license' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:255',
            'bin' => 'nullable|string|max:255',
            'gst' => 'nullable|string|max:255',
            'tax_rate' => 'nullable|numeric|min:0|max:100',

            'currency' => 'nullable|string|max:20',
            'currency_symbol' => 'nullable|string|max:20',
            'currency_position' => 'nullable|in:before,after',

            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'invoice_note' => 'nullable|string',
            'terms_conditions' => 'nullable|string',

            'facebook' => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'youtube' => 'nullable|string|max:255',
            'linkedin' => 'nullable|string|max:255',

            'timezone' => 'nullable|string|max:100',
            'date_format' => 'nullable|string|max:50',
            'time_format' => 'nullable|string|max:50'
        ] + self::themeRules(), [
            'theme_colors.*.regex' => 'Theme colors must be hex codes like #916100.',
        ]);


        $setting = Setting::first();

        if (!$setting) {
            $setting = new Setting();
        }


        /*
        |--------------------------------------------------------------------------
        | Company
        |--------------------------------------------------------------------------
        */

        $setting->company_name = $request->company_name;
        $setting->company_short_name = $request->company_short_name;
        $setting->company_tagline = $request->company_tagline;


        /*
        |--------------------------------------------------------------------------
        | Address
        |--------------------------------------------------------------------------
        */

        $setting->address = $request->address;
        $setting->city = $request->city;
        $setting->state = $request->state;
        $setting->country = $request->country;
        $setting->postal_code = $request->postal_code;


        /*
        |--------------------------------------------------------------------------
        | Contact
        |--------------------------------------------------------------------------
        */

        $setting->phone = $request->phone;
        $setting->phone_two = $request->phone_two;
        $setting->email = $request->email;
        $setting->email_two = $request->email_two;
        $setting->website = $request->website;


        /*
        |--------------------------------------------------------------------------
        | Tax / Business
        |--------------------------------------------------------------------------
        */

        $setting->trade_license = $request->trade_license;
        $setting->tin = $request->tin;
        $setting->bin = $request->bin;
        $setting->gst = $request->gst;
        $setting->tax_rate = $request->tax_rate ?? 0;


        /*
        |--------------------------------------------------------------------------
        | Currency
        |--------------------------------------------------------------------------
        */

        $setting->currency = $request->currency;
        $setting->currency_symbol = $request->currency_symbol;
        $setting->currency_position = $request->currency_position;


        /*
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        */

        $setting->header_text = $request->header_text;
        $setting->footer_text = $request->footer_text;
        $setting->invoice_note = $request->invoice_note;
        $setting->terms_conditions = $request->terms_conditions;


        /*
        |--------------------------------------------------------------------------
        | Social
        |--------------------------------------------------------------------------
        */

        $setting->facebook = $request->facebook;
        $setting->instagram = $request->instagram;
        $setting->youtube = $request->youtube;
        $setting->linkedin = $request->linkedin;


        /*
        |--------------------------------------------------------------------------
        | System
        |--------------------------------------------------------------------------
        */

        $setting->timezone = $request->timezone;
        $setting->date_format = $request->date_format;
        $setting->time_format = $request->time_format;
        $setting->theme_colors = self::cleanThemeColors($request->input('theme_colors'));
        $setting->status = 'Active';
        $setting->deleted = 'No';


        /*
        |--------------------------------------------------------------------------
        | Upload Directory
        |--------------------------------------------------------------------------
        */

        $uploadPath = public_path('uploads/settings');

        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }


        /*
        |--------------------------------------------------------------------------
        | Logo
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('logo')) {

            if ($setting->logo && File::exists(public_path($setting->logo))) {
                File::delete(public_path($setting->logo));
            }

            $file = $request->file('logo');

            $fileName = 'logo_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->logo = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Dark Logo
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('logo_dark')) {

            if ($setting->logo_dark && File::exists(public_path($setting->logo_dark))) {
                File::delete(public_path($setting->logo_dark));
            }

            $file = $request->file('logo_dark');

            $fileName = 'logo_dark_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->logo_dark = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Favicon
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('favicon')) {

            if ($setting->favicon && File::exists(public_path($setting->favicon))) {
                File::delete(public_path($setting->favicon));
            }

            $file = $request->file('favicon');

            $fileName = 'favicon_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->favicon = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Signature
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('signature')) {

            if ($setting->signature && File::exists(public_path($setting->signature))) {
                File::delete(public_path($setting->signature));
            }

            $file = $request->file('signature');

            $fileName = 'signature_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->signature = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Watermark
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('watermark')) {

            if ($setting->watermark && File::exists(public_path($setting->watermark))) {
                File::delete(public_path($setting->watermark));
            }

            $file = $request->file('watermark');

            $fileName = 'watermark_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->watermark = 'uploads/settings/' . $fileName;
        }


        /*
        |--------------------------------------------------------------------------
        | Login page background (brand panel) + how much brand color covers it
        |--------------------------------------------------------------------------
        */

        $removeLoginBg = $request->boolean('remove_login_background');
        if (($request->hasFile('login_background') || $removeLoginBg)
            && $setting->login_background && File::exists(public_path($setting->login_background))) {
            File::delete(public_path($setting->login_background));
            $setting->login_background = null;
        }

        if ($request->hasFile('login_background')) {

            $file = $request->file('login_background');

            $fileName = 'login_bg_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $fileName);

            $setting->login_background = 'uploads/settings/' . $fileName;
        } elseif ($removeLoginBg) {
            $setting->login_background = null;
        }

        if ($request->filled('login_overlay')) {
            $setting->login_overlay = (int) $request->input('login_overlay');
        }


        $setting->save();


        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'General settings updated successfully.');
    }

    /* ================================================================== */
    /*  Theme colors (was app/Services/ThemeColors.php)                   */
    /* ================================================================== */

    /**
 * Admin colors customizable from General Settings (settings.theme_colors, JSON of hex values).
 *
 * css() turns the saved colors into a small stylesheet that the admin header prints after
 * components.css. It overrides the design tokens of public/backend/css/tokens.css (--color-*),
 * which style.css and components.css read, plus the top bar, which has no token of its own.
 * Only colors that differ from the defaults (= the token values) are written, so an untouched
 * install renders exactly the design system. Shades (hover, press, soft fill, focus ring,
 * readable text, dark-mode variant) are derived from the chosen color instead of being asked for.
 *
 * Dark mode: accent, button and sidebar colors apply in both themes (the accent is lightened
 * for dark backgrounds). Top bar, page background and status colors are light-theme only,
 * because a light custom color would break the dark theme's surfaces.
     */
    // key => [label, help, default (= the token in public/backend/css/tokens.css), group]
    public const THEME_FIELDS = [
        'primary'        => ['Theme / accent color', 'Links, active tabs, focus rings, highlights and icons. Lightened automatically in dark mode.', '#533afd', 'Brand'],
        'button'         => ['Primary button', 'Save / Submit / Generate buttons. Text color is picked automatically for contrast.', '#533afd', 'Brand'],
        'sidebar_bg'     => ['Sidebar background', 'Left menu background (both themes).', '#1c1e54', 'Sidebar'],
        'sidebar_text'   => ['Sidebar text & icons', 'Menu labels and icons.', '#c6c9d7', 'Sidebar'],
        'sidebar_active' => ['Sidebar active item', 'Highlight bar, icon and wordmark of the current page.', '#b9b9f9', 'Sidebar'],
        'topbar_bg'      => ['Top bar background', 'Header bar with search, theme toggle and profile (light theme).', '#ffffff', 'Top bar'],
        'topbar_text'    => ['Top bar text & icons', 'Icons and text in the header bar (light theme).', '#0d253d', 'Top bar'],
        'page_bg'        => ['Page background', 'Area behind the cards (light theme).', '#f6f9fc', 'Page'],
        'surface'        => ['Card & panel background', 'Cards, modals, menus, inputs and tables (light theme).', '#ffffff', 'Page'],
        'text'           => ['Text color', 'Headings, body text and table values (light theme).', '#0d253d', 'Page'],
        'text_muted'     => ['Secondary text', 'Subtitles, labels, help text, table headers (light theme).', '#61718a', 'Page'],
        'border'         => ['Borders & dividers', 'Card borders, table lines, separators (light theme). Input borders are derived from it.', '#e3e8ee', 'Page'],
        'success'        => ['Success', 'Active / Approved / money-in badges and success text (light theme).', '#0e7c4a', 'Status'],
        'warning'        => ['Warning', 'Pending / due badges and warning text (light theme).', '#9a5a0a', 'Status'],
        'danger'         => ['Danger', 'Delete buttons, errors, money-out and rejected badges (light theme).', '#c0123c', 'Status'],
    ];

    private const INK = '#0d253d';   // --color-ink

    /** Validation rules for the settings form (theme_colors[key] => hex or empty). */
    public static function themeRules(): array
    {
        $rules = ['theme_colors' => ['nullable', 'array']];
        foreach (array_keys(self::THEME_FIELDS) as $key) {
            $rules["theme_colors.$key"] = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return $rules;
    }

    /** Only valid colors that differ from the default are stored; empty = default. */
    public static function cleanThemeColors(?array $input): ?array
    {
        $out = [];
        foreach (self::THEME_FIELDS as $key => [, , $default]) {
            $value = strtolower(trim((string) ($input[$key] ?? '')));
            if (preg_match('/^#[0-9a-f]{6}$/', $value) && $value !== $default) {
                $out[$key] = $value;
            }
        }

        return $out ?: null;
    }

    /** Saved colors merged over the defaults: [key => hex]. */
    public static function resolveThemeColors($stored): array
    {
        $stored = is_string($stored) ? json_decode($stored, true) : (array) $stored;
        $colors = [];
        foreach (self::THEME_FIELDS as $key => [, , $default]) {
            $value = strtolower((string) ($stored[$key] ?? ''));
            $colors[$key] = preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $default;
        }

        return $colors;
    }

    public static function themeCss($stored): string
    {
        $c = self::resolveThemeColors($stored);
        $changed = fn (string $key) => $c[$key] !== self::THEME_FIELDS[$key][2];
        $light = [];   // :root (light theme, inherited by dark unless re-set there)
        $dark = [];    // html[data-theme="dark"]
        $rules = [];

        if ($changed('primary')) {
            $p = $c['primary'];
            $pd = self::mix($p, '#ffffff', 0.55);   // readable on navy
            $light += [
                '--color-accent' => $p, '--color-accent-soft' => self::mix($p, '#ffffff', 0.91), '--color-accent-ink' => self::mix($p, '#000000', 0.2),
                '--color-focus' => $p, '--color-focus-ring' => self::rgba($p, 0.28), '--color-info' => self::mix($p, '#000000', 0.15),
                '--bs-primary' => $p, '--bs-primary-rgb' => self::rgbList($p), '--bs-link-color' => $p, '--bs-link-color-rgb' => self::rgbList($p),
            ];
            $dark += [
                '--color-accent' => $pd, '--color-accent-soft' => self::mix($p, self::INK, 0.75), '--color-accent-ink' => $pd,
                '--color-focus' => $pd, '--color-focus-ring' => self::rgba($pd, 0.35),
                '--bs-primary' => $pd, '--bs-primary-rgb' => self::rgbList($pd), '--bs-link-color' => $pd, '--bs-link-color-rgb' => self::rgbList($pd),
            ];
        }

        if ($changed('button')) {
            $b = $c['button'];
            $set = [
                '--color-button' => $b, '--color-button-hover' => self::mix($b, '#000000', 0.15),
                '--color-button-press' => self::mix($b, '#000000', 0.32), '--color-button-text' => self::textOn($b),
            ];
            $light += $set;
            $dark += $set;
        }

        if ($changed('sidebar_bg') || $changed('sidebar_text')) {
            $s = $c['sidebar_bg'];
            $t = $c['sidebar_text'];
            $isLight = self::luminance($s) > 0.4;   // a light sidebar needs dark borders
            $set = [
                '--color-sidebar' => $s, '--color-sidebar-soft' => self::mix($s, $t, 0.1),
                '--color-sidebar-border' => $isLight ? 'rgba(0,0,0,.08)' : 'rgba(255,255,255,.1)',
                '--color-sidebar-text' => $t, '--color-sidebar-muted' => self::mix($t, $s, 0.35),
                '--color-sidebar-text-strong' => self::textOn($s),
            ];
            $light += $set;
            $dark += $set;
        }

        if ($changed('sidebar_active')) {
            $light['--color-sidebar-active'] = $c['sidebar_active'];
            $dark['--color-sidebar-active'] = $c['sidebar_active'];
        }

        if ($changed('topbar_bg') || $changed('topbar_text')) {
            $bg = $c['topbar_bg'];
            $t = $c['topbar_text'];
            $l = 'html:not([data-theme="dark"])';
            $rules[] = "$l .admin-navbar,$l .admin-navbar.bg-white{background:$bg!important;color:$t;border-bottom-color:" . self::mix($bg, $t, 0.12) . '}'
                . "$l .admin-navbar .sidebar-toggle,$l .admin-navbar .icon-button,$l .admin-navbar .profile-button{background-color:$bg!important;color:$t;border-color:" . self::mix($bg, $t, 0.18) . '}'
                . "$l .admin-navbar .sidebar-toggle span{background:$t}"
                . "$l .admin-navbar .search-input{background:" . self::mix($bg, $t, 0.05) . ";color:$t;border-color:" . self::mix($bg, $t, 0.18) . '}'
                . "$l .admin-navbar .search-input::placeholder{color:" . self::mix($t, $bg, 0.4) . '}';
        }

        $lightOnly = [];
        if ($changed('page_bg')) {
            $lightOnly['--color-bg'] = $c['page_bg'];
        }
        if ($changed('surface')) {
            $lightOnly['--color-surface'] = $c['surface'];
            // inset areas (table heads, sections) sit between the card and the page
            $lightOnly['--color-surface-soft'] = self::mix($c['surface'], $c['page_bg'], 0.7);
        }
        if ($changed('text')) {
            $lightOnly['--color-text'] = $c['text'];
            $lightOnly['--color-text-secondary'] = self::mix($c['text'], $c['surface'], 0.15);
            $lightOnly['--bs-body-color'] = $c['text'];
        }
        if ($changed('text_muted')) {
            $lightOnly['--color-text-muted'] = $c['text_muted'];
        }
        if ($changed('border')) {
            $lightOnly['--color-border'] = $c['border'];
            $lightOnly['--color-border-input'] = self::mix($c['border'], $c['text_muted'], 0.3);
        }
        foreach (['success', 'warning', 'danger'] as $key) {
            if ($changed($key)) {
                $lightOnly["--color-$key"] = $c[$key];
                $lightOnly["--bs-$key-rgb"] = self::rgbList($c[$key]);
            }
        }

        $block = fn (string $selector, array $vars) => $vars
            ? $selector . '{' . implode(';', array_map(fn ($k, $v) => "$k:$v", array_keys($vars), $vars)) . '}'
            : '';

        return implode("\n", array_filter([
            $block(':root', $light),
            $block('html:not([data-theme="dark"])', $lightOnly),
            $block('html[data-theme="dark"]', $dark),
            ...$rules,
        ]));
    }

    /**
     * Plain hex palette for dompdf templates (no CSS variables there), built from the same
     * saved settings so invoices and reports follow Settings > Theme Colors. Paper stays white.
     */
    public static function pdfColors(): array
    {
        static $palette = null;
        if ($palette !== null) {
            return $palette;
        }

        $c = self::resolveThemeColors(\Illuminate\Support\Facades\DB::table('settings')->where('deleted', 'No')->value('theme_colors'));
        $paper = '#ffffff';

        return $palette = [
            'paper'         => $paper,
            'accent'        => $c['primary'],                                   // titles, rules, links
            'brand'         => $c['button'],                                    // filled bands / table heads
            'on_brand'      => self::textOn($c['button']),
            'accent_alt'    => $c['sidebar_active'],                            // thin secondary accent lines
            'ink'           => $c['text'],
            'secondary'     => self::mix($c['text'], $paper, 0.2),
            'muted'         => $c['text_muted'],
            'faint'         => self::mix($c['text_muted'], $paper, 0.3),
            'border'        => $c['border'],
            'border_strong' => self::mix($c['border'], $c['text_muted'], 0.25),
            'soft'          => self::mix($c['page_bg'], $paper, 0.35),
            'success'       => $c['success'],
            'success_soft'  => self::mix($c['success'], $paper, 0.9),
            'warning'       => $c['warning'],
            'warning_soft'  => self::mix($c['warning'], $paper, 0.9),
            'danger'        => $c['danger'],
            'danger_soft'   => self::mix($c['danger'], $paper, 0.9),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Color math                                                        */
    /* ------------------------------------------------------------------ */

    public static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    /** $a blended toward $b by $t (0..1). */
    public static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);

        return sprintf('#%02x%02x%02x', round($r1 + ($r2 - $r1) * $t), round($g1 + ($g2 - $g1) * $t), round($b1 + ($b2 - $b1) * $t));
    }

    public static function rgba(string $hex, float $alpha): string
    {
        return 'rgba(' . self::rgbList($hex) . ',' . $alpha . ')';
    }

    private static function rgbList(string $hex): string
    {
        return implode(',', self::rgb($hex));
    }

    /** WCAG relative luminance. */
    public static function luminance(string $hex): float
    {
        $c = array_map(function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $l = [self::luminance($a), self::luminance($b)];

        return (max($l) + 0.05) / (min($l) + 0.05);
    }

    /** White or ink, whichever reads better on $bg. */
    public static function textOn(string $bg): string
    {
        return self::contrast($bg, '#ffffff') >= self::contrast($bg, self::INK) ? '#ffffff' : self::INK;
    }
}
