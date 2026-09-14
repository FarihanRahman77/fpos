@extends('admin.master')

@section('title', 'General Settings')

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
                               class="form-control"
                               value="{{ old('city', $setting->city ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            State
                        </label>

                        <input type="text"
                               name="state"
                               class="form-control"
                               value="{{ old('state', $setting->state ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Country
                        </label>

                        <input type="text"
                               name="country"
                               class="form-control"
                               value="{{ old('country', $setting->country ?? 'Bangladesh') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Postal Code
                        </label>

                        <input type="text"
                               name="postal_code"
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
                               class="form-control"
                               value="{{ old('phone_two', $setting->phone_two ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input type="email"
                               name="email"
                               class="form-control"
                               value="{{ old('email', $setting->email ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Email Two
                        </label>

                        <input type="email"
                               name="email_two"
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
                               class="form-control"
                               value="{{ old('trade_license', $setting->trade_license ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            TIN
                        </label>

                        <input type="text"
                               name="tin"
                               class="form-control"
                               value="{{ old('tin', $setting->tin ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            BIN
                        </label>

                        <input type="text"
                               name="bin"
                               class="form-control"
                               value="{{ old('bin', $setting->bin ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            GST
                        </label>

                        <input type="text"
                               name="gst"
                               class="form-control"
                               value="{{ old('gst', $setting->gst ?? '') }}">

                    </div>


                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Tax Rate (%)
                        </label>

                        <input type="number"
                               name="tax_rate"
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
                               class="form-control"
                               value="{{ old('timezone', $setting->timezone ?? 'Asia/Dhaka') }}">

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Date Format
                        </label>

                        <input type="text"
                               name="date_format"
                               class="form-control"
                               value="{{ old('date_format', $setting->date_format ?? 'd-m-Y') }}">

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Time Format
                        </label>

                        <input type="text"
                               name="time_format"
                               class="form-control"
                               value="{{ old('time_format', $setting->time_format ?? 'h:i A') }}">

                    </div>


                    <!-- STATUS -->

                    

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

<script>

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


@endsection
