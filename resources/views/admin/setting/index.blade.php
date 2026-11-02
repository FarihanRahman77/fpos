@extends('admin.master')

@section('title')
{{ $generalSetting['company_name'] ?? '' }} Settings
@endsection

@section('content')

<div class="container-fluid">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">General Settings</h4>

        </div>
    </div>


    <form action="{{ route('admin.settings.update') }}"
          method="POST"
          enctype="multipart/form-data">

        @csrf


        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>

                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif


        {{-- Success Message --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert">
                </button>
            </div>
        @endif


        <!-- ========================================================= -->
        <!-- COMPANY INFORMATION -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-building me-2"></i>
                    Company Information
                </h5>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Company Name
                        </label>

                        <input type="text"
                               name="company_name"
                               class="form-control"
                               value="{{ old('company_name', $setting->company_name ?? '') }}"
                               placeholder="Enter company name">

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Company Short Name
                        </label>

                        <input type="text"
                               name="company_short_name"
                               class="form-control"
                               value="{{ old('company_short_name', $setting->company_short_name ?? '') }}"
                               placeholder="Enter short name">

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Company Tagline
                        </label>

                        <input type="text"
                               name="company_tagline"
                               class="form-control"
                               value="{{ old('company_tagline', $setting->company_tagline ?? '') }}"
                               placeholder="Enter company tagline">

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- BRANDING -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-image me-2"></i>
                    Branding
                </h5>
            </div>


            <div class="card-body">

                <div class="row">


                    <!-- LOGO -->
                    <div class="col-md-4 mb-4">

                        <label class="form-label">
                            Logo
                        </label>

                        <input type="file"
                               name="logo"
                               id="logo"
                               class="form-control image-input"
                               accept="image/*"
                               data-preview="logoPreview">

                        <div class="mt-3">

                            <div class="border rounded p-2 text-center bg-light"
                                 style="min-height: 130px;">

                                <img
                                    id="logoPreview"
                                    src="{{ !empty($setting->logo) ? asset($setting->logo) : '' }}"
                                    alt="Logo Preview"
                                    class="image-preview"
                                    style="
                                        max-width: 100%;
                                        max-height: 110px;
                                        {{ empty($setting->logo) ? 'display:none;' : '' }}
                                    "
                                >

                                <div class="no-image-text"
                                     id="logoNoImage"
                                     style="{{ !empty($setting->logo) ? 'display:none;' : '' }}">

                                    <i class="bi bi-image fs-2 text-muted"></i>

                                    <div class="text-muted small">
                                        No logo selected
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- DARK LOGO -->
                    <div class="col-md-4 mb-4">

                        <label class="form-label">
                            Dark Logo
                        </label>

                        <input type="file"
                               name="logo_dark"
                               id="logo_dark"
                               class="form-control image-input"
                               accept="image/*"
                               data-preview="logoDarkPreview">

                        <div class="mt-3">

                            <div class="border rounded p-2 text-center bg-dark"
                                 style="min-height: 130px;">

                                <img
                                    id="logoDarkPreview"
                                    src="{{ !empty($setting->logo_dark) ? asset($setting->logo_dark) : '' }}"
                                    alt="Dark Logo Preview"
                                    class="image-preview"
                                    style="
                                        max-width: 100%;
                                        max-height: 110px;
                                        {{ empty($setting->logo_dark) ? 'display:none;' : '' }}
                                    "
                                >

                                <div class="no-image-text text-white"
                                     id="logoDarkNoImage"
                                     style="{{ !empty($setting->logo_dark) ? 'display:none;' : '' }}">

                                    <i class="bi bi-image fs-2"></i>

                                    <div class="small">
                                        No dark logo selected
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- FAVICON -->
                    <div class="col-md-4 mb-4">

                        <label class="form-label">
                            Favicon
                        </label>

                        <input type="file"
                               name="favicon"
                               id="favicon"
                               class="form-control image-input"
                               accept="image/png,image/jpeg,image/webp,image/x-icon"
                               data-preview="faviconPreview">

                        <div class="mt-3">

                            <div class="border rounded p-2 text-center bg-light"
                                 style="min-height: 130px;">

                                <img
                                    id="faviconPreview"
                                    src="{{ !empty($setting->favicon) ? asset($setting->favicon) : '' }}"
                                    alt="Favicon Preview"
                                    class="image-preview"
                                    style="
                                        max-width: 80px;
                                        max-height: 80px;
                                        {{ empty($setting->favicon) ? 'display:none;' : '' }}
                                    "
                                >

                                <div class="no-image-text"
                                     id="faviconNoImage"
                                     style="{{ !empty($setting->favicon) ? 'display:none;' : '' }}">

                                    <i class="bi bi-image fs-2 text-muted"></i>

                                    <div class="text-muted small">
                                        No favicon selected
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- SIGNATURE -->
                    <div class="col-md-6 mb-4">

                        <label class="form-label">
                            Signature
                        </label>

                        <input type="file"
                               name="signature"
                               id="signature"
                               class="form-control image-input"
                               accept="image/*"
                               data-preview="signaturePreview">

                        <div class="mt-3">

                            <div class="border rounded p-2 text-center bg-light"
                                 style="min-height: 140px;">

                                <img
                                    id="signaturePreview"
                                    src="{{ !empty($setting->signature) ? asset($setting->signature) : '' }}"
                                    alt="Signature Preview"
                                    class="image-preview"
                                    style="
                                        max-width: 100%;
                                        max-height: 120px;
                                        {{ empty($setting->signature) ? 'display:none;' : '' }}
                                    "
                                >

                                <div class="no-image-text"
                                     id="signatureNoImage"
                                     style="{{ !empty($setting->signature) ? 'display:none;' : '' }}">

                                    <i class="bi bi-pen fs-2 text-muted"></i>

                                    <div class="text-muted small">
                                        No signature selected
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- WATERMARK -->
                    <div class="col-md-6 mb-4">

                        <label class="form-label">
                            Watermark
                        </label>

                        <input type="file"
                               name="watermark"
                               id="watermark"
                               class="form-control image-input"
                               accept="image/*"
                               data-preview="watermarkPreview">

                        <div class="mt-3">

                            <div class="border rounded p-2 text-center bg-light"
                                 style="min-height: 140px;">

                                <img
                                    id="watermarkPreview"
                                    src="{{ !empty($setting->watermark) ? asset($setting->watermark) : '' }}"
                                    alt="Watermark Preview"
                                    class="image-preview"
                                    style="
                                        max-width: 100%;
                                        max-height: 120px;
                                        {{ empty($setting->watermark) ? 'display:none;' : '' }}
                                    "
                                >

                                <div class="no-image-text"
                                     id="watermarkNoImage"
                                     style="{{ !empty($setting->watermark) ? 'display:none;' : '' }}">

                                    <i class="bi bi-droplet fs-2 text-muted"></i>

                                    <div class="text-muted small">
                                        No watermark selected
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- LOGIN PAGE BACKGROUND -->
                    <div class="col-12 mb-4">
                        <label class="form-label" for="login_background">Login Page Background</label>
                        <div class="row g-3 align-items-start">
                            <div class="col-md-6">
                                <input type="file" name="login_background" id="login_background" class="form-control image-input"
                                    accept="image/png,image/jpeg,image/webp" data-preview="loginBgPreview">
                                <small class="text-muted d-block mt-1">Shown behind the right-hand panel of the login page. A wide photo works best (about 1600 × 1200, up to 5 MB).</small>

                                <label class="form-label mt-3" for="login_overlay">
                                    Brand color over the image: <span id="loginOverlayValue">{{ (int) ($setting->login_overlay ?? 70) }}</span>%
                                </label>
                                <input type="range" class="form-range" name="login_overlay" id="login_overlay" min="0" max="95" step="5"
                                    value="{{ (int) ($setting->login_overlay ?? 70) }}">
                                <small class="text-muted d-block">Higher keeps the text easy to read; lower shows more of the photo. The color is the Sidebar background from Theme Colors.</small>

                                @if (!empty($setting->login_background))
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" name="remove_login_background" id="remove_login_background" value="1">
                                        <label class="form-check-label" for="remove_login_background">Remove the background image</label>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <div class="login-bg-preview" id="loginBgBox" style="--login-overlay: {{ (int) ($setting->login_overlay ?? 70) }}%;">
                                    <img id="loginBgPreview" src="{{ !empty($setting->login_background) ? asset($setting->login_background) : '' }}"
                                        alt="Login background preview" class="image-preview"
                                        style="{{ empty($setting->login_background) ? 'display:none;' : '' }}">
                                    <div class="login-bg-shade"></div>
                                    <div class="login-bg-text">Everything in its right place.</div>
                                    <div class="no-image-text" id="loginBgNoImage" style="{{ !empty($setting->login_background) ? 'display:none;' : '' }}"></div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- ADDRESS & CONTACT -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-geo-alt me-2"></i>
                    Address & Contact Information
                </h5>
            </div>


            <div class="card-body">

                <div class="row">


                    <div class="col-md-12 mb-3">

                        <label class="form-label">
                            Address
                        </label>

                        <textarea
                            name="address"
                            rows="3"
                            class="form-control"
                            placeholder="Enter company address">{{ old('address', $setting->address ?? '') }}</textarea>

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            City
                        </label>

                        <input type="text"
                               name="city"
                               placeholder="e.g. Dhaka"
                               class="form-control"
                               value="{{ old('city', $setting->city ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            State
                        </label>

                        <input type="text"
                               name="state"
                               placeholder="e.g. Dhaka Division"
                               class="form-control"
                               value="{{ old('state', $setting->state ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Country
                        </label>

                        <input type="text"
                               name="country"
                               placeholder="e.g. Bangladesh"
                               class="form-control"
                               value="{{ old('country', $setting->country ?? 'Bangladesh') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Postal Code
                        </label>

                        <input type="text"
                               name="postal_code"
                               placeholder="e.g. 1207"
                               class="form-control"
                               value="{{ old('postal_code', $setting->postal_code ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Phone
                        </label>

                        <input type="text"
                               name="phone"
                               class="form-control"
                               value="{{ old('phone', $setting->phone ?? '') }}"
                               placeholder="01XXXXXXXXX">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Phone Two
                        </label>

                        <input type="text"
                               name="phone_two"
                               placeholder="e.g. 01711000000"
                               class="form-control"
                               value="{{ old('phone_two', $setting->phone_two ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input type="email"
                               name="email"
                               placeholder="e.g. info@example.com"
                               class="form-control"
                               value="{{ old('email', $setting->email ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Email Two
                        </label>

                        <input type="email"
                               name="email_two"
                               placeholder="e.g. support@example.com"
                               class="form-control"
                               value="{{ old('email_two', $setting->email_two ?? '') }}">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Website
                        </label>

                        <input type="text"
                               name="website"
                               class="form-control"
                               value="{{ old('website', $setting->website ?? '') }}"
                               placeholder="https://example.com">

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- TAX & BUSINESS -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-receipt me-2"></i>
                    Tax & Business Information
                </h5>
            </div>


            <div class="card-body">

                <div class="row">

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Trade License
                        </label>

                        <input type="text"
                               name="trade_license"
                               placeholder="e.g. TRAD/DNCC/012345/2024"
                               class="form-control"
                               value="{{ old('trade_license', $setting->trade_license ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            TIN
                        </label>

                        <input type="text"
                               name="tin"
                               placeholder="e.g. 123456789012"
                               class="form-control"
                               value="{{ old('tin', $setting->tin ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            BIN
                        </label>

                        <input type="text"
                               name="bin"
                               placeholder="e.g. 000123456-0101"
                               class="form-control"
                               value="{{ old('bin', $setting->bin ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            GST
                        </label>

                        <input type="text"
                               name="gst"
                               placeholder="Enter GST number"
                               class="form-control"
                               value="{{ old('gst', $setting->gst ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Tax Rate (%)
                        </label>

                        <input type="number"
                               name="tax_rate"
                               placeholder="e.g. 15"
                               class="form-control"
                               step="0.01"
                               min="0"
                               max="100"
                               value="{{ old('tax_rate', $setting->tax_rate ?? 0) }}">

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- CURRENCY -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-currency-exchange me-2"></i>
                    Currency Settings
                </h5>
            </div>


            <div class="card-body">

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Currency
                        </label>

                        <input type="text"
                               name="currency"
                               class="form-control"
                               value="{{ old('currency', $setting->currency ?? 'BDT') }}"
                               placeholder="BDT">

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Currency Symbol
                        </label>

                        <input type="text"
                               name="currency_symbol"
                               class="form-control"
                               value="{{ old('currency_symbol', $setting->currency_symbol ?? '৳') }}"
                               placeholder="৳">

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Currency Position
                        </label>

                        <select name="currency_position"
                                class="form-select">

                            <option value="before"
                                {{ old('currency_position', $setting->currency_position ?? 'before') == 'before' ? 'selected' : '' }}>
                                Before Amount
                            </option>

                            <option value="after"
                                {{ old('currency_position', $setting->currency_position ?? '') == 'after' ? 'selected' : '' }}>
                                After Amount
                            </option>

                        </select>

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- HEADER & FOOTER -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-layout-text-window-reverse me-2"></i>
                    Header & Footer
                </h5>
            </div>


            <div class="card-body">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Header Text
                        </label>

                        <textarea
                            name="header_text"
                            rows="4"
                            class="form-control"
                            placeholder="Enter header text">{{ old('header_text', $setting->header_text ?? '') }}</textarea>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Footer Text
                        </label>

                        <textarea
                            name="footer_text"
                            rows="4"
                            class="form-control"
                            placeholder="Enter footer text">{{ old('footer_text', $setting->footer_text ?? '') }}</textarea>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Invoice Note
                        </label>

                        <textarea
                            name="invoice_note"
                            rows="4"
                            class="form-control"
                            placeholder="Enter invoice note">{{ old('invoice_note', $setting->invoice_note ?? '') }}</textarea>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Terms & Conditions
                        </label>

                        <textarea
                            name="terms_conditions"
                            rows="4"
                            class="form-control"
                            placeholder="Enter terms and conditions">{{ old('terms_conditions', $setting->terms_conditions ?? '') }}</textarea>

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- SOCIAL MEDIA -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-share me-2"></i>
                    Social Media
                </h5>
            </div>


            <div class="card-body">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Facebook
                        </label>

                        <input type="text"
                               name="facebook"
                               class="form-control"
                               value="{{ old('facebook', $setting->facebook ?? '') }}"
                               placeholder="Facebook URL">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Instagram
                        </label>

                        <input type="text"
                               name="instagram"
                               class="form-control"
                               value="{{ old('instagram', $setting->instagram ?? '') }}"
                               placeholder="Instagram URL">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            YouTube
                        </label>

                        <input type="text"
                               name="youtube"
                               class="form-control"
                               value="{{ old('youtube', $setting->youtube ?? '') }}"
                               placeholder="YouTube URL">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            LinkedIn
                        </label>

                        <input type="text"
                               name="linkedin"
                               class="form-control"
                               value="{{ old('linkedin', $setting->linkedin ?? '') }}"
                               placeholder="LinkedIn URL">

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- SYSTEM SETTINGS -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-gear me-2"></i>
                    System Settings
                </h5>
            </div>


            <div class="card-body">

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Timezone
                        </label>

                        <input type="text"
                               name="timezone"
                               placeholder="e.g. Asia/Dhaka"
                               class="form-control"
                               value="{{ old('timezone', $setting->timezone ?? 'Asia/Dhaka') }}">

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Date Format
                        </label>

                        <input type="text"
                               name="date_format"
                               placeholder="e.g. d-m-Y"
                               class="form-control"
                               value="{{ old('date_format', $setting->date_format ?? 'd-m-Y') }}">

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Time Format
                        </label>

                        <input type="text"
                               name="time_format"
                               placeholder="e.g. h:i A"
                               class="form-control"
                               value="{{ old('time_format', $setting->time_format ?? 'h:i A') }}">

                    </div>


                    <!-- STATUS -->



                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- THEME COLORS -->
        <!-- ========================================================= -->

        @php
            $themeSaved  = (array) old('theme_colors', $setting->theme_colors ?? []);
        @endphp

        <div class="card shadow-sm mb-4" id="themeColors">

            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <h5 class="mb-0">
                    <i class="bi bi-palette me-2"></i>
                    Theme Colors
                </h5>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="themeResetAll">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset all to default
                </button>
            </div>

            <div class="card-body">
                <p class="text-muted small mb-3">
                    Leave a color on <b>default</b> to keep the built-in theme. Hover, soft and dark-mode shades are worked out automatically.
                    Top bar, page background and status colors apply to the light theme; the others apply to both themes.
                </p>

                @error('theme_colors.*')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                <div class="row g-4">
                    <div class="col-xl-7">
                        @foreach (collect($themeFields)->groupBy(fn ($f) => $f[3], true) as $group => $fields)
                            <h6 class="text-uppercase text-muted small fw-semibold mb-2 {{ $loop->first ? '' : 'mt-3' }}">{{ $group }}</h6>
                            <div class="row g-3">
                                @foreach ($fields as $key => [$label, $help, $default])
                                    @php $value = $themeSaved[$key] ?? ''; @endphp
                                    <div class="col-md-6">
                                        <label class="form-label mb-1" for="theme_{{ $key }}_hex">{{ $label }}</label>
                                        <div class="input-group theme-color" data-key="{{ $key }}" data-default="{{ $default }}">
                                            <input type="color" class="form-control form-control-color theme-picker" value="{{ $value ?: $default }}"
                                                aria-label="{{ $label }} picker" title="Pick a color">
                                            <input type="text" class="form-control theme-hex" id="theme_{{ $key }}_hex" name="theme_colors[{{ $key }}]"
                                                value="{{ $value }}" placeholder="default {{ $default }}" maxlength="7" pattern="#[0-9a-fA-F]{6}" autocomplete="off">
                                            <button type="button" class="btn btn-outline-secondary theme-default" title="Back to default {{ $default }}">Default</button>
                                        </div>
                                        <div class="form-text">{{ $help }}</div>
                                        <div class="small text-danger theme-warning"></div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="col-xl-5">
                        <h6 class="text-uppercase text-muted small fw-semibold mb-2">Preview</h6>
                        <div class="theme-preview" id="themePreview" aria-hidden="true">
                            <div class="tp-sidebar">
                                <div class="tp-brand">FPOS</div>
                                <div class="tp-item"><i class="bi bi-speedometer2"></i> Dashboard</div>
                                <div class="tp-item tp-active"><i class="bi bi-people"></i> Employees</div>
                                <div class="tp-item"><i class="bi bi-bar-chart"></i> Reports</div>
                            </div>
                            <div class="tp-main">
                                <div class="tp-topbar"><span class="tp-burger">☰</span><span class="tp-search">Search…</span><i class="bi bi-moon"></i></div>
                                <div class="tp-page">
                                    <div class="tp-card">
                                        <div class="tp-title">Employees <a href="javascript:void(0)" class="tp-link">View all</a></div>
                                        <div class="tp-muted mb-2">12 active · 3 on leave</div>
                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                            <span class="tp-badge" data-status="success">Active</span>
                                            <span class="tp-badge" data-status="warning">Pending</span>
                                            <span class="tp-badge" data-status="danger">Rejected</span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <span class="tp-btn">Save</span>
                                            <span class="tp-btn tp-btn-outline">Cancel</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">The preview shows the light theme. Save to apply everywhere.</div>
                    </div>
                </div>
            </div>

        </div>


        <!-- ========================================================= -->
        <!-- SAVE BUTTON -->
        <!-- ========================================================= -->

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <div class="d-flex justify-content-end">

                    <button type="submit"
                            class="btn btn-primary px-4">

                        <i class="bi bi-check-circle me-1"></i>

                        Save Settings

                    </button>

                </div>

            </div>

        </div>


    </form>

</div>


<!-- ============================================================= -->
<!-- IMAGE PREVIEW SCRIPT -->
<!-- ============================================================= -->

<style>
    .theme-color .form-control-color { max-width: 52px; min-height: 44px; padding: 4px; }
    .theme-color .theme-hex, .theme-color .btn { min-height: 44px; }
    .theme-color .theme-hex { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; text-transform: lowercase; }
    .theme-preview { display: flex; min-height: 230px; border: 1px solid var(--admin-border); border-radius: var(--radius-lg); overflow: hidden; font-size: var(--text-caption); }
    .tp-sidebar { width: 38%; padding: 10px 8px; }
    .tp-brand { font-weight: var(--font-weight-medium); font-size: var(--text-body-lg); padding: 2px 8px 10px; }
    .tp-item { padding: 7px 8px; border-radius: var(--radius-sm); margin-bottom: 2px; }
    .tp-item i { margin-right: 4px; }
    .tp-main { flex: 1; display: flex; flex-direction: column; }
    .tp-topbar { display: flex; align-items: center; gap: 8px; padding: 9px 10px; border-bottom: 1px solid; }
    .tp-search { flex: 1; border: 1px solid; border-radius: var(--radius-sm); padding: 2px 8px; opacity: .8; }
    .tp-page { flex: 1; padding: 10px; }
    .tp-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 10px; color: var(--color-text); }
    .tp-title { font-weight: var(--font-weight-medium); display: flex; justify-content: space-between; margin-bottom: 8px; }
    .tp-link { text-decoration: none; font-weight: var(--font-weight-medium); }
    .tp-badge { border-radius: var(--radius-pill); padding: 2px 8px; color: var(--color-on-primary); font-weight: var(--font-weight-medium); font-size: var(--text-micro); }
    .tp-btn { border-radius: var(--radius-sm); padding: 5px 12px; font-weight: var(--font-weight-medium); border: 1px solid; }

    /* Login background preview: the image, the brand color at the chosen strength, sample text. */
    .login-bg-preview { position: relative; height: 190px; overflow: hidden; border-radius: var(--radius-lg); background: var(--color-sidebar); }
    .login-bg-preview img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; max-height: none !important; max-width: none !important; }
    .login-bg-shade { position: absolute; inset: 0; background: color-mix(in srgb, var(--color-sidebar) var(--login-overlay, 70%), transparent); }
    .login-bg-text { position: absolute; left: 0; right: 0; bottom: var(--space-lg); text-align: center; color: var(--color-sidebar-text-strong); font-size: var(--text-body-lg); font-weight: var(--font-weight-medium); }
    .login-bg-preview .no-image-text { display: none !important; }
</style>

<script>

/* Theme colors: picker <-> hex box, default buttons, contrast warnings and the live preview. */
document.addEventListener('DOMContentLoaded', function () {

    const card = document.getElementById('themeColors');
    if (!card) return;

    const rows = Array.from(card.querySelectorAll('.theme-color'));
    const valid = function (v) { return /^#[0-9a-f]{6}$/i.test(v); };
    const get = function (key) {
        const row = card.querySelector('.theme-color[data-key="' + key + '"]');
        const v = row.querySelector('.theme-hex').value.trim();
        return (valid(v) ? v : row.dataset.default).toLowerCase();
    };

    // Same math as SettingController (theme color helpers).
    const rgb = function (h) { return [1, 3, 5].map(function (i) { return parseInt(h.substr(i, 2), 16); }); };
    const lum = function (h) {
        const c = rgb(h).map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
        return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    };
    const contrast = function (a, b) { const l = [lum(a), lum(b)].sort(function (x, y) { return y - x; }); return (l[0] + 0.05) / (l[1] + 0.05); };
    const textOn = function (bg) { return contrast(bg, '#ffffff') >= contrast(bg, '#0d253d') ? '#ffffff' : '#0d253d'; };
    const mix = function (a, b, t) {
        const x = rgb(a), y = rgb(b);
        return '#' + x.map(function (v, i) { return Math.round(v + (y[i] - v) * t).toString(16).padStart(2, '0'); }).join('');
    };
    const alpha = function (h, a) { return 'rgba(' + rgb(h).join(',') + ',' + a + ')'; };

    // [key, against, what] pairs checked for readability (WCAG AA 4.5:1 for text, 3:1 for large/UI).
    const checks = {
        primary: [function (c) { return contrast(c.primary, c.surface); }, 4.5, 'Links in this color are hard to read on the cards.'],
        sidebar_text: [function (c) { return contrast(c.sidebar_text, c.sidebar_bg); }, 4.5, 'Sidebar text is hard to read on the sidebar background.'],
        sidebar_active: [function (c) { return contrast(c.sidebar_active, c.sidebar_bg); }, 3, 'Active item barely stands out from the sidebar.'],
        topbar_text: [function (c) { return contrast(c.topbar_text, c.topbar_bg); }, 4.5, 'Top bar text is hard to read on its background.'],
        text: [function (c) { return Math.min(contrast(c.text, c.surface), contrast(c.text, c.page_bg)); }, 4.5, 'Text is hard to read on the cards or the page.'],
        text_muted: [function (c) { return Math.min(contrast(c.text_muted, c.surface), contrast(c.text_muted, c.page_bg)); }, 4.5, 'Secondary text is hard to read on the cards or the page.'],
        border: [function (c) { return contrast(c.border, c.surface); }, 1.15, 'Borders are almost invisible on the cards.'],
        success: [function (c) { return contrast(c.success, c.surface); }, 4.5, 'Too light for text on the cards.'],
        warning: [function (c) { return contrast(c.warning, c.surface); }, 4.5, 'Too light for text on the cards.'],
        danger: [function (c) { return contrast(c.danger, c.surface); }, 4.5, 'Too light for text on the cards.']
    };

    function refresh() {
        const c = {};
        rows.forEach(function (row) { c[row.dataset.key] = get(row.dataset.key); });

        rows.forEach(function (row) {
            const key = row.dataset.key;
            const hex = row.querySelector('.theme-hex');
            const warn = row.parentElement.querySelector('.theme-warning');
            let msg = hex.value.trim() && !valid(hex.value.trim()) ? 'Use a hex code like #916100.' : '';
            if (!msg && checks[key]) {
                const ratio = checks[key][0](c);
                if (ratio < checks[key][1]) msg = checks[key][2] + ' (contrast ' + ratio.toFixed(1) + ':1)';
            }
            warn.textContent = msg;
            hex.classList.toggle('is-invalid', hex.value.trim() !== '' && !valid(hex.value.trim()));
        });

        const p = document.getElementById('themePreview');
        const side = p.querySelector('.tp-sidebar');
        side.style.background = c.sidebar_bg;
        side.style.color = c.sidebar_text;
        p.querySelector('.tp-brand').style.color = c.sidebar_active;
        const active = p.querySelector('.tp-active');
        active.style.background = alpha(c.sidebar_active, 0.14);
        active.style.boxShadow = 'inset 3px 0 0 ' + c.sidebar_active;
        active.style.color = textOn(c.sidebar_bg);
        active.querySelector('i').style.color = c.sidebar_active;

        const top = p.querySelector('.tp-topbar');
        top.style.background = c.topbar_bg;
        top.style.color = c.topbar_text;
        top.style.borderColor = mix(c.topbar_bg, c.topbar_text, 0.12);
        p.querySelector('.tp-search').style.borderColor = mix(c.topbar_bg, c.topbar_text, 0.18);
        p.querySelector('.tp-page').style.background = c.page_bg;
        const tpCard = p.querySelector('.tp-card');
        tpCard.style.background = c.surface;
        tpCard.style.borderColor = c.border;
        tpCard.style.color = c.text;
        p.querySelector('.tp-muted').style.color = c.text_muted;
        p.querySelector('.tp-link').style.color = c.primary;

        const btn = p.querySelector('.tp-btn:not(.tp-btn-outline)');
        btn.style.background = c.button;
        btn.style.borderColor = c.button;
        btn.style.color = textOn(c.button);
        const outline = p.querySelector('.tp-btn-outline');
        outline.style.color = c.button;
        outline.style.borderColor = c.button;
        p.querySelectorAll('.tp-badge').forEach(function (b) {
            b.style.background = c[b.dataset.status];
            b.style.color = textOn(c[b.dataset.status]);
        });
    }

    rows.forEach(function (row) {
        const picker = row.querySelector('.theme-picker');
        const hex = row.querySelector('.theme-hex');

        picker.addEventListener('input', function () { hex.value = picker.value; refresh(); });
        hex.addEventListener('input', function () {
            if (valid(hex.value.trim())) picker.value = hex.value.trim().toLowerCase();
            refresh();
        });
        row.querySelector('.theme-default').addEventListener('click', function () {
            hex.value = '';
            picker.value = row.dataset.default;
            refresh();
        });
    });

    document.getElementById('themeResetAll').addEventListener('click', function () {
        rows.forEach(function (row) {
            row.querySelector('.theme-hex').value = '';
            row.querySelector('.theme-picker').value = row.dataset.default;
        });
        refresh();
    });

    refresh();
});

document.addEventListener('DOMContentLoaded', function () {

    const imageInputs = document.querySelectorAll('.image-input');


    imageInputs.forEach(function (input) {

        input.addEventListener('change', function (event) {

            const file = event.target.files[0];

            const previewId = input.getAttribute('data-preview');

            const preview = document.getElementById(previewId);


            if (!preview) {
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | No file selected
            |--------------------------------------------------------------------------
            */

            if (!file) {

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | Check image
            |--------------------------------------------------------------------------
            */

            if (!file.type.startsWith('image/')) {

                alert('Please select a valid image file.');

                input.value = '';

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | FileReader
            |--------------------------------------------------------------------------
            */

            const reader = new FileReader();


            reader.onload = function (e) {

                preview.src = e.target.result;

                preview.style.display = 'inline-block';


                /*
                |--------------------------------------------------------------------------
                | Hide "No Image" message
                |--------------------------------------------------------------------------
                */

                const noImageElement =
                    document.getElementById(
                        previewId.replace('Preview', 'NoImage')
                    );


                if (noImageElement) {

                    noImageElement.style.display = 'none';

                }

            };


            reader.readAsDataURL(file);

        });

    });

});

</script>

<script>
    // Login background: live overlay strength in the preview.
    document.addEventListener('DOMContentLoaded', function () {
        const range = document.getElementById('login_overlay');
        if (!range) return;
        range.addEventListener('input', function () {
            document.getElementById('loginOverlayValue').textContent = range.value;
            document.getElementById('loginBgBox').style.setProperty('--login-overlay', range.value + '%');
        });
    });
</script>


@endsection
