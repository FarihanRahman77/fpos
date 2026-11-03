@extends('pdf.layout')
@php
    $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
    $doc = [
        'title'     => 'Cash & Bank Book',
        'info'      => $filters_text,
        'printedAt' => $printedAt,
        'signatures' => ['Prepared by' => auth()->user()->name ?? '', 'Checked by' => '', 'Approved by' => ''],
    ];
@endphp

@section('content')
    @foreach ($days as $day)
        <div class="pdf-h">
            {{ $day['date']->format('l, d M Y') }}
            <span class="meta">&nbsp;&nbsp; Opening {{ number_format($day['totals']['opening'], 2) }} &nbsp;·&nbsp; In {{ number_format($day['totals']['in'], 2) }}
                &nbsp;·&nbsp; Out {{ number_format($day['totals']['out'], 2) }} &nbsp;·&nbsp; Closing {{ number_format($day['totals']['closing'], 2) }}</span>
        </div>
        <table class="pdf-table compact">
            <thead>
                <tr>
                    <th style="width: 6%;">Time</th>
                    <th style="width: 11%;">Type</th>
                    <th style="width: 9%;">Voucher</th>
                    <th style="width: 15%;">Party</th>
                    <th style="width: 10%;">Reference</th>
                    <th>Remarks</th>
                    <th class="num" style="width: 9%;">In</th>
                    <th class="num" style="width: 9%;">Out</th>
                    <th class="num" style="width: 10%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($day['accounts'] as $a)
                    <tr class="group"><td colspan="8">{{ $a['method'] }} › {{ $a['account'] }} — opening</td><td class="num">{{ number_format($a['opening'], 2) }}</td></tr>
                    @foreach ($a['rows'] as $r)
                        <tr>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($r['datetime'])->format('h:i A') }}</td>
                            <td>{{ $r['type'] }}</td>
                            <td>{{ $r['voucher'] }}</td>
                            <td>{{ $r['party'] ?: '-' }}</td>
                            <td>{{ $r['reference'] ?: '-' }}</td>
                            <td class="muted">{{ $r['remarks'] }}</td>
                            <td class="num pos">{{ $m($r['in']) }}</td>
                            <td class="num neg">{{ $m($r['out']) }}</td>
                            <td class="num b {{ $r['balance'] < 0 ? 'neg' : '' }}">{{ number_format($r['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal">
                        <td colspan="6">Closing — {{ $a['account'] }}</td>
                        <td class="num">{{ $m($a['in']) }}</td>
                        <td class="num">{{ $m($a['out']) }}</td>
                        <td class="num {{ $a['closing'] < 0 ? 'neg' : '' }}">{{ number_format($a['closing'], 2) }}</td>
                    </tr>
                @empty
                    <tr class="empty"><td colspan="9">No liquid asset balance or movement.</td></tr>
                @endforelse
                @foreach ($day['methods'] as $mt)
                    <tr class="subtotal">
                        <td colspan="6">{{ $mt['method'] }} total (opening {{ number_format($mt['opening'], 2) }})</td>
                        <td class="num">{{ $m($mt['in']) }}</td>
                        <td class="num">{{ $m($mt['out']) }}</td>
                        <td class="num">{{ number_format($mt['closing'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="6">Day total — closing becomes next day's opening</td>
                    <td class="num">{{ $m($day['totals']['in']) }}</td>
                    <td class="num">{{ $m($day['totals']['out']) }}</td>
                    <td class="num">{{ number_format($day['totals']['closing'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <div class="pdf-h">Summary by type</div>
    <table class="pdf-table avoid" style="width: 55%;">
        <thead>
            <tr><th>Type</th><th class="num">Entries</th><th class="num">In</th><th class="num">Out</th></tr>
        </thead>
        <tbody>
            @foreach ($by_type as $bt)
                <tr><td>{{ $bt['type'] }}</td><td class="num">{{ $bt['count'] }}</td><td class="num">{{ $m($bt['in']) }}</td><td class="num">{{ $m($bt['out']) }}</td></tr>
            @endforeach
            <tr class="total">
                <td>Opening {{ number_format($totals['opening'], 2) }} / Closing {{ number_format($totals['closing'], 2) }}</td>
                <td class="num">{{ $totals['entries'] }}</td><td class="num">{{ number_format($totals['in'], 2) }}</td><td class="num">{{ number_format($totals['out'], 2) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
