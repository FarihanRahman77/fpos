<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Transactions Ledger</title>
    @php
        $m = fn ($v) => number_format((float) $v, 2);
        $b = fn ($v) => (float) $v == 0 ? '' : number_format((float) $v, 2);
    @endphp
    <style>
        @page { margin: 22px 24px 44px 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5px; color: {{ $pc['ink'] }}; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: {{ $pc['muted'] }}; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }

        .company-name { font-size: 14px; font-weight: bold; }
        .doc-title { font-size: 14px; font-weight: bold; color: {{ $pc['accent'] }}; letter-spacing: 1px; }
        .rule { border-top: 2px solid {{ $pc['accent'] }}; margin: 6px 0 6px 0; }
        .meta td { padding: 1px 8px 1px 0; vertical-align: top; }
        .meta .k { font-weight: bold; width: 70px; }

        .section { margin-top: 12px; }
        .grid th, .grid td { border: 1px solid {{ $pc['border_strong'] }}; padding: 3px 4px; vertical-align: top; }
        .grid .band th { background: {{ $pc['soft'] }}; font-size: 9px; text-align: center; padding: 4px; }
        .grid .cols th { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; font-size: 7px; text-align: left; }
        .grid .cols th.right { text-align: right; }
        .grid td.num { text-align: right; white-space: nowrap; }
        .grid .sub { color: {{ $pc['muted'] }}; font-size: 6.5px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .sum { margin-top: 6px; page-break-inside: avoid; }
        .sum td { padding: 1.5px 0; font-weight: bold; }
        .sum td.k { width: 26%; }
        .sum td.v { width: 22%; padding-left: 4px; }

        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7px; color: {{ $pc['faint'] }}; }
    </style>
</head>
<body>

    <table>
        <tr>
            <td style="width: 62%; vertical-align: top;">
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
                <div class="doc-title">TRANSACTIONS LEDGER</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="meta">
        <tr><td class="k">Printed Date</td><td>: {{ $printedAt }}</td></tr>
        <tr><td class="k">Printed By</td><td>: {{ $printedBy ?: '-' }}</td></tr>
        @foreach ($filters_text as $label => $value)
            <tr><td class="k">{{ $label }}</td><td>: {{ $value }}</td></tr>
        @endforeach
    </table>

    @foreach ($methods as $sec)
        @php $label = $sec['method']; @endphp
        <div class="section">
            <table class="grid">
                <thead>
                    <tr class="band"><th colspan="7">{{ $label }} Report</th></tr>
                    <tr class="cols">
                        <th style="width: 4%;">SL</th>
                        <th style="width: 31%;">Particulars</th>
                        <th style="width: 23%;">Remarks</th>
                        <th style="width: 11%;">Voucher Type</th>
                        <th class="right" style="width: 10%;">{{ $label }} In</th>
                        <th class="right" style="width: 10%;">{{ $label }} Out</th>
                        <th class="right" style="width: 11%;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sec['rows'] as $i => $r)
                        <tr>
                            <td class="center">{{ $i + 1 }}</td>
                            <td>
                                {{ $r['particulars'] }}
                                <div class="sub">{{ $r['datetime']->format('d M Y h:i A') }}{{ $r['refs'] ? ' · ' . $r['refs'] : '' }}</div>
                            </td>
                            <td>
                                {{ $r['account'] }}
                                @if ($r['remarks'])<div class="sub">{{ $r['remarks'] }}</div>@endif
                            </td>
                            <td>{{ $r['type'] }}</td>
                            <td class="num">{{ $b($r['in']) }}</td>
                            <td class="num">{{ $b($r['out']) }}</td>
                            <td class="num">{{ $m($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="center muted">No transactions in this period</td></tr>
                    @endforelse
                </tbody>
            </table>

            <table class="sum">
                <tr>
                    <td class="k">Today {{ $label }} In</td><td class="v">: {{ $m($sec['in']) }}</td>
                    <td class="k">{{ $label }} Opening Balance</td><td class="v">: {{ $m($sec['opening']) }}</td>
                </tr>
                <tr>
                    <td class="k">Today {{ $label }} Out</td><td class="v">: {{ $m($sec['out']) }}</td>
                    <td class="k">Today {{ $label }} Closing Balance</td><td class="v">: {{ $m($sec['period']) }}</td>
                </tr>
                <tr>
                    <td class="k">Today {{ $label }} Closing Balance</td><td class="v">: {{ $m($sec['period']) }}</td>
                    <td class="k">Net {{ $label }} Closing Balance</td><td class="v">: {{ $m($sec['closing']) }}</td>
                </tr>
                <tr>
                    <td class="k">Net {{ $label }} Closing Balance</td><td class="v">: {{ $m($sec['closing']) }}</td>
                    <td></td><td></td>
                </tr>
            </table>
        </div>
    @endforeach

    <div class="section">
        <table class="grid">
            <thead>
                <tr class="band"><th colspan="7">Closing Summary</th></tr>
                <tr class="cols">
                    <th style="width: 4%;">SL</th>
                    <th style="width: 18%;">Payment Method</th>
                    <th style="width: 26%;">Ledger Account</th>
                    <th class="right" style="width: 13%;">Opening Balance</th>
                    <th class="right" style="width: 13%;">In</th>
                    <th class="right" style="width: 13%;">Out</th>
                    <th class="right" style="width: 13%;">Closing Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary as $i => $a)
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>{{ $a['method'] }}</td>
                        <td>{{ $a['account'] }}{{ $a['code'] ? ' (' . $a['code'] . ')' : '' }}</td>
                        <td class="num">{{ $m($a['opening']) }}</td>
                        <td class="num">{{ $m($a['in']) }}</td>
                        <td class="num">{{ $m($a['out']) }}</td>
                        <td class="num bold">{{ $m($a['closing']) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" class="right bold">Total</td>
                    <td class="num bold">{{ $m($totals['opening']) }}</td>
                    <td class="num bold">{{ $m($totals['in']) }}</td>
                    <td class="num bold">{{ $m($totals['out']) }}</td>
                    <td class="num bold">{{ $m($totals['closing']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        {{ $company['name'] }} · Transactions Ledger · {{ $from->format('d M Y') }}{{ $from->isSameDay($to) ? '' : ' – ' . $to->format('d M Y') }}
    </div>
</body>
</html>
