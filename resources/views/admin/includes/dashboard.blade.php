@extends('admin.master')
@section('title', 'Dashboard')

@php
    $cur = $currency ?? '৳';
    $money = fn ($v, $dec = 0) => ($v < 0 ? '−' : '') . $cur . number_format(abs((float) $v), $dec);
    // Lakh / crore, as people read amounts in BDT.
    $compact = function ($v) {
        $a = abs((float) $v);
        $s = match (true) {
            $a >= 1e7 => rtrim(rtrim(number_format($a / 1e7, 1), '0'), '.') . 'Cr',
            $a >= 1e5 => rtrim(rtrim(number_format($a / 1e5, 1), '0'), '.') . 'L',
            $a >= 1e3 => rtrim(rtrim(number_format($a / 1e3, 1), '0'), '.') . 'K',
            default   => number_format($a),
        };
        return ($v < 0 ? '−' : '') . $s;
    };
    $nice = function ($v) {
        if ($v <= 0) {
            return 1;
        }
        $p = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $p >= $v) {
                return $m * $p;
            }
        }
        return 10 * $p;
    };
    $pct = fn ($part, $max) => $max > 0 ? max(0, min(100, $part / $max * 100)) : 0;
    // 12-point sparkline as an SVG path (line + soft area), drawn in currentColor.
    $spark = function (array $values) {
        $n = count($values);
        if ($n < 2 || max($values) <= 0) {
            return '<svg class="dash-spark" viewBox="0 0 120 32" preserveAspectRatio="none" aria-hidden="true"><line x1="0" y1="30" x2="120" y2="30" /></svg>';
        }
        $max = max($values);
        $pts = [];
        foreach (array_values($values) as $i => $v) {
            $pts[] = round($i / ($n - 1) * 120, 1) . ',' . round(30 - ($v / $max) * 26, 1);
        }
        $line = 'M' . implode(' L', $pts);
        return '<svg class="dash-spark" viewBox="0 0 120 32" preserveAspectRatio="none" aria-hidden="true">'
            . '<path class="area" d="' . $line . ' L120,32 L0,32 Z" /><path class="line" d="' . $line . '" /></svg>';
    };
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name ?? '')->explode(' ')->first();
@endphp

@push('styles')
    <style>
        .dash { --dash-gap: var(--space-lg); }

        /* ---------- Hero ---------- */
        .dash-hero {
            position: relative;
            overflow: hidden;
            margin-bottom: var(--dash-gap);
            padding: var(--space-xl) var(--space-xl) var(--space-lg);
            border-radius: var(--radius-xl);
            background:
                radial-gradient(ellipse at 90% 0%, color-mix(in srgb, var(--color-primary) 55%, transparent), transparent 55%),
                radial-gradient(ellipse at 0% 120%, color-mix(in srgb, var(--color-sidebar-active) 30%, transparent), transparent 50%),
                var(--color-sidebar);
            color: var(--color-sidebar-text-strong);
            box-shadow: var(--shadow-2);
        }

        .dash-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image: linear-gradient(color-mix(in srgb, var(--color-sidebar-text-strong) 4%, transparent) 1px, transparent 1px),
                linear-gradient(90deg, color-mix(in srgb, var(--color-sidebar-text-strong) 4%, transparent) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .dash-hero > * { position: relative; z-index: 1; }

        .dash-hero-top {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--space-lg);
        }

        .dash-hero h1 {
            margin: 0;
            font-size: var(--text-display-md);
            font-weight: var(--font-weight-light);
            letter-spacing: var(--tracking-display-md);
            color: var(--color-sidebar-text-strong);
        }

        .dash-hero-sub { margin-top: var(--space-xs); color: var(--color-sidebar-text); font-size: var(--text-body-tabular); }

        .dash-periods {
            display: inline-flex;
            padding: var(--space-xxs);
            border: 1px solid color-mix(in srgb, var(--color-sidebar-text-strong) 18%, transparent);
            border-radius: var(--radius-pill);
            background: color-mix(in srgb, var(--color-sidebar-text-strong) 6%, transparent);
        }

        .dash-periods a {
            display: inline-flex;
            align-items: center;
            min-height: var(--control-height-sm);
            padding: 0 var(--space-md);
            border-radius: var(--radius-pill);
            color: var(--color-sidebar-text);
            font-size: var(--text-caption);
            text-decoration: none;
            white-space: nowrap;
        }

        .dash-periods a:hover { color: var(--color-sidebar-text-strong); }
        .dash-periods a.active { background: var(--color-sidebar-text-strong); color: var(--color-sidebar); font-weight: var(--font-weight-medium); }
        .dash-periods a:focus-visible { outline: none; box-shadow: var(--shadow-focus); }

        .dash-hero-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--space-lg);
            margin-top: var(--space-xl);
        }

        .dash-hero-stat small { display: block; color: var(--color-sidebar-text); font-size: var(--text-caption); }
        .dash-hero-stat b {
            display: block;
            margin-top: var(--space-xxs);
            font-size: var(--text-display-lg);
            font-weight: var(--font-weight-light);
            letter-spacing: var(--tracking-display-lg);
            line-height: var(--leading-display-lg);
            font-feature-settings: var(--font-feature-numeric);
            white-space: nowrap;
        }

        .dash-hero-stat .meta { margin-top: var(--space-xs); font-size: var(--text-micro); color: var(--color-sidebar-text); }

        .dash-actions {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid color-mix(in srgb, var(--color-sidebar-text-strong) 12%, transparent);
        }

        .dash-action {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            min-height: var(--control-height-sm);
            padding: 0 var(--space-md);
            border: 1px solid color-mix(in srgb, var(--color-sidebar-text-strong) 18%, transparent);
            border-radius: var(--radius-pill);
            color: var(--color-sidebar-text-strong);
            font-size: var(--text-caption);
            text-decoration: none;
        }

        .dash-action:hover { background: color-mix(in srgb, var(--color-sidebar-text-strong) 10%, transparent); color: var(--color-sidebar-text-strong); }
        .dash-action:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
        .dash-action.primary { background: var(--color-button); border-color: var(--color-button); color: var(--color-button-text); }
        .dash-action.primary:hover { background: var(--color-button-hover); }

        /* ---------- Cards ---------- */
        .dash-card {
            height: 100%;
            padding: var(--space-xl);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-1);
            min-width: 0;
        }

        .dash-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-md); margin-bottom: var(--space-lg); }
        .dash-card-head h2 { margin: 0; font-size: var(--text-heading-sm); font-weight: var(--font-weight-regular); line-height: var(--leading-heading-sm); color: var(--color-text); }
        .dash-card-head p { margin: var(--space-xxs) 0 0; font-size: var(--text-caption); color: var(--color-text-muted); }
        .dash-link { flex: 0 0 auto; font-size: var(--text-caption); color: var(--color-accent); text-decoration: none; white-space: nowrap; }
        .dash-link:hover { text-decoration: underline; }

        /* ---------- KPI tiles ---------- */
        .dash-kpi {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
            height: 100%;
            padding: var(--space-lg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-1);
            color: var(--color-text);
            text-decoration: none;
            min-width: 0;
            overflow: hidden;
        }

        a.dash-kpi:hover { border-color: var(--color-border-input); color: var(--color-text); }
        a.dash-kpi:focus-visible { outline: none; box-shadow: var(--shadow-focus); }

        .dash-kpi-top { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--text-caption); color: var(--color-text-muted); }

        .dash-ico {
            display: inline-grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: var(--radius-md);
            font-size: var(--text-body-lg);
        }

        .tone-accent .dash-ico { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .tone-success .dash-ico { background: var(--color-success-soft); color: var(--color-success); }
        .tone-warning .dash-ico { background: var(--color-warning-soft); color: var(--color-warning); }
        .tone-danger .dash-ico { background: var(--color-danger-soft); color: var(--color-danger); }
        .tone-accent .dash-spark { color: var(--color-accent); }
        .tone-success .dash-spark { color: var(--color-success); }
        .tone-warning .dash-spark { color: var(--color-warning); }
        .tone-danger .dash-spark { color: var(--color-danger); }

        .dash-kpi-value {
            margin-top: var(--space-xs);
            font-size: var(--text-heading-lg);
            font-weight: var(--font-weight-light);
            letter-spacing: var(--tracking-heading-lg);
            font-feature-settings: var(--font-feature-numeric);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-kpi-meta { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-xs) var(--space-sm); font-size: var(--text-micro); color: var(--color-text-muted); }

        .dash-spark { display: block; width: 100%; height: 32px; margin-top: var(--space-sm); overflow: visible; }
        .dash-spark .line { fill: none; stroke: currentColor; stroke-width: 2; vector-effect: non-scaling-stroke; stroke-linejoin: round; stroke-linecap: round; }
        .dash-spark .area { fill: currentColor; opacity: .1; }
        .dash-spark line { stroke: var(--color-border); stroke-width: 2; vector-effect: non-scaling-stroke; }

        .dash-delta {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xxs);
            padding: 0 var(--space-sm);
            border-radius: var(--radius-pill);
            font-weight: var(--font-weight-medium);
            font-feature-settings: var(--font-feature-numeric);
        }

        .dash-delta.good { background: var(--color-success-soft); color: var(--color-success); }
        .dash-delta.bad { background: var(--color-danger-soft); color: var(--color-danger); }
        .dash-delta.flat { background: var(--color-surface-soft); color: var(--color-text-secondary); }
        .dash-hero .dash-delta.flat { background: color-mix(in srgb, var(--color-sidebar-text-strong) 12%, transparent); color: var(--color-sidebar-text-strong); }

        .dash-neg { color: var(--color-danger) !important; }

        /* ---------- Bar charts (plain HTML, colors from tokens) ---------- */
        .dash-legend { display: flex; flex-wrap: wrap; gap: var(--space-xs) var(--space-lg); font-size: var(--text-caption); color: var(--color-text-secondary); }
        .dash-legend span { display: inline-flex; align-items: center; gap: var(--space-xs); }
        .dash-swatch { display: inline-block; width: 10px; height: 10px; border-radius: var(--radius-xs); background: var(--color-accent); }
        .dash-swatch.alt { background: var(--color-border-input); }

        .dash-chart { position: relative; display: grid; grid-template-columns: auto 1fr; gap: var(--space-sm); height: 260px; margin-top: var(--space-lg); }
        .dash-axis { position: relative; width: 40px; margin-bottom: 22px; font-size: var(--text-micro); color: var(--color-text-muted); font-feature-settings: var(--font-feature-numeric); }
        .dash-axis span { position: absolute; right: 0; transform: translateY(50%); }
        .dash-plot { position: relative; min-width: 0; }
        .dash-grid { position: absolute; inset: 0 0 22px 0; pointer-events: none; }
        .dash-grid span { position: absolute; left: 0; right: 0; border-top: 1px solid var(--color-border); }
        .dash-grid span.base { border-top-color: var(--color-border-input); }
        .dash-cols { position: absolute; inset: 0; display: flex; gap: var(--space-xxs); }

        .dash-col {
            position: relative;
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            padding: 0;
            border: 0;
            border-radius: var(--radius-sm);
            background: transparent;
            color: inherit;
        }

        .dash-col:hover { background: var(--color-surface-hover); }
        .dash-bars { flex: 1 1 auto; display: flex; align-items: flex-end; justify-content: center; gap: var(--space-xxs); min-height: 0; }
        .dash-bar { width: min(14px, 40%); border-radius: var(--radius-xs) var(--radius-xs) 0 0; background: var(--color-accent); }
        .dash-bar.alt { background: var(--color-border-input); }
        .dash-bar.zero { height: 1px !important; background: var(--color-border); }
        .dash-col small { flex: 0 0 22px; line-height: 22px; text-align: center; font-size: var(--text-micro); color: var(--color-text-muted); white-space: nowrap; }

        .dash-tip {
            position: fixed;
            z-index: 1080;
            min-width: 160px;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            box-shadow: var(--shadow-2);
            color: var(--color-text);
            font-size: var(--text-caption);
            pointer-events: none;
            opacity: 0;
            transition: opacity .12s;
        }

        .dash-tip.show { opacity: 1; }
        .dash-tip strong { display: block; margin-bottom: var(--space-xs); font-weight: var(--font-weight-medium); }
        .dash-tip div { display: flex; align-items: center; justify-content: space-between; gap: var(--space-lg); }
        .dash-tip div span { display: inline-flex; align-items: center; gap: var(--space-xs); color: var(--color-text-secondary); }
        .dash-tip b { font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); }

        /* ---------- P&L ---------- */
        .dash-pl { margin: 0; padding: 0; list-style: none; }
        .dash-pl li { padding: var(--space-sm) 0; }
        .dash-pl .row-top { display: flex; justify-content: space-between; gap: var(--space-md); font-size: var(--text-body-tabular); }
        .dash-pl .row-top span { color: var(--color-text-secondary); }
        .dash-pl .row-top b { font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); }
        .dash-pl .total { margin-top: var(--space-xs); padding-top: var(--space-md); border-top: 1px solid var(--color-border); }
        .dash-pl .total .row-top span { color: var(--color-text); font-weight: var(--font-weight-medium); }
        .dash-pl .total .row-top b { font-size: var(--text-heading-md); font-weight: var(--font-weight-light); }

        .dash-track { height: 6px; margin-top: var(--space-xs); border-radius: var(--radius-pill); background: var(--color-surface-soft); overflow: hidden; }
        .dash-track span { display: block; height: 100%; border-radius: var(--radius-pill); background: var(--color-accent); }
        .dash-track.alt span { background: var(--color-border-input); }
        .dash-track.bad span { background: var(--color-danger); }
        .dash-track.good span { background: var(--color-success); }

        /* ---------- Lists ---------- */
        .dash-rows { display: flex; flex-direction: column; gap: var(--space-md); margin: 0; padding: 0; list-style: none; }
        .dash-row-top { display: flex; align-items: baseline; justify-content: space-between; gap: var(--space-md); font-size: var(--text-body-tabular); }
        .dash-row-top > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dash-row-top b { font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }
        .dash-row-sub { font-size: var(--text-micro); color: var(--color-text-muted); }

        .dash-sub-accounts { margin: var(--space-xs) 0 0; padding: 0; list-style: none; }
        .dash-sub-accounts li { display: flex; justify-content: space-between; gap: var(--space-md); padding: var(--space-xxs) 0 var(--space-xxs) var(--space-md); border-left: 2px solid var(--color-border); font-size: var(--text-caption); color: var(--color-text-secondary); }
        .dash-sub-accounts b { font-weight: var(--font-weight-regular); font-feature-settings: var(--font-feature-numeric); }

        .dash-total-line { display: flex; justify-content: space-between; align-items: baseline; margin-top: var(--space-lg); padding-top: var(--space-md); border-top: 1px solid var(--color-border); font-size: var(--text-body-tabular); color: var(--color-text-muted); }
        .dash-total-line b { font-size: var(--text-heading-sm); font-weight: var(--font-weight-light); color: var(--color-text); font-feature-settings: var(--font-feature-numeric); }

        .dash-product { display: flex; align-items: center; gap: var(--space-md); }
        .dash-product-img { display: grid; place-items: center; flex: 0 0 40px; height: 40px; overflow: hidden; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface-soft); color: var(--color-accent-ink); font-size: var(--text-caption); font-weight: var(--font-weight-medium); }
        .dash-product-img img { width: 100%; height: 100%; object-fit: contain; }
        .dash-product-body { flex: 1 1 auto; min-width: 0; }

        .dash-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-sm); }
        .dash-tiles.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .dash-tile { display: block; padding: var(--space-md); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); text-decoration: none; min-width: 0; }
        a.dash-tile:hover { border-color: var(--color-accent); color: var(--color-text); }
        a.dash-tile:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
        .dash-tile small { display: block; font-size: var(--text-micro); color: var(--color-text-muted); }
        .dash-tile b { display: block; font-size: var(--text-heading-sm); font-weight: var(--font-weight-light); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-tile span { font-size: var(--text-micro); color: var(--color-text-secondary); font-feature-settings: var(--font-feature-numeric); }

        .dash-status { display: inline-flex; align-items: center; gap: var(--space-xs); padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-size: var(--text-micro); font-weight: var(--font-weight-medium); white-space: nowrap; }
        .dash-status.ok { background: var(--color-success-soft); color: var(--color-success); }
        .dash-status.warn { background: var(--color-warning-soft); color: var(--color-warning); }
        .dash-status.bad { background: var(--color-danger-soft); color: var(--color-danger); }

        .dash-list { margin: 0; padding: 0; list-style: none; }
        .dash-list li { display: flex; align-items: center; justify-content: space-between; gap: var(--space-md); padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border); font-size: var(--text-body-tabular); }
        .dash-list li:last-child { border-bottom: 0; }
        .dash-list .who { min-width: 0; }
        .dash-list .who div { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dash-list .who small { display: block; font-size: var(--text-micro); color: var(--color-text-muted); }
        .dash-list b { font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }

        /* ---------- Activity ---------- */
        .dash-feed { margin: 0; padding: 0; list-style: none; }
        .dash-feed li { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border); }
        .dash-feed li:last-child { border-bottom: 0; }
        .dash-feed .dash-ico { flex-basis: 36px; width: 36px; height: 36px; border-radius: 50%; }
        .dash-feed .body { flex: 1 1 auto; min-width: 0; }
        .dash-feed .body div { font-size: var(--text-body-tabular); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dash-feed .body small { display: block; font-size: var(--text-micro); color: var(--color-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dash-feed .amt { text-align: right; white-space: nowrap; }
        .dash-feed .amt b { display: block; font-size: var(--text-body-tabular); font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); }
        .dash-feed .amt small { font-size: var(--text-micro); color: var(--color-text-muted); }
        .dash-feed .in b { color: var(--color-success); }

        .dash-empty { padding: var(--space-xl) var(--space-md); text-align: center; color: var(--color-text-muted); font-size: var(--text-caption); }
        .dash-empty i { display: block; margin-bottom: var(--space-xs); font-size: var(--text-heading-lg); color: var(--color-border-input); }

        .dash-date { display: grid; place-items: center; flex: 0 0 44px; height: 44px; border-radius: var(--radius-md); background: var(--color-accent-soft); color: var(--color-accent-ink); line-height: 1; }
        .dash-date b { font-size: var(--text-body-lg); font-weight: var(--font-weight-medium); }
        .dash-date small { font-size: var(--text-micro-cap); text-transform: uppercase; }
        .dash-holiday { display: flex; align-items: center; gap: var(--space-md); min-width: 0; }

        .dash-meter { display: flex; height: 10px; margin: var(--space-sm) 0 var(--space-xs); border-radius: var(--radius-pill); overflow: hidden; background: var(--color-surface-soft); }
        .dash-meter span { height: 100%; }
        .dash-meter .paid { background: var(--color-success); }
        .dash-meter .due { background: var(--color-warning); }

        @media (max-width: 767.98px) {
            .dash-hero { padding: var(--space-lg); }
            .dash-hero-stats { grid-template-columns: 1fr; gap: var(--space-md); }
            .dash-hero-stat b { font-size: var(--text-display-md); }
            .dash-periods { width: 100%; }
            .dash-periods a { flex: 1 1 0; justify-content: center; padding: 0 var(--space-xs); }
        }

        @media (max-width: 575.98px) {
            .dash-card { padding: var(--space-lg); }
            .dash-kpi { padding: var(--space-md); }
            .dash-kpi-value { font-size: var(--text-heading-sm); }
            .dash-chart { height: 220px; }
            .dash-axis { width: 32px; }
            .dash-col small { font-size: var(--text-micro-cap); }
            .dash-tiles.three { grid-template-columns: 1fr 1fr 1fr; }
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-4 dash">
        @if (!empty($failed))
            <div class="alert alert-danger">The dashboard could not load its figures. Please check the log and try again.</div>
        @else
            @php
                $k = $kpi;
                $delta = function ($change, $goodWhenUp = true) {
                    if ($change === null) {
                        return '<span class="dash-delta flat">new</span>';
                    }
                    if (abs($change) < 0.05) {
                        return '<span class="dash-delta flat">0%</span>';
                    }
                    $up = $change > 0;
                    $class = $goodWhenUp === null ? 'flat' : (($up === $goodWhenUp) ? 'good' : 'bad');
                    return '<span class="dash-delta ' . $class . '"><i class="bi ' . ($up ? 'bi-arrow-up-right' : 'bi-arrow-down-right') . '" aria-hidden="true"></i>'
                        . ($up ? '+' : '−') . number_format(abs($change), 1) . '%</span>';
                };
                $vs = ['today' => 'vs yesterday', 'week' => 'vs last week', 'month' => 'vs last month', 'year' => 'vs last year'][$period];
                $series = fn ($key) => array_column($monthly, $key);
            @endphp

            {{-- ================= Hero ================= --}}
            <section class="dash-hero" aria-label="Overview">
                <div class="dash-hero-top">
                    <div>
                        <h1>{{ $greeting }}, {{ $firstName }}</h1>
                        <div class="dash-hero-sub">
                            {{ now()->format('l, d F Y') }} · {{ $periodLabel }}
                            ({{ $from->format('d M') }}{{ $from->isSameDay($to) ? '' : ' – ' . $to->format('d M') }})
                        </div>
                    </div>
                    <nav class="dash-periods" aria-label="Period">
                        @foreach (\App\Http\Controllers\Admin\DashboardController::PERIODS as $key => $label)
                            <a href="{{ route('dashboard', ['period' => $key]) }}" class="{{ $period === $key ? 'active' : '' }}"
                                @if ($period === $key) aria-current="page" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>

                <div class="dash-hero-stats">
                    <div class="dash-hero-stat">
                        <small>Sales</small>
                        <b title="{{ $money($k['sales'], 2) }}">{{ $money($k['sales']) }}</b>
                        <div class="meta">{!! $delta($k['change']['sales']) !!} {{ $vs }} · {{ $k['orders'] }} {{ \Illuminate\Support\Str::plural('invoice', $k['orders']) }}</div>
                    </div>
                    <div class="dash-hero-stat">
                        <small>Net profit</small>
                        <b class="{{ $k['net'] < 0 ? 'dash-neg' : '' }}" title="{{ $money($k['net'], 2) }}">{{ $money($k['net']) }}</b>
                        <div class="meta">Gross profit {{ $money($k['profit']) }} − expenses {{ $money($k['expenses']) }}</div>
                    </div>
                    <div class="dash-hero-stat">
                        <small>Cash &amp; bank on hand</small>
                        <b class="{{ $cash['total'] < 0 ? 'dash-neg' : '' }}" title="{{ $money($cash['total'], 2) }}">{{ $money($cash['total']) }}</b>
                        <div class="meta">across {{ count($cash['methods']) }} payment {{ \Illuminate\Support\Str::plural('method', count($cash['methods'])) }}</div>
                    </div>
                </div>

                <nav class="dash-actions" aria-label="Quick actions">
                    <a class="dash-action primary" href="{{ route('sales.pos') }}"><i class="bi bi-shop" aria-hidden="true"></i> Open POS</a>
                    <a class="dash-action" href="{{ route('purchases.create') }}"><i class="bi bi-bag-plus" aria-hidden="true"></i> New purchase</a>
                    <a class="dash-action" href="{{ route('admin.quotations.create') }}"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> New quotation</a>
                    <a class="dash-action" href="{{ route('admin.payment.index', 'Payment Receive') }}"><i class="bi bi-cash-coin" aria-hidden="true"></i> Receive payment</a>
                    <a class="dash-action" href="{{ route('admin.expense.index') }}"><i class="bi bi-receipt" aria-hidden="true"></i> Add expense</a>
                    <a class="dash-action" href="{{ route('admin.reports.salary-sheet') }}"><i class="bi bi-people" aria-hidden="true"></i> Salary sheet</a>
                    <a class="dash-action" href="{{ route('admin.reports.transaction-ledger') }}"><i class="bi bi-journal-text" aria-hidden="true"></i> Transaction ledger</a>
                </nav>
            </section>

            {{-- ================= KPI tiles ================= --}}
            <section class="row g-3 mb-3" aria-label="Key figures">
                <div class="col-6 col-xl-3">
                    <div class="dash-kpi tone-success">
                        <div class="dash-kpi-top"><span class="dash-ico"><i class="bi bi-pie-chart" aria-hidden="true"></i></span> Gross profit</div>
                        <div class="dash-kpi-value" title="{{ $money($k['profit'], 2) }}">{{ $money($k['profit']) }}</div>
                        <div class="dash-kpi-meta">{!! $delta($k['change']['profit']) !!} {{ $k['margin'] === null ? 'no sales yet' : number_format($k['margin'], 1) . '% margin' }}</div>
                        <div class="dash-track good mt-3" title="Margin"><span style="width: {{ max(0, min(100, $k['margin'] ?? 0)) }}%"></span></div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <a class="dash-kpi tone-accent" href="{{ route('admin.payment.index', 'Payment Receive') }}">
                        <div class="dash-kpi-top"><span class="dash-ico"><i class="bi bi-wallet2" aria-hidden="true"></i></span> Collected</div>
                        <div class="dash-kpi-value" title="{{ $money($k['collected'], 2) }}">{{ $money($k['collected']) }}</div>
                        <div class="dash-kpi-meta">{!! $delta($k['change']['collected']) !!} {{ $vs }}</div>
                        {!! $spark($series('collected')) !!}
                    </a>
                </div>
                <div class="col-6 col-xl-3">
                    <a class="dash-kpi tone-danger" href="{{ route('admin.expense.index') }}">
                        <div class="dash-kpi-top"><span class="dash-ico"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></span> Expenses</div>
                        <div class="dash-kpi-value" title="{{ $money($k['expenses'], 2) }}">{{ $money($k['expenses']) }}</div>
                        <div class="dash-kpi-meta">{!! $delta($k['change']['expenses'], false) !!} incl. bills &amp; salary</div>
                        {!! $spark($series('expenses')) !!}
                    </a>
                </div>
                <div class="col-6 col-xl-3">
                    <a class="dash-kpi tone-warning" href="{{ route('purchases.index') }}">
                        <div class="dash-kpi-top"><span class="dash-ico"><i class="bi bi-bag" aria-hidden="true"></i></span> Purchases</div>
                        <div class="dash-kpi-value" title="{{ $money($k['purchases'], 2) }}">{{ $money($k['purchases']) }}</div>
                        <div class="dash-kpi-meta">{!! $delta($k['change']['purchases'], null) !!} {{ $vs }}</div>
                        {!! $spark($series('purchases')) !!}
                    </a>
                </div>

                <div class="col-6 col-xl-3">
                    <a class="dash-kpi tone-accent" href="{{ route('admin.reports.party-ledger', 'customer') }}">
                        <div class="dash-kpi-top"><span class="dash-ico"><i class="bi bi-person-down" aria-hidden="true"></i></span> Receivable</div>
                        <div class="dash-kpi-value" title="{{ $money($dues['receivable'], 2) }}">{{ $money($dues['receivable']) }}</div>
                        <div class="dash-kpi-meta">{{ $dues['customerCount'] }} {{ \Illuminate\Support\Str::plural('customer', $dues['customerCount']) }} owe you</div>
                    </a>
                </div>
                <div class="col-6 col-xl-3">
                    <a class="dash-kpi tone-warning" href="{{ route('admin.reports.party-ledger', 'supplier') }}">
                        <div class="dash-kpi-top"><span class="dash-ico"><i class="bi bi-person-up" aria-hidden="true"></i></span> Payable</div>
                        <div class="dash-kpi-value" title="{{ $money($dues['payable'], 2) }}">{{ $money($dues['payable']) }}</div>
                        <div class="dash-kpi-meta">to {{ $dues['supplierCount'] }} {{ \Illuminate\Support\Str::plural('supplier', $dues['supplierCount']) }}</div>
                    </a>
                </div>
                <div class="col-6 col-xl-3">
                    <a class="dash-kpi tone-success" href="{{ route('admin.reports.stock-ledger') }}">
                        <div class="dash-kpi-top"><span class="dash-ico"><i class="bi bi-boxes" aria-hidden="true"></i></span> Stock value</div>
                        <div class="dash-kpi-value" title="{{ $money($stock['value'], 2) }}">{{ $money($stock['value']) }}</div>
                        <div class="dash-kpi-meta">{{ rtrim(rtrim(number_format($stock['units'], 2), '0'), '.') }} units · {{ $stock['lowCount'] + $stock['outCount'] }} need attention</div>
                    </a>
                </div>
                <div class="col-6 col-xl-3">
                    <a class="dash-kpi {{ $hr['payrollMonth']['due'] > 0 ? 'tone-danger' : 'tone-accent' }}" href="{{ route('admin.reports.salary-sheet') }}">
                        <div class="dash-kpi-top"><span class="dash-ico"><i class="bi bi-people" aria-hidden="true"></i></span> Salary due</div>
                        <div class="dash-kpi-value">{{ $money($hr['payrollMonth']['due']) }}</div>
                        <div class="dash-kpi-meta">{{ now()->format('F') }}: {{ $hr['payrollMonth']['processed'] }} of {{ $hr['employees'] }} processed</div>
                    </a>
                </div>
            </section>

            {{-- ================= Trend + P&L ================= --}}
            @php
                $mMax = $nice(max(array_merge([0], $series('sales'), $series('purchases'))));
                $dMax = max(array_merge([0], array_column($daily, 'total')));
                $plMax = max(1, $k['sales'], $k['cost'], $k['expenses'], abs($k['net']));
            @endphp
            <section class="row g-3 mb-3">
                <div class="col-xl-8">
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div>
                                <h2>Sales vs purchases</h2>
                                <p>Last 12 months · {{ $money(array_sum($series('sales'))) }} sold, {{ $money(array_sum($series('purchases'))) }} bought</p>
                            </div>
                            <div class="dash-legend" aria-hidden="true">
                                <span><i class="dash-swatch"></i> Sales</span>
                                <span><i class="dash-swatch alt"></i> Purchases</span>
                            </div>
                        </div>
                        <div class="dash-chart" aria-hidden="true">
                            <div class="dash-axis">
                                @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
                                    <span style="bottom: {{ $f * 100 }}%">{{ $compact($mMax * $f) }}</span>
                                @endforeach
                            </div>
                            <div class="dash-plot">
                                <div class="dash-grid">
                                    @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
                                        <span class="{{ $f == 0 ? 'base' : '' }}" style="bottom: {{ $f * 100 }}%"></span>
                                    @endforeach
                                </div>
                                <div class="dash-cols">
                                    @foreach ($monthly as $m)
                                        <button type="button" class="dash-col" tabindex="-1"
                                            data-tip="<strong>{{ e($m['title']) }}</strong><div><span><i class='dash-swatch'></i>Sales</span><b>{{ e($money($m['sales'], 2)) }}</b></div><div><span><i class='dash-swatch alt'></i>Purchases</span><b>{{ e($money($m['purchases'], 2)) }}</b></div><div><span>Expenses</span><b>{{ e($money($m['expenses'], 2)) }}</b></div>">
                                            <span class="dash-bars">
                                                <span class="dash-bar {{ $m['sales'] ? '' : 'zero' }}" style="height: {{ $pct($m['sales'], $mMax) }}%"></span>
                                                <span class="dash-bar alt {{ $m['purchases'] ? '' : 'zero' }}" style="height: {{ $pct($m['purchases'], $mMax) }}%"></span>
                                            </span>
                                            <small>{{ $m['label'] }}</small>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <table class="visually-hidden">
                            <caption>Sales and purchases by month</caption>
                            <thead><tr><th>Month</th><th>Sales</th><th>Purchases</th><th>Expenses</th></tr></thead>
                            <tbody>
                                @foreach ($monthly as $m)
                                    <tr><td>{{ $m['title'] }}</td><td>{{ $money($m['sales'], 2) }}</td><td>{{ $money($m['purchases'], 2) }}</td><td>{{ $money($m['expenses'], 2) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </article>
                </div>

                <div class="col-xl-4">
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div>
                                <h2>Profit &amp; loss</h2>
                                <p>{{ $periodLabel }} · from sales, cost of goods and expenses</p>
                            </div>
                        </div>
                        <ul class="dash-pl">
                            <li>
                                <div class="row-top"><span>Sales</span><b>{{ $money($k['sales']) }}</b></div>
                                <div class="dash-track"><span style="width: {{ $pct($k['sales'], $plMax) }}%"></span></div>
                            </li>
                            <li>
                                <div class="row-top"><span>Cost of goods sold</span><b>−{{ $money($k['cost']) }}</b></div>
                                <div class="dash-track alt"><span style="width: {{ $pct($k['cost'], $plMax) }}%"></span></div>
                            </li>
                            <li>
                                <div class="row-top"><span>Gross profit</span><b>{{ $money($k['profit']) }}</b></div>
                                <div class="dash-track good"><span style="width: {{ $pct(max(0, $k['profit']), $plMax) }}%"></span></div>
                            </li>
                            <li>
                                <div class="row-top"><span>Expenses, bills &amp; salary</span><b>−{{ $money($k['expenses']) }}</b></div>
                                <div class="dash-track bad"><span style="width: {{ $pct($k['expenses'], $plMax) }}%"></span></div>
                            </li>
                            <li class="total">
                                <div class="row-top"><span>Net profit</span><b class="{{ $k['net'] < 0 ? 'dash-neg' : '' }}">{{ $money($k['net']) }}</b></div>
                                <div class="dash-kpi-meta mt-1">{!! $delta($k['change']['net']) !!} {{ $vs }}@if ($k['returns'] > 0) · {{ $money($k['returns']) }} returned @endif</div>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            {{-- ================= Today · cash · products ================= --}}
            <section class="row g-3 mb-3">
                <div class="col-md-6 col-xl-4">
                    <article class="dash-card d-flex flex-column">
                        <div class="dash-card-head mb-2">
                            <div><h2>Today</h2><p>{{ now()->format('d M Y') }}</p></div>
                            <a class="dash-link" href="{{ route('sales.index') }}">All sales</a>
                        </div>
                        <div class="dash-kpi-value m-0" style="font-size: var(--text-display-lg)">{{ $money($today['sales']) }}</div>
                        <div class="dash-kpi-meta mt-1">{!! $delta($today['change']['sales'] ?? null) !!} vs yesterday · {{ $today['orders'] }} {{ \Illuminate\Support\Str::plural('invoice', $today['orders']) }}</div>
                        <div class="dash-tiles three mt-3">
                            <div class="dash-tile"><small>Items sold</small><b>{{ rtrim(rtrim(number_format($today['items'], 2), '0'), '.') }}</b></div>
                            <div class="dash-tile"><small>Collected</small><b>{{ $compact($today['collected']) }}</b></div>
                            <div class="dash-tile"><small>Avg. invoice</small><b>{{ $compact($today['avg']) }}</b></div>
                        </div>
                        <div class="mt-auto pt-3">
                            <div class="dash-row-sub">Daily sales · last 30 days</div>
                            <div class="dash-chart mt-2" style="height: 110px; grid-template-columns: 1fr;" aria-hidden="true">
                                <div class="dash-plot">
                                    <div class="dash-grid"><span class="base" style="bottom: 0"></span></div>
                                    <div class="dash-cols">
                                        @foreach ($daily as $d)
                                            <button type="button" tabindex="-1" class="dash-col"
                                                data-tip="<strong>{{ e($d['date']) }}</strong><div><span>Sales</span><b>{{ e($money($d['total'], 2)) }}</b></div><div><span>Invoices</span><b>{{ $d['count'] }}</b></div>">
                                                <span class="dash-bars"><span class="dash-bar {{ $d['total'] ? '' : 'zero' }}" style="width: 100%; max-width: 10px; height: {{ $pct($d['total'], $dMax) }}%"></span></span>
                                                <small style="visibility: hidden;">.</small>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="col-md-6 col-xl-4">
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div><h2>Cash &amp; bank</h2><p>Current balance by payment method</p></div>
                            <a class="dash-link" href="{{ route('admin.reports.liquid-asset') }}">Cash book</a>
                        </div>
                        @php $cMax = max(array_merge([0], array_map(fn ($m) => abs($m['balance']), $cash['methods']))); @endphp
                        @forelse ($cash['methods'] as $m)
                            <div class="mb-3">
                                <div class="dash-row-top"><span>{{ $m['name'] }}</span><b class="{{ $m['balance'] < 0 ? 'dash-neg' : '' }}">{{ $money($m['balance'], 2) }}</b></div>
                                <div class="dash-track {{ $m['balance'] < 0 ? 'bad' : '' }}"><span style="width: {{ $pct(abs($m['balance']), $cMax) }}%"></span></div>
                                @if (count($m['accounts']) > 1 || (count($m['accounts']) === 1 && $m['accounts'][0]['name'] !== $m['name']))
                                    <ul class="dash-sub-accounts">
                                        @foreach ($m['accounts'] as $a)
                                            <li><span>{{ $a['name'] }}</span><b class="{{ $a['balance'] < 0 ? 'dash-neg' : '' }}">{{ $money($a['balance'], 2) }}</b></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @empty
                            <div class="dash-empty"><i class="bi bi-bank" aria-hidden="true"></i>No payment methods yet. Add them under Chart of Accounts › Liquid Asset.</div>
                        @endforelse
                        <div class="dash-total-line"><span>Total on hand</span><b class="{{ $cash['total'] < 0 ? 'dash-neg' : '' }}">{{ $money($cash['total'], 2) }}</b></div>
                    </article>
                </div>

                <div class="col-xl-4">
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div><h2>Top products</h2><p>By revenue · {{ strtolower($periodLabel) }}</p></div>
                            <a class="dash-link" href="{{ route('products.index') }}">Products</a>
                        </div>
                        @php $tMax = max(array_merge([0], array_map(fn ($p) => (float) $p->revenue, $topProducts))); @endphp
                        @if ($topProducts)
                            <ol class="dash-rows">
                                @foreach ($topProducts as $p)
                                    <li class="dash-product">
                                        <span class="dash-product-img">
                                            @if ($p->image_url)<img src="{{ $p->image_url }}" alt="" loading="lazy">@else{{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}@endif
                                        </span>
                                        <div class="dash-product-body">
                                            <div class="dash-row-top"><span title="{{ $p->name }}">{{ $p->name }}</span><b>{{ $money($p->revenue) }}</b></div>
                                            <div class="dash-row-sub">{{ rtrim(rtrim(number_format($p->qty, 2), '0'), '.') }} sold · {{ $money($p->profit) }} profit</div>
                                            <div class="dash-track"><span style="width: {{ $pct($p->revenue, $tMax) }}%"></span></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <div class="dash-empty"><i class="bi bi-box-seam" aria-hidden="true"></i>No sales {{ strtolower($periodLabel) }}.</div>
                        @endif
                        @if ($salesMix)
                            <div class="dash-row-sub mt-4 mb-2">Sales channels</div>
                            <ul class="dash-rows">
                                @foreach ($salesMix as $mix)
                                    <li>
                                        <div class="dash-row-top"><span>{{ $mix['type'] }} <span class="dash-row-sub">· {{ $mix['count'] }}</span></span>
                                            <b>{{ $money($mix['total']) }} <span class="dash-row-sub">{{ number_format($mix['share'], 0) }}%</span></b></div>
                                        <div class="dash-track"><span style="width: {{ $mix['share'] }}%"></span></div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                </div>
            </section>

            {{-- ================= Activity + stock ================= --}}
            <section class="row g-3 mb-3">
                <div class="col-xl-8">
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div><h2>Recent activity</h2><p>Latest sales, purchases, receipts, payments, expenses, salary and loans</p></div>
                            <a class="dash-link" href="{{ route('admin.reports.transaction-ledger') }}">Transaction ledger</a>
                        </div>
                        @if ($activity)
                            @php
                                $tone = ['sale' => 'tone-success', 'receipt' => 'tone-success', 'purchase' => 'tone-warning', 'payment' => 'tone-warning',
                                    'expense' => 'tone-danger', 'salary' => 'tone-accent', 'loan' => 'tone-accent'];
                            @endphp
                            <ul class="dash-feed">
                                @foreach ($activity as $a)
                                    <li class="{{ $tone[$a['kind']] ?? 'tone-accent' }}">
                                        <span class="dash-ico"><i class="bi {{ $a['icon'] }}" aria-hidden="true"></i></span>
                                        <div class="body">
                                            <div>{{ $a['title'] }} <span class="text-muted">· {{ $a['ref'] }}</span></div>
                                            <small>{{ $a['who'] }}</small>
                                        </div>
                                        <div class="amt {{ $a['in'] ? 'in' : '' }}">
                                            <b>{{ $a['in'] ? '+' : '−' }}{{ $money($a['amount'], 2) }}</b>
                                            <small>{{ $a['when']->diffForHumans() }}</small>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="dash-empty"><i class="bi bi-activity" aria-hidden="true"></i>Nothing has happened yet.</div>
                        @endif
                    </article>
                </div>

                <div class="col-xl-4">
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div>
                                <h2>Stock</h2>
                                <p>{{ number_format($stock['products']) }} products · {{ $money($stock['value']) }} at cost</p>
                            </div>
                            <a class="dash-link" href="{{ route('admin.reports.stock-ledger') }}">Stock ledger</a>
                        </div>
                        <div class="dash-tiles mb-3">
                            <div class="dash-tile"><small>Low stock</small><b class="{{ $stock['lowCount'] ? 'text-warning' : '' }}">{{ $stock['lowCount'] }}</b><span>at or below alert</span></div>
                            <div class="dash-tile"><small>Out of stock</small><b class="{{ $stock['outCount'] ? 'dash-neg' : '' }}">{{ $stock['outCount'] }}</b><span>can't be sold</span></div>
                        </div>
                        @if ($stock['alerts'])
                            <ul class="dash-list">
                                @foreach ($stock['alerts'] as $p)
                                    <li>
                                        <div class="who"><div title="{{ $p->name }}">{{ $p->name }}</div><small>{{ $p->sku ?: 'No SKU' }} · alert at {{ rtrim(rtrim(number_format((float) $p->stock_alert, 2), '0'), '.') }}</small></div>
                                        @if ($p->stock <= 0)
                                            <span class="dash-status bad"><i class="bi bi-x-circle" aria-hidden="true"></i> Out</span>
                                        @else
                                            <span class="dash-status warn"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ rtrim(rtrim(number_format($p->stock, 2), '0'), '.') }} left</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="dash-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i>Every product is above its alert level.</div>
                        @endif
                        <div class="dash-tiles mt-3">
                            <a class="dash-tile" href="{{ route('admin.quotations.index') }}"><small>Open quotations</small><b>{{ $pipeline['quoteOpen'] }}</b><span>{{ $money($pipeline['quoteOpenValue']) }}</span></a>
                            <a class="dash-tile" href="{{ route('sales.orders.index') }}"><small>Open orders</small><b>{{ $pipeline['orderOpen'] }}</b><span>{{ $money($pipeline['orderDue']) }} due</span></a>
                        </div>
                    </article>
                </div>
            </section>

            {{-- ================= Dues + people ================= --}}
            <section class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div><h2>Customers who owe you</h2><p>Top 5 of {{ $money($dues['receivable']) }}</p></div>
                            <a class="dash-link" href="{{ route('admin.payment.index', 'Payment Receive') }}">Collect</a>
                        </div>
                        @if ($dues['topCustomers'])
                            <ul class="dash-list">
                                @foreach ($dues['topCustomers'] as $c)
                                    <li><div class="who"><div>{{ $c->name }}</div><small>{{ $c->phone ?: $c->type }}</small></div><b>{{ $money($c->due, 2) }}</b></li>
                                @endforeach
                            </ul>
                        @else
                            <div class="dash-empty"><i class="bi bi-emoji-smile" aria-hidden="true"></i>No customer dues.</div>
                        @endif
                    </article>
                </div>

                <div class="col-md-6 col-xl-4">
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div><h2>Suppliers you owe</h2><p>Top 5 of {{ $money($dues['payable']) }}</p></div>
                            <a class="dash-link" href="{{ route('admin.payment.index', 'Payment') }}">Pay</a>
                        </div>
                        @if ($dues['topSuppliers'])
                            <ul class="dash-list">
                                @foreach ($dues['topSuppliers'] as $s)
                                    <li><div class="who"><div>{{ $s->name }}</div><small>{{ $s->phone ?: 'Supplier' }}</small></div><b>{{ $money($s->due, 2) }}</b></li>
                                @endforeach
                            </ul>
                        @else
                            <div class="dash-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i>Nothing owed to suppliers.</div>
                        @endif
                    </article>
                </div>

                <div class="col-xl-4">
                    @php $pm = $hr['payrollMonth']; @endphp
                    <article class="dash-card">
                        <div class="dash-card-head">
                            <div><h2>People &amp; payroll</h2><p>{{ now()->format('F Y') }}</p></div>
                            <a class="dash-link" href="{{ route('admin.reports.salary-sheet') }}">Salary sheet</a>
                        </div>
                        <div class="dash-tiles three mb-3">
                            <a class="dash-tile" href="{{ route('admin.employee.index') }}"><small>Active</small><b>{{ $hr['employees'] }}</b></a>
                            <a class="dash-tile" href="{{ route('admin.leave.index') }}"><small>On leave</small><b>{{ $hr['onLeave'] }}</b></a>
                            <a class="dash-tile" href="{{ route('admin.leave.index') }}"><small>Leave requests</small><b class="{{ $hr['pending'] ? 'text-warning' : '' }}">{{ $hr['pending'] }}</b></a>
                        </div>

                        @if ($pm['processed'])
                            <div class="dash-row-top"><span>Salary {{ $money($pm['net']) }}</span><b>{{ $pm['processed'] }} of {{ $hr['employees'] }} processed</b></div>
                            <div class="dash-meter" role="img" aria-label="Paid {{ $money($pm['paid']) }}, due {{ $money($pm['due']) }}">
                                <span class="paid" style="width: {{ $pct($pm['paid'], $pm['net']) }}%"></span>
                                <span class="due" style="width: {{ $pct($pm['due'], $pm['net']) }}%"></span>
                            </div>
                            <div class="dash-row-sub"><span class="text-success">●</span> Paid {{ $money($pm['paid']) }} &nbsp; <span class="text-warning">●</span> Due {{ $money($pm['due']) }}</div>
                        @else
                            <div class="dash-row-sub">{{ now()->format('F') }} salary not processed yet. <a href="{{ route('admin.reports.salary-sheet') }}">Process it</a></div>
                        @endif

                        <ul class="dash-list mt-2">
                            <li>
                                <div class="who"><div>Loans &amp; advances</div><small>{{ $hr['loans']['count'] }} running</small></div>
                                <a class="dash-link" href="{{ route('admin.employee-loans.index') }}"><b>{{ $money($hr['loans']['balance']) }}</b></a>
                            </li>
                            @foreach ($hr['holidays'] as $h)
                                <li>
                                    <div class="dash-holiday">
                                        <span class="dash-date"><b>{{ \Carbon\Carbon::parse($h->from_date)->format('d') }}</b><small>{{ \Carbon\Carbon::parse($h->from_date)->format('M') }}</small></span>
                                        <div class="who"><div>{{ $h->title }}</div><small>{{ $h->type ?: 'Holiday' }}{{ $h->to_date !== $h->from_date ? ' · until ' . \Carbon\Carbon::parse($h->to_date)->format('d M') : '' }}</small></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if (!$hr['holidays'])
                            <div class="dash-row-sub mt-2">No upcoming holidays. <a href="{{ route('admin.leave.calendar.index') }}">Open calendar</a></div>
                        @endif
                    </article>
                </div>
            </section>
        @endif
    </div>

    <div class="dash-tip" id="dashTip" role="tooltip"></div>
@endsection

@push('scripts')
    <script>
        (function () {
            var tip = document.getElementById('dashTip');
            if (!tip) return;

            function place(e) {
                var x = e.clientX + 14, y = e.clientY + 14;
                var w = tip.offsetWidth, h = tip.offsetHeight;
                if (x + w > window.innerWidth - 8) x = e.clientX - w - 14;
                if (y + h > window.innerHeight - 8) y = e.clientY - h - 14;
                tip.style.left = Math.max(8, x) + 'px';
                tip.style.top = Math.max(8, y) + 'px';
            }

            document.querySelectorAll('[data-tip]').forEach(function (el) {
                el.addEventListener('mouseenter', function (e) { tip.innerHTML = el.getAttribute('data-tip'); tip.classList.add('show'); place(e); });
                el.addEventListener('mousemove', place);
                el.addEventListener('mouseleave', function () { tip.classList.remove('show'); });
            });
        })();
    </script>
@endpush
