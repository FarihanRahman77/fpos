@extends('admin.master')
@section('title', 'Dashboard')

@php
    use App\Http\Controllers\Admin\BranchController;
    use Illuminate\Support\Str;

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
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
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
    $can = $can ?? [];
    $branchTag = fn ($id) => !empty($multi) && ($name = \App\Http\Controllers\Admin\BranchController::label($id)) !== '' ? '<span class="dash-branch" title="Branch">' . e($name) . '</span>' : '';
@endphp

@push('styles')
    <style>
        /* Dashboard only. Colors are tokens; the hero reads --color-hero-* (Theme Settings) and falls back to the sidebar's. */
        .dash {
            --dash-gap: var(--space-xl);
            --dash-list-h: 284px;
            --dash-hero-bg: var(--color-hero-bg, var(--color-sidebar));
            --dash-hero-text: var(--color-hero-text, var(--color-sidebar-text-strong));
            --dash-hero-muted: var(--color-hero-muted, var(--color-sidebar-text));
            --dash-hero-glow: var(--color-hero-accent, var(--color-primary));
            --dash-btn-radius: var(--radius-button, var(--radius-pill));
        }

        /* ---------- Hero ---------- */
        .dash-hero {
            position: relative;
            overflow: hidden;
            padding: var(--space-xl) var(--space-xl) var(--space-lg);
            border-radius: var(--radius-xl);
            background:
                radial-gradient(ellipse at 100% 0%, color-mix(in srgb, var(--dash-hero-glow) 45%, transparent), transparent 60%),
                radial-gradient(ellipse at 0% 130%, color-mix(in srgb, var(--dash-hero-glow) 18%, transparent), transparent 55%),
                var(--dash-hero-bg);
            color: var(--dash-hero-text);
            box-shadow: var(--shadow-2);
        }

        .dash-hero-top { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: var(--space-lg); }
        .dash-hero h1 { margin: 0; font-size: var(--text-display-md); font-weight: var(--font-weight-light); letter-spacing: var(--tracking-display-md); line-height: var(--leading-display-md); color: var(--dash-hero-text); }
        .dash-hero-sub { margin-top: var(--space-xs); color: var(--dash-hero-muted); font-size: var(--text-body-tabular); }

        .dash-periods {
            display: inline-flex;
            padding: var(--space-xxs);
            border: 1px solid color-mix(in srgb, var(--dash-hero-text) 18%, transparent);
            border-radius: var(--dash-btn-radius);
            background: color-mix(in srgb, var(--dash-hero-text) 6%, transparent);
        }

        .dash-periods a {
            display: inline-flex;
            align-items: center;
            min-height: var(--control-height-sm);
            padding: 0 var(--space-md);
            border-radius: var(--dash-btn-radius);
            color: var(--dash-hero-muted);
            font-size: var(--text-caption);
            text-decoration: none;
            white-space: nowrap;
        }

        .dash-periods a:hover { color: var(--dash-hero-text); }
        .dash-periods a.active { background: var(--dash-hero-text); color: var(--dash-hero-bg); font-weight: var(--font-weight-medium); }
        .dash-periods a:focus-visible { outline: none; box-shadow: var(--shadow-focus); }

        .dash-hero-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg) var(--space-xl); margin-top: var(--space-xl); }
        .dash-hero-stat { min-width: 0; padding-left: var(--space-lg); border-left: 1px solid color-mix(in srgb, var(--dash-hero-text) 16%, transparent); }
        .dash-hero-stat small { display: block; color: var(--dash-hero-muted); font-size: var(--text-caption); }
        .dash-hero-stat b {
            display: block;
            margin-top: var(--space-xxs);
            font-size: var(--text-display-lg);
            font-weight: var(--font-weight-light);
            letter-spacing: var(--tracking-display-lg);
            line-height: var(--leading-display-lg);
            font-feature-settings: var(--font-feature-numeric);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-hero-stat .meta { margin-top: var(--space-sm); font-size: var(--text-micro); color: var(--dash-hero-muted); }

        .dash-actions {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid color-mix(in srgb, var(--dash-hero-text) 12%, transparent);
        }

        .dash-action {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            min-height: var(--control-height-sm);
            padding: 0 var(--space-lg);
            border: 1px solid color-mix(in srgb, var(--dash-hero-text) 20%, transparent);
            border-radius: var(--dash-btn-radius);
            color: var(--dash-hero-text);
            font-size: var(--text-button-sm);
            text-decoration: none;
            white-space: nowrap;
        }

        .dash-action:hover { background: color-mix(in srgb, var(--dash-hero-text) 10%, transparent); color: var(--dash-hero-text); }
        .dash-action:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
        .dash-action.primary { background: var(--color-button); border-color: var(--color-button); color: var(--color-button-text); }
        .dash-action.primary:hover { background: var(--color-button-hover); border-color: var(--color-button-hover); }

        /* ---------- Sections & grid: cards in one row share one height ---------- */
        .dash-section { margin-top: var(--space-xxl); }
        .dash-section-title {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin: 0 0 var(--space-md);
            font-size: var(--text-caption);
            font-weight: var(--font-weight-medium);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--color-text-muted);
        }
        .dash-section-title::after { content: ""; flex: 1 1 auto; height: 1px; background: var(--color-border); }

        .dash-row { display: grid; grid-template-columns: minmax(0, 1fr); gap: var(--dash-gap); align-items: stretch; }
        .dash-row + .dash-row { margin-top: var(--dash-gap); }

        @media (min-width: 768px) {
            .dash-row.c2, .dash-row.c3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .dash-row.c3 > :nth-child(3) { grid-column: 1 / -1; }
        }

        @media (min-width: 1200px) {
            .dash-row.c3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .dash-row.c3 > :nth-child(3) { grid-column: auto; }
            .dash-row.wide { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }
        }

        /* ---------- Cards ---------- */
        .dash-card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100%;
            padding: var(--space-xl);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-1);
        }

        .dash-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-md); margin-bottom: var(--space-lg); }
        .dash-card-head > div { min-width: 0; }
        .dash-card-head h3 { margin: 0; font-size: var(--text-heading-sm); font-weight: var(--font-weight-regular); line-height: var(--leading-heading-sm); color: var(--color-text); }
        .dash-card-head p { margin: var(--space-xxs) 0 0; font-size: var(--text-caption); color: var(--color-text-muted); }
        .dash-link { flex: 0 0 auto; display: inline-flex; align-items: center; gap: var(--space-xxs); font-size: var(--text-caption); color: var(--color-link); text-decoration: none; white-space: nowrap; }
        .dash-link:hover { text-decoration: underline; color: var(--color-link); }
        .dash-card-body { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
        .dash-card-foot {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: var(--space-md);
            margin-top: auto;
            padding-top: var(--space-md);
            border-top: 1px solid var(--color-border);
            font-size: var(--text-caption);
            color: var(--color-text-muted);
        }
        .dash-card-foot b { font-size: var(--text-heading-sm); font-weight: var(--font-weight-light); color: var(--color-text); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }
        .dash-card-foot .dash-link b { color: inherit; }

        /* ---------- KPI tiles ---------- */
        .dash-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-lg); margin-top: var(--dash-gap); }
        @media (min-width: 1200px) { .dash-kpis { grid-template-columns: repeat(var(--kpi-cols, 4), minmax(0, 1fr)); } }
        @media (max-width: 1199.98px) { .dash-kpis > :last-child:nth-child(odd) { grid-column: 1 / -1; } }

        .dash-kpi {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
            min-width: 0;
            padding: var(--space-lg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-1);
            color: var(--color-text);
            text-decoration: none;
            overflow: hidden;
        }

        a.dash-kpi:hover { border-color: var(--color-border-input); color: var(--color-text); }
        a.dash-kpi:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
        .dash-kpi-top { display: flex; align-items: center; gap: var(--space-sm); font-size: var(--text-caption); color: var(--color-text-muted); }

        .dash-ico { display: inline-grid; flex: 0 0 32px; width: 32px; height: 32px; place-items: center; border-radius: var(--radius-md); font-size: var(--text-body-md); }
        .tone-accent .dash-ico, .dash-ico.tone-accent { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .tone-success .dash-ico, .dash-ico.tone-success { background: var(--color-success-soft); color: var(--color-success); }
        .tone-warning .dash-ico, .dash-ico.tone-warning { background: var(--color-warning-soft); color: var(--color-warning); }
        .tone-danger .dash-ico, .dash-ico.tone-danger { background: var(--color-danger-soft); color: var(--color-danger); }
        .tone-accent .dash-spark { color: var(--color-accent); }
        .tone-success .dash-spark { color: var(--color-success); }
        .tone-warning .dash-spark { color: var(--color-warning); }
        .tone-danger .dash-spark { color: var(--color-danger); }

        .dash-kpi-value {
            margin-top: var(--space-xs);
            font-size: var(--text-heading-lg);
            font-weight: var(--font-weight-light);
            letter-spacing: var(--tracking-heading-lg);
            line-height: var(--leading-heading-lg);
            font-feature-settings: var(--font-feature-numeric);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-kpi-meta { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-xs) var(--space-sm); font-size: var(--text-micro); color: var(--color-text-muted); }
        .dash-kpi .dash-spark, .dash-kpi .dash-track { margin-top: auto; }

        .dash-spark { display: block; flex: 0 0 28px; width: 100%; height: 28px; padding-top: var(--space-sm); overflow: visible; box-sizing: content-box; }
        .dash-spark .line { fill: none; stroke: currentColor; stroke-width: 2; vector-effect: non-scaling-stroke; stroke-linejoin: round; stroke-linecap: round; }
        .dash-spark .area { fill: currentColor; opacity: .1; }
        .dash-spark line { stroke: var(--color-border); stroke-width: 2; vector-effect: non-scaling-stroke; }

        .dash-delta { display: inline-flex; align-items: center; gap: var(--space-xxs); padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }
        .dash-delta.good { background: var(--color-success-soft); color: var(--color-success); }
        .dash-delta.bad { background: var(--color-danger-soft); color: var(--color-danger); }
        .dash-delta.flat { background: var(--color-surface-soft); color: var(--color-text-secondary); }
        .dash-hero .dash-delta.flat { background: color-mix(in srgb, var(--dash-hero-text) 12%, transparent); color: var(--dash-hero-text); }

        .dash-neg { color: var(--color-danger) !important; }
        .dash-muted { color: var(--color-text-muted); }

        /* ---------- Tabs (segmented) ---------- */
        .dash-seg { display: inline-flex; flex: 0 0 auto; padding: var(--space-xxs); border: 1px solid var(--color-border); border-radius: var(--dash-btn-radius); background: var(--color-surface-soft); }
        .dash-seg button { min-height: 30px; padding: 0 var(--space-md); border: 0; border-radius: var(--dash-btn-radius); background: transparent; color: var(--color-text-secondary); font-size: var(--text-caption); white-space: nowrap; }
        .dash-seg button[aria-selected="true"] { background: var(--color-surface); color: var(--color-text); box-shadow: var(--shadow-1); font-weight: var(--font-weight-medium); }
        .dash-seg button:focus-visible { outline: none; box-shadow: var(--shadow-focus); }

        /* ---------- Charts (plain HTML/SVG, colors from tokens) ---------- */
        .dash-legend { display: flex; flex-wrap: wrap; gap: var(--space-xs) var(--space-lg); font-size: var(--text-caption); color: var(--color-text-secondary); }
        .dash-legend span { display: inline-flex; align-items: center; gap: var(--space-xs); }
        .dash-swatch { display: inline-block; flex: 0 0 10px; width: 10px; height: 10px; border-radius: var(--radius-xs); background: var(--color-accent); }
        .dash-swatch.alt { background: var(--color-border-input); }
        .dash-swatch.line { height: 2px; width: 14px; flex-basis: 14px; border-radius: var(--radius-pill); }

        .dash-chart { position: relative; display: grid; grid-template-columns: auto 1fr; gap: var(--space-sm); height: 248px; margin-top: var(--space-lg); }
        .dash-axis { position: relative; width: 40px; margin-bottom: 22px; font-size: var(--text-micro); color: var(--color-text-muted); font-feature-settings: var(--font-feature-numeric); }
        .dash-axis span { position: absolute; right: 0; transform: translateY(50%); }
        .dash-plot { position: relative; min-width: 0; }
        .dash-grid { position: absolute; inset: 0 0 22px 0; pointer-events: none; }
        .dash-grid span { position: absolute; left: 0; right: 0; border-top: 1px solid var(--color-border); }
        .dash-grid span.base { border-top-color: var(--color-border-input); }
        .dash-cols { position: absolute; inset: 0; display: flex; gap: var(--space-xxs); }
        .dash-col { position: relative; flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; padding: 0; border: 0; border-radius: var(--radius-sm); background: transparent; color: inherit; }
        .dash-col:hover { background: var(--color-surface-hover); }
        .dash-bars { flex: 1 1 auto; display: flex; align-items: flex-end; justify-content: center; gap: 2px; min-height: 0; }
        .dash-bar { width: min(14px, 40%); border-radius: var(--radius-xs) var(--radius-xs) 0 0; background: var(--color-accent); }
        .dash-bar.alt { background: var(--color-border-input); }
        .dash-bar.zero { height: 1px !important; background: var(--color-border); }
        .dash-col small { flex: 0 0 22px; line-height: 22px; text-align: center; font-size: var(--text-micro); color: var(--color-text-muted); white-space: nowrap; }

        /* line chart: SVG lines + HTML hover columns with a crosshair */
        .dash-line-svg { position: absolute; inset: 0 0 22px 0; width: 100%; height: calc(100% - 22px); overflow: visible; pointer-events: none; }
        .dash-line-svg path { fill: none; stroke-width: 2; vector-effect: non-scaling-stroke; stroke-linejoin: round; stroke-linecap: round; }
        .dash-line-svg .now { stroke: var(--color-accent); }
        .dash-line-svg .now-area { fill: var(--color-accent); stroke: none; opacity: .08; }
        .dash-line-svg .prev { stroke: var(--color-border-input); stroke-dasharray: 4 4; }
        .dash-dots { position: absolute; inset: 0 0 22px 0; pointer-events: none; }
        .dash-line-dot { position: absolute; width: 10px; height: 10px; margin: 0 0 -5px -5px; border: 2px solid var(--color-surface); border-radius: 50%; background: var(--color-accent); pointer-events: none; }
        .dash-cols.line { gap: 0; }
        .dash-cols.line .dash-col { border-radius: 0; }
        .dash-cols.line .dash-col:hover { background: transparent; }
        .dash-cols.line .dash-col:hover::before { content: ""; position: absolute; top: 0; bottom: 22px; left: 50%; border-left: 1px solid var(--color-border-input); }
        .dash-xlabels { position: absolute; left: 0; right: 0; bottom: 0; height: 22px; pointer-events: none; }
        .dash-xlabels span { position: absolute; transform: translateX(-50%); line-height: 22px; font-size: var(--text-micro); color: var(--color-text-muted); }

        .dash-tip {
            position: fixed;
            z-index: 1080;
            min-width: 180px;
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
        .dash-pl { display: flex; flex-direction: column; flex: 1 1 auto; justify-content: space-between; margin: 0; padding: 0; list-style: none; }
        .dash-pl li { padding: var(--space-sm) 0; }
        .dash-pl .row-top { display: flex; justify-content: space-between; gap: var(--space-md); font-size: var(--text-body-tabular); }
        .dash-pl .row-top span { color: var(--color-text-secondary); }
        .dash-pl .row-top b { font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }
        .dash-pl .total { margin-top: var(--space-xs); padding-top: var(--space-md); border-top: 1px solid var(--color-border); }
        .dash-pl .total .row-top span { color: var(--color-text); font-weight: var(--font-weight-medium); }
        .dash-pl .total .row-top b { font-size: var(--text-heading-md); font-weight: var(--font-weight-light); }

        .dash-track { height: 6px; margin-top: var(--space-xs); border-radius: var(--radius-pill); background: var(--color-surface-soft); overflow: hidden; }
        .dash-track span { display: block; height: 100%; border-radius: var(--radius-pill); background: var(--color-accent); }
        .dash-track.alt span { background: var(--color-border-input); }
        .dash-track.bad span { background: var(--color-danger); }
        .dash-track.good span { background: var(--color-success); }

        /* ---------- Data tables inside cards ---------- */
        .dash-table-wrap {
            flex: 1 1 var(--dash-list-h);
            min-height: 0;
            height: var(--dash-list-h);
            margin: 0 calc(-1 * var(--space-xl));
            overflow: auto;
            border-top: 1px solid var(--color-border);
            overscroll-behavior: contain;
        }
        .dash-table-wrap.short { --dash-list-h: 196px; }
        .dash-table-wrap.tall { --dash-list-h: 420px; }
        .dash-table-wrap.auto { flex: 0 0 auto; height: auto; max-height: none; }
        .dash-table { width: 100%; margin: 0; }
        .dash-table > thead > tr > th { position: sticky; top: 0; z-index: 1; padding: var(--space-sm) var(--space-md); }
        .dash-table > tbody > tr > td { min-width: 0; padding: var(--space-sm) var(--space-md); vertical-align: middle; border-bottom: 1px solid var(--color-border); }
        .dash-table > tbody > tr:last-child > td { border-bottom: 0; }
        .dash-table th:first-child, .dash-table td:first-child { padding-left: var(--space-xl); }
        .dash-table th:last-child, .dash-table td:last-child { padding-right: var(--space-xl); }
        .dash-table .num { text-align: right; white-space: nowrap; font-feature-settings: var(--font-feature-numeric); }
        .dash-table .num b { font-weight: var(--font-weight-medium); }
        .dash-table .fit { width: 1%; white-space: nowrap; }
        .dash-table .grow { width: 100%; max-width: 0; }
        .dash-table .dash-act { width: 34%; max-width: 0; }
        .dash-table .dash-who { max-width: 0; }
        .dash-ell { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dash-sub { display: block; font-size: var(--text-micro); color: var(--color-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; letter-spacing: normal; }
        .dash-table a.dash-row-link { color: var(--color-text); text-decoration: none; }
        .dash-table a.dash-row-link:hover { color: var(--color-link); text-decoration: underline; }
        .dash-table .dash-ico { flex-basis: 28px; width: 28px; height: 28px; font-size: var(--text-body-tabular); }
        .dash-cell-flex { display: flex; align-items: center; gap: var(--space-sm); min-width: 0; }
        .dash-cell-flex > div { min-width: 0; }

        .dash-branch {
            display: inline-block;
            max-width: 100%;
            margin-left: var(--space-xs);
            padding: 0 var(--space-xs);
            border-radius: var(--radius-xs);
            background: var(--color-surface-soft);
            border: 1px solid var(--color-border);
            color: var(--color-text-secondary);
            font-size: var(--text-micro-cap);
            line-height: 16px;
            vertical-align: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dash-sub .dash-branch { margin-left: 0; }

        .dash-status { display: inline-flex; align-items: center; gap: var(--space-xs); padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-size: var(--text-micro); line-height: 20px; font-weight: var(--font-weight-medium); white-space: nowrap; }
        .dash-status.ok { background: var(--color-success-soft); color: var(--color-success); }
        .dash-status.warn { background: var(--color-warning-soft); color: var(--color-warning); }
        .dash-status.bad { background: var(--color-danger-soft); color: var(--color-danger); }
        .dash-status.info { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .dash-status.flat { background: var(--color-surface-soft); color: var(--color-text-secondary); }

        .dash-empty { flex: 1 1 auto; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-xs); min-height: 100%; padding: var(--space-xl) var(--space-lg); text-align: center; color: var(--color-text-muted); font-size: var(--text-caption); }
        .dash-empty i { font-size: var(--text-heading-lg); color: var(--color-border-input); }

        /* ---------- Tiles ---------- */
        .dash-tiles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-sm); margin-bottom: var(--space-lg); }
        .dash-tiles.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .dash-tile { display: block; min-width: 0; padding: var(--space-sm) var(--space-md); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text); text-decoration: none; }
        a.dash-tile:hover { border-color: var(--color-accent); color: var(--color-text); }
        a.dash-tile:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
        .dash-tile small { display: block; font-size: var(--text-micro); color: var(--color-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-tile b { display: block; font-size: var(--text-heading-sm); font-weight: var(--font-weight-light); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-tile span { display: block; font-size: var(--text-micro); color: var(--color-text-secondary); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ---------- Today ---------- */
        .dash-big { font-size: var(--text-display-lg); font-weight: var(--font-weight-light); letter-spacing: var(--tracking-display-lg); line-height: var(--leading-display-lg); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-mini-title { margin: 0 0 var(--space-sm); font-size: var(--text-micro); font-weight: var(--font-weight-medium); letter-spacing: 0.04em; text-transform: uppercase; color: var(--color-text-muted); }
        .dash-rows { display: flex; flex-direction: column; gap: var(--space-sm); margin: 0; padding: 0; list-style: none; }
        .dash-row-top { display: flex; align-items: baseline; justify-content: space-between; gap: var(--space-md); font-size: var(--text-caption); }
        .dash-row-top > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--color-text-secondary); }
        .dash-row-top b { font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }

        /* ---------- Stacked bars (aging, attendance, payroll) ---------- */
        .dash-stack { display: flex; gap: 2px; height: 10px; border-radius: var(--radius-pill); overflow: hidden; background: var(--color-surface-soft); }
        .dash-stack span { height: 100%; min-width: 0; }
        .dash-stack .s0 { background: color-mix(in srgb, var(--color-accent) 35%, var(--color-surface)); }
        .dash-stack .s1 { background: color-mix(in srgb, var(--color-accent) 60%, var(--color-surface)); }
        .dash-stack .s2 { background: color-mix(in srgb, var(--color-accent) 80%, var(--color-surface)); }
        .dash-stack .s3 { background: var(--color-accent); }
        .dash-stack .ok { background: var(--color-success); }
        .dash-stack .warn { background: var(--color-warning); }
        .dash-stack .bad { background: var(--color-danger); }
        .dash-stack .info { background: var(--color-accent); }
        .dash-stack .flat { background: var(--color-border-input); }
        .dash-key { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--space-sm); margin: var(--space-sm) 0 var(--space-lg); }
        .dash-key div { min-width: 0; }
        .dash-key small { display: flex; align-items: center; gap: var(--space-xs); font-size: var(--text-micro); color: var(--color-text-muted); white-space: nowrap; overflow: hidden; }
        .dash-key b { display: block; font-size: var(--text-caption); font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dash-key.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .dash-key .dash-swatch { flex-basis: 8px; width: 8px; height: 8px; }
        .dash-key .s0 { background: color-mix(in srgb, var(--color-accent) 35%, var(--color-surface)); }
        .dash-key .s1 { background: color-mix(in srgb, var(--color-accent) 60%, var(--color-surface)); }
        .dash-key .s2 { background: color-mix(in srgb, var(--color-accent) 80%, var(--color-surface)); }
        .dash-key .s3 { background: var(--color-accent); }

        .dash-date { display: grid; place-items: center; flex: 0 0 38px; width: 38px; height: 38px; border-radius: var(--radius-md); background: var(--color-accent-soft); color: var(--color-accent-ink); line-height: 1; }
        .dash-date b { font-size: var(--text-body-md); font-weight: var(--font-weight-medium); }
        .dash-date small { font-size: var(--text-micro-cap); text-transform: uppercase; }

        .dash-closing { display: flex; align-items: center; justify-content: space-between; gap: var(--space-md); margin-top: var(--space-md); padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); background: var(--color-surface-soft); font-size: var(--text-caption); color: var(--color-text-secondary); }
        .dash-closing > span { min-width: 0; }

        @media (max-width: 767.98px) {
            .dash { --dash-gap: var(--space-lg); --dash-list-h: 264px; }
            .dash-section { margin-top: var(--space-xl); }
            .dash-hero { padding: var(--space-lg); }
            .dash-hero-stats { grid-template-columns: 1fr; gap: var(--space-md); }
            .dash-hero-stat b { font-size: var(--text-display-md); }
            .dash-periods { width: 100%; }
            .dash-periods a { flex: 1 1 0; justify-content: center; padding: 0 var(--space-xs); }
            .dash-actions { flex-wrap: nowrap; overflow-x: auto; margin-left: calc(-1 * var(--space-lg)); margin-right: calc(-1 * var(--space-lg)); padding-left: var(--space-lg); padding-right: var(--space-lg); scrollbar-width: none; }
            .dash-card { padding: var(--space-lg); }
            .dash-card-head { flex-wrap: wrap; }
            .dash-table-wrap { margin: 0 calc(-1 * var(--space-lg)); }
            .dash-table th:first-child, .dash-table td:first-child { padding-left: var(--space-lg); }
            .dash-table th:last-child, .dash-table td:last-child { padding-right: var(--space-lg); }
            .dash-kpis { gap: var(--space-sm); }
            .dash-kpi { padding: var(--space-md); }
            .dash-kpi-value { font-size: var(--text-heading-sm); }
            .dash-chart { height: 220px; }
            .dash-axis { width: 34px; }
            .dash-col small { font-size: var(--text-micro-cap); }
            .dash-hide-sm { display: none; }
            .dash-table .dash-act { width: 100%; }
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
                $period4 = strtolower($periodLabel);
                $showSalesKpis = $can['sales'] || $can['profit'];
            @endphp

            {{-- ================= Hero ================= --}}
            <section class="dash-hero" aria-label="Overview">
                <div class="dash-hero-top">
                    <div>
                        <h1>{{ $greeting }}, {{ $firstName }}</h1>
                        <div class="dash-hero-sub">
                            {{ now()->format('l, d F Y') }} · {{ $periodLabel }}
                            ({{ $from->format('d M') }}{{ $from->isSameDay($to) ? '' : ' – ' . $to->format('d M') }})
                            @if (!empty($multi)) · {{ count(\App\Http\Controllers\Admin\BranchController::visibleIds()) }} branches combined @endif
                        </div>
                    </div>
                    <nav class="dash-periods" aria-label="Period">
                        @foreach (\App\Http\Controllers\Admin\DashboardController::PERIODS as $key => $label)
                            <a href="{{ route('dashboard', ['period' => $key]) }}" class="{{ $period === $key ? 'active' : '' }}"
                                @if ($period === $key) aria-current="page" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>

                @if ($can['sales'] || $can['profit'] || $can['cash'])
                    <div class="dash-hero-stats">
                        @if ($can['sales'])
                            <div class="dash-hero-stat">
                                <small>Sales</small>
                                <b title="{{ $money($k['sales'], 2) }}">{{ $money($k['sales']) }}</b>
                                <div class="meta">{!! $delta($k['change']['sales']) !!} {{ $vs }} · {{ $k['orders'] }} {{ \Illuminate\Support\Str::plural('invoice', $k['orders']) }}</div>
                            </div>
                        @endif
                        @if ($can['profit'])
                            <div class="dash-hero-stat">
                                <small>Net profit</small>
                                <b class="{{ $k['net'] < 0 ? 'dash-neg' : '' }}" title="{{ $money($k['net'], 2) }}">{{ $money($k['net']) }}</b>
                                <div class="meta">{!! $delta($k['change']['net']) !!} {{ $vs }} · gross {{ $money($k['profit']) }}</div>
                            </div>
                        @endif
                        @if ($can['cash'])
                            <div class="dash-hero-stat">
                                <small>Cash &amp; bank on hand</small>
                                <b class="{{ $cash['total'] < 0 ? 'dash-neg' : '' }}" title="{{ $money($cash['total'], 2) }}">{{ $money($cash['total']) }}</b>
                                <div class="meta">{{ count($cash['accounts']) }} {{ \Illuminate\Support\Str::plural('account', count($cash['accounts'])) }} in {{ count($cash['methods']) }} payment {{ \Illuminate\Support\Str::plural('method', count($cash['methods'])) }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                <nav class="dash-actions" aria-label="Quick actions">
                    @perm('pos.view')<a class="dash-action primary" href="{{ route('sales.pos') }}"><i class="bi bi-shop" aria-hidden="true"></i> Open POS</a>@endperm
                    @perm('purchases.create')<a class="dash-action" href="{{ route('purchases.create') }}"><i class="bi bi-bag-plus" aria-hidden="true"></i> New purchase</a>@endperm
                    @perm('quotations.create')<a class="dash-action" href="{{ route('admin.quotations.create') }}"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> New quotation</a>@endperm
                    @perm('payment_receive.create')<a class="dash-action" href="{{ route('admin.payment.index', 'Payment Receive') }}"><i class="bi bi-cash-coin" aria-hidden="true"></i> Receive payment</a>@endperm
                    @perm('expenses.create')<a class="dash-action" href="{{ route('admin.expense.index') }}"><i class="bi bi-receipt" aria-hidden="true"></i> Add expense</a>@endperm
                    @perm('cash_closing.view')<a class="dash-action" href="{{ route('admin.business-reports.show', 'cash-closing') }}"><i class="bi bi-safe2" aria-hidden="true"></i> Cash closing</a>@endperm
                    @perm('payroll.view')<a class="dash-action" href="{{ route('admin.reports.salary-sheet') }}"><i class="bi bi-people" aria-hidden="true"></i> Salary sheet</a>@endperm
                    @perm('reports.view')<a class="dash-action" href="{{ route('admin.business-reports.index') }}"><i class="bi bi-bar-chart-line" aria-hidden="true"></i> Reports</a>@endperm
                </nav>
            </section>

            {{-- ================= KPI tiles ================= --}}
            @php
                $tiles = [];
                if ($can['profit']) {
                    $tiles[] = ['tone' => 'success', 'icon' => 'bi-pie-chart', 'label' => 'Gross profit', 'value' => $k['profit'], 'url' => null,
                        'meta' => $delta($k['change']['profit']) . ' ' . e($k['margin'] === null ? 'no sales yet' : number_format($k['margin'], 1) . '% margin'),
                        'foot' => '<div class="dash-track good" title="Margin"><span style="width: ' . max(0, min(100, $k['margin'] ?? 0)) . '%"></span></div>'];
                }
                if ($can['collected']) {
                    $tiles[] = ['tone' => 'accent', 'icon' => 'bi-wallet2', 'label' => 'Collected', 'value' => $k['collected'], 'url' => route('admin.payment.index', 'Payment Receive'),
                        'meta' => $delta($k['change']['collected']) . ' ' . e($vs), 'foot' => $spark($series('collected'))];
                }
                if ($can['expenses']) {
                    $tiles[] = ['tone' => 'danger', 'icon' => 'bi-receipt-cutoff', 'label' => 'Expenses', 'value' => $k['expenses'], 'url' => \App\Http\Controllers\Admin\PermissionController::allows('expenses.view') ? route('admin.expense.index') : null,
                        'meta' => $delta($k['change']['expenses'], false) . ' incl. bills &amp; salary', 'foot' => $spark($series('expenses'))];
                }
                if ($can['purchases']) {
                    $tiles[] = ['tone' => 'warning', 'icon' => 'bi-bag', 'label' => 'Purchases', 'value' => $k['purchases'], 'url' => route('purchases.index'),
                        'meta' => $delta($k['change']['purchases'], null) . ' ' . e($vs), 'foot' => $spark($series('purchases'))];
                }
                if ($can['receivable']) {
                    $r = $dues['receivable'];
                    $tiles[] = ['tone' => 'accent', 'icon' => 'bi-person-down', 'label' => 'Receivable', 'value' => $r['total'], 'url' => '#money',
                        'meta' => e($r['count'] . ' ' . \Illuminate\Support\Str::plural('customer', $r['count']) . ' owe you'),
                        'foot' => '<div class="dash-track" title="Over 30 days old"><span style="width: ' . $pct($r['total'] - $r['aging']['b0'], $r['total']) . '%"></span></div>'];
                }
                if ($can['payable']) {
                    $p = $dues['payable'];
                    $tiles[] = ['tone' => 'warning', 'icon' => 'bi-person-up', 'label' => 'Payable', 'value' => $p['total'], 'url' => '#money',
                        'meta' => e('to ' . $p['count'] . ' ' . \Illuminate\Support\Str::plural('supplier', $p['count'])),
                        'foot' => '<div class="dash-track alt" title="Over 30 days old"><span style="width: ' . $pct($p['total'] - $p['aging']['b0'], $p['total']) . '%"></span></div>'];
                }
                if ($can['stock']) {
                    $tiles[] = ['tone' => 'success', 'icon' => 'bi-boxes', 'label' => 'Stock value', 'value' => $stock['value'], 'url' => '#stock',
                        'meta' => e($qty($stock['units']) . ' units · ' . ($stock['lowCount'] + $stock['outCount']) . ' need attention'),
                        'foot' => '<div class="dash-track bad" title="Low or out of stock"><span style="width: ' . $pct($stock['lowCount'] + $stock['outCount'], $stock['products']) . '%"></span></div>'];
                }
                if ($can['payroll']) {
                    $unpaid = $hr['unpaidMonths'];
                    $tiles[] = ['tone' => $hr['unpaidTotal'] > 0 ? 'danger' : 'accent', 'icon' => 'bi-people', 'label' => 'Salary due', 'value' => $hr['unpaidTotal'],
                        'url' => $unpaid ? $unpaid[0]['url'] : route('admin.reports.salary-sheet'),
                        'meta' => e(count($unpaid) > 1 ? count($unpaid) . ' months unpaid · oldest ' . \Carbon\Carbon::parse($unpaid[0]['month'] . '-01')->format('M Y')
                            : ($unpaid ? $unpaid[0]['label'] . ' · ' . $unpaid[0]['employees'] . ' employee(s)' : now()->format('F') . ': ' . $hr['payrollMonth']['processed'] . ' processed')),
                        'foot' => $hr['payrollMonth']['net'] > 0 ? '<div class="dash-track good" title="This month paid"><span style="width: ' . $pct($hr['payrollMonth']['paid'], $hr['payrollMonth']['net']) . '%"></span></div>' : ''];
                }
            @endphp
            @if ($tiles)
                <section class="dash-kpis" aria-label="Key figures" style="--kpi-cols: {{ count($tiles) <= 4 ? count($tiles) : (int) ceil(count($tiles) / 2) }}">
                    @foreach ($tiles as $t)
                        @php $tag = $t['url'] ? 'a' : 'div'; @endphp
                        <{{ $tag }} class="dash-kpi tone-{{ $t['tone'] }}" @if ($t['url']) href="{{ $t['url'] }}" @endif>
                            <div class="dash-kpi-top"><span class="dash-ico"><i class="bi {{ $t['icon'] }}" aria-hidden="true"></i></span> {{ $t['label'] }}</div>
                            <div class="dash-kpi-value {{ $t['value'] < 0 ? 'dash-neg' : '' }}" title="{{ $money($t['value'], 2) }}">{{ $money($t['value']) }}</div>
                            <div class="dash-kpi-meta">{!! $t['meta'] !!}</div>
                            {!! $t['foot'] !!}
                        </{{ $tag }}>
                    @endforeach
                </section>
            @endif

            {{-- ================= Sales ================= --}}
            @if ($showSalesKpis || $can['pipeline'])
                <section class="dash-section" aria-labelledby="dashSales">
                    <h2 class="dash-section-title" id="dashSales"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Sales</h2>

                    @if ($showSalesKpis)
                        @php
                            $days = $trend['days'];
                            $nDays = count($days);
                            $tMax = $nice(max(array_merge([0], array_filter(array_column($days, 'runThis'), fn ($v) => $v !== null), array_filter(array_column($days, 'runLast'), fn ($v) => $v !== null))));
                            $px = fn ($i) => $nDays > 1 ? round($i / ($nDays - 1) * 1000, 2) : 0;
                            $py = fn ($v) => round(300 - $pct($v, $tMax) * 3, 2);
                            $pathOf = function ($key) use ($days, $px, $py) {
                                $pts = [];
                                foreach ($days as $i => $d) {
                                    if ($d[$key] !== null) {
                                        $pts[] = $px($i) . ',' . $py($d[$key]);
                                    }
                                }
                                return $pts ? 'M' . implode(' L', $pts) : '';
                            };
                            $nowPath = $pathOf('runThis');
                            $lastPath = $pathOf('runLast');
                            $lastIdx = $trend['today'] - 1;
                            $mMax = $nice(max(array_merge([0], $series('sales'), $series('purchases'))));
                            $plMax = max(1, $k['sales'], $k['cost'], $k['expenses'], abs($k['net']));
                            $mtdDelta = $trend['lastToDate'] > 0 ? ($trend['thisTotal'] - $trend['lastToDate']) / $trend['lastToDate'] * 100 : null;
                        @endphp
                        <div class="dash-row {{ $can['profit'] && $can['sales'] ? 'wide' : '' }}">
                            @if ($can['sales'])
                                <article class="dash-card">
                                    <div class="dash-card-head">
                                        <div>
                                            <h3>Sales trend</h3>
                                            <p data-trend-sub="month">{{ $trend['thisLabel'] }} to date {{ $money($trend['thisTotal']) }} {!! $delta($mtdDelta) !!} vs {{ $money($trend['lastToDate']) }} by day {{ $trend['today'] }} of {{ $trend['lastLabel'] }}</p>
                                            <p data-trend-sub="year" hidden>Last 12 months · {{ $money(array_sum($series('sales'))) }} sold @if ($can['purchases'])· {{ $money(array_sum($series('purchases'))) }} bought @endif</p>
                                        </div>
                                        <div class="dash-seg" role="tablist" aria-label="Trend range">
                                            <button type="button" role="tab" aria-selected="true" aria-controls="dashTrendMonth" data-trend="month">This month</button>
                                            <button type="button" role="tab" aria-selected="false" aria-controls="dashTrendYear" data-trend="year">12 months</button>
                                        </div>
                                    </div>

                                    <div id="dashTrendMonth" role="tabpanel" class="dash-card-body">
                                        <div class="dash-legend">
                                            <span><i class="dash-swatch line"></i> {{ $trend['thisLabel'] }} (running total)</span>
                                            <span><i class="dash-swatch line alt"></i> {{ $trend['lastLabel'] }}</span>
                                        </div>
                                        <div class="dash-chart" aria-hidden="true">
                                            <div class="dash-axis">
                                                @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
                                                    <span style="bottom: {{ $f * 100 }}%">{{ $compact($tMax * $f) }}</span>
                                                @endforeach
                                            </div>
                                            <div class="dash-plot">
                                                <div class="dash-grid">
                                                    @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
                                                        <span class="{{ $f == 0 ? 'base' : '' }}" style="bottom: {{ $f * 100 }}%"></span>
                                                    @endforeach
                                                </div>
                                                <svg class="dash-line-svg" viewBox="0 0 1000 300" preserveAspectRatio="none">
                                                    @if ($nowPath)<path class="now-area" d="{{ $nowPath }} L{{ $px($lastIdx) }},300 L0,300 Z" />@endif
                                                    @if ($lastPath)<path class="prev" d="{{ $lastPath }}" />@endif
                                                    @if ($nowPath)<path class="now" d="{{ $nowPath }}" />@endif
                                                </svg>
                                                @if (isset($days[$lastIdx]) && $days[$lastIdx]['runThis'] !== null)
                                                    <div class="dash-dots"><span class="dash-line-dot" style="left: {{ $px($lastIdx) / 10 }}%; bottom: {{ $pct($days[$lastIdx]['runThis'], $tMax) }}%;"></span></div>
                                                @endif
                                                <div class="dash-cols line">
                                                    @foreach ($days as $d)
                                                        <button type="button" class="dash-col" tabindex="-1"
                                                            data-tip="<strong>Day {{ $d['day'] }}</strong>@if ($d['this'] !== null)<div><span><i class='dash-swatch'></i>{{ e($trend['thisLabel']) }}</span><b>{{ e($money($d['this'], 2)) }}</b></div><div><span>to date</span><b>{{ e($money($d['runThis'], 2)) }}</b></div>@endif @if ($d['last'] !== null)<div><span><i class='dash-swatch alt'></i>{{ e($trend['lastLabel']) }}</span><b>{{ e($money($d['last'], 2)) }}</b></div><div><span>to date</span><b>{{ e($money($d['runLast'], 2)) }}</b></div>@endif">
                                                            <span class="dash-bars"></span><small></small>
                                                        </button>
                                                    @endforeach
                                                </div>
                                                <div class="dash-xlabels">
                                                    @foreach (array_unique([1, 8, 15, 22, $nDays]) as $dn)
                                                        <span style="left: {{ $px($dn - 1) / 10 }}%">{{ $dn }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="dashTrendYear" role="tabpanel" class="dash-card-body" hidden>
                                        <div class="dash-legend">
                                            <span><i class="dash-swatch"></i> Sales</span>
                                            @if ($can['purchases'])<span><i class="dash-swatch alt"></i> Purchases</span>@endif
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
                                                            data-tip="<strong>{{ e($m['title']) }}</strong><div><span><i class='dash-swatch'></i>Sales</span><b>{{ e($money($m['sales'], 2)) }}</b></div>@if ($can['purchases'])<div><span><i class='dash-swatch alt'></i>Purchases</span><b>{{ e($money($m['purchases'], 2)) }}</b></div>@endif @if ($can['expenses'])<div><span>Expenses</span><b>{{ e($money($m['expenses'], 2)) }}</b></div>@endif">
                                                            <span class="dash-bars">
                                                                <span class="dash-bar {{ $m['sales'] ? '' : 'zero' }}" style="height: {{ $pct($m['sales'], $mMax) }}%"></span>
                                                                @if ($can['purchases'])<span class="dash-bar alt {{ $m['purchases'] ? '' : 'zero' }}" style="height: {{ $pct($m['purchases'], $mMax) }}%"></span>@endif
                                                            </span>
                                                            <small>{{ $m['label'] }}</small>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <table class="visually-hidden">
                                        <caption>Sales by month</caption>
                                        <thead><tr><th>Month</th><th>Sales</th><th>Purchases</th><th>Expenses</th></tr></thead>
                                        <tbody>
                                            @foreach ($monthly as $m)
                                                <tr><td>{{ $m['title'] }}</td><td>{{ $money($m['sales'], 2) }}</td><td>{{ $money($m['purchases'], 2) }}</td><td>{{ $money($m['expenses'], 2) }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </article>
                            @endif

                            @if ($can['profit'])
                                <article class="dash-card">
                                    <div class="dash-card-head">
                                        <div>
                                            <h3>Profit &amp; loss</h3>
                                            <p>{{ $periodLabel }} · sales without VAT</p>
                                        </div>
                                        @perm('statements.view')<a class="dash-link" href="{{ route('admin.accounting.profit-loss') }}">Statement <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
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
                            @endif
                        </div>
                    @endif

                    @php $salesRow = ($can['sales'] ? 2 : 0) + ($can['pipeline'] ? 1 : 0); @endphp
                    @if ($salesRow)
                        <div class="dash-row c{{ $salesRow }}">
                            @if ($can['sales'])
                                <article class="dash-card">
                                    <div class="dash-card-head">
                                        <div><h3>Today</h3><p>{{ now()->format('l, d M') }}</p></div>
                                        @perm('sales.view')<a class="dash-link" href="{{ route('sales.index') }}">All sales <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
                                    </div>
                                    <div class="dash-card-body">
                                        <div class="dash-big" title="{{ $money($today['sales'], 2) }}">{{ $money($today['sales']) }}</div>
                                        <div class="dash-kpi-meta mt-2 mb-3">{!! $delta($today['change']['sales'] ?? null) !!} vs yesterday · {{ $today['orders'] }} {{ \Illuminate\Support\Str::plural('invoice', $today['orders']) }}</div>
                                        <div class="dash-tiles three">
                                            <div class="dash-tile"><small>Items sold</small><b>{{ $qty($today['items']) }}</b></div>
                                            <div class="dash-tile"><small>Avg. invoice</small><b title="{{ $money($today['avg'], 2) }}">{{ $compact($today['avg']) }}</b></div>
                                            @if ($can['collected'])
                                                <div class="dash-tile"><small>Collected</small><b title="{{ $money($today['collected'], 2) }}">{{ $compact($today['collected']) }}</b></div>
                                            @else
                                                <div class="dash-tile"><small>Returns</small><b>{{ $compact($today['returns']) }}</b></div>
                                            @endif
                                        </div>
                                        <h4 class="dash-mini-title">Sales channels · {{ $period4 }}</h4>
                                        @if ($salesMix)
                                            <ul class="dash-rows">
                                                @foreach (array_slice($salesMix, 0, 4) as $mix)
                                                    <li>
                                                        <div class="dash-row-top"><span>{{ $mix['type'] }} · {{ $mix['count'] }}</span><b>{{ $money($mix['total']) }} <span class="dash-muted">{{ number_format($mix['share'], 0) }}%</span></b></div>
                                                        <div class="dash-track"><span style="width: {{ $mix['share'] }}%"></span></div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <div class="dash-empty"><i class="bi bi-bag" aria-hidden="true"></i>No sales {{ $period4 }}.</div>
                                        @endif
                                    </div>
                                </article>

                                <article class="dash-card">
                                    <div class="dash-card-head">
                                        <div><h3>Top products</h3><p>By revenue · {{ $period4 }}</p></div>
                                        @perm('reports.view')
                                            <a class="dash-link" href="{{ route('admin.business-reports.show', 'product-profit') }}">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                        @else
                                            @perm('products.view')<a class="dash-link" href="{{ route('products.index') }}">Products <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
                                        @endperm
                                    </div>
                                    <div class="dash-table-wrap">
                                        @if ($topProducts)
                                            <table class="table dash-table">
                                                <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Revenue</th></tr></thead>
                                                <tbody>
                                                    @foreach ($topProducts as $p)
                                                        <tr>
                                                            <td class="grow"><span class="dash-ell" title="{{ $p->name }}">{{ $p->name }}</span><span class="dash-sub">{{ $p->sku ?: 'No SKU' }}</span></td>
                                                            <td class="num">{{ $qty($p->qty) }}</td>
                                                            <td class="num"><b title="{{ $money($p->revenue, 2) }}">{{ $money($p->revenue) }}</b>@if ($can['profit'])<span class="dash-sub {{ $p->profit < 0 ? 'dash-neg' : '' }}">{{ $money($p->profit) }} profit</span>@endif</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <div class="dash-empty"><i class="bi bi-box-seam" aria-hidden="true"></i>No products sold {{ $period4 }}.</div>
                                        @endif
                                    </div>
                                    <div class="dash-card-foot">
                                        <span>{{ $k['orders'] }} {{ \Illuminate\Support\Str::plural('invoice', $k['orders']) }} · avg {{ $money($k['avg']) }}</span>
                                        <b>{{ $money($k['sales']) }}</b>
                                    </div>
                                </article>
                            @endif

                            @if ($can['pipeline'])
                                <article class="dash-card">
                                    <div class="dash-card-head">
                                        <div><h3>Orders &amp; quotations</h3><p>Open, nearest delivery first</p></div>
                                        @perm('sale_orders.view')<a class="dash-link" href="{{ route('sales.orders.index') }}">All orders <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
                                    </div>
                                    <div class="dash-tiles">
                                        @perm('quotations.view')
                                            <a class="dash-tile" href="{{ route('admin.quotations.index') }}"><small>Open quotations</small><b>{{ $pipeline['quoteOpen'] }}</b><span>{{ $money($pipeline['quoteOpenValue']) }}@if ($pipeline['quoteExpiring']) · {{ $pipeline['quoteExpiring'] }} expiring @endif</span></a>
                                        @else
                                            <div class="dash-tile"><small>Open quotations</small><b>{{ $pipeline['quoteOpen'] }}</b><span>{{ $money($pipeline['quoteOpenValue']) }}</span></div>
                                        @endperm
                                        <div class="dash-tile"><small>Open orders</small><b>{{ $pipeline['orderOpen'] }}</b><span class="{{ $pipeline['orderLate'] ? 'dash-neg' : '' }}">{{ $pipeline['orderLate'] ? $pipeline['orderLate'] . ' due / late' : $money($pipeline['orderDue']) . ' unpaid' }}</span></div>
                                    </div>
                                    <div class="dash-table-wrap short">
                                        @if ($pipeline['orders'])
                                            <table class="table dash-table">
                                                <thead><tr><th>Order</th><th class="num">Delivery</th><th class="num">Amount</th></tr></thead>
                                                <tbody>
                                                    @foreach ($pipeline['orders'] as $o)
                                                        @php $late = $o->delivery_date && $o->delivery_date <= now()->toDateString(); @endphp
                                                        <tr>
                                                            <td class="grow"><span class="dash-ell" title="{{ $o->customer ?: 'Walk-In Customer' }}">{{ $o->customer ?: 'Walk-In Customer' }}</span><span class="dash-sub">{{ $o->order_no }} · {{ $o->order_status }} {!! $branchTag($o->branch_id) !!}</span></td>
                                                            <td class="num">@if ($o->delivery_date)<span class="dash-status {{ $late ? 'bad' : 'flat' }}">{{ \Carbon\Carbon::parse($o->delivery_date)->format('d M') }}</span>@else<span class="dash-muted">—</span>@endif</td>
                                                            <td class="num"><b>{{ $money($o->grand_total) }}</b></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <div class="dash-empty"><i class="bi bi-truck" aria-hidden="true"></i>No open sale orders.</div>
                                        @endif
                                    </div>
                                    <div class="dash-card-foot">
                                        <span>{{ $pipeline['quoteAccepted'] }} of {{ $pipeline['quoteTotal'] }} quotations accepted</span>
                                        <b title="Open orders value">{{ $money($pipeline['orderValue']) }}</b>
                                    </div>
                                </article>
                            @endif
                        </div>
                    @endif
                </section>
            @endif

            {{-- ================= Money ================= --}}
            @php $moneyRow = ($can['cash'] ? 1 : 0) + ($can['receivable'] ? 1 : 0) + ($can['payable'] ? 1 : 0); @endphp
            @if ($moneyRow || $activity)
                <section class="dash-section" id="money" aria-labelledby="dashMoney">
                    <h2 class="dash-section-title" id="dashMoney"><i class="bi bi-cash-stack" aria-hidden="true"></i> Money</h2>

                    @if ($moneyRow)
                        <div class="dash-row c{{ $moneyRow }}">
                            @if ($can['cash'])
                                <article class="dash-card">
                                    <div class="dash-card-head">
                                        <div><h3>Cash &amp; bank</h3><p>Balance by account, now</p></div>
                                        @perm('reports.view')<a class="dash-link" href="{{ route('admin.reports.liquid-asset') }}">Cash book <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
                                    </div>
                                    <div class="dash-table-wrap">
                                        @if ($cash['accounts'])
                                            <table class="table dash-table">
                                                <thead><tr><th>Account</th><th class="num">Balance</th></tr></thead>
                                                <tbody>
                                                    @foreach ($cash['accounts'] as $a)
                                                        <tr>
                                                            <td class="grow"><span class="dash-ell" title="{{ $a['name'] }}">{{ $a['name'] }}</span><span class="dash-sub">{{ $a['method'] }}</span></td>
                                                            <td class="num"><b class="{{ $a['balance'] < 0 ? 'dash-neg' : '' }}" title="{{ $money($a['balance'], 2) }}">{{ $money($a['balance']) }}</b></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <div class="dash-empty"><i class="bi bi-bank" aria-hidden="true"></i>No payment methods yet. Add them under Chart of Accounts › Liquid Asset.</div>
                                        @endif
                                    </div>
                                    <div class="dash-card-foot">
                                        <span>Total on hand</span>
                                        <b class="{{ $cash['total'] < 0 ? 'dash-neg' : '' }}" title="{{ $money($cash['total'], 2) }}">{{ $money($cash['total']) }}</b>
                                    </div>
                                    @if ($closing)
                                        <div class="dash-closing">
                                            <span>
                                                @if ($closing['saved'] >= $closing['branches'])
                                                    <span class="dash-status ok"><i class="bi bi-check2-circle" aria-hidden="true"></i> Closed</span>
                                                    Today {{ $money($closing['amount']) }}@if ($closing['at']) · {{ \Carbon\Carbon::parse($closing['at'])->format('h:i A') }}@endif
                                                @elseif ($closing['saved'])
                                                    <span class="dash-status warn"><i class="bi bi-hourglass-split" aria-hidden="true"></i> {{ $closing['saved'] }}/{{ $closing['branches'] }}</span> branches closed today
                                                @else
                                                    <span class="dash-status flat"><i class="bi bi-safe2" aria-hidden="true"></i> Open</span> Not closed today
                                                @endif
                                            </span>
                                            <a class="dash-link" href="{{ route('admin.business-reports.show', 'cash-closing') }}">Z-report</a>
                                        </div>
                                    @endif
                                </article>
                            @endif

                            @foreach (['receivable' => ['Receivables', 'receivable-aging', 'customer', 'Payment Receive', 'Collect', 'bi-emoji-smile', 'No customer dues.'],
                                       'payable' => ['Payables', 'payable-aging', 'supplier', 'Payment', 'Pay', 'bi-check2-circle', 'Nothing owed to suppliers.']] as $side => [$title, $report, $ledger, $payType, $payLabel, $emptyIcon, $emptyText])
                                @continue(!$can[$side])
                                @php $d = $dues[$side]; @endphp
                                <article class="dash-card">
                                    <div class="dash-card-head">
                                        <div><h3>{{ $title }}</h3><p>{{ $side === 'receivable' ? 'Customers who owe you' : 'Suppliers you owe' }} · {{ $d['count'] }}</p></div>
                                        @perm('reports.view')<a class="dash-link" href="{{ route('admin.business-reports.show', $report) }}">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
                                    </div>
                                    <div class="dash-stack" role="img" aria-label="{{ collect(\App\Http\Controllers\Admin\DashboardController::AGING)->map(fn ($l, $b) => $l . ' ' . $money($d['aging'][$b]))->implode(', ') }}">
                                        @foreach (array_keys(\App\Http\Controllers\Admin\DashboardController::AGING) as $i => $b)
                                            @if ($d['aging'][$b] > 0)<span class="s{{ $i }}" style="width: {{ $pct($d['aging'][$b], $d['total']) }}%"></span>@endif
                                        @endforeach
                                    </div>
                                    <div class="dash-key">
                                        @foreach (\App\Http\Controllers\Admin\DashboardController::AGING as $b => $label)
                                            <div><small><i class="dash-swatch s{{ $loop->index }}"></i>{{ str_replace(' days', 'd', $label) }}</small><b title="{{ $money($d['aging'][$b], 2) }}">{{ $compact($d['aging'][$b]) }}</b></div>
                                        @endforeach
                                    </div>
                                    <div class="dash-table-wrap">
                                        @if ($d['top'])
                                            <table class="table dash-table">
                                                <thead><tr><th>{{ ucfirst($ledger) }}</th><th class="num">Due</th></tr></thead>
                                                <tbody>
                                                    @foreach ($d['top'] as $c)
                                                        <tr>
                                                            <td class="grow"><span class="dash-ell" title="{{ $c->name }}">{{ $c->name }}</span><span class="dash-sub">{{ $c->phone ?: $c->type }}</span></td>
                                                            <td class="num"><b title="{{ $money($c->due, 2) }}">{{ $money($c->due) }}</b>@if ($c->aging['b90'] > 0.004)<span class="dash-sub dash-neg">{{ $compact($c->aging['b90']) }} over 90d</span>@endif</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <div class="dash-empty"><i class="bi {{ $emptyIcon }}" aria-hidden="true"></i>{{ $emptyText }}</div>
                                        @endif
                                    </div>
                                    <div class="dash-card-foot">
                                        @perm($side === 'receivable' ? 'payment_receive.create' : 'payments.create')
                                            <a class="dash-link" href="{{ route('admin.payment.index', $payType) }}">{{ $payLabel }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                        @else
                                            <span>Total {{ $side }}</span>
                                        @endperm
                                        <b title="{{ $money($d['total'], 2) }}">{{ $money($d['total']) }}</b>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @if ($activity)
                        <div class="dash-row">
                            <article class="dash-card">
                                <div class="dash-card-head">
                                    <div><h3>Recent activity</h3><p>Latest sales, purchases, receipts, payments, expenses, salary and loans</p></div>
                                    @perm('reports.view')<a class="dash-link" href="{{ route('admin.reports.transaction-ledger') }}">Transaction ledger <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
                                </div>
                                <div class="dash-table-wrap auto" style="border-bottom: 0;">
                                    <table class="table dash-table">
                                        <thead>
                                            <tr><th>Activity</th><th class="dash-hide-sm">Party / details</th>@if (!empty($multi))<th class="dash-hide-sm">Branch</th>@endif<th class="num dash-hide-sm">When</th><th class="num">Amount</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($activity as $a)
                                                <tr>
                                                    <td class="dash-act">
                                                        <div class="dash-cell-flex">
                                                            <span class="dash-ico tone-{{ $a['tone'] }}"><i class="bi {{ $a['icon'] }}" aria-hidden="true"></i></span>
                                                            <div><span class="dash-ell">{{ $a['title'] }}</span><span class="dash-sub">{{ $a['ref'] }}<span class="d-md-none"> · {{ $a['who'] }}</span></span></div>
                                                        </div>
                                                    </td>
                                                    <td class="dash-who dash-hide-sm"><span class="dash-ell" title="{{ $a['who'] }}">{{ $a['who'] }}</span></td>
                                                    @if (!empty($multi))<td class="fit dash-hide-sm">{!! $branchTag($a['branch_id']) !!}</td>@endif
                                                    <td class="num dash-hide-sm dash-muted" title="{{ $a['when']->format('d M Y h:i A') }}">{{ $a['when']->diffForHumans() }}</td>
                                                    <td class="num"><b class="{{ $a['in'] ? 'text-success' : '' }}">{{ $a['in'] ? '+' : '−' }}{{ $money($a['amount'], 2) }}</b></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        </div>
                    @endif
                </section>
            @endif

            {{-- ================= Stock ================= --}}
            @if ($can['stock'])
                <section class="dash-section" id="stock" aria-labelledby="dashStock">
                    <h2 class="dash-section-title" id="dashStock"><i class="bi bi-boxes" aria-hidden="true"></i> Stock</h2>
                    <div class="dash-row c2">
                        <article class="dash-card">
                            <div class="dash-card-head">
                                <div><h3>Reorder list</h3><p>{{ $stock['lowCount'] }} low · {{ $stock['outCount'] }} out of {{ number_format($stock['products']) }} products</p></div>
                                @perm('reports.view')
                                    <a class="dash-link" href="{{ route('admin.business-reports.show', 'reorder') }}">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                @else
                                    @perm('products.view')<a class="dash-link" href="{{ route('products.index') }}">Products <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
                                @endperm
                            </div>
                            <div class="dash-table-wrap">
                                @if ($stock['alerts'])
                                    <table class="table dash-table">
                                        <thead><tr><th>Product</th><th class="num">On hand</th><th class="num">Alert at</th><th class="num">Status</th></tr></thead>
                                        <tbody>
                                            @foreach ($stock['alerts'] as $p)
                                                <tr>
                                                    <td class="grow"><span class="dash-ell" title="{{ $p->name }}">{{ $p->name }}</span><span class="dash-sub">{{ $p->sku ?: 'No SKU' }}</span></td>
                                                    <td class="num">{{ $qty(max(0, $p->stock)) }}</td>
                                                    <td class="num dash-muted">{{ $qty($p->stock_alert) }}</td>
                                                    <td class="num">
                                                        @if ($p->stock <= 0)
                                                            <span class="dash-status bad"><i class="bi bi-x-circle" aria-hidden="true"></i> Out</span>
                                                        @else
                                                            <span class="dash-status warn"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Low</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div class="dash-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i>Every product is above its alert level.</div>
                                @endif
                            </div>
                            <div class="dash-card-foot">
                                <span>{{ $qty($stock['units']) }} units in stock, at cost</span>
                                <b title="{{ $money($stock['value'], 2) }}">{{ $money($stock['value']) }}</b>
                            </div>
                        </article>

                        <article class="dash-card">
                            <div class="dash-card-head">
                                <div><h3>Expiring batches</h3><p>Expired or expiring in the next 30 days, still in stock</p></div>
                                @perm('stock.view')<a class="dash-link" href="{{ route('admin.stock.batches') }}">All batches <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endperm
                            </div>
                            <div class="dash-table-wrap">
                                @if ($expiring['rows'])
                                    <table class="table dash-table">
                                        <thead><tr><th>Product / batch</th><th class="num">Qty</th><th class="num">Expiry</th></tr></thead>
                                        <tbody>
                                            @foreach ($expiring['rows'] as $b)
                                                <tr>
                                                    <td class="grow"><span class="dash-ell" title="{{ $b->name }}">{{ $b->name }}</span><span class="dash-sub">Batch {{ $b->batch_no ?: '—' }} {!! $branchTag($b->branch_id) !!}</span></td>
                                                    <td class="num">{{ $qty($b->qty) }}</td>
                                                    <td class="num">
                                                        <span class="dash-status {{ $b->days < 0 ? 'bad' : ($b->days <= 7 ? 'warn' : 'flat') }}" title="{{ \Carbon\Carbon::parse($b->expiry_date)->format('d M Y') }}">
                                                            {{ $b->days < 0 ? 'Expired ' . abs($b->days) . 'd' : ($b->days === 0 ? 'Today' : 'in ' . $b->days . 'd') }}
                                                        </span>
                                                        <span class="dash-sub">{{ \Carbon\Carbon::parse($b->expiry_date)->format('d M Y') }}</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <div class="dash-empty"><i class="bi bi-calendar-check" aria-hidden="true"></i>No batch expires in the next 30 days.</div>
                                @endif
                            </div>
                            <div class="dash-card-foot">
                                <span>{{ $expiring['expired'] }} expired · {{ $expiring['soon'] }} expiring</span>
                                <b title="Stock value of these batches, at cost">{{ $money($expiring['value']) }}</b>
                            </div>
                        </article>
                    </div>
                </section>
            @endif

            {{-- ================= People ================= --}}
            @php $peopleRow = ($attendance ? 1 : 0) + ($can['payroll'] ? 1 : 0) + (($can['leave'] || $can['holidays']) ? 1 : 0); @endphp
            @if ($peopleRow)
                <section class="dash-section" aria-labelledby="dashPeople">
                    <h2 class="dash-section-title" id="dashPeople"><i class="bi bi-people" aria-hidden="true"></i> People</h2>
                    <div class="dash-row c{{ $peopleRow }}">
                        @if ($attendance)
                            @php
                                $att = $attendance;
                                $attRows = [['Present', 'present', 'ok', 'bi-check2-circle'], ['Late', 'late', 'warn', 'bi-clock-history'], ['Absent', 'absent', 'bad', 'bi-x-circle'],
                                    ['On leave', 'leave', 'info', 'bi-calendar2-minus'], ['Off / holiday', 'off', 'flat', 'bi-moon'], ['Not marked', 'unmarked', 'flat', 'bi-dash-circle']];
                            @endphp
                            <article class="dash-card">
                                <div class="dash-card-head">
                                    <div><h3>Attendance today</h3><p>{{ $att['marked'] }} of {{ $att['total'] }} employees marked</p></div>
                                    <a class="dash-link" href="{{ route('admin.attendance.daily') }}">Attendance <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                </div>
                                <div class="dash-stack" role="img" aria-label="{{ collect($attRows)->map(fn ($r) => $r[0] . ' ' . $att[$r[1]])->implode(', ') }}">
                                    @foreach ($attRows as [$label, $key, $tone])
                                        @if ($att[$key] > 0)<span class="{{ $key === 'unmarked' ? '' : $tone }}" style="width: {{ $pct($att[$key], $att['total']) }}%" title="{{ $label }}"></span>@endif
                                    @endforeach
                                </div>
                                <div class="dash-table-wrap auto mt-3">
                                    <table class="table dash-table">
                                        <thead><tr><th>Status</th><th class="num">Employees</th><th class="num">Share</th></tr></thead>
                                        <tbody>
                                            @foreach ($attRows as [$label, $key, $tone, $icon])
                                                <tr>
                                                    <td class="grow"><span class="dash-status {{ $tone }}"><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}</span></td>
                                                    <td class="num"><b>{{ $att[$key] }}</b></td>
                                                    <td class="num dash-muted">{{ $att['total'] ? number_format($att[$key] / $att['total'] * 100) : 0 }}%</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="dash-card-foot">
                                    <span>Active employees</span>
                                    <b>{{ $hr['employees'] }}</b>
                                </div>
                            </article>
                        @endif

                        @if ($can['payroll'])
                            @php $pm = $hr['payrollMonth']; @endphp
                            <article class="dash-card">
                                <div class="dash-card-head">
                                    <div><h3>Payroll</h3><p>{{ now()->format('F Y') }}: {{ $pm['processed'] }} {{ \Illuminate\Support\Str::plural('employee', $pm['processed']) }} processed</p></div>
                                    <a class="dash-link" href="{{ route('admin.reports.salary-sheet') }}">Salary sheet <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                </div>
                                <div class="dash-stack" role="img" aria-label="Paid {{ $money($pm['paid']) }}, due {{ $money($pm['due']) }}">
                                    @if ($pm['net'] > 0)
                                        <span class="ok" style="width: {{ $pct($pm['paid'], $pm['net']) }}%"></span>
                                        <span class="warn" style="width: {{ $pct($pm['due'], $pm['net']) }}%"></span>
                                    @endif
                                </div>
                                <div class="dash-key three">
                                    <div><small>Net salary</small><b title="{{ $money($pm['net'], 2) }}">{{ $compact($pm['net']) }}</b></div>
                                    <div><small><i class="bi bi-check2-circle text-success" aria-hidden="true"></i> Paid</small><b>{{ $compact($pm['paid']) }}</b></div>
                                    <div><small><i class="bi bi-hourglass-split text-warning" aria-hidden="true"></i> Due</small><b>{{ $compact($pm['due']) }}</b></div>
                                </div>
                                <div class="dash-table-wrap short">
                                    @if ($hr['unpaidMonths'])
                                        <table class="table dash-table">
                                            <thead><tr><th>Unpaid month</th><th class="num">Due</th><th class="num fit"></th></tr></thead>
                                            <tbody>
                                                @foreach ($hr['unpaidMonths'] as $u)
                                                    <tr>
                                                        <td class="grow"><span class="dash-ell">{{ $u['label'] }}</span><span class="dash-sub">{{ $u['employees'] }} {{ \Illuminate\Support\Str::plural('employee', $u['employees']) }} not fully paid</span></td>
                                                        <td class="num"><b title="{{ $money($u['due'], 2) }}">{{ $money($u['due']) }}</b></td>
                                                        <td class="num fit">@perm('payroll.create')<a class="dash-link" href="{{ $u['url'] }}" title="Pay {{ $u['label'] }} salary">Pay</a>@endperm</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <div class="dash-empty"><i class="bi bi-check2-all" aria-hidden="true"></i>Every processed month is fully paid.</div>
                                    @endif
                                </div>
                                <div class="dash-card-foot">
                                    @if ($hr['loans'])
                                        <a class="dash-link" href="{{ route('admin.employee-loans.index') }}">{{ $hr['loans']['count'] }} running {{ \Illuminate\Support\Str::plural('loan', $hr['loans']['count']) }} · {{ $money($hr['loans']['balance']) }}</a>
                                    @else
                                        <span>Unpaid salary</span>
                                    @endif
                                    <b class="{{ $hr['unpaidTotal'] > 0 ? 'dash-neg' : '' }}" title="Unpaid salary, all months">{{ $money($hr['unpaidTotal']) }}</b>
                                </div>
                            </article>
                        @endif

                        @if ($can['leave'] || $can['holidays'])
                            <article class="dash-card">
                                <div class="dash-card-head">
                                    <div><h3>Leave &amp; holidays</h3><p>Away today and coming up</p></div>
                                    @if ($can['holidays'])<a class="dash-link" href="{{ route('admin.leave.calendar.index') }}">Calendar <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endif
                                </div>
                                @if ($can['leave'])
                                    <div class="dash-tiles">
                                        <a class="dash-tile" href="{{ route('admin.leave.index') }}"><small>On leave today</small><b>{{ $hr['onLeave'] }}</b></a>
                                        <a class="dash-tile" href="{{ route('admin.leave.index') }}"><small>Requests waiting</small><b class="{{ $hr['pending'] ? 'text-warning' : '' }}">{{ $hr['pending'] }}</b></a>
                                    </div>
                                @endif
                                <div class="dash-table-wrap {{ $can['leave'] ? 'short' : '' }}">
                                    @if ($hr['holidays'])
                                        <table class="table dash-table">
                                            <thead><tr><th>Upcoming holiday</th><th class="num">Days</th></tr></thead>
                                            <tbody>
                                                @foreach ($hr['holidays'] as $h)
                                                    @php $hf = \Carbon\Carbon::parse($h->from_date); $ht = \Carbon\Carbon::parse($h->to_date); @endphp
                                                    <tr>
                                                        <td class="grow">
                                                            <div class="dash-cell-flex">
                                                                <span class="dash-date"><b>{{ $hf->format('d') }}</b><small>{{ $hf->format('M') }}</small></span>
                                                                <div><span class="dash-ell" title="{{ $h->title }}">{{ $h->title }}</span><span class="dash-sub">{{ $h->type ?: 'Holiday' }} · {{ $hf->isSameDay($ht) ? $hf->format('l') : $hf->format('d M') . ' – ' . $ht->format('d M') }}</span></div>
                                                            </div>
                                                        </td>
                                                        <td class="num">{{ $hf->diffInDays($ht) + 1 }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <div class="dash-empty"><i class="bi bi-calendar3" aria-hidden="true"></i>No upcoming holidays.</div>
                                    @endif
                                </div>
                                <div class="dash-card-foot">
                                    <span>Next holiday</span>
                                    <b>{{ $hr['holidays'] ? \Carbon\Carbon::parse($hr['holidays'][0]->from_date)->format('d M') : '—' }}</b>
                                </div>
                            </article>
                        @endif
                    </div>
                </section>
            @endif
        @endif
    </div>

    <div class="dash-tip" id="dashTip" role="tooltip"></div>
@endsection

@push('scripts')
    <script>
        (function () {
            // Trend card: This month / 12 months.
            document.querySelectorAll('[data-trend]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var which = btn.getAttribute('data-trend');
                    document.querySelectorAll('[data-trend]').forEach(function (b) { b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
                    document.getElementById('dashTrendMonth').hidden = which !== 'month';
                    document.getElementById('dashTrendYear').hidden = which !== 'year';
                    document.querySelectorAll('[data-trend-sub]').forEach(function (p) { p.hidden = p.getAttribute('data-trend-sub') !== which; });
                });
            });

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
