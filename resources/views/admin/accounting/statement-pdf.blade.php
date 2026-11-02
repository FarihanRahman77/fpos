<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @php
        $m = fn ($v) => $v === null ? '' : number_format((float) $v, 2);
        $span = count($columns) + 2;
        $valueWidth = count($columns) > 1 ? 18 : 22;
    @endphp
    <style>
        @page { margin: 22px 24px 44px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: {{ $pc['ink'] }}; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: {{ $pc['muted'] }}; }
        .right { text-align: right; }

        .company-name { font-size: 14px; font-weight: bold; }
        .doc-title { font-size: 14px; font-weight: bold; color: {{ $pc['accent'] }}; letter-spacing: 1px; }
        .doc-period { font-size: 9px; color: {{ $pc['secondary'] }}; margin-top: 2px; }
        .rule { border-top: 2px solid {{ $pc['accent'] }}; margin: 6px 0 6px 0; }
        .meta td { padding: 1px 8px 1px 0; vertical-align: top; }
        .meta .k { font-weight: bold; width: 70px; }

        .grid { margin-top: 10px; }
        .grid th, .grid td { padding: 3px 5px; vertical-align: top; border-bottom: 0.5px solid {{ $pc['border'] }}; }
        .grid thead th { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; font-size: 7.5px; text-align: left; border-bottom: 0; }
        .grid thead th.num { text-align: right; }
        .grid td.num { text-align: right; white-space: nowrap; }
        .grid td.code { color: {{ $pc['muted'] }}; font-size: 7px; white-space: nowrap; }
        .grid .neg { color: {{ $pc['danger'] }}; }
        .grid tr.section td { background: {{ $pc['soft'] }}; font-weight: bold; font-size: 8.5px; padding-top: 5px; }
        .grid tr.head td { font-weight: bold; color: {{ $pc['accent'] }}; }
        .grid tr.group td { font-weight: bold; color: {{ $pc['secondary'] }}; }
        .grid tr.subtotal td { font-weight: bold; border-top: 1px solid {{ $pc['border_strong'] }}; }
        .grid tr.total td { font-weight: bold; background: {{ $pc['soft'] }}; border-top: 1px solid {{ $pc['accent'] }}; }
        .grid tr.grand td { font-weight: bold; font-size: 9px; background: {{ $pc['soft'] }}; border-top: 2px solid {{ $pc['ink'] }}; border-bottom: 2px solid {{ $pc['ink'] }}; }
        .ind-1 { padding-left: 14px !important; }
        .ind-2 { padding-left: 26px !important; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .check { margin-top: 8px; padding: 5px 7px; font-weight: bold; }
        .check.ok { background: {{ $pc['success_soft'] }}; color: {{ $pc['success'] }}; }
        .check.bad { background: {{ $pc['danger_soft'] }}; color: {{ $pc['danger'] }}; }
        .notes { margin-top: 10px; padding: 6px 8px; border: 0.5px dashed {{ $pc['border_strong'] }}; color: {{ $pc['muted'] }}; font-size: 7px; page-break-inside: avoid; }
        .notes div + div { margin-top: 3px; }

        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7px; color: {{ $pc['faint'] }}; }
    </style>
</head>
<body>

    <table>
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <table>
                    <tr>
                        @if ($company['logo'])
                            <td style="width: 50px; vertical-align: middle;"><img src="{{ $company['logo'] }}" style="max-height: 34px; max-width: 46px;" alt=""></td>
                        @endif
                        <td style="vertical-align: middle;">
                            <div class="company-name">{{ $company['name'] }}</div>
                            @if ($company['address'])<div class="muted">{{ $company['address'] }}</div>@endif
                            <div class="muted">
                                @if ($company['phone'])Phone: {{ $company['phone'] }}@endif
                                @if ($company['email']) &nbsp;|&nbsp; {{ $company['email'] }}@endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="vertical-align: top;" class="right">
                <div class="doc-title">{{ strtoupper($title) }}</div>
                <div class="doc-period">{{ $period }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="meta">
        @foreach ($filters_text as $label => $value)
            <tr><td class="k">{{ $label }}</td><td>: {{ $value }}</td></tr>
        @endforeach
        <tr><td class="k">Printed Date</td><td>: {{ $printedAt }}</td></tr>
        <tr><td class="k">Printed By</td><td>: {{ $printedBy ?: '-' }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 12%;">Code</th>
                <th>Particulars</th>
                @foreach ($columns as $c)
                    <th class="num" style="width: {{ $valueWidth }}%;">{{ $c }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($sections as $sec)
                <tr class="section"><td colspan="{{ $span }}">{{ $sec['title'] }}</td></tr>
                @foreach ($sec['lines'] as $l)
                    <tr class="{{ $l['kind'] }}">
                        <td class="code">{{ $l['code'] ?? '' }}</td>
                        <td class="ind-{{ (int) ($l['indent'] ?? 0) }}">{{ $l['label'] }}</td>
                        @foreach ($columns as $i => $c)
                            @php $val = $l['values'][$i] ?? null; @endphp
                            <td class="num {{ $val !== null && $val < 0 ? 'neg' : '' }}">{{ $m($val) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
            @if ($grand)
                <tr class="grand">
                    <td></td>
                    <td>{{ $grand['label'] }}</td>
                    @foreach ($grand['values'] as $val)
                        <td class="num {{ $val < 0 ? 'neg' : '' }}">{{ $m($val) }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @foreach ($checks as $c)
        <div class="check {{ $c['ok'] ? 'ok' : 'bad' }}">{{ $c['ok'] ? 'Balanced' : 'Not balanced' }} — {{ $c['label'] }}: {{ $c['detail'] }}</div>
    @endforeach

    @if ($notes)
        <div class="notes">
            @foreach ($notes as $n)
                <div>{{ $n }}</div>
            @endforeach
        </div>
    @endif

    <div class="footer">{{ $company['name'] }} · {{ $title }} · {{ $period }}</div>
</body>
</html>
