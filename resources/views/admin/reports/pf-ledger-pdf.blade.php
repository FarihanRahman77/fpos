@extends('pdf.layout')
@php
    $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
    $doc = [
        'title'     => 'PF Ledger',
        'number'    => $employee->name . ' (' . $employee->employee_code . ')',
        'info'      => $filters_text,
        'printedAt' => $printedAt,
        'signatures' => ['Prepared by' => auth()->user()->name ?? '', 'Employee signature' => '', 'Authorised signature' => ''],
    ];
@endphp

@section('content')
    <table class="pdf-table">
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Basic</th>
                <th class="num">Gross</th>
                <th class="num">Deducted PF</th>
                <th class="num">Projected PF</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening"><td colspan="5">Opening balance (deducted PF)</td><td class="num">{{ number_format($opening, 2) }}</td></tr>
            @forelse ($periods as $n => $period)
                <tr class="group"><td colspan="6">{{ \App\Http\Controllers\Admin\ReportController::pfPeriodTitle($n, $period['entry']) }}</td></tr>
                @foreach ($period['rows'] as $r)
                    <tr>
                        <td>{{ $r['month']->format('M Y') }} <span class="muted">{{ $r['source'] ? '· ' . $r['source'] : '· projected (not processed)' }}</span></td>
                        <td class="num">{{ $m($r['basic']) }}</td>
                        <td class="num">{{ $m($r['gross']) }}</td>
                        <td class="num">{{ $m($r['deducted']) }}</td>
                        <td class="num muted">{{ $m($r['projected']) }}</td>
                        <td class="num b">{{ number_format($r['balance'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="3">Period total ({{ count($period['rows']) }} months)</td>
                    <td class="num">{{ number_format($period['deducted'], 2) }}</td>
                    <td class="num muted">{{ number_format($period['projected'], 2) }}</td>
                    <td></td>
                </tr>
            @empty
                <tr class="empty"><td colspan="6">No PF months in this period.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="3">Total / Closing balance (deducted only)</td>
                <td class="num">{{ number_format($deducted_total, 2) }}</td>
                <td class="num">{{ number_format($projected_total, 2) }}</td>
                <td class="num">{{ number_format($closing, 2) }}</td>
            </tr>
        </tbody>
    </table>
    <div class="avoid">@include('pdf.partials.notes', ['note' => ['Note', 'Deducted = PF taken in a processed salary run (in the fund). Projected = months not processed yet, at the current salary (joining / leaving month pro-rated); not in the balance.']])</div>
@endsection
