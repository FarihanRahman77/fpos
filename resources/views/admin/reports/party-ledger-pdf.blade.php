@extends('pdf.layout')
@php
    $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
    $bal = $balance_text;
    $doc = [
        'title'     => $config['title'],
        'info'      => $filters_text,
        'printedAt' => $printedAt,
        'signatures' => ['Prepared by' => auth()->user()->name ?? '', 'Checked by' => '', 'Approved by' => ''],
    ];
@endphp

@section('content')
    @forelse ($items as $item)
        <div class="pdf-h">
            {{ $item['name'] }}
            <span class="meta">
                @if ($item['code']) &nbsp; {{ $item['code'] }} @endif
                &nbsp;·&nbsp; {{ $item['type'] }}
                @if ($item['phone']) &nbsp;·&nbsp; {{ $item['phone'] }} @endif
                &nbsp;·&nbsp; {{ $balance_label($item['closing']) }}
            </span>
        </div>
        <table class="pdf-table compact">
            <thead>
                <tr>
                    <th style="width: 10%;">Date</th>
                    <th style="width: 11%;">Voucher</th>
                    <th style="width: 15%;">Particulars</th>
                    <th style="width: 11%;">Reference</th>
                    <th>Remarks</th>
                    <th class="num" style="width: 11%;">Debit</th>
                    <th class="num" style="width: 11%;">Credit</th>
                    <th class="num" style="width: 13%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="opening">
                    <td colspan="7">Balance brought forward (before {{ $from->format('d M Y') }})</td>
                    <td class="num b">{{ $bal($item['opening']) }}</td>
                </tr>
                @forelse ($item['rows'] as $r)
                    <tr>
                        <td class="nowrap">{{ $r['date']->format('d M Y') }}</td>
                        <td>{{ $r['voucher'] ?: '-' }}</td>
                        <td>{{ $r['particular'] }}</td>
                        <td>{{ $r['reference'] ?: '-' }}</td>
                        <td class="muted">{{ $r['note'] }}</td>
                        <td class="num">{{ $m($r['dr']) }}</td>
                        <td class="num">{{ $m($r['cr']) }}</td>
                        <td class="num b">{{ $bal($r['balance']) }}</td>
                    </tr>
                @empty
                    <tr class="empty"><td colspan="8">No transactions in this period</td></tr>
                @endforelse
                <tr class="total">
                    <td colspan="5" class="r">Total / Closing balance</td>
                    <td class="num">{{ number_format($item['dr'], 2) }}</td>
                    <td class="num">{{ number_format($item['cr'], 2) }}</td>
                    <td class="num">{{ $bal($item['closing']) }}</td>
                </tr>
            </tbody>
        </table>
    @empty
        <table class="pdf-table"><tbody><tr class="empty"><td>No {{ $ledger }} transactions match these filters.</td></tr></tbody></table>
    @endforelse

    <div class="avoid">
    <div class="pdf-h">Summary</div>
    <table class="pdf-kv avoid" style="width: 55%;">
        <tr><td class="k">Parties</td><td class="v num">{{ $totals['parties'] }}</td></tr>
        <tr><td class="k">Balance brought forward</td><td class="v num">{{ $bal($totals['opening']) }}</td></tr>
        <tr><td class="k">Total debit</td><td class="v num">{{ number_format($totals['dr'], 2) }}</td></tr>
        <tr><td class="k">Total credit</td><td class="v num">{{ number_format($totals['cr'], 2) }}</td></tr>
        <tr><td class="k">Total {{ strtolower($config['labels']['Dr']) }} (Dr balances)</td><td class="v num">{{ number_format($totals['balance_dr'], 2) }}</td></tr>
        <tr><td class="k">Total {{ strtolower($config['labels']['Cr']) }} (Cr balances)</td><td class="v num">{{ number_format($totals['balance_cr'], 2) }}</td></tr>
        <tr><td class="k b">Net closing balance</td><td class="v num b">{{ $bal($totals['closing']) }}</td></tr>
    </table>
    </div>
@endsection
