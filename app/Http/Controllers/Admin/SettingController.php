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

        return view('admin.setting.index', ['setting' => $setting]);
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

            $fileName = 'logo_' . time() . '.' . $file->guessExtension();

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

            $fileName = 'logo_dark_' . time() . '.' . $file->guessExtension();

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

            $fileName = 'favicon_' . time() . '.' . $file->guessExtension();

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

            $fileName = 'signature_' . time() . '.' . $file->guessExtension();

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

            $fileName = 'watermark_' . time() . '.' . $file->guessExtension();

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

            $fileName = 'login_bg_' . time() . '.' . $file->guessExtension();

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
    /*  Theme Settings: colors (light + dark) and the PDF template         */
    /* ================================================================== */

    /**
     * Settings > Theme Settings. Every color token of public/backend/css/tokens.css is editable for the
     * light and the dark theme. settings.theme_colors = {"light": {key: hex}, "dark": {key: hex}} holds only
     * the colors that were changed (an old flat light map is still read, see themeStored()).
     *
     * A color left on "auto" uses its default, or - when it has a derive rule and a color it depends on was
     * changed - is worked out from that color (hover/press shades, soft fills, readable text on fills), the
     * same way in PHP (themeResolve) and in the page's live preview (theme.blade.php).
     * themeCss() prints only the tokens that differ from tokens.css, so an untouched install renders the
     * design system exactly.
     */
    public const THEME_GROUPS = [
        'brand'   => ['Brand & links', 'bi-palette'],
        'buttons' => ['Buttons', 'bi-hand-index-thumb'],
        'sidebar' => ['Sidebar', 'bi-layout-sidebar'],
        'topbar'  => ['Top bar', 'bi-window'],
        'page'    => ['Page & surfaces', 'bi-window-stack'],
        'text'    => ['Text', 'bi-fonts'],
        'borders' => ['Borders & inputs', 'bi-input-cursor-text'],
        'tables'  => ['Tables', 'bi-table'],
        'status'  => ['Status colors', 'bi-flag'],
        'hero'    => ['Dashboard hero', 'bi-stars'],
    ];

    // key => [label, help, group, [light default, dark default], derive rule|null, CSS tokens]
    // derive rule: ['same', base] | ['mix', base, '#hex' or '@key', t or [t light, t dark]] | ['on', base] (readable text on base);
    // or ['light' => rule, 'dark' => rule] when the themes differ.
    public const THEME_FIELDS = [
        'accent'          => ['Accent color', 'Links, active tabs, highlights and icons.', 'brand', ['#533afd', '#b9b9f9'], null, ['--color-accent']],
        'accent_soft'     => ['Accent soft fill', 'Soft accent backgrounds: selected rows, info pills, avatars.', 'brand', ['#efedff', '#363748'], ['mix', 'accent', '@surface', [0.91, 0.8]], ['--color-accent-soft']],
        'accent_ink'      => ['Text on soft accent', 'Text and icons placed on the soft accent fill.', 'brand', ['#4434d4', '#b9b9f9'], ['light' => ['mix', 'accent', '#000000', 0.2], 'dark' => ['same', 'accent']], ['--color-accent-ink']],
        'link'            => ['Links', 'Text links in pages and tables.', 'brand', ['#533afd', '#b9b9f9'], ['same', 'accent'], ['--color-link', '--bs-link-color']],
        'focus'           => ['Focus ring', 'Keyboard focus outline of inputs and buttons.', 'brand', ['#533afd', '#b9b9f9'], ['same', 'accent'], ['--color-focus']],

        'button'          => ['Primary button', 'Save / Submit / Generate buttons.', 'buttons', ['#533afd', '#665efd'], ['light' => ['same', 'accent']], ['--color-button']],
        'button_hover'    => ['Button hover', 'Primary button under the pointer.', 'buttons', ['#4434d4', '#533afd'], ['mix', 'button', '#000000', 0.15], ['--color-button-hover']],
        'button_press'    => ['Button pressed', 'Primary button while pressed.', 'buttons', ['#2e2b8c', '#4434d4'], ['mix', 'button', '#000000', 0.32], ['--color-button-press']],
        'button_text'     => ['Button text', 'Text on filled buttons. Auto picks white or ink for contrast.', 'buttons', ['#ffffff', '#ffffff'], ['on', 'button'], ['--color-button-text']],

        'sidebar'         => ['Sidebar background', 'Left menu background.', 'sidebar', ['#0b0b10', '#050508'], null, ['--color-sidebar']],
        'sidebar_hover'   => ['Sidebar hover / open group', 'Hovered item and open submenu background.', 'sidebar', ['#222227', '#1d1d20'], ['mix', 'sidebar', '@sidebar_text', 0.12], ['--color-sidebar-soft']],
        'sidebar_border'  => ['Sidebar dividers', 'Lines between sidebar sections.', 'sidebar', ['#26262b', '#202124'], ['mix', 'sidebar', '@sidebar_text', 0.14], ['--color-sidebar-border']],
        'sidebar_text'    => ['Sidebar text & icons', 'Menu labels and icons.', 'sidebar', ['#c9cad3', '#c9cad3'], null, ['--color-sidebar-text']],
        'sidebar_muted'   => ['Sidebar muted text', 'Section labels and secondary sidebar text.', 'sidebar', ['#87878f', '#84858c'], ['mix', 'sidebar_text', '@sidebar', 0.35], ['--color-sidebar-muted']],
        'sidebar_strong'  => ['Sidebar strong text', 'Wordmark and the user name. Auto = readable on the sidebar.', 'sidebar', ['#ffffff', '#ffffff'], ['on', 'sidebar'], ['--color-sidebar-text-strong']],
        'sidebar_active'  => ['Sidebar active item', 'Highlight bar, icon and text of the current page.', 'sidebar', ['#ffffff', '#ffffff'], null, ['--color-sidebar-active']],

        'topbar'          => ['Top bar background', 'Header bar with search, theme toggle and profile.', 'topbar', ['#000000', '#000000'], ['same', 'surface'], ['--color-topbar']],
        'topbar_text'     => ['Top bar text & icons', 'Icons and text in the header bar.', 'topbar', ['#ffffff', '#f3f4f6'], ['same', 'text'], ['--color-topbar-text']],
        'topbar_border'   => ['Top bar border', 'Line under the header bar.', 'topbar', ['#1f1f24', '#1c1c22'], ['same', 'border'], ['--color-topbar-border']],

        'bg'              => ['Page background', 'Area behind the cards.', 'page', ['#f6f9fc', '#0b0c10'], null, ['--color-bg', '--bs-body-bg']],
        'surface'         => ['Cards & panels', 'Cards, modals, menus and dropdowns.', 'page', ['#ffffff', '#15161c'], null, ['--color-surface']],
        'surface_soft'    => ['Inset areas', 'Sections inside cards, light badges, search box.', 'page', ['#f6f9fc', '#101116'], ['mix', 'surface', '@bg', [1, 0.5]], ['--color-surface-soft']],
        'surface_hover'   => ['Row hover', 'Table rows and list items under the pointer.', 'page', ['#f8f7ff', '#1d1d33'], ['light' => ['mix', 'surface', '@accent', 0.04], 'dark' => ['mix', 'surface', '@button', 0.1]], ['--color-surface-hover']],

        'text'            => ['Text', 'Headings, body text and table values.', 'text', ['#0d253d', '#f3f4f6'], null, ['--color-text', '--bs-body-color']],
        'text_secondary'  => ['Secondary text', 'Sub-headings and supporting values.', 'text', ['#273951', '#d2d3d5'], ['mix', 'text', '@surface', 0.15], ['--color-text-secondary']],
        'text_muted'      => ['Muted text', 'Labels, help text, captions.', 'text', ['#61718a', '#a3a8b5'], null, ['--color-text-muted']],

        'border'          => ['Borders & dividers', 'Card borders, table lines, separators.', 'borders', ['#e3e8ee', '#2a2c36'], null, ['--color-border']],
        'border_input'    => ['Input border', 'Text boxes, selects and checkboxes.', 'borders', ['#a8c3de', '#4e515c'], ['mix', 'border', '@text_muted', 0.3], ['--color-border-input']],
        'input_bg'        => ['Input background', 'Inside of text boxes and selects.', 'borders', ['#ffffff', '#15161c'], ['same', 'surface'], ['--color-input-bg']],

        'table_head'      => ['Table header background', 'Column headings of tables.', 'tables', ['#f6f9fc', '#101116'], ['same', 'surface_soft'], ['--color-table-head']],
        'table_head_text' => ['Table header text', 'Column heading labels.', 'tables', ['#61718a', '#a3a8b5'], ['same', 'text_muted'], ['--color-table-head-text']],

        'success'         => ['Success', 'Active / approved / money-in badges and text.', 'status', ['#0e7c4a', '#4fd194'], null, ['--color-success']],
        'success_soft'    => ['Success background', 'Soft fill behind success badges and alerts.', 'status', ['#e7f2ed', '#1d302d'], ['mix', 'success', '@surface', [0.9, 0.86]], ['--color-success-soft']],
        'warning'         => ['Warning', 'Pending / due badges and warning text.', 'status', ['#9a5a0a', '#f0b45a'], null, ['--color-warning']],
        'warning_soft'    => ['Warning background', 'Soft fill behind warning badges and alerts.', 'status', ['#f5efe7', '#342c25'], ['mix', 'warning', '@surface', [0.9, 0.86]], ['--color-warning-soft']],
        'danger'          => ['Danger', 'Delete buttons, errors, money-out and rejected badges.', 'status', ['#c0123c', '#ff7d9c'], null, ['--color-danger']],
        'danger_soft'     => ['Danger background', 'Soft fill behind danger badges and alerts.', 'status', ['#f9eaed', '#36242e'], ['mix', 'danger', '@surface', [0.91, 0.86]], ['--color-danger-soft']],
        'info'            => ['Info', 'Information badges and text.', 'status', ['#4434d4', '#4434d4'], ['same', 'accent_ink'], ['--color-info']],
        'info_soft'       => ['Info background', 'Soft fill behind information badges.', 'status', ['#efedff', '#363748'], ['same', 'accent_soft'], ['--color-info-soft']],

        'hero_bg'         => ['Hero background', 'Dashboard welcome card (and other dark feature panels).', 'hero', ['#0b0b10', '#050508'], ['same', 'sidebar'], ['--color-hero-bg']],
        'hero_text'       => ['Hero text', 'Headline and figures on the hero card. Auto = readable on its background.', 'hero', ['#ffffff', '#ffffff'], ['on', 'hero_bg'], ['--color-hero-text']],
        'hero_muted'      => ['Hero secondary text', 'Labels and captions on the hero card.', 'hero', ['#b6b6b7', '#b4b4b5'], ['mix', 'hero_text', '@hero_bg', 0.3], ['--color-hero-muted']],
        'hero_accent'     => ['Hero glow', 'Accent glow of the hero gradient.', 'hero', ['#533afd', '#665efd'], ['same', 'button'], ['--color-hero-accent']],
    ];

    /**
     * Ready-made palettes by sidebar + top bar family. Only the colors that differ from Midnight (the default =
     * THEME_FIELDS defaults = tokens.css) are listed; themePresetsFull() expands each into every field so
     * applying a preset fills all light and dark colors.
     */
    public const THEME_PRESETS = [
        'midnight' => ['Midnight', ['light' => [], 'dark' => []]],
        'white' => ['White', [
            'light' => ['sidebar' => '#ffffff', 'sidebar_text' => '#3d4a5c', 'sidebar_muted' => '#6b7687', 'sidebar_active' => '#533afd', 'sidebar_hover' => '#f1f3f8', 'sidebar_border' => '#e6e9ef',
                        'topbar' => '#ffffff', 'topbar_text' => '#0d253d', 'topbar_border' => '#e3e8ee', 'hero_bg' => '#1c1e54'],
            'dark'  => ['sidebar' => '#14161c', 'sidebar_text' => '#c9cdd6', 'sidebar_active' => '#b9b9f9', 'topbar' => '#14161c', 'topbar_text' => '#f3f4f6', 'topbar_border' => '#262933',
                        'bg' => '#0b0c10', 'surface' => '#14161c', 'text' => '#f3f4f6', 'text_muted' => '#a3a8b5', 'border' => '#262933', 'hero_bg' => '#1c1e54'],
        ]],
        'green' => ['Green', [
            'light' => ['accent' => '#047857', 'sidebar' => '#06392c', 'sidebar_text' => '#c3ded3', 'sidebar_active' => '#6ee7b7', 'topbar' => '#06392c', 'topbar_text' => '#ffffff', 'topbar_border' => '#0d4a3a',
                        'bg' => '#f4f9f6', 'border' => '#dde9e3', 'text' => '#0f2e25', 'text_muted' => '#55706a', 'hero_accent' => '#10b981'],
            'dark'  => ['accent' => '#6ee7b7', 'button' => '#047857', 'sidebar' => '#03231b', 'sidebar_text' => '#c3ded3', 'sidebar_active' => '#6ee7b7', 'topbar' => '#03231b', 'topbar_text' => '#ecfdf5', 'topbar_border' => '#0f3a2f',
                        'bg' => '#071a15', 'surface' => '#0c261f', 'text' => '#ecfdf5', 'text_muted' => '#a3c4b8', 'border' => '#1e4237', 'hero_accent' => '#10b981'],
        ]],
        'yellow' => ['Yellow', [
            'light' => ['accent' => '#b45309', 'sidebar' => '#e0a526', 'sidebar_text' => '#2a1d00', 'sidebar_active' => '#000000', 'topbar' => '#e0a526', 'topbar_text' => '#1f1600', 'topbar_border' => '#c9921c',
                        'bg' => '#fdfaf3', 'border' => '#efe6d2', 'text' => '#2b2210', 'text_muted' => '#6f6450', 'hero_bg' => '#3d2a00', 'hero_accent' => '#f59e0b'],
            'dark'  => ['accent' => '#fbbf24', 'button' => '#b45309', 'sidebar' => '#2a1f05', 'sidebar_text' => '#f3e3b8', 'sidebar_active' => '#fbbf24', 'topbar' => '#2a1f05', 'topbar_text' => '#fdf6e3', 'topbar_border' => '#3d2e0c',
                        'bg' => '#14110a', 'surface' => '#1d1910', 'text' => '#fdf6e3', 'text_muted' => '#c9bb98', 'border' => '#3a3220', 'hero_accent' => '#f59e0b'],
        ]],
        'blue' => ['Blue', [
            'light' => ['accent' => '#1d4ed8', 'sidebar' => '#0b2a6b', 'sidebar_text' => '#c7d4ee', 'sidebar_active' => '#93c5fd', 'topbar' => '#0b2a6b', 'topbar_text' => '#ffffff', 'topbar_border' => '#163a85',
                        'bg' => '#f3f6fc', 'border' => '#dfe6f2', 'text' => '#0d2238', 'text_muted' => '#566a85', 'hero_accent' => '#3b82f6'],
            'dark'  => ['accent' => '#93c5fd', 'button' => '#2563eb', 'sidebar' => '#061a45', 'sidebar_text' => '#c7d4ee', 'sidebar_active' => '#93c5fd', 'topbar' => '#061a45', 'topbar_text' => '#eef5fc', 'topbar_border' => '#12295c',
                        'bg' => '#06122a', 'surface' => '#0b1d3d', 'text' => '#eef5fc', 'text_muted' => '#a3b8d6', 'border' => '#1c3460', 'hero_accent' => '#3b82f6'],
        ]],
        'indigo' => ['Indigo (classic)', [
            'light' => ['sidebar' => '#1c1e54', 'sidebar_hover' => '#373968', 'sidebar_border' => '#333565', 'sidebar_text' => '#c6c9d7', 'sidebar_muted' => '#8b8da9', 'sidebar_active' => '#b9b9f9',
                        'topbar' => '#ffffff', 'topbar_text' => '#0d253d', 'topbar_border' => '#e3e8ee', 'hero_bg' => '#1c1e54', 'hero_muted' => '#c6c9d7'],
            'dark'  => ['accent_soft' => '#1f3063', 'sidebar' => '#14153b', 'sidebar_hover' => '#373968', 'sidebar_border' => '#2c2c4f', 'sidebar_text' => '#c6c9d7', 'sidebar_muted' => '#8b8da9', 'sidebar_active' => '#b9b9f9',
                        'topbar' => '#0d253d', 'topbar_text' => '#f6f9fc', 'topbar_border' => '#273951', 'bg' => '#0b1e32', 'surface' => '#0d253d', 'surface_soft' => '#0c2137', 'surface_hover' => '#162b50',
                        'text' => '#f6f9fc', 'text_secondary' => '#e3e8ee', 'text_muted' => '#a8c3de', 'border' => '#273951', 'border_input' => '#4a5c75', 'input_bg' => '#0d253d', 'table_head' => '#0c2137',
                        'table_head_text' => '#a8c3de', 'success_soft' => '#163d49', 'warning_soft' => '#2d3941', 'danger_soft' => '#2f314a', 'info_soft' => '#1f3063', 'hero_bg' => '#14153b', 'hero_muted' => '#c6c9d7'],
        ]],
    ];

    /** Button corner radius choices (Theme Settings), px; 5 = tokens.css --radius-button. */
    public const BUTTON_RADII = [5, 10, 15];

    /** Every preset expanded to all fields: [key => [name, ['light' => [field => hex], 'dark' => [...]]]]. */
    public static function themePresetsFull(): array
    {
        $out = [];
        foreach (self::THEME_PRESETS as $key => [$name, $set]) {
            $out[$key] = [$name, [
                'light' => self::themeResolve($set['light'], 'light'),
                'dark'  => self::themeResolve($set['dark'], 'dark'),
            ]];
        }

        return $out;
    }

    /** Old flat keys (General Settings > Theme Colors before Theme Settings) => new light key. */
    private const LEGACY_THEME_KEYS = [
        'primary' => 'accent', 'button' => 'button', 'sidebar_bg' => 'sidebar', 'sidebar_text' => 'sidebar_text',
        'sidebar_active' => 'sidebar_active', 'topbar_bg' => 'topbar', 'topbar_text' => 'topbar_text', 'page_bg' => 'bg',
        'surface' => 'surface', 'text' => 'text', 'text_muted' => 'text_muted', 'border' => 'border',
        'success' => 'success', 'warning' => 'warning', 'danger' => 'danger',
    ];

    private const INK = '#0d253d';   // --color-ink

    /** Settings > Theme Settings page. */
    public function theme()
    {
        $setting = Setting::where('deleted', 'No')->first() ?? new Setting();

        return view('admin.setting.theme', [
            'stored'      => self::themeStored($setting->theme_colors ?? null),
            'fields'      => self::THEME_FIELDS,
            'groups'      => self::THEME_GROUPS,
            'presets'     => self::themePresetsFull(),
            'buttonRadius' => (int) ($setting->button_radius ?? 5),
            'buttonRadii' => self::BUTTON_RADII,
            'templates'   => self::PDF_TEMPLATES,
            'pdfTemplate' => self::pdfTemplateKey($setting->pdf_template ?? null),
            'pdfAccent'   => $setting->pdf_accent ?? null,
            'themeAccent' => self::pdfColors()['brand'],
        ]);
    }

    public function themeSave(Request $request)
    {
        $hex = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $rules = [
            'colors'       => ['nullable', 'array'],
            'colors.light' => ['nullable', 'array'],
            'colors.dark'  => ['nullable', 'array'],
            'pdf_template' => ['required', 'in:' . implode(',', array_keys(self::PDF_TEMPLATES))],
            'pdf_accent'   => $hex,
            'button_radius' => ['required', 'integer', 'in:' . implode(',', self::BUTTON_RADII)],
        ];
        foreach (array_keys(self::THEME_FIELDS) as $key) {
            $rules["colors.light.$key"] = $hex;
            $rules["colors.dark.$key"] = $hex;
        }
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, [
            'colors.*.*.regex' => 'Colors must be hex codes like #533afd.',
            'pdf_accent.regex' => 'The PDF accent must be a hex code like #533afd.',
            'button_radius.in' => 'Button corner radius must be 5, 10 or 15 px.',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $setting = Setting::where('deleted', 'No')->first();
        if (!$setting) {
            return response()->json(['status' => false, 'message' => 'Save the General Settings first.'], 422);
        }

        $colors = self::cleanThemeColors((array) $request->input('colors', []));
        $setting->theme_colors = $colors;
        $setting->pdf_template = $request->input('pdf_template');
        $setting->pdf_accent = $request->filled('pdf_accent') ? strtolower($request->input('pdf_accent')) : null;
        $setting->button_radius = (int) $request->input('button_radius');
        $setting->save();

        return response()->json([
            'status'  => true,
            'message' => 'Theme settings saved.',
            'css'     => self::themeCss($colors, $setting->button_radius),
            'stored'  => self::themeStored($colors),
        ]);
    }

    /** Back to the design-system colors (the PDF template is kept). */
    public function themeReset()
    {
        $setting = Setting::where('deleted', 'No')->first();
        if ($setting) {
            $setting->theme_colors = null;
            $setting->save();
        }

        return response()->json(['status' => true, 'message' => 'Colors reset to the default theme.', 'css' => self::themeCss(null, $setting->button_radius ?? null), 'stored' => self::themeStored(null)]);
    }

    /** Sample invoice in a template: ?template=modern&format=html|pdf&accent=%23hex (unsaved accent for the preview). */
    public function themePdfPreview(Request $request)
    {
        $template = self::pdfTemplateKey($request->query('template'));
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $request->query('accent')) ? strtolower($request->query('accent')) : null;
        $data = self::pdfSampleData();

        if ($request->query('format') === 'pdf') {
            return self::pdf('pdf.sample', $data, 'portrait', 'a4', $template, $accent)->stream('Sample-' . ucfirst($template) . '.pdf');
        }

        return response(view('pdf.sample', $data + [
            'pdfT' => self::pdfTheme($template, $accent), 'pdfCo' => self::pdfCompany(), 'pdfPreview' => true,
        ])->render());
    }

    /* ---------------------------- colors ---------------------------- */

    /** Valid saved values only, normalized to ['light' => [key => hex], 'dark' => [...]] (reads the old flat map too). */
    public static function themeStored($stored): array
    {
        $stored = is_string($stored) ? json_decode($stored, true) : (array) $stored;
        $stored = is_array($stored) ? $stored : [];

        if (!isset($stored['light']) && !isset($stored['dark'])) {
            $stored = self::legacyThemeColors($stored);
        }

        $out = ['light' => [], 'dark' => []];
        foreach (['light', 'dark'] as $theme) {
            foreach ((array) ($stored[$theme] ?? []) as $key => $value) {
                $value = strtolower(trim((string) $value));
                if (isset(self::THEME_FIELDS[$key]) && preg_match('/^#[0-9a-f]{6}$/', $value)) {
                    $out[$theme][$key] = $value;
                }
            }
        }

        return $out;
    }

    /** The old flat light map; accent, button and sidebar colors used to apply to the dark theme too. */
    public static function legacyThemeColors(array $flat): array
    {
        $light = [];
        foreach (self::LEGACY_THEME_KEYS as $old => $new) {
            $value = strtolower((string) ($flat[$old] ?? ''));
            if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
                $light[$new] = $value;
            }
        }

        $dark = array_intersect_key($light, array_flip(['button', 'sidebar', 'sidebar_text', 'sidebar_active']));
        if (isset($light['accent'])) {
            $dark['accent'] = self::mix($light['accent'], '#ffffff', 0.55);   // readable on navy
        }

        return ['light' => $light, 'dark' => $dark];
    }

    /** What is stored: explicit values only, minus any that would resolve to the same color without them. */
    public static function cleanThemeColors(array $input): ?array
    {
        $clean = self::themeStored(['light' => $input['light'] ?? [], 'dark' => $input['dark'] ?? []]);

        foreach (['light', 'dark'] as $theme) {
            $full = self::themeResolve($clean[$theme], $theme);
            foreach (array_keys($clean[$theme]) as $key) {
                $without = $clean[$theme];
                unset($without[$key]);
                if (self::themeResolve($without, $theme) === $full) {
                    $clean[$theme] = $without;
                }
            }
        }

        return $clean['light'] || $clean['dark'] ? $clean : null;
    }

    /** Every field's color in one theme: saved value, else derived (when a color it follows changed), else default. */
    public static function themeResolve(array $values, string $theme): array
    {
        $i = $theme === 'dark' ? 1 : 0;
        $out = [];

        $get = function (string $key) use (&$get, &$out, $values, $theme, $i) {
            if (isset($out[$key])) {
                return $out[$key];
            }
            [, , , $defaults, $derive] = self::THEME_FIELDS[$key];
            if (isset($values[$key])) {
                return $out[$key] = $values[$key];
            }

            $rule = is_array($derive) && (isset($derive['light']) || isset($derive['dark'])) ? ($derive[$theme] ?? null) : $derive;
            if (!$rule) {
                return $out[$key] = $defaults[$i];
            }

            $deps = [$rule[1]];
            if (($rule[2] ?? '') !== '' && $rule[2][0] === '@') {
                $deps[] = substr($rule[2], 1);
            }
            $changed = false;
            foreach ($deps as $dep) {
                if ($get($dep) !== self::THEME_FIELDS[$dep][3][$i]) {
                    $changed = true;
                }
            }
            if (!$changed) {
                return $out[$key] = $defaults[$i];
            }

            $base = $get($rule[1]);

            return $out[$key] = match ($rule[0]) {
                'same' => $base,
                'on'   => self::textOn($base),
                'mix'  => self::mix(
                    $base,
                    $rule[2][0] === '@' ? $get(substr($rule[2], 1)) : $rule[2],
                    (float) (is_array($rule[3]) ? $rule[3][$i] : $rule[3])
                ),
            };
        };

        foreach (array_keys(self::THEME_FIELDS) as $key) {
            $get($key);
        }

        return array_merge(array_flip(array_keys(self::THEME_FIELDS)), $out);
    }

    /** CSS custom properties for one theme; $changedOnly leaves out the ones equal to tokens.css. */
    public static function themeVars(array $values, string $theme, bool $changedOnly = true): array
    {
        $i = $theme === 'dark' ? 1 : 0;
        $c = self::themeResolve($values, $theme);
        $vars = [];

        foreach (self::THEME_FIELDS as $key => [, , , $defaults, , $tokens]) {
            if ($changedOnly && $c[$key] === $defaults[$i]) {
                continue;
            }
            $v = $c[$key];
            foreach ($tokens as $token) {
                $vars[$token] = $v;
            }
            // Bootstrap's own variables, so its utilities follow too.
            match ($key) {
                'accent'  => $vars += ['--bs-primary' => $v, '--bs-primary-rgb' => self::rgbList($v)],
                'link'    => $vars += ['--bs-link-color-rgb' => self::rgbList($v), '--bs-link-hover-color' => self::mix($v, $i ? '#ffffff' : '#000000', 0.15)],
                'focus'   => $vars += ['--color-focus-ring' => self::rgba($v, $i ? 0.35 : 0.28)],
                'success', 'warning', 'danger' => $vars += ["--bs-$key-rgb" => self::rgbList($v)],
                default   => null,
            };
        }

        return $vars;
    }

    /** The <style id="theme-colors"> printed after the stylesheets (admin header + login page). */
    public static function themeCss($stored, $buttonRadius = null): string
    {
        $values = self::themeStored($stored);
        $css = [];
        $radius = (int) $buttonRadius;
        if (in_array($radius, self::BUTTON_RADII, true) && $radius !== self::BUTTON_RADII[0]) {
            $css[] = ":root{--radius-button:{$radius}px}";   // both themes and the login page
        }
        foreach (['light' => 'html:not([data-theme="dark"])', 'dark' => 'html[data-theme="dark"]'] as $theme => $selector) {
            $vars = self::themeVars($values[$theme], $theme);
            if ($vars) {
                $css[] = $selector . '{' . implode(';', array_map(fn ($k, $v) => "$k:$v", array_keys($vars), $vars)) . '}';
            }
        }

        return implode("\n", $css);
    }

    /**
     * Plain hex palette (light theme) for Excel exports and e-mails (no CSS variables there).
     * PDFs use pdfTheme(), which follows the selected PDF template.
     */
    public static function pdfColors(): array
    {
        static $palette = null;
        if ($palette !== null) {
            return $palette;
        }

        $stored = self::themeStored(\Illuminate\Support\Facades\DB::table('settings')->where('deleted', 'No')->value('theme_colors'));
        $c = self::themeResolve($stored['light'], 'light');
        $paper = '#ffffff';

        return $palette = [
            'paper'         => $paper,
            'accent'        => $c['accent'],                                    // titles, rules, links
            'brand'         => $c['button'],                                    // filled bands / table heads
            'on_brand'      => self::textOn($c['button']),
            'accent_alt'    => $c['sidebar_active'],                            // thin secondary accent lines
            'ink'           => $c['text'],
            'secondary'     => self::mix($c['text'], $paper, 0.2),
            'muted'         => $c['text_muted'],
            'faint'         => self::mix($c['text_muted'], $paper, 0.3),
            'border'        => $c['border'],
            'border_strong' => self::mix($c['border'], $c['text_muted'], 0.25),
            'soft'          => self::mix($c['bg'], $paper, 0.35),
            'success'       => $c['success'],
            'success_soft'  => self::mix($c['success'], $paper, 0.9),
            'warning'       => $c['warning'],
            'warning_soft'  => self::mix($c['warning'], $paper, 0.9),
            'danger'        => $c['danger'],
            'danger_soft'   => self::mix($c['danger'], $paper, 0.9),
        ];
    }

    /* ----------------------------- PDFs ----------------------------- */

    /** Settings > Theme Settings > PDF Template. Every PDF of the app uses resources/views/pdf/layout.blade.php in this design. */
    public const PDF_TEMPLATES = [
        'classic'   => ['Classic', 'Black & white, hairline rules and no fills. Prints and photocopies cleanly.'],
        'modern'    => ['Modern', 'Accent header band, rounded total box and subtle zebra rows.'],
        'minimal'   => ['Minimal', 'Generous white space, thin grey rules and small-caps headings.'],
        'corporate' => ['Corporate', 'Boxed header with logo and document block, bordered tables and shaded totals.'],
        'elegant'   => ['Elegant', 'Serif headings and double rules for a formal, traditional look.'],
    ];

    /** Saved template + accent, read once per request. */
    private static ?array $pdfSaved = null;

    public static function pdfTemplateKey(?string $key): string
    {
        return isset(self::PDF_TEMPLATES[$key]) ? $key : 'classic';
    }

    /**
     * The palette and fonts of a PDF template ($pdfT in the PDF views; dompdf has no CSS variables).
     * Classic is greys only; the others use the theme's button color or Theme Settings > PDF accent.
     */
    public static function pdfTheme(?string $template = null, ?string $accent = null): array
    {
        if (self::$pdfSaved === null) {
            $row = \Illuminate\Support\Facades\DB::table('settings')->where('deleted', 'No')->first();
            self::$pdfSaved = ['template' => self::pdfTemplateKey($row->pdf_template ?? null), 'accent' => $row->pdf_accent ?? null];
        }
        $saved = self::$pdfSaved;

        $template = $template ? self::pdfTemplateKey($template) : $saved['template'];
        $accent = $accent ?: ($saved['accent'] ?: self::pdfColors()['brand']);
        $pc = self::pdfColors();
        $white = '#ffffff';

        if ($template === 'classic') {
            return [
                'template' => 'classic', 'font' => 'DejaVu Sans', 'head_font' => 'DejaVu Sans',
                'ink' => '#111111', 'text' => '#222222', 'muted' => '#555555', 'faint' => '#8a8a8a',
                'rule' => '#bdbdbd', 'rule_strong' => '#111111', 'accent' => '#111111', 'on_accent' => $white,
                'accent_soft' => '#f2f2f2', 'zebra' => $white, 'total_bg' => $white, 'head_bg' => $white, 'head_text' => '#111111',
                'success' => '#111111', 'warning' => '#111111', 'danger' => '#111111', 'paper' => $white, 'page_rgb' => [0.4, 0.4, 0.4],
            ];
        }

        $soft = self::mix($accent, $white, 0.92);
        $base = [
            'template' => $template, 'font' => 'DejaVu Sans', 'head_font' => 'DejaVu Sans',
            'ink' => '#1a2433', 'text' => '#2b3442', 'muted' => '#667085', 'faint' => '#98a2b3',
            'rule' => '#e4e7ec', 'rule_strong' => '#98a2b3', 'accent' => $accent, 'on_accent' => self::textOn($accent),
            'accent_soft' => $soft, 'zebra' => '#f8f9fb', 'total_bg' => $soft, 'head_bg' => $accent, 'head_text' => self::textOn($accent),
            'success' => $pc['success'], 'warning' => $pc['warning'], 'danger' => $pc['danger'], 'paper' => $white,
            'page_rgb' => [0.4, 0.44, 0.52],
        ];

        return match ($template) {
            'minimal'   => ['rule' => '#e6e6e6', 'rule_strong' => '#bfbfbf', 'ink' => '#1f1f1f', 'text' => '#333333', 'muted' => '#7a7a7a', 'faint' => '#a6a6a6',
                            'head_bg' => $white, 'head_text' => '#7a7a7a', 'zebra' => $white, 'total_bg' => $white, 'page_rgb' => [0.55, 0.55, 0.55]] + $base,
            'corporate' => ['rule' => '#cfd5de', 'rule_strong' => '#8a94a6', 'zebra' => $white,
                            'head_bg' => self::mix($accent, '#000000', 0.1), 'head_text' => self::textOn(self::mix($accent, '#000000', 0.1))] + $base,
            'elegant'   => ['head_font' => 'DejaVu Serif', 'ink' => '#1c1c1c', 'text' => '#2e2e2e', 'muted' => '#6e6a64', 'faint' => '#a09a90',
                            'rule' => '#d9d4cb', 'rule_strong' => '#4a4640', 'head_bg' => $white, 'head_text' => '#1c1c1c', 'zebra' => $white,
                            'total_bg' => self::mix($accent, $white, 0.95), 'page_rgb' => [0.43, 0.42, 0.39]] + $base,
            default     => $base,   // modern
        };
    }

    /** Company block for the PDF header/footer ($pdfCo), images as data URIs. */
    public static function pdfCompany(): array
    {
        static $company = null;
        if ($company !== null) {
            return $company;
        }

        $s = \Illuminate\Support\Facades\DB::table('settings')->where('deleted', 'No')->first();
        $g = fn ($key) => trim((string) ($s->$key ?? '')) ?: null;

        return $company = [
            'name'          => $g('company_name') ?: config('app.name'),
            'tagline'       => $g('company_tagline'),
            'address'       => $g('address'),
            'city_line'     => trim(implode(', ', array_filter([$g('city'), $g('state'), $g('postal_code'), $g('country')])), ', ') ?: null,
            'phone'         => trim(implode(', ', array_filter([$g('phone'), $g('phone_two')]))) ?: null,
            'email'         => $g('email'),
            'website'       => $g('website'),
            'bin'           => $g('bin'),
            'tin'           => $g('tin'),
            'trade_license' => $g('trade_license'),
            'currency'      => $g('currency') ?: 'BDT',
            'footer_text'   => $g('footer_text'),
            'invoice_note'  => $g('invoice_note'),
            'terms'         => $g('terms_conditions'),
            'logo'          => self::pdfImage($g('logo')),
            'signature'     => self::pdfImage($g('signature')),
            'watermark'     => self::pdfImage($g('watermark')),
        ];
    }

    /**
     * A public/ image as a data URI for dompdf. Large images (a 1 MB logo makes every PDF 1 MB) are scaled
     * down to 600px and WEBP is converted to PNG when GD is available.
     */
    public static function pdfImage(?string $path): ?string
    {
        if (!$path || !is_file($file = public_path($path))) {
            return null;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $small = filesize($file) <= 150 * 1024;
        if ($small && in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)) {
            return 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($file));
        }
        if (!function_exists('imagecreatefromstring') || !($img = @imagecreatefromstring(file_get_contents($file)))) {
            return in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)
                ? 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($file))
                : null;
        }
        if (imagesx($img) > 600) {
            $scaled = imagescale($img, 600);
            imagedestroy($img);
            $img = $scaled;
        }
        imagesavealpha($img, true);
        ob_start();
        imagepng($img, null, 9);
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    /** Above this many table rows a PDF is refused with a hint (narrow the range or use Excel); bulk payslips are exempt. */
    public const PDF_MAX_ROWS = 2000;

    /**
     * Every PDF of the app is built here: the view (which @extends('pdf.layout')) gets the template palette
     * ($pdfT) and company ($pdfCo), and "Page X of Y" is drawn in the footer after rendering.
     */
    public static function pdf(string $view, array $data = [], string $orientation = 'portrait', $paper = 'a4', ?string $template = null, ?string $accent = null)
    {
        $t = self::pdfTheme($template, $accent);
        $data += ['pdfT' => $t, 'pdfCo' => self::pdfCompany()];

        // dompdf needs about 35 ms per table row: very long ledgers / reports would run for minutes and time out.
        // Building the HTML is cheap, so count the rows first and point the user to a narrower range or Excel.
        $html = view($view, $data)->render();
        $rows = substr_count($html, '<tr');
        if ($rows > self::PDF_MAX_ROWS && !str_starts_with($view, 'admin.payroll.payslips')) {
            $message = 'This PDF would have about ' . number_format($rows) . ' rows (around ' . number_format((int) ceil($rows / 28))
                . ' pages) and would take minutes to build. Narrow the date range or filters, or download the Excel file instead.';
            throw new \Illuminate\Http\Exceptions\HttpResponseException(request()->expectsJson()
                ? response()->json(['status' => false, 'message' => $message], 422)
                : response()->view('admin.pdf-too-large', ['message' => $message], 422));
        }
        @set_time_limit(300);   // bulk payslips and long documents within the limit still take a while

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper($paper, $orientation)
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();   // the wrapper marks it rendered, so stream()/download()/output() won't render again
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont($t['font']);
        $label = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $width = $dompdf->getFontMetrics()->getTextWidth('Page 00 of 00', $font, 5.6);
        $canvas->page_text($canvas->get_width() - 26 - $width, $canvas->get_height() - 35, $label, $font, 5.6, $t['page_rgb']);

        return $pdf;
    }

    /** Sample sales invoice used by the template previews on Theme Settings. */
    public static function pdfSampleData(): array
    {
        $items = [
            ['Basmati Rice Premium 5kg', 'Bag', 4, 820, 0],
            ['Soybean Oil 5L', 'Bottle', 2, 905, 30],
            ['Red Lentil (Masur) 1kg', 'Pack', 6, 135, 0],
            ['Sugar Refined 1kg', 'Pack', 5, 128, 10],
            ['Green Tea 100 bags', 'Box', 3, 340, 0],
        ];
        $rows = [];
        $sub = 0;
        foreach ($items as $n => [$name, $unit, $qty, $price, $disc]) {
            $total = $qty * $price - $disc;
            $sub += $total;
            $rows[] = ['n' => $n + 1, 'name' => $name, 'unit' => $unit, 'qty' => $qty, 'price' => $price, 'disc' => $disc, 'total' => $total];
        }
        $discount = 100;
        $vat = round(($sub - $discount) * 0.05, 2);
        $grand = $sub - $discount + $vat;

        return [
            'rows' => $rows, 'subtotal' => $sub, 'discount' => $discount, 'vat' => $vat, 'grand' => $grand,
            'paid' => 5000, 'due' => $grand - 5000,
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
