<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary Sheet</title>
    @php
        $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
        $labelSpan = count($columns) - count($money_keys) + 1;
    @endphp
    <style>
        @page { margin: 24px 26px 46px 26px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: {{ $pc['ink'] }}; }
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
        .grid th { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; padding: 4px 4px; font-size: 7px; text-transform: uppercase; text-align: left; border: 1px solid {{ $pc['accent'] }}; }
        .grid th.right { text-align: right; }
        .grid td { padding: 5px 4px; border: 1px solid {{ $pc['border'] }}; vertical-align: middle; }
        .grid tr.group td { background: {{ $pc['border'] }}; color: {{ $pc['accent'] }}; font-weight: bold; }
        .grid tr.total td { background: {{ $pc['soft'] }}; font-weight: bold; border-top: 1.5px solid {{ $pc['faint'] }}; }
        .grid tr.grand td { background: {{ $pc['border_strong'] }}; font-weight: bold; font-size: 8.5px; }
        .grid td.sign { width: 70px; }
        .pf { color: {{ $pc['danger'] }}; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .signatures { margin-top: 50px; page-break-inside: avoid; }
        .signatures td { width: 33%; text-align: center; padding: 0 30px; }
        .signatures .line { border-top: 1px solid {{ $pc['secondary'] }}; padding-top: 4px; }

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
                <div class="doc-title">SALARY SHEET</div>
                <div class="bold" style="font-size: 11px; margin-top: 2px;">{{ $month->format('F Y') }}</div>
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
                <th style="width: 20px;">SL</th>
                @foreach ($columns as $key => [$label, $money])
                    <th class="{{ $money ? 'right' : '' }}">{{ $label }}</th>
                @endforeach
                <th>Signature</th>
            </tr>
        </thead>
        <tbody>
            @php $sl = 1; @endphp
            @forelse ($groups as $group)
                <tr class="group"><td colspan="{{ count($columns) + 2 }}">{{ $group['name'] }} ({{ count($group['rows']) }})</td></tr>
                @foreach ($group['rows'] as $r)
                    <tr>
                        <td>{{ $sl++ }}</td>
                        @foreach ($columns as $key => [$label, $money])
                            @if ($money)
                                <td class="right {{ in_array($key, ['net_payable', 'due']) ? 'bold' : '' }} {{ $key === 'pf_amount' ? 'pf' : '' }}">{{ $m($r[$key]) }}</td>
                            @elseif ($key === 'name')
                                <td><span class="bold">{{ $r['name'] }}</span><br><span class="muted">{{ $r['bank'] }} · {{ $r['state'] }}</span></td>
                            @else
                                <td>{{ $r[$key] ?: '-' }}</td>
                            @endif
                        @endforeach
                        <td class="sign"></td>
                    </tr>
                @endforeach
                @if (count($groups) > 1)
                    <tr class="total">
                        <td colspan="{{ $labelSpan }}">Subtotal — {{ $group['name'] }}</td>
                        @foreach ($money_keys as $k)<td class="right">{{ $m($group['totals'][$k]) }}</td>@endforeach
                        <td></td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="{{ count($columns) + 2 }}" class="muted" style="text-align: center; padding: 14px;">No active employees for this month.</td></tr>
            @endforelse
            @if ($groups)
                <tr class="grand">
                    <td colspan="{{ $labelSpan }}">Grand Total ({{ $count }} employees)</td>
                    @foreach ($money_keys as $k)<td class="right">{{ $m($totals[$k]) }}</td>@endforeach
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="line">Prepared by</div></td>
            <td><div class="line">Checked by</div></td>
            <td><div class="line">Approved by</div></td>
        </tr>
    </table>

    <div class="footer">{{ $company['name'] }} &nbsp;·&nbsp; Salary Sheet {{ $month->format('F Y') }}</div>
</body>
</html>
