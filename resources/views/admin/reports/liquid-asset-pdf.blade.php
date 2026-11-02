<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Cash &amp; Bank Book</title>
    @php $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2); @endphp
    <style>
        @page { margin: 24px 26px 46px 26px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: {{ $pc['ink'] }}; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: {{ $pc['muted'] }}; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .in { color: {{ $pc['success'] }}; }
        .out, .neg { color: {{ $pc['danger'] }}; }

        .company-name { font-size: 15px; font-weight: bold; color: {{ $pc['ink'] }}; }
        .doc-title { font-size: 16px; font-weight: bold; color: {{ $pc['accent'] }}; letter-spacing: 1px; }
        .rule { border-top: 2.5px solid {{ $pc['accent'] }}; margin: 6px 0 8px 0; }
        .filters td { padding: 1px 10px 1px 0; }
        .filters .k { color: {{ $pc['muted'] }}; width: 80px; }

        .day { margin-top: 10px; page-break-inside: auto; }
        .day-head td { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; padding: 5px 7px; font-weight: bold; }
        .grid th { background: {{ $pc['border'] }}; padding: 4px 4px; font-size: 7px; text-transform: uppercase; text-align: left; border: 1px solid {{ $pc['border_strong'] }}; }
        .grid th.right { text-align: right; }
        .grid td { padding: 3px 4px; border: 1px solid {{ $pc['border'] }}; vertical-align: top; }
        .grid tr.acct td { background: {{ $pc['soft'] }}; color: {{ $pc['accent'] }}; font-weight: bold; }
        .grid tr.close td { font-weight: bold; border-top: 1.2px solid {{ $pc['faint'] }}; }
        .grid tr.method td { background: {{ $pc['soft'] }}; font-weight: bold; }
        .grid tr.total td { background: {{ $pc['border_strong'] }}; font-weight: bold; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .summary { width: 50%; margin-top: 14px; page-break-inside: avoid; }
        .summary th { background: {{ $pc['border'] }}; padding: 4px 6px; text-align: left; border: 1px solid {{ $pc['border_strong'] }}; }
        .summary td { padding: 4px 6px; border: 1px solid {{ $pc['border_strong'] }}; }
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
                <div class="doc-title">CASH &amp; BANK BOOK</div>
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

    @foreach ($days as $day)
        <div class="day">
            <table class="day-head">
                <tr>
                    <td>{{ $day['date']->format('l, d M Y') }}</td>
                    <td class="right">Opening {{ number_format($day['totals']['opening'], 2) }} &nbsp;·&nbsp; In {{ number_format($day['totals']['in'], 2) }}
                        &nbsp;·&nbsp; Out {{ number_format($day['totals']['out'], 2) }} &nbsp;·&nbsp; Closing {{ number_format($day['totals']['closing'], 2) }}</td>
                </tr>
            </table>
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 45px;">Time</th><th>Type</th><th>Voucher</th><th>Party</th><th>Reference</th><th>Remarks</th>
                        <th class="right">In</th><th class="right">Out</th><th class="right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($day['accounts'] as $a)
                        <tr class="acct"><td colspan="8">{{ $a['method'] }} › {{ $a['account'] }} — opening</td><td class="right">{{ number_format($a['opening'], 2) }}</td></tr>
                        @foreach ($a['rows'] as $r)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($r['datetime'])->format('h:i A') }}</td>
                                <td>{{ $r['type'] }}</td>
                                <td>{{ $r['voucher'] }}</td>
                                <td>{{ $r['party'] ?: '-' }}</td>
                                <td>{{ $r['reference'] ?: '-' }}</td>
                                <td class="muted">{{ $r['remarks'] }}</td>
                                <td class="right in">{{ $m($r['in']) }}</td>
                                <td class="right out">{{ $m($r['out']) }}</td>
                                <td class="right bold {{ $r['balance'] < 0 ? 'neg' : '' }}">{{ number_format($r['balance'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="close">
                            <td colspan="6">Closing — {{ $a['account'] }}</td>
                            <td class="right">{{ $m($a['in']) }}</td>
                            <td class="right">{{ $m($a['out']) }}</td>
                            <td class="right {{ $a['closing'] < 0 ? 'neg' : '' }}">{{ number_format($a['closing'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="muted" style="text-align: center; padding: 10px;">No liquid asset balance or movement.</td></tr>
                    @endforelse
                    @foreach ($day['methods'] as $mt)
                        <tr class="method">
                            <td colspan="6">{{ $mt['method'] }} total (opening {{ number_format($mt['opening'], 2) }})</td>
                            <td class="right">{{ $m($mt['in']) }}</td>
                            <td class="right">{{ $m($mt['out']) }}</td>
                            <td class="right">{{ number_format($mt['closing'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="6">Day total — closing becomes next day's opening</td>
                        <td class="right">{{ $m($day['totals']['in']) }}</td>
                        <td class="right">{{ $m($day['totals']['out']) }}</td>
                        <td class="right">{{ number_format($day['totals']['closing'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach

    <table class="summary">
        <tr><th>Type</th><th class="right">Entries</th><th class="right">In</th><th class="right">Out</th></tr>
        @foreach ($by_type as $t)
            <tr><td>{{ $t['type'] }}</td><td class="right">{{ $t['count'] }}</td><td class="right">{{ $m($t['in']) }}</td><td class="right">{{ $m($t['out']) }}</td></tr>
        @endforeach
        <tr class="bold">
            <td>Opening {{ number_format($totals['opening'], 2) }} / Closing {{ number_format($totals['closing'], 2) }}</td>
            <td class="right">{{ $totals['entries'] }}</td><td class="right">{{ number_format($totals['in'], 2) }}</td><td class="right">{{ number_format($totals['out'], 2) }}</td>
        </tr>
    </table>

    <div class="footer">{{ $company['name'] }} &nbsp;·&nbsp; Daily Cash &amp; Bank Book</div>
</body>
</html>
