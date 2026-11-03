{{-- dompdf CSS of the PDF templates (no CSS variables, flexbox or grid; tables only). $t = template palette. --}}
@php
    $t = $pdfT;
    $tpl = $t['template'];
    // dompdf uses the first (embedded DejaVu) font; the rest are for the browser preview on Theme Settings
    $headFont = "'" . $t['head_font'] . "', " . (str_contains($t['head_font'], 'Serif') ? "Georgia, 'Times New Roman', serif" : "'Segoe UI', Arial, sans-serif");
@endphp
<style>
    @page { margin: 30px 34px 64px 34px; }
    body { font-family: '{{ $t['font'] }}', 'Segoe UI', Arial, sans-serif; font-size: 8.8px; line-height: 1.45; color: {{ $t['text'] }}; margin: 0; background: #ffffff; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    img { border: 0; }
    .r, .num, .right { text-align: right; }
    .c, .center { text-align: center; }
    .b, .bold { font-weight: bold; }
    .i { font-style: italic; }
    .muted { color: {{ $t['muted'] }}; }
    .faint { color: {{ $t['faint'] }}; }
    .small { font-size: 7.6px; }
    .nowrap, .num { white-space: nowrap; }
    .pos { color: {{ $t['success'] }}; }
    .neg { color: {{ $t['danger'] }}; }
    .warn { color: {{ $t['warning'] }}; }
    .accent { color: {{ $t['accent'] }}; }
    .page-break { page-break-after: always; }
    .avoid { page-break-inside: avoid; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }

    /* ---------- labels, badges ---------- */
    .pdf-label { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: .6px; color: {{ $t['muted'] }}; }
    .pdf-badge { display: inline-block; padding: 2px 9px; border: 1px solid {{ $t['muted'] }}; font-size: 7.2px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase;
        border-radius: {{ $tpl === 'classic' || $tpl === 'elegant' ? '0' : '8px' }}; }

    /* ---------- watermark & footer ---------- */
    .pdf-watermark { position: fixed; top: 32%; left: 18%; width: 64%; text-align: center; opacity: .07; }
    .pdf-watermark img { max-width: 100%; max-height: 320px; }
    .pdf-footer { position: fixed; left: 0; right: 0; bottom: -46px; height: 30px; border-top: {{ $tpl === 'elegant' ? '3px double ' . $t['rule_strong'] : ($tpl === 'classic' ? '0.6px solid #111111' : '0.6px solid ' . $t['rule']) }};
        padding-top: 5px; font-size: 7px; line-height: 1.35; color: {{ $t['muted'] }}; }
    .pdf-footer td { vertical-align: top; }
    @if ($tpl === 'modern')
        .pdf-footer { border-top: 2px solid {{ $t['accent'] }}; }
    @endif

    /* ---------- header ---------- */
    .pdf-head { margin-bottom: 14px; }
    .pdf-head-co { width: 60%; padding-right: 12px; }
    .pdf-head-doc { width: 40%; text-align: right; }
    .pdf-logo-cell { width: 1%; padding-right: 10px; vertical-align: middle; }
    .pdf-logo { max-height: 50px; max-width: 120px; }
    .pdf-co-name { font-family: {!! $headFont !!}; font-size: 13.5px; font-weight: bold; color: {{ $t['ink'] }}; line-height: 1.15; }
    .pdf-co-tag { font-size: 7.6px; color: {{ $t['muted'] }}; font-style: italic; margin-bottom: 2px; }
    .pdf-co-line { font-size: 7.8px; color: {{ $t['muted'] }}; line-height: 1.4; }
    .pdf-title { font-family: {!! $headFont !!}; font-size: 19px; font-weight: bold; color: {{ $t['ink'] }}; line-height: 1.1; }
    .pdf-docno { font-size: 9.5px; font-weight: bold; color: {{ $t['text'] }}; margin-top: 4px; }
    .pdf-docmeta { width: auto; margin: 5px 0 0 auto; }
    .pdf-docmeta td { padding: 1px 0 1px 10px; font-size: 7.8px; text-align: right; }
    .pdf-docmeta .k { color: {{ $t['muted'] }}; }
    .pdf-docmeta .v { color: {{ $t['ink'] }}; font-weight: bold; }
    .pdf-status { margin-top: 6px; }

    @if ($tpl === 'classic')
        .pdf-head { border-bottom: 1.4px solid #111111; padding-bottom: 10px; }
        .pdf-title { text-transform: uppercase; letter-spacing: 1.5px; font-size: 17px; }
    @elseif ($tpl === 'modern')
        .pdf-head { background: {{ $t['accent'] }}; color: {{ $t['on_accent'] }}; }
        .pdf-head-co, .pdf-head-doc { padding: 14px 16px; }
        .pdf-head-co { padding-right: 0; }
        .pdf-logo-chip { display: inline-block; background: #ffffff; padding: 5px 7px; border-radius: 6px; }
        .pdf-logo { max-height: 40px; }
        .pdf-co-name, .pdf-title, .pdf-docno, .pdf-docmeta .v { color: {{ $t['on_accent'] }}; }
        .pdf-co-line, .pdf-co-tag, .pdf-docmeta .k { color: {{ $t['on_accent'] }}; opacity: .85; }
        .pdf-title { font-size: 21px; letter-spacing: .3px; }
        .pdf-badge { background: #ffffff; }
    @elseif ($tpl === 'minimal')
        .pdf-head { margin-bottom: 26px; }
        .pdf-title { font-size: 13px; font-weight: normal; text-transform: uppercase; letter-spacing: 3.5px; color: {{ $t['muted'] }}; }
        .pdf-docno { font-size: 11px; color: {{ $t['ink'] }}; margin-top: 6px; }
        .pdf-co-name { font-size: 12px; letter-spacing: .3px; }
        .pdf-title { color: {{ $t['accent'] }}; }
    @elseif ($tpl === 'corporate')
        .pdf-head { border: 1px solid {{ $t['rule_strong'] }}; }
        .pdf-head-co, .pdf-head-doc { padding: 12px 14px; }
        .pdf-head-doc { background: {{ $t['accent_soft'] }}; border-left: 4px solid {{ $t['head_bg'] }}; }
        .pdf-title { text-transform: uppercase; color: {{ $t['head_bg'] }}; font-size: 17px; letter-spacing: .8px; }
    @elseif ($tpl === 'elegant')
        .pdf-head { border-bottom: 3px double {{ $t['accent'] }}; padding-bottom: 12px; margin-bottom: 16px; }
        .pdf-co-name { font-size: 15px; font-weight: normal; letter-spacing: .5px; }
        .pdf-title { font-size: 22px; font-weight: normal; color: {{ $t['accent'] }}; letter-spacing: .5px; }
        .pdf-docno { font-style: italic; font-weight: normal; }
    @endif

    /* ---------- info strip (report filters) ---------- */
    .pdf-info { margin: 0 0 12px 0; }
    .pdf-info td { padding: 5px 8px 5px 0; font-size: 8px; }
    .pdf-info .v { color: {{ $t['ink'] }}; font-weight: bold; }
    @if ($tpl === 'classic')
        .pdf-info { border-bottom: 0.6px solid #bdbdbd; }
    @elseif ($tpl === 'modern' || $tpl === 'corporate')
        .pdf-info { background: {{ $t['accent_soft'] }}; }
        .pdf-info td { padding: 6px 10px; }
        @if ($tpl === 'corporate') .pdf-info { border: 1px solid {{ $t['rule'] }}; } @endif
    @elseif ($tpl === 'elegant')
        .pdf-info { border-bottom: 0.6px solid {{ $t['rule'] }}; }
    @endif

    /* ---------- section headings ---------- */
    .pdf-h { font-family: {!! $headFont !!}; font-size: 10px; font-weight: bold; color: {{ $t['ink'] }}; margin: 14px 0 6px 0; padding-bottom: 3px; }
    .pdf-h .meta, .pdf-h small { font-family: '{{ $t['font'] }}', sans-serif; font-weight: normal; font-size: 7.6px; color: {{ $t['muted'] }}; }
    @if ($tpl === 'classic')
        .pdf-h { text-transform: uppercase; letter-spacing: .8px; font-size: 9px; border-bottom: 0.8px solid #111111; }
    @elseif ($tpl === 'modern')
        .pdf-h { border-left: 3px solid {{ $t['accent'] }}; padding: 2px 0 2px 7px; color: {{ $t['accent'] }}; }
    @elseif ($tpl === 'minimal')
        .pdf-h { text-transform: uppercase; letter-spacing: 2px; font-size: 8px; color: {{ $t['muted'] }}; font-weight: normal; margin-top: 18px; }
    @elseif ($tpl === 'corporate')
        .pdf-h { background: {{ $t['head_bg'] }}; color: {{ $t['head_text'] }}; padding: 5px 8px; }
        .pdf-h .meta, .pdf-h small { color: {{ $t['head_text'] }}; }
    @elseif ($tpl === 'elegant')
        .pdf-h { font-weight: normal; font-size: 12px; color: {{ $t['accent'] }}; border-bottom: 3px double {{ $t['rule'] }}; }
    @endif

    /* ---------- party blocks ---------- */
    .pdf-party { margin: 0 0 14px 0; }
    .pdf-party-cell { padding: 0; }
    .pdf-party-in { padding: 8px 10px; }
    .pdf-party-cell.first .pdf-party-in { padding-left: 0; }
    .pdf-party-name { font-size: 10px; font-weight: bold; color: {{ $t['ink'] }}; margin: 2px 0 1px 0; }
    .pdf-party-line { font-size: 8px; color: {{ $t['muted'] }}; }
    .pdf-kv-mini { width: auto; margin-top: 2px; }
    .pdf-kv-mini td { padding: 1px 10px 1px 0; font-size: 8px; color: {{ $t['ink'] }}; }
    .pdf-kv-mini .k { color: {{ $t['muted'] }}; white-space: nowrap; }
    @if ($tpl === 'classic')
        .pdf-party { border-bottom: 0.6px solid #bdbdbd; }
    @elseif ($tpl === 'modern')
        .pdf-party { border-collapse: separate; border-spacing: 0; }
        .pdf-party-cell { background: {{ $t['zebra'] }}; border-left: 6px solid #ffffff; }
        .pdf-party-cell.first { border-left: 0; }
        .pdf-party-in, .pdf-party-cell.first .pdf-party-in { padding: 9px 11px; }
        .pdf-party .pdf-label { color: {{ $t['accent'] }}; }
    @elseif ($tpl === 'minimal')
        .pdf-party { margin-bottom: 22px; }
        .pdf-party .pdf-label { font-weight: normal; letter-spacing: 1.5px; }
    @elseif ($tpl === 'corporate')
        .pdf-party-cell { border: 1px solid {{ $t['rule'] }}; }
        .pdf-party-in, .pdf-party-cell.first .pdf-party-in { padding: 8px 10px; }
        .pdf-party .pdf-label { color: {{ $t['head_bg'] }}; }
    @elseif ($tpl === 'elegant')
        .pdf-party .pdf-label { font-family: {!! $headFont !!}; font-style: italic; text-transform: none; letter-spacing: 0; font-size: 9px; font-weight: normal; color: {{ $t['accent'] }}; }
        .pdf-party { border-bottom: 0.6px solid {{ $t['rule'] }}; }
    @endif

    /* ---------- item / ledger tables ---------- */
    .pdf-table { margin: 0 0 12px 0; }
    .pdf-table th { font-size: 7.2px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; text-align: left; padding: 6px 6px; color: {{ $t['head_text'] }}; }
    .pdf-table th.r, .pdf-table th.num, .pdf-table th.right { text-align: right; }
    .pdf-table th.c, .pdf-table th.center { text-align: center; }
    .pdf-table td { padding: 5.5px 6px; font-size: 8.4px; color: {{ $t['text'] }}; }
    .pdf-table.compact th { padding: 4px 4px; font-size: 6.6px; }
    .pdf-table.compact td { padding: 3.5px 4px; font-size: 7.4px; }
    .pdf-table .sub { font-size: 7.2px; color: {{ $t['muted'] }}; }
    .pdf-table tr.opening td { font-style: italic; color: {{ $t['muted'] }}; }
    .pdf-table tr.total td, .pdf-table tr.subtotal td { font-weight: bold; color: {{ $t['ink'] }}; }
    .pdf-table tr.group td { font-weight: bold; color: {{ $t['ink'] }}; }
    .pdf-table tr.empty td { text-align: center; font-style: italic; color: {{ $t['muted'] }}; padding: 14px; }

    @if ($tpl === 'classic')
        .pdf-table th { border-top: 1px solid #111111; border-bottom: 1px solid #111111; color: #111111; }
        .pdf-table td { border-bottom: 0.5px solid #bdbdbd; }
        .pdf-table tr.total td { border-top: 1px solid #111111; border-bottom: 2.4px double #111111; }
        .pdf-table tr.subtotal td { border-top: 0.8px solid #111111; }
        .pdf-table tr.group td { border-bottom: 0.8px solid #111111; text-transform: uppercase; font-size: 7.6px; letter-spacing: .4px; }
    @elseif ($tpl === 'modern')
        .pdf-table th { background: {{ $t['accent'] }}; color: {{ $t['on_accent'] }}; border: 0.6px solid {{ $t['accent'] }}; }
        .pdf-table tbody tr:nth-child(even) td { background: {{ $t['zebra'] }}; }
        .pdf-table td { border-bottom: 0.5px solid {{ $t['rule'] }}; }
        .pdf-table tr.total td { background: {{ $t['accent_soft'] }}; border-top: 1.2px solid {{ $t['accent'] }}; border-bottom: 0; }
        .pdf-table tr.subtotal td { background: {{ $t['zebra'] }}; border-top: 0.8px solid {{ $t['rule_strong'] }}; }
        .pdf-table tr.group td { background: {{ $t['accent_soft'] }}; color: {{ $t['accent'] }}; }
    @elseif ($tpl === 'minimal')
        .pdf-table th { font-weight: normal; letter-spacing: 1.4px; color: {{ $t['muted'] }}; border-bottom: 0.6px solid {{ $t['rule_strong'] }}; padding-top: 4px; padding-bottom: 7px; }
        .pdf-table td { padding-top: 7px; padding-bottom: 7px; border-bottom: 0.5px solid {{ $t['rule'] }}; }
        .pdf-table tr.total td { border-top: 0.8px solid {{ $t['ink'] }}; border-bottom: 0; }
        .pdf-table tr.subtotal td { border-top: 0.6px solid {{ $t['rule_strong'] }}; }
        .pdf-table tr.group td { color: {{ $t['accent'] }}; text-transform: uppercase; letter-spacing: 1px; font-size: 7.4px; padding-top: 12px; }
    @elseif ($tpl === 'corporate')
        .pdf-table th { background: {{ $t['head_bg'] }}; color: {{ $t['head_text'] }}; border: 0.6px solid {{ $t['head_bg'] }}; }
        .pdf-table td { border: 0.6px solid {{ $t['rule'] }}; }
        .pdf-table tr.total td { background: {{ $t['accent_soft'] }}; border-top: 1.2px solid {{ $t['rule_strong'] }}; }
        .pdf-table tr.subtotal td { background: #f4f6f8; }
        .pdf-table tr.group td { background: {{ $t['accent_soft'] }}; color: {{ $t['head_bg'] }}; }
    @elseif ($tpl === 'elegant')
        .pdf-table th { font-family: {!! $headFont !!}; text-transform: none; letter-spacing: 0; font-size: 8px; font-style: italic; font-weight: normal; color: {{ $t['ink'] }};
            border-top: 0.8px solid {{ $t['rule_strong'] }}; border-bottom: 3px double {{ $t['rule_strong'] }}; }
        .pdf-table td { border-bottom: 0.5px solid {{ $t['rule'] }}; }
        .pdf-table tr.total td { border-top: 0.8px solid {{ $t['rule_strong'] }}; border-bottom: 3px double {{ $t['accent'] }}; }
        .pdf-table tr.subtotal td { border-top: 0.6px solid {{ $t['rule_strong'] }}; }
        .pdf-table tr.group td { font-family: {!! $headFont !!}; color: {{ $t['accent'] }}; font-style: italic; }
    @endif

    /* ---------- bottom: words / notes left, totals right ---------- */
    .pdf-bottom { margin-top: 4px; page-break-inside: avoid; }
    .pdf-bottom-left { width: 56%; padding-right: 22px; }
    .pdf-bottom-right { width: 44%; }
    .pdf-totals td { padding: 4px 8px; font-size: 8.6px; }
    .pdf-totals .k { color: {{ $t['muted'] }}; }
    .pdf-totals .num { color: {{ $t['ink'] }}; }
    .pdf-totals tr.grand td { font-size: 11.5px; font-weight: bold; color: {{ $t['ink'] }}; padding-top: 7px; padding-bottom: 7px; }
    .pdf-totals tr.after td { font-size: 8.6px; }
    .pdf-totals tr.due td { font-weight: bold; color: {{ $t['danger'] }}; }
    .pdf-totals tr.clear td, .pdf-totals tr.due.clear td { color: {{ $t['success'] }}; }
    .pdf-totals tr.strong td { font-weight: bold; color: {{ $t['ink'] }}; }
    @if ($tpl === 'classic')
        .pdf-totals tr.grand td { border-top: 1px solid #111111; border-bottom: 2.4px double #111111; }
    @elseif ($tpl === 'modern')
        .pdf-totals { background: {{ $t['accent_soft'] }}; border-radius: 8px; }
        .pdf-totals tr:first-child td { padding-top: 9px; }
        .pdf-totals tr.grand td { background: {{ $t['accent'] }}; color: {{ $t['on_accent'] }}; border-top: 0.6px solid {{ $t['accent'] }}; border-bottom: 0.6px solid {{ $t['accent'] }}; }
        .pdf-totals tr.grand td.k { border-left: 0.6px solid {{ $t['accent'] }}; }
        .pdf-totals tr.grand td.num { border-right: 0.6px solid {{ $t['accent'] }}; }
        .pdf-totals tr:last-child td { padding-bottom: 9px; }
    @elseif ($tpl === 'minimal')
        .pdf-totals tr.grand td { border-top: 0.8px solid {{ $t['ink'] }}; font-weight: normal; font-size: 13px; }
        .pdf-totals tr.grand .k { color: {{ $t['ink'] }}; }
    @elseif ($tpl === 'corporate')
        .pdf-totals td { border: 0.6px solid {{ $t['rule'] }}; }
        .pdf-totals .k { background: {{ $t['accent_soft'] }}; color: {{ $t['ink'] }}; font-weight: bold; }
        .pdf-totals tr.grand td { background: {{ $t['head_bg'] }}; color: {{ $t['head_text'] }}; border-color: {{ $t['head_bg'] }}; }
    @elseif ($tpl === 'elegant')
        .pdf-totals { background: {{ $t['total_bg'] }}; }
        .pdf-totals tr.grand td { font-family: {!! $headFont !!}; font-weight: normal; font-size: 13px; border-top: 0.8px solid {{ $t['rule_strong'] }}; border-bottom: 3px double {{ $t['accent'] }}; }
    @endif

    .pdf-words { margin-bottom: 10px; padding: 7px 0; }
    .pdf-words-text { font-size: 8.6px; font-weight: bold; color: {{ $t['ink'] }}; font-style: italic; }
    @if ($tpl === 'modern' || $tpl === 'corporate')
        .pdf-words { padding: 7px 10px; border-left: 3px solid {{ $t['accent'] }}; background: {{ $t['zebra'] === '#ffffff' ? $t['accent_soft'] : $t['zebra'] }}; }
    @elseif ($tpl === 'classic')
        .pdf-words { border-top: 0.5px solid #bdbdbd; border-bottom: 0.5px solid #bdbdbd; }
    @elseif ($tpl === 'elegant')
        .pdf-words-text { font-family: {!! $headFont !!}; font-weight: normal; }
    @endif
    .pdf-notes { margin-bottom: 10px; }
    .pdf-notes-text { font-size: 7.8px; color: {{ $t['muted'] }}; margin-top: 2px; }

    /* ---------- key/value grids and summary cards ---------- */
    .pdf-kv { margin-bottom: 10px; }
    .pdf-kv td { padding: 4px 6px; font-size: 8.4px; border-bottom: 0.5px solid {{ $tpl === 'classic' ? '#bdbdbd' : $t['rule'] }}; }
    .pdf-kv td.k { color: {{ $t['muted'] }}; width: 22%; white-space: nowrap; }
    .pdf-kv td.v { color: {{ $t['ink'] }}; }
    .pdf-cards { margin: 0 0 12px 0; border-collapse: separate; border-spacing: 6px 0; }
    .pdf-cards td { padding: 8px 10px; border: 0.8px solid {{ $tpl === 'classic' ? '#111111' : $t['rule'] }}; }
    .pdf-cards .pdf-card-v { font-size: 12px; font-weight: bold; color: {{ $t['ink'] }}; margin-top: 2px; }
    .pdf-cards .pdf-card-s { font-size: 7px; color: {{ $t['muted'] }}; }
    @if ($tpl === 'modern')
        .pdf-cards td { background: {{ $t['accent_soft'] }}; border: 0; border-radius: 6px; }
        .pdf-cards .pdf-card-v { color: {{ $t['accent'] }}; }
    @elseif ($tpl === 'corporate')
        .pdf-cards td { border-top: 3px solid {{ $t['head_bg'] }}; }
    @elseif ($tpl === 'elegant')
        .pdf-cards td { border: 0; border-top: 0.8px solid {{ $t['rule_strong'] }}; border-bottom: 3px double {{ $t['rule'] }}; }
        .pdf-cards .pdf-card-v { font-family: {!! $headFont !!}; font-weight: normal; }
    @elseif ($tpl === 'minimal')
        .pdf-cards td { border: 0; border-left: 0.6px solid {{ $t['rule_strong'] }}; }
    @endif

    /* ---------- signatures ---------- */
    .pdf-sign { margin-top: 38px; page-break-inside: avoid; }
    .pdf-sign td { vertical-align: bottom; }
    .pdf-sign td.gap { padding-left: 34px; }
    .pdf-sign-space { height: 34px; text-align: center; }
    .pdf-sign-img { max-height: 32px; max-width: 120px; }
    .pdf-sign-name { display: block; padding-top: 18px; height: 12px; line-height: 12px; overflow: hidden; font-size: 8px; color: {{ $t['ink'] }}; }
    .pdf-sign-line { border-top: 0.8px solid {{ $tpl === 'classic' ? '#111111' : $t['rule_strong'] }}; padding-top: 4px; text-align: center; font-size: 7.6px; color: {{ $t['muted'] }}; }
    @if ($tpl === 'elegant')
        .pdf-sign-line { font-family: {!! $headFont !!}; font-style: italic; font-size: 8.4px; }
    @elseif ($tpl === 'minimal')
        .pdf-sign-line { text-transform: uppercase; letter-spacing: 1.2px; font-size: 6.8px; }
    @endif

    /* ---------- browser preview (Theme Settings): an A4 sheet ---------- */
    body.is-preview { width: 726px; min-height: 1043px; padding: 30px 34px 50px 34px; position: relative; }
    body.is-preview .pdf-footer { position: absolute; bottom: 14px; left: 34px; right: 34px; }
    body.is-preview .pdf-watermark { position: absolute; }
</style>
