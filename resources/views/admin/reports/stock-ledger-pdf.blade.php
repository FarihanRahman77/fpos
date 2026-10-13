<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stock Ledger</title>
    @php
        $q = fn ($v) => $v == 0 ? '-' : rtrim(rtrim(number_format($v, 3), '0'), '.');
    @endphp
    <style>
        @page { margin: 24px 26px 46px 26px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        .company-name { font-size: 15px; font-weight: bold; color: #0f172a; }
        .doc-title { font-size: 16px; font-weight: bold; color: #1e3a8a; letter-spacing: 1px; }
        .rule { border-top: 2.5px solid #1e3a8a; margin: 6px 0 8px 0; }

        .filters td { padding: 1px 10px 1px 0; vertical-align: top; }
        .filters .k { color: #64748b; width: 70px; }

        .block { margin-top: 10px; page-break-inside: auto; }
        .block-head { background: #1e3a8a; color: #fff; padding: 5px 7px; }
        .block-head .meta { color: #cbd5e1; font-size: 7.5px; }
        .grid th { background: #e2e8f0; padding: 4px 5px; font-size: 7.5px; text-transform: uppercase; text-align: left; border: 1px solid #cbd5e1; }
        .grid td { padding: 3.5px 5px; border: 1px solid #e2e8f0; vertical-align: top; }
        .grid tr.opening td { background: #f8fafc; font-style: italic; color: #475569; }
        .grid tr.total td { background: #f1f5f9; font-weight: bold; border-top: 1.5px solid #94a3b8; }
        .in { color: #15803d; }
        .out { color: #b91c1c; }
        .neg { color: #b91c1c; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .summary { width: 45%; margin-top: 14px; page-break-inside: avoid; }
        .summary td { padding: 4px 7px; border: 1px solid #cbd5e1; }
        .summary td.k { background: #f1f5f9; font-weight: bold; width: 55%; }

        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; }
    </style>
</head>
<body>

    <table>
        <tr>
            <td style="width: 62%; vertical-align: top;">
                <table>
                    <tr>
                        @if ($company['logo'])
                            <td style="width: 52px; vertical-align: middle;"><img src="{{ $company['logo'] }}" style="max-height: 36px; max-width: 48px;" alt=""></td>
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
            <td class="right" style="vertical-align: top;">
                <div class="doc-title">STOCK LEDGER</div>
                <div class="muted" style="margin-top: 2px;">Printed: {{ $printedAt }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="filters">
        @foreach ($filters_text as $label => $value)
            <tr><td class="k">{{ $label }}</td><td class="bold">{{ $value }}</td></tr>
        @endforeach
    </table>

    @forelse ($items as $item)
        <div class="block">
            <div class="block-head">
                <span class="bold" style="font-size: 9.5px;">{{ $item['product'] }}</span>
                <span class="meta">
                    @if ($item['sku']) &nbsp; SKU: {{ $item['sku'] }}@endif
                    @if ($item['category']) &nbsp;·&nbsp; {{ $item['category'] }}@endif
                    @if ($item['brand']) &nbsp;·&nbsp; {{ $item['brand'] }}@endif
                    @if ($item['unit']) &nbsp;·&nbsp; Unit: {{ $item['unit'] }}@endif
                </span>
            </div>
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 13%;">Date</th>
                        <th style="width: 11%;">Type</th>
                        <th style="width: 11%;">Reference</th>
                        <th style="width: 18%;">Party</th>
                        <th>Remarks</th>
                        <th class="right" style="width: 8%;">In</th>
                        <th class="right" style="width: 8%;">Out</th>
                        <th class="right" style="width: 9%;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="opening">
                        <td colspan="7">Opening balance (before {{ $from->format('d M Y') }})</td>
                        <td class="right bold">{{ $q($item['opening']) }}</td>
                    </tr>
                    @forelse ($item['rows'] as $r)
                        <tr>
                            <td>{{ $r['date']->format('d M Y h:i A') }}</td>
                            <td>{{ $r['type'] }}</td>
                            <td>{{ $r['reference'] ?: '-' }}</td>
                            <td>{{ $r['party'] ?: '-' }}</td>
                            <td class="muted">{{ $r['remarks'] }}</td>
                            <td class="right in">{{ $q($r['in']) }}</td>
                            <td class="right out">{{ $q($r['out']) }}</td>
                            <td class="right bold {{ $r['balance'] < 0 ? 'neg' : '' }}">{{ $q($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted" style="text-align: center; font-style: italic;">No movement in this period</td></tr>
                    @endforelse
                    <tr class="total">
                        <td colspan="5" class="right">Total / Closing balance</td>
                        <td class="right in">{{ $q($item['in']) }}</td>
                        <td class="right out">{{ $q($item['out']) }}</td>
                        <td class="right {{ $item['closing'] < 0 ? 'neg' : '' }}">{{ $q($item['closing']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <p class="muted" style="text-align: center; margin-top: 30px;">No stock movement matches these filters.</p>
    @endforelse

    <table class="summary">
        <tr><td class="k">Products / variants</td><td class="right">{{ $totals['items'] }}</td></tr>
        <tr><td class="k">Opening balance</td><td class="right">{{ $q($totals['opening']) }}</td></tr>
        <tr><td class="k">Total in</td><td class="right in">{{ $q($totals['in']) }}</td></tr>
        <tr><td class="k">Total out</td><td class="right out">{{ $q($totals['out']) }}</td></tr>
        <tr><td class="k">Closing balance</td><td class="right bold">{{ $q($totals['closing']) }}</td></tr>
    </table>

    <div class="footer">{{ $company['name'] }} &nbsp;|&nbsp; Stock Ledger &nbsp;|&nbsp; {{ $filters_text['Period'] ?? '' }}</div>

    {{-- Page numbers are drawn by ReportController after rendering. --}}
</body>
</html>
