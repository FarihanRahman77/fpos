@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Theme Settings
@endsection

@push('styles')
    <style>
        .ts-head { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-md); margin-bottom: var(--space-xl); }
        .ts-head h4 { margin: 0; }
        .ts-head .ts-actions { margin-left: auto; display: flex; flex-wrap: wrap; gap: var(--space-sm); }

        /* Presets */
        .ts-presets { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: var(--space-md); }
        .ts-preset { display: flex; flex-direction: column; gap: var(--space-sm); padding: var(--space-md); min-height: 44px; text-align: left;
            border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text); cursor: pointer; }
        .ts-preset:hover { border-color: var(--color-accent); }
        .ts-preset:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
        .ts-preset.active { border-color: var(--color-accent); box-shadow: 0 0 0 1px var(--color-accent); }
        .ts-swatches { display: flex; gap: var(--space-xs); }
        .ts-swatches span { flex: 1; height: 22px; border-radius: var(--radius-xs); border: 1px solid var(--color-border); }
        .ts-swatches.dark span { height: 10px; }
        .ts-preset-name { font-size: var(--text-caption); font-weight: var(--font-weight-medium); }
        .ts-tabs .nav-link { min-height: 44px; border-radius: var(--radius-button); color: var(--color-accent); }
        .ts-tabs .nav-link.active { background: var(--color-button); color: var(--color-button-text); }
        .ts-radii { display: flex; flex-wrap: wrap; gap: var(--space-md); }
        .ts-radius { display: flex; align-items: center; gap: var(--space-md); min-height: 44px; padding: var(--space-sm) var(--space-lg);
            border: 1px solid var(--color-border); border-radius: var(--radius-md); cursor: pointer; color: var(--color-text); }
        .ts-radius.active { border-color: var(--color-accent); box-shadow: 0 0 0 1px var(--color-accent); }
        .ts-radius-sample { display: inline-block; padding: 6px var(--space-lg); background: var(--color-button); color: var(--color-button-text); font-size: var(--text-caption); }
        .ts-radius-sample[data-radius="5"] { border-radius: 5px; }
        .ts-radius-sample[data-radius="10"] { border-radius: 10px; }
        .ts-radius-sample[data-radius="15"] { border-radius: 15px; }
        .tp-hero { border-radius: var(--radius-md); padding: var(--space-md); color: var(--color-hero-text);
            background: radial-gradient(ellipse at 100% 0%, color-mix(in srgb, var(--color-hero-accent) 45%, transparent), transparent 60%), var(--color-hero-bg); }
        .tp-hero b { display: block; font-size: var(--text-heading-sm); font-weight: var(--font-weight-light); }
        .tp-hero small { color: var(--color-hero-muted); }

        /* Editor */
        .ts-group { border: 1px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: var(--space-md); background: var(--color-surface); }
        .ts-group > summary { list-style: none; display: flex; align-items: center; gap: var(--space-sm); min-height: 44px; padding: var(--space-sm) var(--space-lg);
            cursor: pointer; font-weight: var(--font-weight-medium); color: var(--color-text); }
        .ts-group > summary::-webkit-details-marker { display: none; }
        .ts-group > summary .ts-count { margin-left: auto; font-size: var(--text-micro); color: var(--color-text-muted); font-weight: var(--font-weight-regular); }
        .ts-group > summary .bi-chevron-down { transition: transform .15s ease; color: var(--color-text-muted); }
        .ts-group[open] > summary .bi-chevron-down { transform: rotate(180deg); }
        .ts-group > summary:focus-visible { outline: none; box-shadow: var(--shadow-focus); border-radius: var(--radius-md); }
        .ts-fields { padding: 0 var(--space-lg) var(--space-lg); display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-lg); }
        .ts-field label { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--text-caption); font-weight: var(--font-weight-medium); color: var(--color-text); margin-bottom: var(--space-xs); }
        .ts-state { font-size: var(--text-micro-cap); text-transform: uppercase; letter-spacing: .04em; padding: 1px 6px; border-radius: var(--radius-pill);
            background: var(--color-surface-soft); color: var(--color-text-muted); }
        .ts-state.custom { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .ts-input { display: flex; gap: var(--space-xs); }
        .ts-input .form-control-color { width: 48px; flex: 0 0 48px; min-height: 44px; padding: var(--space-xs); }
        .ts-input .ts-hex { font-family: var(--font-mono); text-transform: lowercase; min-height: 44px; }
        .ts-input .btn { min-width: 44px; min-height: 44px; }
        .ts-help { font-size: var(--text-micro); color: var(--color-text-muted); margin-top: var(--space-xs); }
        .ts-warn { font-size: var(--text-micro); color: var(--color-danger); margin-top: var(--space-xxs); }
        .ts-warn:empty { display: none; }

        /* Live preview: a small copy of the admin UI drawn from the colors being edited. */
        .ts-sticky { position: sticky; top: 88px; }
        .tp { display: flex; min-height: 360px; border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden;
            font-size: var(--text-caption); background: var(--color-bg); color: var(--color-text); }
        .tp-side { width: 34%; background: var(--color-sidebar); color: var(--color-sidebar-text); padding: var(--space-md) var(--space-sm); }
        .tp-brand { color: var(--color-sidebar-text-strong); font-weight: var(--font-weight-medium); font-size: var(--text-body-lg); padding: 0 var(--space-sm) var(--space-md);
            border-bottom: 1px solid var(--color-sidebar-border); margin-bottom: var(--space-sm); }
        .tp-label { color: var(--color-sidebar-muted); font-size: var(--text-micro-cap); text-transform: uppercase; letter-spacing: .06em; padding: var(--space-sm) var(--space-sm) var(--space-xs); }
        .tp-item { padding: 6px var(--space-sm); border-radius: var(--radius-sm); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tp-item i { margin-right: var(--space-xs); }
        .tp-item.hover { background: var(--color-sidebar-soft); }
        .tp-item.active { color: var(--color-sidebar-active); background: var(--color-sidebar-soft); box-shadow: inset 3px 0 0 var(--color-sidebar-active); }
        .tp-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .tp-top { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm) var(--space-md); background: var(--color-topbar);
            color: var(--color-topbar-text); border-bottom: 1px solid var(--color-topbar-border); }
        .tp-search { flex: 1; border: 1px solid var(--color-topbar-border); border-radius: var(--radius-pill); padding: 2px var(--space-sm); opacity: .75; }
        .tp-body { padding: var(--space-md); display: flex; flex-direction: column; gap: var(--space-sm); }
        .tp-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-md); }
        .tp-title { display: flex; justify-content: space-between; align-items: center; font-weight: var(--font-weight-medium); margin-bottom: var(--space-xs); }
        .tp-title a { color: var(--color-link); text-decoration: none; font-size: var(--text-micro); }
        .tp-muted { color: var(--color-text-muted); font-size: var(--text-micro); }
        .tp-sec { color: var(--color-text-secondary); }
        .tp-inputs { display: flex; gap: var(--space-xs); margin: var(--space-sm) 0; }
        .tp-input { flex: 1; border: 1px solid var(--color-border-input); background: var(--color-input-bg); border-radius: var(--radius-sm); padding: 4px var(--space-sm); color: var(--color-text-muted); }
        .tp-input.focus { border-color: var(--color-focus); box-shadow: 0 0 0 3px var(--color-focus-ring); color: var(--color-text); }
        .tp-btns { display: flex; flex-wrap: wrap; gap: var(--space-xs); }
        .tp-btn { border-radius: var(--radius-button); padding: 4px var(--space-md); background: var(--color-button); color: var(--color-button-text); font-weight: var(--font-weight-medium); }
        .tp-btn.hover { background: var(--color-button-hover); }
        .tp-btn.ghost { background: transparent; color: var(--color-accent); border: 1px solid var(--color-accent); }
        .tp-table { width: 100%; border-collapse: collapse; }
        .tp-table th { background: var(--color-table-head); color: var(--color-table-head-text); font-size: var(--text-micro-cap); text-transform: uppercase; letter-spacing: .04em;
            padding: 5px var(--space-sm); text-align: left; font-weight: var(--font-weight-medium); }
        .tp-table td { padding: 5px var(--space-sm); border-top: 1px solid var(--color-border); }
        .tp-table tr.hover td { background: var(--color-surface-hover); }
        .tp-table .num { text-align: right; font-feature-settings: var(--font-feature-numeric); }
        .tp-badge { display: inline-block; border-radius: var(--radius-pill); padding: 1px 8px; font-size: var(--text-micro); font-weight: var(--font-weight-medium); }
        .tp-badge.success { background: var(--color-success-soft); color: var(--color-success); }
        .tp-badge.warning { background: var(--color-warning-soft); color: var(--color-warning); }
        .tp-badge.danger { background: var(--color-danger-soft); color: var(--color-danger); }
        .tp-badge.info { background: var(--color-info-soft); color: var(--color-info); }
        .tp-badge.accent { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .tp-inset { background: var(--color-surface-soft); border-radius: var(--radius-sm); padding: var(--space-xs) var(--space-sm); }

        /* PDF templates */
        .pt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: var(--space-lg); }
        .pt-card { position: relative; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); overflow: hidden; display: flex; flex-direction: column; }
        .pt-card.active { border-color: var(--color-accent); box-shadow: 0 0 0 2px var(--color-accent); }
        .pt-thumb { position: relative; height: 300px; overflow: hidden; background: var(--color-surface-soft); border-bottom: 1px solid var(--color-border); cursor: pointer; }
        .pt-thumb iframe { position: absolute; top: 0; left: 0; width: 794px; height: 1123px; border: 0; transform: scale(.29); transform-origin: 0 0; pointer-events: none; background: var(--color-canvas); }
        .pt-body { padding: var(--space-md); display: flex; flex-direction: column; gap: var(--space-sm); flex: 1; }
        .pt-body .form-check-label { font-weight: var(--font-weight-medium); }
        .pt-desc { font-size: var(--text-micro); color: var(--color-text-muted); flex: 1; }
        .pt-tag { position: absolute; top: var(--space-sm); right: var(--space-sm); z-index: 1; }
        .pt-actions { display: flex; gap: var(--space-xs); }
        .pt-actions .btn { flex: 1; min-height: 36px; }
        .pt-big { width: 100%; height: 75vh; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-canvas); }
        .ts-savebar { position: sticky; bottom: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: var(--space-sm); align-items: center; justify-content: flex-end;
            padding: var(--space-md) var(--space-lg); background: var(--color-surface); border-top: 1px solid var(--color-border); box-shadow: var(--shadow-2); border-radius: var(--radius-md) var(--radius-md) 0 0; }
        .ts-savebar .ts-dirty { margin-right: auto; color: var(--color-text-muted); font-size: var(--text-caption); }
        @media (max-width: 1199.98px) { .ts-sticky { position: static; } }
        @media (max-width: 575.98px) { .tp-side { display: none; } .ts-fields { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
<div class="container-fluid">

    <div class="ts-head">
        <div>
            <h4>Theme Settings</h4>
            <div class="text-muted small">Every color of the application for the light and dark theme, and the design used by all PDFs.</div>
        </div>
        <div class="ts-actions">
            <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i> General Settings</a>
        </div>
    </div>

    <form id="themeForm" autocomplete="off" onsubmit="return false;">

        {{-- Presets --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <h5 class="mb-0"><i class="bi bi-stars me-2"></i>Presets</h5>
                <span class="text-muted small ms-2">A preset fills the light and dark colors; fine-tune any of them below, then save.</span>
            </div>
            <div class="card-body">
                <div class="ts-presets">
                    @foreach ($presets as $pkey => [$pname, $pset])
                        <button type="button" class="ts-preset" data-preset="{{ $pkey }}" aria-label="Apply the {{ $pname }} preset">
                            <span class="ts-swatches" data-theme-swatch="light"><span></span><span></span><span></span><span></span></span>
                            <span class="ts-swatches dark" data-theme-swatch="dark"><span></span><span></span><span></span><span></span></span>
                            <span class="ts-preset-name">{{ $pname }}@if ($loop->first) <span class="ts-state">default</span>@endif</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Button corners --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <h5 class="mb-0"><i class="bi bi-bounding-box-circles me-2"></i>Button corner radius</h5>
                <span class="text-muted small ms-2">Applies to every button in the application, in both themes and on the login page.</span>
            </div>
            <div class="card-body">
                <div class="ts-radii" role="radiogroup" aria-label="Button corner radius">
                    @foreach ($buttonRadii as $r)
                        <label class="ts-radius {{ $buttonRadius === $r ? 'active' : '' }}">
                            <input type="radio" class="form-check-input" name="button_radius" value="{{ $r }}" @checked($buttonRadius === $r)>
                            <span class="ts-radius-sample" data-radius="{{ $r }}">Save</span>
                            <span>{{ $r }}px{{ $loop->first ? ' (default)' : '' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Editor --}}
            <div class="col-xl-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                        <h5 class="mb-0"><i class="bi bi-palette me-2"></i>Colors</h5>
                        <ul class="nav nav-pills ts-tabs ms-auto" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" type="button" data-edit-theme="light" role="tab" aria-selected="true"><i class="bi bi-sun me-1"></i> Light theme</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" type="button" data-edit-theme="dark" role="tab" aria-selected="false"><i class="bi bi-moon-stars me-1"></i> Dark theme</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <b>Auto</b> keeps the design default, or works the shade out from the color it follows (hover, pressed, soft fills, readable text on fills).
                            Type or pick a color to set it; <i class="bi bi-arrow-counterclockwise"></i> puts one field back on auto.
                        </p>
                        @foreach (['light', 'dark'] as $theme)
                            <div class="ts-theme" data-theme-pane="{{ $theme }}" @if ($theme === 'dark') hidden @endif>
                                @foreach ($groups as $gkey => [$gname, $gicon])
                                    @php $gfields = array_filter($fields, fn ($f) => $f[2] === $gkey); @endphp
                                    <details class="ts-group" @if ($loop->first) open @endif>
                                        <summary><i class="bi {{ $gicon }}"></i> {{ $gname }} <span class="ts-count" data-group-count="{{ $theme }}-{{ $gkey }}"></span> <i class="bi bi-chevron-down"></i></summary>
                                        <div class="ts-fields">
                                            @foreach ($gfields as $key => $f)
                                                <div class="ts-field" data-theme="{{ $theme }}" data-key="{{ $key }}">
                                                    <label for="c_{{ $theme }}_{{ $key }}">{{ $f[0] }} <span class="ts-state">auto</span></label>
                                                    <div class="ts-input">
                                                        <input type="color" class="form-control form-control-color ts-picker" aria-label="{{ $f[0] }} ({{ $theme }}) picker">
                                                        <input type="text" class="form-control ts-hex" id="c_{{ $theme }}_{{ $key }}" name="colors[{{ $theme }}][{{ $key }}]"
                                                            value="{{ $stored[$theme][$key] ?? '' }}" maxlength="7" spellcheck="false">
                                                        <button type="button" class="btn btn-outline-secondary ts-reset" title="Back to auto" aria-label="Reset {{ $f[0] }} to auto"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                    </div>
                                                    <div class="ts-help">{{ $f[1] }}</div>
                                                    <div class="ts-warn" role="status"></div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Live preview --}}
            <div class="col-xl-5">
                <div class="card shadow-sm ts-sticky">
                    <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                        <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Live preview</h5>
                        <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Preview theme">
                            <button type="button" class="btn btn-outline-secondary active" data-preview-theme="light"><i class="bi bi-sun"></i> Light</button>
                            <button type="button" class="btn btn-outline-secondary" data-preview-theme="dark"><i class="bi bi-moon-stars"></i> Dark</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="tp" id="tpPreview" aria-hidden="true">
                            <div class="tp-side">
                                <div class="tp-brand">{{ $generalSetting['company_short_name'] ?? 'FPOS' }}</div>
                                <div class="tp-item"><i class="bi bi-speedometer2"></i> Dashboard</div>
                                <div class="tp-item hover"><i class="bi bi-cart3"></i> Sales</div>
                                <div class="tp-item active"><i class="bi bi-people"></i> Employees</div>
                                <div class="tp-label">Reports</div>
                                <div class="tp-item"><i class="bi bi-bar-chart"></i> Reports Center</div>
                                <div class="tp-item"><i class="bi bi-gear"></i> Settings</div>
                            </div>
                            <div class="tp-main">
                                <div class="tp-top"><i class="bi bi-list"></i><span class="tp-search">Search…</span><i class="bi bi-moon"></i><i class="bi bi-person-circle"></i></div>
                                <div class="tp-body">
                                    <div class="tp-hero"><small>Good morning</small><b>Today's sales 48,250.00</b><small>32 invoices · 4 due</small></div>
                                    <div class="tp-card">
                                        <div class="tp-title">Employees <a href="javascript:void(0)">View all</a></div>
                                        <div class="tp-muted">12 active · <span class="tp-sec">3 on leave</span></div>
                                        <div class="tp-inputs"><span class="tp-input">Name</span><span class="tp-input focus">Rahim|</span></div>
                                        <div class="tp-btns">
                                            <span class="tp-btn">Save</span><span class="tp-btn hover">Hover</span><span class="tp-btn ghost">Cancel</span>
                                        </div>
                                    </div>
                                    <div class="tp-card" style="padding: 0; overflow: hidden;">
                                        <table class="tp-table">
                                            <tr><th>Invoice</th><th>Status</th><th class="num">Amount</th></tr>
                                            <tr><td>INV-1042</td><td><span class="tp-badge success">Paid</span></td><td class="num">12,500.00</td></tr>
                                            <tr class="hover"><td>INV-1043</td><td><span class="tp-badge warning">Due</span></td><td class="num">4,200.00</td></tr>
                                            <tr><td>INV-1044</td><td><span class="tp-badge danger">Void</span></td><td class="num">980.00</td></tr>
                                        </table>
                                    </div>
                                    <div class="tp-inset tp-muted"><span class="tp-badge info">Info</span> <span class="tp-badge accent">Selected</span> Inset area</div>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">Updates as you edit. Save to apply everywhere.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- PDF template --}}
        <div class="card shadow-sm mb-4" id="pdfTemplates">
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <h5 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>PDF Template</h5>
                <span class="text-muted small ms-2">The selected design is used by every PDF: invoices, vouchers, payslips, ledgers, statements and reports.</span>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end mb-4">
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label" for="pdf_accent_hex">PDF accent color</label>
                        <div class="ts-input">
                            <input type="color" class="form-control form-control-color" id="pdf_accent_picker" value="{{ $pdfAccent ?: $themeAccent }}" aria-label="PDF accent picker">
                            <input type="text" class="form-control ts-hex" id="pdf_accent_hex" name="pdf_accent" value="{{ $pdfAccent }}" placeholder="theme {{ $themeAccent }}" maxlength="7" spellcheck="false">
                            <button type="button" class="btn btn-outline-secondary" id="pdfAccentReset" title="Use the theme's button color" aria-label="Use the theme color"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-8 text-muted small">
                        Empty = the theme's primary button color. Classic is always black &amp; white.
                    </div>
                </div>
                <div class="pt-grid">
                    @foreach ($templates as $tkey => [$tname, $tdesc])
                        <div class="pt-card {{ $pdfTemplate === $tkey ? 'active' : '' }}" data-template="{{ $tkey }}">
                            @if ($tkey === 'classic')<span class="badge bg-light pt-tag">Default</span>@endif
                            <div class="pt-thumb" data-open-preview="{{ $tkey }}" title="Open a larger preview">
                                <iframe title="{{ $tname }} template preview" tabindex="-1"
                                    data-src="{{ route('admin.settings.theme.pdf-preview', ['template' => $tkey]) }}"></iframe>
                            </div>
                            <div class="pt-body">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="pdf_template" id="tpl_{{ $tkey }}" value="{{ $tkey }}" @checked($pdfTemplate === $tkey)>
                                    <label class="form-check-label" for="tpl_{{ $tkey }}">{{ $tname }}</label>
                                </div>
                                <div class="pt-desc">{{ $tdesc }}</div>
                                <div class="pt-actions">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-open-preview="{{ $tkey }}"><i class="bi bi-arrows-fullscreen me-1"></i> Preview</button>
                                    <a class="btn btn-sm btn-outline-secondary pt-download" target="_blank" rel="noopener"
                                        href="{{ route('admin.settings.theme.pdf-preview', ['template' => $tkey, 'format' => 'pdf']) }}"
                                        data-base="{{ route('admin.settings.theme.pdf-preview', ['template' => $tkey, 'format' => 'pdf']) }}"><i class="bi bi-download me-1"></i> PDF</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="ts-savebar">
            <span class="ts-dirty" id="tsDirty">No unsaved changes</span>
            @perm('settings.edit')
            <button type="button" class="btn btn-outline-danger" id="tsResetAll"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset all colors to default</button>
            <button type="button" class="btn btn-primary" id="tsSave"><i class="bi bi-check2-circle me-1"></i> Save theme settings</button>
            @endperm
        </div>
    </form>
</div>

{{-- Larger template preview --}}
<div class="modal fade" id="ptModal" tabindex="-1" aria-labelledby="ptModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ptModalTitle">Template preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><iframe class="pt-big" id="ptModalFrame" title="Template preview"></iframe></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
/* Theme Settings: same resolve / derive math as SettingController::themeResolve(). */
(function () {
    const FIELDS = @json($fields);
    const PRESETS = @json($presets);
    const TEMPLATES = @json($templates);
    const PREVIEW_URL = @json(route('admin.settings.theme.pdf-preview'));
    const INK = '#0d253d';
    const form = document.getElementById('themeForm');
    const HEX = /^#[0-9a-f]{6}$/;
    let previewTheme = 'light';
    let dirty = false;

    const rgb = h => [1, 3, 5].map(i => parseInt(h.substr(i, 2), 16));
    const mix = (a, b, t) => { const x = rgb(a), y = rgb(b); return '#' + x.map((v, i) => Math.round(v + (y[i] - v) * t).toString(16).padStart(2, '0')).join(''); };
    const lum = h => { const c = rgb(h).map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
    const contrast = (a, b) => { const l = [lum(a), lum(b)]; return (Math.max(...l) + 0.05) / (Math.min(...l) + 0.05); };
    const textOn = bg => contrast(bg, '#ffffff') >= contrast(bg, INK) ? '#ffffff' : INK;
    const rgba = (h, a) => 'rgba(' + rgb(h).join(',') + ',' + a + ')';

    function field(theme, key) { return form.querySelector('.ts-field[data-theme="' + theme + '"][data-key="' + key + '"]'); }
    function hexInput(theme, key) { return field(theme, key).querySelector('.ts-hex'); }

    function values(theme) {
        const out = {};
        Object.keys(FIELDS).forEach(function (key) {
            const v = hexInput(theme, key).value.trim().toLowerCase();
            if (HEX.test(v)) { out[key] = v; }
        });
        return out;
    }

    function resolve(vals, theme) {
        const i = theme === 'dark' ? 1 : 0;
        const out = {};
        function get(key) {
            if (out[key]) { return out[key]; }
            const f = FIELDS[key];
            if (vals[key]) { return (out[key] = vals[key]); }
            let rule = f[4];
            if (rule && !Array.isArray(rule)) { rule = rule[theme] || null; }
            if (!rule) { return (out[key] = f[3][i]); }
            const deps = [rule[1]];
            if (typeof rule[2] === 'string' && rule[2][0] === '@') { deps.push(rule[2].slice(1)); }
            const changed = deps.some(d => get(d) !== FIELDS[d][3][i]);
            if (!changed) { return (out[key] = f[3][i]); }
            const base = get(rule[1]);
            if (rule[0] === 'same') { return (out[key] = base); }
            if (rule[0] === 'on') { return (out[key] = textOn(base)); }
            const other = rule[2][0] === '@' ? get(rule[2].slice(1)) : rule[2];
            const t = Array.isArray(rule[3]) ? rule[3][i] : rule[3];
            return (out[key] = mix(base, other, t));
        }
        Object.keys(FIELDS).forEach(get);
        return out;
    }

    const CHECKS = [   // [text key, background key, minimum ratio, message]
        ['text', 'surface', 4.5, 'Text is hard to read on cards'], ['text_muted', 'surface', 4.5, 'Muted text is hard to read on cards'],
        ['button_text', 'button', 4.5, 'Button text is hard to read'], ['sidebar_text', 'sidebar', 4.5, 'Sidebar text is hard to read'],
        ['sidebar_active', 'sidebar', 3, 'Active item is hard to see on the sidebar'], ['topbar_text', 'topbar', 4.5, 'Top bar text is hard to read'],
        ['accent', 'surface', 3, 'Accent is hard to see on cards'], ['link', 'surface', 4.5, 'Links are hard to read on cards'],
        ['table_head_text', 'table_head', 4.5, 'Table headings are hard to read'], ['success', 'success_soft', 4.5, 'Success badge text is hard to read'],
        ['warning', 'warning_soft', 4.5, 'Warning badge text is hard to read'], ['danger', 'danger_soft', 4.5, 'Danger badge text is hard to read'],
    ];

    function refresh() {
        ['light', 'dark'].forEach(function (theme) {
            const vals = values(theme);
            const c = resolve(vals, theme);
            const counts = {};
            Object.keys(FIELDS).forEach(function (key) {
                const row = field(theme, key);
                const custom = !!vals[key];
                row.querySelector('.ts-picker').value = c[key];
                row.querySelector('.ts-hex').placeholder = 'auto ' + c[key];
                const st = row.querySelector('.ts-state');
                st.textContent = custom ? 'custom' : 'auto';
                st.classList.toggle('custom', custom);
                row.querySelector('.ts-warn').textContent = '';
                const g = FIELDS[key][2];
                counts[g] = (counts[g] || 0) + (custom ? 1 : 0);
            });
            CHECKS.forEach(function ([fg, bg, min, msg]) {
                const ratio = contrast(c[fg], c[bg]);
                if (ratio < min) {
                    field(theme, fg).querySelector('.ts-warn').textContent = msg + ' (' + ratio.toFixed(1) + ':1, needs ' + min + ':1).';
                }
            });
            form.querySelectorAll('[data-group-count^="' + theme + '-"]').forEach(function (el) {
                const n = counts[el.dataset.groupCount.slice(theme.length + 1)] || 0;
                el.textContent = n ? n + ' custom' : '';
            });
        });
        paintPreview();
    }

    function paintPreview() {
        const c = resolve(values(previewTheme), previewTheme);
        const box = document.getElementById('tpPreview');
        Object.keys(FIELDS).forEach(function (key) {
            FIELDS[key][5].forEach(function (token) { box.style.setProperty(token, c[key]); });
        });
        box.style.setProperty('--color-focus-ring', rgba(c.focus, previewTheme === 'dark' ? 0.35 : 0.28));
    }

    function markDirty() {
        dirty = true;
        document.getElementById('tsDirty').textContent = 'Unsaved changes';
    }

    /* Field events */
    form.querySelectorAll('.ts-field').forEach(function (row) {
        const hex = row.querySelector('.ts-hex');
        row.querySelector('.ts-picker').addEventListener('input', function () { hex.value = this.value; markDirty(); refresh(); });
        hex.addEventListener('input', function () {
            let v = this.value.trim().toLowerCase();
            if (v && v[0] !== '#') { v = '#' + v; }
            hex.classList.toggle('is-invalid', v !== '' && !HEX.test(v));
            if (v === '' || HEX.test(v)) { if (this.value !== v) { this.value = v; } markDirty(); refresh(); }
        });
        row.querySelector('.ts-reset').addEventListener('click', function () { hex.value = ''; hex.classList.remove('is-invalid'); markDirty(); refresh(); });
    });

    /* Light / dark editing tabs */
    form.querySelectorAll('[data-edit-theme]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const theme = btn.dataset.editTheme;
            form.querySelectorAll('[data-edit-theme]').forEach(b => { b.classList.toggle('active', b === btn); b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
            form.querySelectorAll('[data-theme-pane]').forEach(p => { p.hidden = p.dataset.themePane !== theme; });
            setPreview(theme);
        });
    });

    function setPreview(theme) {
        previewTheme = theme;
        document.querySelectorAll('[data-preview-theme]').forEach(b => b.classList.toggle('active', b.dataset.previewTheme === theme));
        paintPreview();
    }
    document.querySelectorAll('[data-preview-theme]').forEach(b => b.addEventListener('click', () => setPreview(b.dataset.previewTheme)));

    /* Presets */
    form.querySelectorAll('.ts-preset').forEach(function (btn) {
        const set = PRESETS[btn.dataset.preset][1];
        ['light', 'dark'].forEach(function (theme) {
            const c = resolve(set[theme] || {}, theme);
            const spans = btn.querySelectorAll('[data-theme-swatch="' + theme + '"] span');
            [c.sidebar, c.accent, c.bg, c.surface].forEach((col, n) => { spans[n].style.background = col; });
        });
        btn.addEventListener('click', function () {
            ['light', 'dark'].forEach(function (theme) {
                Object.keys(FIELDS).forEach(key => { hexInput(theme, key).value = (set[theme] || {})[key] || ''; });
            });
            form.querySelectorAll('.ts-preset').forEach(b => b.classList.toggle('active', b === btn));
            markDirty();
            refresh();
        });
    });

    /* Button corner radius */
    function paintRadius() {
        const r = form.querySelector('input[name="button_radius"]:checked');
        if (r) { document.getElementById('tpPreview').style.setProperty('--radius-button', r.value + 'px'); }
    }
    form.querySelectorAll('input[name="button_radius"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            form.querySelectorAll('.ts-radius').forEach(l => l.classList.toggle('active', l.contains(radio)));
            paintRadius();
            markDirty();
        });
    });
    paintRadius();

    /* PDF template cards */
    const accentHex = document.getElementById('pdf_accent_hex');
    const accentPicker = document.getElementById('pdf_accent_picker');
    function pdfAccent() { const v = accentHex.value.trim().toLowerCase(); return HEX.test(v) ? v : ''; }
    function previewUrl(tpl, format) {
        const q = new URLSearchParams({ template: tpl });
        if (format) { q.set('format', format); }
        if (pdfAccent()) { q.set('accent', pdfAccent()); }
        return PREVIEW_URL + '?' + q.toString();
    }
    function loadThumbs() {
        form.querySelectorAll('.pt-card').forEach(function (card) {
            const tpl = card.dataset.template;
            card.querySelector('iframe').src = previewUrl(tpl);
            card.querySelector('.pt-download').href = previewUrl(tpl, 'pdf');
        });
    }
    let accentTimer = null;
    function accentChanged() { markDirty(); clearTimeout(accentTimer); accentTimer = setTimeout(loadThumbs, 500); }
    accentPicker.addEventListener('input', function () { accentHex.value = this.value; accentChanged(); });
    accentHex.addEventListener('input', function () {
        let v = this.value.trim().toLowerCase();
        accentHex.classList.toggle('is-invalid', v !== '' && !HEX.test(v));
        if (v === '' || HEX.test(v)) { if (HEX.test(v)) { accentPicker.value = v; } accentChanged(); }
    });
    document.getElementById('pdfAccentReset').addEventListener('click', function () { accentHex.value = ''; accentHex.classList.remove('is-invalid'); accentChanged(); });

    form.querySelectorAll('input[name="pdf_template"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            form.querySelectorAll('.pt-card').forEach(c => c.classList.toggle('active', c.dataset.template === radio.value));
            markDirty();
        });
    });
    document.querySelectorAll('[data-open-preview]').forEach(function (el) {
        el.addEventListener('click', function () {
            const tpl = el.dataset.openPreview;
            const radio = document.getElementById('tpl_' + tpl);
            if (el.classList.contains('pt-thumb') && !radio.checked) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
            document.getElementById('ptModalTitle').textContent = TEMPLATES[tpl][0] + ' template preview';
            document.getElementById('ptModalFrame').src = previewUrl(tpl);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('ptModal')).show();
        });
    });

    /* Save / reset */
    function applyCss(css) {
        let style = document.getElementById('theme-colors');
        if (!style) { style = document.createElement('style'); style.id = 'theme-colors'; document.head.appendChild(style); }
        style.textContent = css || '';
    }
    function fill(stored) {
        ['light', 'dark'].forEach(function (theme) {
            Object.keys(FIELDS).forEach(key => { const h = hexInput(theme, key); h.value = (stored[theme] || {})[key] || ''; h.classList.remove('is-invalid'); });
        });
    }
    function saved(message) {
        dirty = false;
        document.getElementById('tsDirty').textContent = 'All changes saved';
        if (window.toastr) { toastr.success(message); }
    }
    function failed(xhr) {
        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save the theme settings.';
        if (window.toastr) { toastr.error(msg); } else { alert(msg); }
    }

    const saveBtn = document.getElementById('tsSave');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (form.querySelector('.is-invalid')) {
                if (window.toastr) { toastr.error('Fix the colors marked in red (hex like #533afd).'); }
                return;
            }
            saveBtn.disabled = true;
            $.ajax({
                url: "{{ route('admin.settings.theme.save') }}",
                type: 'POST',
                dataType: 'json',
                data: $(form).serialize() + '&_token={{ csrf_token() }}',
                success: function (response) {
                    // alert(JSON.stringify(response));
                    saveBtn.disabled = false;
                    applyCss(response.css);
                    fill(response.stored);
                    refresh();
                    saved(response.message);
                },
                error: function (xhr) {
                    // alert(JSON.stringify(xhr));
                    saveBtn.disabled = false;
                    failed(xhr);
                }
            });
        });
    }

    const resetBtn = document.getElementById('tsResetAll');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Reset all colors?', text: 'Light and dark colors go back to the default theme. The PDF template is kept.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Reset colors'
            }).then(function (result) {
                if (!result.isConfirmed) { return; }
                $.ajax({
                    url: "{{ route('admin.settings.theme.reset') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        // alert(JSON.stringify(response));
                        applyCss(response.css);
                        fill(response.stored);
                        form.querySelectorAll('.ts-preset').forEach(b => b.classList.remove('active'));
                        refresh();
                        saved(response.message);
                    },
                    error: function (xhr) {
                        // alert(JSON.stringify(xhr));
                        failed(xhr);
                    }
                });
            });
        });
    }

    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    refresh();
    loadThumbs();
})();
</script>
@endpush
