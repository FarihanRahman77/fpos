@extends('pdf.layout')
@php
    $brFmt = fn ($v, $type) => \App\Http\Controllers\Admin\BusinessReportController::fmt($v, $type);
    $keys = array_keys($columns);
    $isNum = fn ($k) => in_array($columns[$k][1], ['money', 'qty', 'int', 'pct', 'heat'], true);
    $td = function ($row, $k) use ($columns, $isNum, $brFmt) {
        $v = $row[$k] ?? null;
        $neg = in_array($columns[$k][1], ['money', 'qty'], true) && is_numeric($v) && (float) $v < 0;
        return '<td class="' . ($isNum($k) ? 'num' : '') . ($neg ? ' neg' : '') . '">' . e($brFmt($v, $columns[$k][1])) . '</td>';
    };
    $wide = count($keys) > 8;
    $cardTone = ['success' => 'pos', 'danger' => 'neg', 'warning' => 'warn', 'accent' => 'accent'];
    $cardsPerRow = $wide ? 6 : 4;
    $doc = [
        'title'      => str_replace('-', "\u{2011}", $title),   // non-breaking hyphen: "(Z-Report)" wraps as one word
        'info'       => $filters_text,
        'signatures' => ['Prepared by' => $printedBy ?? '', 'Checked by' => '', 'Approved by' => ''],
        'printedBy'  => $printedBy ?: null,
        'printedAt'  => $printedAt,
    ];
@endphp

@section('styles')
    <style>
        .pdf-cards td.blank { border: 0; background: {{ $pdfT['paper'] }}; }
        .pdf-cards { margin-bottom: 6px; }
        .pdf-cards.last { margin-bottom: 12px; }
        .pdf-notes { margin-top: 4px; page-break-inside: avoid; }
    </style>
@endsection

@section('content')
    @if ($cards)
        @foreach (array_chunk($cards, $cardsPerRow) as $cardRow)
            <table class="pdf-cards {{ $loop->last ? 'last' : '' }}">
                <tr>
                    @foreach ($cardRow as $card)
                        <td style="width: {{ floor(100 / $cardsPerRow) }}%;">
                            <div class="pdf-label">{{ $card[0] }}</div>
                            <div class="pdf-card-v {{ $cardTone[$card[3] ?? ''] ?? '' }}">{{ is_numeric($card[1]) || $card[1] === null ? $brFmt($card[1] ?? 0, $card[2]) : $card[1] }}</div>
                        </td>
                    @endforeach
                    @if (count($cards) > $cardsPerRow)
                        @for ($k = count($cardRow); $k < $cardsPerRow; $k++)<td class="blank" style="width: {{ floor(100 / $cardsPerRow) }}%;"></td>@endfor
                    @endif
                </tr>
            </table>
        @endforeach
    @endif

    <table class="pdf-table {{ count($keys) > 6 ? 'compact' : '' }}">
        <thead>
            <tr>@foreach ($columns as $k => [$label, $type])<th class="{{ $isNum($k) ? 'num' : '' }}">{{ $label }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @if ($groups)
                @foreach ($groups as $g)
                    <tr class="group"><td colspan="{{ count($keys) }}">{{ $g['name'] }}</td></tr>
                    @foreach ($g['rows'] as $row)
                        <tr>@foreach ($keys as $k){!! $td($row, $k) !!}@endforeach</tr>
                    @endforeach
                    @if (!empty($g['totals']) && count($groups) > 1)
                        <tr class="subtotal">@foreach ($keys as $i => $k)@if ($i === 0)<td>Subtotal</td>@else{!! $td($g['totals'], $k) !!}@endif @endforeach</tr>
                    @endif
                @endforeach
            @else
                @forelse ($rows as $row)
                    <tr>@foreach ($keys as $k){!! $td($row, $k) !!}@endforeach</tr>
                @empty
                    <tr class="empty"><td colspan="{{ count($keys) }}">Nothing to show for these filters.</td></tr>
                @endforelse
            @endif
            @if ($totals)
                <tr class="total">@foreach ($keys as $i => $k)@if ($i === 0)<td>Total</td>@else{!! $td($totals, $k) !!}@endif @endforeach</tr>
            @endif
        </tbody>
    </table>

    @if ($notes)
        @include('pdf.partials.notes', ['note' => ['Notes', implode("\n", array_map(fn ($n) => '• ' . $n, $notes))]])
    @endif
@endsection
