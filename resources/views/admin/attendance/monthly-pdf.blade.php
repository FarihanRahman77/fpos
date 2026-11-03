@extends('pdf.layout')
@php
    $soft = fn ($hex) => \App\Http\Controllers\Admin\SettingController::mix($hex, '#ffffff', 0.88);
    $doc = [
        'title'      => 'Attendance Report',
        'number'     => $month_label,
        'info'       => ['Month' => $month_label, 'Department' => $department, 'Employees' => count($employees)],
        'signatures' => ['Prepared by' => auth()->user()->name ?? '', 'Checked by' => '', 'Approved by' => ''],
    ];
@endphp

@section('styles')
    <style>
        .att { margin-top: 2px; }
        .att th, .att td { text-align: center; vertical-align: middle; }
        .att th { font-size: 6.2px; padding: 3px 1px; letter-spacing: 0; }
        .att td { font-size: 6.5px; padding: 3px 1px; border: 0.5px solid {{ $pdfT['rule'] }}; }
        .att th.d, .att td.d { width: 15px; }
        .att th.off { background: {{ $pdfT['accent_soft'] }}; color: {{ $pdfT['ink'] }}; }
        .att th.emp, .att td.emp { text-align: left; padding-left: 4px; width: 120px; }
        .att th.n, .att td.n { width: 20px; }
        .att td.emp .sub { font-size: 6px; color: {{ $pdfT['muted'] }}; }
        .att tbody tr td.n { font-weight: bold; color: {{ $pdfT['ink'] }}; background: {{ $pdfT['accent_soft'] }}; }
        .att tbody tr td.c-P { color: {{ $pdfT['success'] }}; }
        .att tbody tr td.c-L, .att tbody tr td.c-HD { color: {{ $pdfT['warning'] }}; background: {{ $soft($pdfT['warning']) }}; font-weight: bold; }
        .att tbody tr td.c-A { color: {{ $pdfT['danger'] }}; background: {{ $soft($pdfT['danger']) }}; font-weight: bold; }
        .att tbody tr td.c-LV { color: {{ $pdfT['accent'] }}; font-weight: bold; }
        .att tbody tr td.c-H, .att tbody tr td.c-W { color: {{ $pdfT['muted'] }}; background: {{ $pdfT['accent_soft'] }}; }
        .legend { margin-top: 4px; font-size: 7.5px; color: {{ $pdfT['muted'] }}; }
        .legend b { color: {{ $pdfT['ink'] }}; }
    </style>
@endsection

@section('content')
    <table class="pdf-table compact att">
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
                <tr class="empty"><td colspan="{{ count($dates) + 8 }}">No employees.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="legend">
        @foreach ($codes as $status => $code)<b>{{ $code }}</b> {{ $status }} &nbsp; @endforeach
        <b>·</b> no attendance taken &nbsp; · &nbsp; P column = present incl. late
    </div>
@endsection
