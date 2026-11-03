@extends('pdf.layout')
@php
    $q = fn ($v) => $v == 0 ? '-' : rtrim(rtrim(number_format($v, 3), '0'), '.');
    $doc = [
        'title'     => 'Stock Ledger',
        'info'      => $filters_text,
        'printedAt' => $printedAt,
        'signatures' => ['Prepared by' => auth()->user()->name ?? '', 'Checked by' => '', 'Approved by' => ''],
    ];
@endphp

@section('content')
    @forelse ($items as $item)
        <div class="pdf-h">
            {{ $item['product'] }}
            <span class="meta">
                @if ($item['sku']) &nbsp; SKU: {{ $item['sku'] }} @endif
                @if ($item['category']) &nbsp;·&nbsp; {{ $item['category'] }} @endif
                @if ($item['brand']) &nbsp;·&nbsp; {{ $item['brand'] }} @endif
                @if ($item['unit']) &nbsp;·&nbsp; Unit: {{ $item['unit'] }} @endif
            </span>
        </div>
        <table class="pdf-table compact">
            <thead>
                <tr>
                    <th style="width: 13%;">Date</th>
                    <th style="width: 11%;">Type</th>
                    <th style="width: 11%;">Reference</th>
                    <th style="width: 18%;">Party</th>
                    <th>Remarks</th>
                    <th class="num" style="width: 8%;">In</th>
                    <th class="num" style="width: 8%;">Out</th>
                    <th class="num" style="width: 9%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="opening">
                    <td colspan="7">Opening balance (before {{ $from->format('d M Y') }})</td>
                    <td class="num b">{{ $q($item['opening']) }}</td>
                </tr>
                @forelse ($item['rows'] as $r)
                    <tr>
                        <td class="nowrap">{{ $r['date']->format('d M Y h:i A') }}</td>
                        <td>{{ $r['type'] }}</td>
                        <td>{{ $r['reference'] ?: '-' }}</td>
                        <td>{{ $r['party'] ?: '-' }}</td>
                        <td class="muted">{{ $r['remarks'] }}</td>
                        <td class="num pos">{{ $q($r['in']) }}</td>
                        <td class="num neg">{{ $q($r['out']) }}</td>
                        <td class="num b {{ $r['balance'] < 0 ? 'neg' : '' }}">{{ $q($r['balance']) }}</td>
                    </tr>
                @empty
                    <tr class="empty"><td colspan="8">No movement in this period</td></tr>
                @endforelse
                <tr class="total">
                    <td colspan="5" class="r">Total / Closing balance</td>
                    <td class="num pos">{{ $q($item['in']) }}</td>
                    <td class="num neg">{{ $q($item['out']) }}</td>
                    <td class="num {{ $item['closing'] < 0 ? 'neg' : '' }}">{{ $q($item['closing']) }}</td>
                </tr>
            </tbody>
        </table>
    @empty
        <table class="pdf-table"><tbody><tr class="empty"><td>No stock movement matches these filters.</td></tr></tbody></table>
    @endforelse

    <div class="avoid">
    <div class="pdf-h">Summary</div>
    <table class="pdf-cards avoid">
        <tr>
            <td><span class="pdf-label">Products / variants</span><div class="pdf-card-v">{{ $totals['items'] }}</div></td>
            <td><span class="pdf-label">Opening balance</span><div class="pdf-card-v">{{ $q($totals['opening']) }}</div></td>
            <td><span class="pdf-label">Total in</span><div class="pdf-card-v">{{ $q($totals['in']) }}</div></td>
            <td><span class="pdf-label">Total out</span><div class="pdf-card-v">{{ $q($totals['out']) }}</div></td>
            <td><span class="pdf-label">Closing balance</span><div class="pdf-card-v">{{ $q($totals['closing']) }}</div></td>
        </tr>
    </table>
    </div>
@endsection
