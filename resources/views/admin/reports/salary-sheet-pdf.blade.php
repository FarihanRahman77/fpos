@extends('pdf.layout')
@php
    $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
    $labelSpan = count($columns) - count($money_keys) + 1;
    $doc = [
        'title'     => 'Salary Sheet',
        'number'    => $month->format('F Y'),
        'info'      => $filters_text,
        'printedAt' => $printedAt,
        'signatures' => ['Prepared by' => auth()->user()->name ?? '', 'Checked by' => '', 'Approved by' => ''],
    ];
@endphp

@section('styles')
    <style>
        /* many money columns on A4 landscape: tighter cells, wrapping headers */
        .pdf-table.sheet th { white-space: normal; font-size: 5.4px; letter-spacing: 0; padding: 3px 1.5px; }
        .pdf-table.sheet td { font-size: 5.8px; padding: 3px 1.5px; vertical-align: middle; }
        .pdf-table.sheet td .sub { font-size: 5.6px; }
        .pdf-table.sheet td.sign { width: 40px; }
    </style>
@endsection

@section('content')
    <table class="pdf-table compact sheet">
        <thead>
            <tr>
                <th style="width: 20px;">SL</th>
                @foreach ($columns as $key => [$label, $money])
                    <th class="{{ $money ? 'num' : '' }}" @if ($key === 'name') style="width: 8%;" @endif>{{ $label }}</th>
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
                        <td class="muted">{{ $sl++ }}</td>
                        @foreach ($columns as $key => [$label, $money])
                            @if ($money)
                                <td class="num {{ in_array($key, ['net_payable', 'due']) ? 'b' : '' }} {{ $key === 'pf_amount' ? 'neg' : '' }}">{{ $m($r[$key]) }}</td>
                            @elseif ($key === 'name')
                                <td><span class="b">{{ $r['name'] }}</span><br><span class="sub">{{ $r['bank'] }} · {{ $r['state'] }}</span></td>
                            @else
                                <td>{{ $r[$key] ?: '-' }}</td>
                            @endif
                        @endforeach
                        <td class="sign"></td>
                    </tr>
                @endforeach
                @if (count($groups) > 1)
                    <tr class="subtotal">
                        <td colspan="{{ $labelSpan }}">Subtotal — {{ $group['name'] }}</td>
                        @foreach ($money_keys as $k)<td class="num">{{ $m($group['totals'][$k]) }}</td>@endforeach
                        <td></td>
                    </tr>
                @endif
            @empty
                <tr class="empty"><td colspan="{{ count($columns) + 2 }}">No active employees for this month.</td></tr>
            @endforelse
            @if ($groups)
                <tr class="total">
                    <td colspan="{{ $labelSpan }}">Grand Total ({{ $count }} employees)</td>
                    @foreach ($money_keys as $k)<td class="num">{{ $m($totals[$k]) }}</td>@endforeach
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    @if ($groups && isset($totals['net_payable']))
        <table class="avoid">
            <tr>
                <td style="width: 60%;">@include('pdf.partials.words', ['w' => ['amount' => $totals['net_payable']]])</td>
                <td></td>
            </tr>
        </table>
    @endif
@endsection
