<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
        <style>
        @page { margin: 26px 28px 46px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: {{ $pc['ink'] }}; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: {{ $pc['muted'] }}; }
        .r { text-align: right; }
        .company { font-size: 14px; font-weight: bold; }
        .title { font-size: 14px; font-weight: bold; color: {{ $pc['accent'] }}; text-align: right; }
        .rule { border-top: 2px solid {{ $pc['accent'] }}; margin: 6px 0; }
        .meta td { padding: 1px 8px 1px 0; }
        .meta .k { font-weight: bold; width: 80px; }
        .cards { margin: 8px 0; }
        .cards td { border: 1px solid {{ $pc['border'] }}; background: {{ $pc['soft'] }}; padding: 6px 8px; }
        .cards .v { font-size: 11px; font-weight: bold; margin-top: 2px; }
        .grid { margin-top: 6px; }
        .grid th { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; padding: 4px; font-size: 7px; text-align: left; }
        .grid th.r { text-align: right; }
        .grid td { padding: 3.5px 4px; border-bottom: 1px solid {{ $pc['border'] }}; }
        .grid .grp td { background: {{ $pc['soft'] }}; font-weight: bold; }
        .grid .sub td, .grid .tot td { background: {{ $pc['soft'] }}; font-weight: bold; }
        .grid .tot td { border-top: 1.5px solid {{ $pc['border_strong'] }}; }
        .neg { color: {{ $pc['danger'] }}; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .notes { margin-top: 10px; color: {{ $pc['muted'] }}; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 7px; color: {{ $pc['faint'] }}; }
    </style>
</head>
<body>
    @php
        $brFmt = fn ($v, $type) => \App\Http\Controllers\Admin\BusinessReportController::fmt($v, $type);
        $keys = array_keys($columns);
        $isNum = fn ($k) => in_array($columns[$k][1], ['money', 'qty', 'int', 'pct', 'heat'], true);
        $td = function ($row, $k) use ($columns, $isNum, $brFmt) {
            $v = $row[$k] ?? null;
            $neg = in_array($columns[$k][1], ['money', 'qty'], true) && is_numeric($v) && (float) $v < 0;
            return '<td class="' . ($isNum($k) ? 'r' : '') . ($neg ? ' neg' : '') . '">' . e($brFmt($v, $columns[$k][1])) . '</td>';
        };
    @endphp

    <table>
        <tr>
            <td><div class="company">{{ $company }}</div></td>
            <td class="title">{{ strtoupper($title) }}</td>
        </tr>
    </table>
    <div class="rule"></div>
    <table class="meta">
        @foreach ($filters_text as $label => $value)
            <tr><td class="k">{{ $label }}</td><td>: {{ $value }}</td></tr>
        @endforeach
        <tr><td class="k">Printed</td><td>: {{ $printedAt }}{{ $printedBy ? ' by ' . $printedBy : '' }}</td></tr>
    </table>

    @if ($cards)
        <table class="cards">
            <tr>
                @foreach ($cards as $card)
                    <td>
                        <div class="muted">{{ $card[0] }}</div>
                        <div class="v">{{ is_numeric($card[1]) || $card[1] === null ? $brFmt($card[1] ?? 0, $card[2]) : $card[1] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="grid">
        <thead>
            <tr>@foreach ($columns as $k => [$label, $type])<th class="{{ $isNum($k) ? 'r' : '' }}">{{ $label }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @if ($groups)
                @foreach ($groups as $g)
                    <tr class="grp"><td colspan="{{ count($keys) }}">{{ $g['name'] }}</td></tr>
                    @foreach ($g['rows'] as $row)
                        <tr>@foreach ($keys as $k){!! $td($row, $k) !!}@endforeach</tr>
                    @endforeach
                    @if (!empty($g['totals']) && count($groups) > 1)
                        <tr class="sub">@foreach ($keys as $i => $k)@if ($i === 0)<td>Subtotal</td>@else{!! $td($g['totals'], $k) !!}@endif @endforeach</tr>
                    @endif
                @endforeach
            @else
                @forelse ($rows as $row)
                    <tr>@foreach ($keys as $k){!! $td($row, $k) !!}@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($keys) }}" class="muted">Nothing to show for these filters.</td></tr>
                @endforelse
            @endif
            @if ($totals)
                <tr class="tot">@foreach ($keys as $i => $k)@if ($i === 0)<td>Total</td>@else{!! $td($totals, $k) !!}@endif @endforeach</tr>
            @endif
        </tbody>
    </table>

    @if ($notes)
        <div class="notes">@foreach ($notes as $n)<div>• {{ $n }}</div>@endforeach</div>
    @endif

    <div class="footer">{{ $company }} · {{ $title }} · {{ $printedAt }}</div>
</body>
</html>
