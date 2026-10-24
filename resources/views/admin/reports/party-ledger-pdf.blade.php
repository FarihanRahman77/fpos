<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $config['title'] }}</title>
    @php
        $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
        $bal = fn ($v) => \App\Services\Reports\PartyLedgerReport::balanceText($v);
    @endphp
    <style>
        @page { margin: 24px 26px 46px 26px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .nowrap { white-space: nowrap; }

        .company-name { font-size: 15px; font-weight: bold; color: #0f172a; }
        .doc-title { font-size: 16px; font-weight: bold; color: #1e3a8a; letter-spacing: 1px; }
        .rule { border-top: 2.5px solid #1e3a8a; margin: 6px 0 8px 0; }

        .filters td { padding: 1px 10px 1px 0; vertical-align: top; }
        .filters .k { color: #64748b; width: 70px; }

        .block { margin-top: 10px; page-break-inside: auto; }
        .block-head { background: #1e3a8a; color: #fff; padding: 5px 7px; }
        .block-head .meta { color: #cbd5e1; font-size: 7px; }
        .grid th { background: #e2e8f0; padding: 4px 4px; font-size: 7px; text-transform: uppercase; text-align: left; border: 1px solid #cbd5e1; }
        .grid th.right { text-align: right; }
        .grid td { padding: 3.5px 4px; border: 1px solid #e2e8f0; vertical-align: top; }
        .grid tr.opening td { background: #f8fafc; font-style: italic; color: #475569; }
        .grid tr.total td { background: #f1f5f9; font-weight: bold; border-top: 1.5px solid #94a3b8; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .summary { width: 50%; margin-top: 14px; page-break-inside: avoid; }
        .summary td { padding: 4px 7px; border: 1px solid #cbd5e1; }
        .summary td.k { background: #f1f5f9; font-weight: bold; width: 58%; }

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
                <div class="doc-title">{{ strtoupper($config['title']) }}</div>
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
                <span class="bold" style="font-size: 9.5px;">{{ $item['name'] }}</span>
                <span class="meta">
                    @if ($item['code']) &nbsp; {{ $item['code'] }}@endif
                    &nbsp;·&nbsp; {{ $item['type'] }}
                    @if ($item['phone']) &nbsp;·&nbsp; {{ $item['phone'] }}@endif
                    &nbsp;·&nbsp; {{ $service->balanceLabel($item['closing']) }}
                </span>
            </div>
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 10%;">Date</th>
                        <th style="width: 11%;">Voucher</th>
                        <th style="width: 15%;">Particulars</th>
                        <th style="width: 11%;">Reference</th>
                        <th>Remarks</th>
                        <th class="right" style="width: 11%;">Debit</th>
                        <th class="right" style="width: 11%;">Credit</th>
                        <th class="right" style="width: 13%;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="opening">
                        <td colspan="7">Balance brought forward (before {{ $from->format('d M Y') }})</td>
                        <td class="right bold nowrap">{{ $bal($item['opening']) }}</td>
                    </tr>
                    @forelse ($item['rows'] as $r)
                        <tr>
                            <td class="nowrap">{{ $r['date']->format('d M Y') }}</td>
                            <td>{{ $r['voucher'] ?: '-' }}</td>
                            <td>{{ $r['particular'] }}</td>
                            <td>{{ $r['reference'] ?: '-' }}</td>
                            <td class="muted">{{ $r['note'] }}</td>
                            <td class="right">{{ $m($r['dr']) }}</td>
                            <td class="right">{{ $m($r['cr']) }}</td>
                            <td class="right bold nowrap">{{ $bal($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted" style="text-align: center; font-style: italic;">No transactions in this period</td></tr>
                    @endforelse
                    <tr class="total">
                        <td colspan="5" class="right">Total / Closing balance</td>
                        <td class="right">{{ number_format($item['dr'], 2) }}</td>
                        <td class="right">{{ number_format($item['cr'], 2) }}</td>
                        <td class="right nowrap">{{ $bal($item['closing']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <p class="muted" style="text-align: center; margin-top: 30px;">No {{ $ledger }} transactions match these filters.</p>
    @endforelse

    <table class="summary">
        <tr><td class="k">Parties</td><td class="right">{{ $totals['parties'] }}</td></tr>
        <tr><td class="k">Balance brought forward</td><td class="right">{{ $bal($totals['opening']) }}</td></tr>
        <tr><td class="k">Total debit</td><td class="right">{{ number_format($totals['dr'], 2) }}</td></tr>
        <tr><td class="k">Total credit</td><td class="right">{{ number_format($totals['cr'], 2) }}</td></tr>
        <tr><td class="k">Total {{ strtolower($config['labels']['Dr']) }} (Dr balances)</td><td class="right">{{ number_format($totals['balance_dr'], 2) }}</td></tr>
        <tr><td class="k">Total {{ strtolower($config['labels']['Cr']) }} (Cr balances)</td><td class="right">{{ number_format($totals['balance_cr'], 2) }}</td></tr>
        <tr><td class="k">Net closing balance</td><td class="right bold">{{ $bal($totals['closing']) }}</td></tr>
    </table>

    <div class="footer">{{ $company['name'] }} &nbsp;|&nbsp; {{ $config['title'] }} &nbsp;|&nbsp; {{ $filters_text['Period'] ?? '' }}</div>

    {{-- Page numbers are drawn by ReportController after rendering. --}}
</body>
</html>
