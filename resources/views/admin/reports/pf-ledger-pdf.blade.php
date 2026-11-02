<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PF Ledger</title>
    @php $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2); @endphp
    <style>
        @page { margin: 24px 26px 46px 26px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: {{ $pc['ink'] }}; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: {{ $pc['muted'] }}; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        .company-name { font-size: 15px; font-weight: bold; color: {{ $pc['ink'] }}; }
        .doc-title { font-size: 16px; font-weight: bold; color: {{ $pc['accent'] }}; letter-spacing: 1px; }
        .rule { border-top: 2.5px solid {{ $pc['accent'] }}; margin: 6px 0 8px 0; }

        .filters td { padding: 1px 10px 1px 0; vertical-align: top; }
        .filters .k { color: {{ $pc['muted'] }}; width: 70px; }

        .grid { margin-top: 10px; }
        .grid th { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; padding: 4px 5px; font-size: 7.5px; text-transform: uppercase; text-align: left; border: 1px solid {{ $pc['accent'] }}; }
        .grid th.right { text-align: right; }
        .grid td { padding: 3.5px 5px; border: 1px solid {{ $pc['border'] }}; }
        .grid tr.opening td { background: {{ $pc['soft'] }}; font-style: italic; color: {{ $pc['secondary'] }}; }
        .grid tr.period td { background: {{ $pc['border'] }}; color: {{ $pc['accent'] }}; font-weight: bold; }
        .grid tr.total td { background: {{ $pc['soft'] }}; font-weight: bold; border-top: 1.5px solid {{ $pc['faint'] }}; }
        .grid tr.grand td { background: {{ $pc['border_strong'] }}; font-weight: bold; font-size: 9px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7.5px; color: {{ $pc['faint'] }}; }
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
                <div class="doc-title">PF LEDGER</div>
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
                <th>Month</th>
                <th class="right">Basic</th>
                <th class="right">Gross</th>
                <th class="right">PF</th>
                <th class="right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening"><td colspan="4">Opening balance</td><td class="right">{{ number_format($opening, 2) }}</td></tr>
            @forelse ($periods as $n => $period)
                <tr class="period"><td colspan="5">{{ \App\Http\Controllers\Admin\ReportController::pfPeriodTitle($n, $period['entry']) }}</td></tr>
                @foreach ($period['rows'] as $r)
                    <tr>
                        <td>{{ $r['month']->format('M Y') }} <span class="muted">{{ $r['source'] ? '· ' . $r['source'] : '· expected' }}</span></td>
                        <td class="right">{{ $m($r['basic']) }}</td>
                        <td class="right">{{ $m($r['gross']) }}</td>
                        <td class="right">{{ $m($r['pf']) }}</td>
                        <td class="right bold">{{ number_format($r['balance'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">Period total ({{ count($period['rows']) }} months)</td>
                    <td class="right">{{ number_format($period['total'], 2) }}</td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="text-align: center; padding: 14px;">No PF months in this period.</td></tr>
            @endforelse
            <tr class="grand">
                <td colspan="3">Total PF / Closing balance</td>
                <td class="right">{{ number_format($total, 2) }}</td>
                <td class="right">{{ number_format($closing, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">{{ $company['name'] }} &nbsp;·&nbsp; PF Ledger &nbsp;·&nbsp; {{ $employee->name }} ({{ $employee->employee_code }})</div>
</body>
</html>
