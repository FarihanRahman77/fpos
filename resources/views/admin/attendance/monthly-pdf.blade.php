<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Attendance {{ $month_label }}</title>
    @include('admin.inventory.partials.invoice-style')
    <style>
        @page { margin: 26px 24px 46px 24px; }
        .grid { margin-top: 16px; }
        .grid th.d, .grid td.d { width: 15px; }
        .grid th { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; font-size: 6.5px; padding: 3px 1px; text-align: center; border: 1px solid {{ $pc['brand'] }}; font-weight: bold; }
        .grid th.off { background: {{ $pc['secondary'] }}; border-color: {{ $pc['secondary'] }}; }
        .grid th.emp, .grid td.emp { text-align: left; padding-left: 4px; width: 120px; min-width: 120px; }
        .grid th.n, .grid td.n { width: 20px; }
        .grid td { font-size: 6.5px; padding: 3px 1px; text-align: center; border: 1px solid {{ $pc['border'] }}; vertical-align: middle; }
        .grid td.emp .sub { font-size: 6px; color: {{ $pc['muted'] }}; }
        .grid td.n { font-weight: bold; background: {{ $pc['soft'] }}; }
        .c-P { color: {{ $pc['success'] }}; }
        .c-L, .c-HD { color: {{ $pc['warning'] }}; background: {{ $pc['warning_soft'] }}; font-weight: bold; }
        .c-A { color: {{ $pc['danger'] }}; background: {{ $pc['danger_soft'] }}; font-weight: bold; }
        .c-LV { color: {{ $pc['accent'] }}; font-weight: bold; }
        .c-H, .c-W { color: {{ $pc['muted'] }}; background: {{ $pc['soft'] }}; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .legend { margin-top: 10px; font-size: 7.5px; color: {{ $pc['muted'] }}; }
        .legend b { color: {{ $pc['ink'] }}; }
    </style>
</head>
<body>
    <div class="inv-footer">
        <table><tr>
            <td>Generated {{ now()->format('d M Y, h:i A') }}{{ auth()->user() ? ' by ' . auth()->user()->name : '' }}</td>
            <td class="r">{{ $generalSetting['company_name'] ?? config('app.name') }}</td>
        </tr></table>
    </div>

    @include('admin.inventory.partials.invoice-header', ['title' => 'Attendance Report', 'number' => $month_label . ' · ' . $department])

    <table class="grid">
        <thead>
            <tr>
                <th class="emp">Employee</th>
                @foreach ($dates as $d)
                    <th class="d {{ $d['holiday'] || $d['weekend'] ? 'off' : '' }}">{{ $d['day'] }}<br>{{ substr($d['dow'], 0, 2) }}</th>
                @endforeach
                <th class="n">P</th><th class="n">L</th><th class="n">HD</th><th class="n">A</th><th class="n">LV</th><th class="n">Off</th><th class="n">OT h</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employees as $e)
                @php $c = $e['counts']; @endphp
                <tr>
                    <td class="emp">{{ $e['name'] }}<div class="sub">{{ $e['code'] }}{{ $e['department'] ? ' · ' . $e['department'] : '' }}</div></td>
                    @foreach ($e['days'] as $d)
                        <td class="d c-{{ $d['code'] }}">{{ $d['code'] }}</td>
                    @endforeach
                    <td class="n">{{ $c['present'] + $c['late'] }}</td>
                    <td class="n">{{ $c['late'] }}</td>
                    <td class="n">{{ $c['half_day'] }}</td>
                    <td class="n">{{ $c['absent'] }}</td>
                    <td class="n">{{ $c['leave'] }}</td>
                    <td class="n">{{ $c['holiday'] + $c['weekly_off'] }}</td>
                    <td class="n">{{ $c['ot_hours'] ? rtrim(rtrim(number_format($c['ot_hours'], 2), '0'), '.') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($dates) + 8 }}">No employees.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="legend">
        @foreach ($codes as $status => $code)<b>{{ $code }}</b> {{ $status }} &nbsp; @endforeach
        <b>·</b> no attendance taken &nbsp; · &nbsp; P column = present incl. late
    </div>
</body>
</html>
