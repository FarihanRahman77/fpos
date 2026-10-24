<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Transactions Ledger</title>
    @php
        $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
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

        .grid { margin-top: 8px; }
        .grid th { background: #1e3a8a; color: #fff; padding: 4px 4px; font-size: 7px; text-transform: uppercase; text-align: left; border: 1px solid #1e3a8a; }
        .grid th.right { text-align: right; }
        .grid td { padding: 3.5px 4px; border: 1px solid #e2e8f0; vertical-align: top; }
        .grid tr:nth-child(even) td { background: #f8fafc; }
        .grid tr.total td { background: #f1f5f9; font-weight: bold; border-top: 1.5px solid #94a3b8; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .summary { width: 48%; margin-top: 14px; page-break-inside: avoid; }
        .summary th { background: #e2e8f0; padding: 4px 7px; font-size: 7px; text-transform: uppercase; text-align: left; border: 1px solid #cbd5e1; }
        .summary th.right { text-align: right; }
        .summary td { padding: 4px 7px; border: 1px solid #cbd5e1; }
        .summary tr.total td { background: #f1f5f9; font-weight: bold; }

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
                <div class="doc-title">TRANSACTIONS LEDGER</div>
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

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 3%;">SL</th>
                <th style="width: 11%;">Date &amp; Time</th>
                <th style="width: 8%;">Voucher</th>
                <th style="width: 12%;">Type</th>
                <th style="width: 13%;">Party</th>
                <th style="width: 8%;">Reference</th>
                <th style="width: 11%;">Account / Method</th>
                <th>Remarks</th>
                <th class="right" style="width: 8.5%;">Debit</th>
                <th class="right" style="width: 8.5%;">Credit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td class="nowrap">{{ $r->datetime->format('d M Y h:i:s A') }}</td>
                    <td>{{ $r->voucher ?: '-' }}</td>
                    <td>{{ $r->type }}</td>
                    <td>{{ $r->party ?: '-' }}@if ($r->party_type)<br><span class="muted">{{ $r->party_type }}</span>@endif</td>
                    <td>{{ $r->reference ?: '-' }}</td>
                    <td>{{ $r->account ?: '-' }}</td>
                    <td class="muted">{{ $r->remarks }}</td>
                    <td class="right">{{ $m($r->dr) }}</td>
                    <td class="right">{{ $m($r->cr) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted" style="text-align: center; font-style: italic;">No transactions in this period</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="8" class="right">Total ({{ $totals['entries'] }} entries)</td>
                <td class="right">{{ number_format($totals['dr'], 2) }}</td>
                <td class="right">{{ number_format($totals['cr'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($by_type->isNotEmpty())
        <table class="summary">
            <thead>
                <tr>
                    <th>Summary by type</th>
                    <th class="right">Entries</th>
                    <th class="right">Debit</th>
                    <th class="right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($by_type as $t)
                    <tr>
                        <td>{{ $t['type'] }}</td>
                        <td class="right">{{ $t['count'] }}</td>
                        <td class="right">{{ $m($t['dr']) }}</td>
                        <td class="right">{{ $m($t['cr']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    <td class="right">{{ $totals['entries'] }}</td>
                    <td class="right">{{ number_format($totals['dr'], 2) }}</td>
                    <td class="right">{{ number_format($totals['cr'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="footer">{{ $company['name'] }} &nbsp;|&nbsp; Transactions Ledger &nbsp;|&nbsp; {{ $filters_text['Period'] ?? '' }}</div>

    {{-- Page numbers are drawn by ReportController after rendering. --}}
</body>
</html>
