@extends('pdf.layout')
@php
    $m = fn ($v) => abs((float) $v) < 0.005 ? '-' : number_format((float) $v, 2);
    $withBranch = $lines && $lines[0]['branch'] !== '';
    $span = 4 + ($withBranch ? 1 : 0);
    $doc = [
        'title'     => 'Suspense Statement',
        'number'    => $person->name . ($person->code ? ' · ' . $person->code : ''),
        'info'      => $filters_text,
        'printedAt' => $printedAt,
        'printedBy' => $printedBy,
        'signatures' => ['Prepared by' => $printedBy ?? '', 'Checked by' => '', 'Approved by' => ''],
    ];
@endphp

@section('content')
    @include('pdf.partials.party', ['parties' => [
        ['label' => $person_type, 'kv' => array_filter([
            'Name'  => $person->name,
            'Code'  => $person->code ?: '-',
            'Phone' => $person->phone ?: '-',
            'NID'   => $investor ? ($person->nid ?: '-') : null,
            'Share' => $investor && $person->share_percent !== null ? rtrim(rtrim(number_format((float) $person->share_percent, 2), '0'), '.') . '%' : null,
        ])],
        ['label' => 'Summary', 'kv' => array_filter([
            'Opening outstanding' => number_format($opening['balance'], 2),
            'Paid / withdrawn'    => number_format($totals['advance'], 2),
            'Returned / adjusted' => number_format($totals['settled'], 2),
            'Closing outstanding' => number_format($closing['balance'], 2),
            'Closing capital'     => $investor ? number_format($closing['capital'], 2) : null,
        ], fn ($v) => $v !== null)],
    ]])

    <table class="pdf-table compact">
        <thead>
            <tr>
                <th style="width: 10%;">Date</th>
                <th style="width: 10%;">Voucher</th>
                <th style="width: 11%;">Type</th>
                <th>Particulars</th>
                @if ($withBranch)<th style="width: 10%;">Branch</th>@endif
                <th class="num" style="width: 10%;">Paid / Withdrawn</th>
                <th class="num" style="width: 10%;">Returned / Adjusted</th>
                <th class="num" style="width: 10%;">Outstanding</th>
                @if ($investor)
                    <th class="num" style="width: 9%;">Capital +/−</th>
                    <th class="num" style="width: 10%;">Capital</th>
                @endif
            </tr>
        </thead>
        <tbody>
            <tr class="opening">
                <td colspan="{{ $span + 2 }}">Opening balance{{ $from ? ' (before ' . \Carbon\Carbon::parse($from)->format('d M Y') . ')' : '' }}</td>
                <td class="num b {{ $opening['balance'] < 0 ? 'neg' : '' }}">{{ number_format($opening['balance'], 2) }}</td>
                @if ($investor)
                    <td></td>
                    <td class="num b">{{ number_format($opening['capital'], 2) }}</td>
                @endif
            </tr>
            @forelse ($lines as $l)
                <tr>
                    <td class="nowrap">{{ $l['date']->format('d M Y') }}</td>
                    <td>{{ $l['voucher'] }}</td>
                    <td>{{ $l['type'] }}</td>
                    <td class="muted">{{ $l['particulars'] }}</td>
                    @if ($withBranch)<td>{{ $l['branch'] }}</td>@endif
                    <td class="num">{{ $m($l['advance']) }}</td>
                    <td class="num">{{ $m($l['settled']) }}</td>
                    <td class="num b {{ $l['balance'] < 0 ? 'neg' : '' }}">{{ number_format($l['balance'], 2) }}</td>
                    @if ($investor)
                        <td class="num {{ $l['capital_change'] < 0 ? 'neg' : ($l['capital_change'] > 0 ? 'pos' : '') }}">{{ $m($l['capital_change']) }}</td>
                        <td class="num">{{ number_format($l['capital'], 2) }}</td>
                    @endif
                </tr>
            @empty
                <tr class="empty"><td colspan="{{ $span + 3 + ($investor ? 2 : 0) }}">No entries in this period</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="{{ $span }}" class="r">Total / Closing balance</td>
                <td class="num">{{ number_format($totals['advance'], 2) }}</td>
                <td class="num">{{ number_format($totals['settled'], 2) }}</td>
                <td class="num {{ $closing['balance'] < 0 ? 'neg' : '' }}">{{ number_format($closing['balance'], 2) }}</td>
                @if ($investor)
                    <td class="num">{{ $m($totals['capital_in'] - $totals['capital_out']) }}</td>
                    <td class="num">{{ number_format($closing['capital'], 2) }}</td>
                @endif
            </tr>
        </tbody>
    </table>

    @include('pdf.partials.notes', ['note' => ['Notes',
        'Outstanding = paid / withdrawn − returned − adjusted' . ($investor ? '' : ' − deposited') . '. A negative outstanding is money the office owes the person.'
        . ($investor ? "\nCapital = opening capital + investments − capital withdrawals." : ''),
    ]])
@endsection
