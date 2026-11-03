@extends('pdf.layout')
@php
    $m = fn ($v) => $v === null ? '' : number_format((float) $v, 2);
    $span = count($columns) + 2;
    $valueWidth = count($columns) > 1 ? 18 : 22;
    $doc = [
        'title'      => $title,
        'number'     => $period,
        'info'       => $filters_text,
        'signatures' => ['Prepared by' => $printedBy ?? '', 'Checked by' => '', 'Approved by' => ''],
        'printedBy'  => $printedBy ?: null,
        'printedAt'  => $printedAt,
    ];
@endphp

@section('styles')
    <style>
        .pdf-table td.code { color: {{ $pdfT['muted'] }}; font-size: 7.2px; white-space: nowrap; }
        .pdf-table tbody tr.section td { font-weight: bold; color: {{ $pdfT['ink'] }}; background: {{ $pdfT['accent_soft'] }}; padding-top: 6px; }
        .pdf-table tr.head td { font-weight: bold; color: {{ $pdfT['accent'] }}; }
        .pdf-table tbody tr.grand td { font-weight: bold; font-size: 9.2px; color: {{ $pdfT['ink'] }}; background: {{ $pdfT['accent_soft'] }};
            border-top: 1.2px solid {{ $pdfT['rule_strong'] }}; border-bottom: 2.4px double {{ $pdfT['rule_strong'] }}; }
        .pdf-table tr.group td { text-transform: none; letter-spacing: 0; font-size: 8.4px; }
        .ind-1 { padding-left: 16px !important; }
        .ind-2 { padding-left: 28px !important; }
        .pdf-check { margin: 6px 0 0 0; padding: 5px 8px; font-weight: bold; border-left: 3px solid {{ $pdfT['rule_strong'] }}; }
        .pdf-check.ok { color: {{ $pdfT['success'] }}; border-left-color: {{ $pdfT['success'] }}; }
        .pdf-check.bad { color: {{ $pdfT['danger'] }}; border-left-color: {{ $pdfT['danger'] }}; }
        .pdf-notes { margin-top: 12px; page-break-inside: avoid; }
    </style>
@endsection

@section('content')
    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 12%;">Code</th>
                <th>Particulars</th>
                @foreach ($columns as $c)
                    <th class="num" style="width: {{ $valueWidth }}%;">{{ $c }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($sections as $sec)
                <tr class="section"><td colspan="{{ $span }}">{{ $sec['title'] }}</td></tr>
                @foreach ($sec['lines'] as $l)
                    <tr class="{{ $l['kind'] }}">
                        <td class="code">{{ $l['code'] ?? '' }}</td>
                        <td class="ind-{{ (int) ($l['indent'] ?? 0) }}">{{ $l['label'] }}</td>
                        @foreach ($columns as $i => $c)
                            @php $val = $l['values'][$i] ?? null; @endphp
                            <td class="num {{ $val !== null && $val < 0 ? 'neg' : '' }}">{{ $m($val) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
            @if ($grand)
                <tr class="grand">
                    <td></td>
                    <td>{{ $grand['label'] }}</td>
                    @foreach ($grand['values'] as $val)
                        <td class="num {{ $val < 0 ? 'neg' : '' }}">{{ $m($val) }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @foreach ($checks as $c)
        <div class="pdf-check {{ $c['ok'] ? 'ok' : 'bad' }}">{{ $c['ok'] ? 'Balanced' : 'Not balanced' }} — {{ $c['label'] }}: {{ $c['detail'] }}</div>
    @endforeach

    @if ($notes)
        @include('pdf.partials.notes', ['note' => ['Notes', implode("\n", $notes)]])
    @endif
@endsection
