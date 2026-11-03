@extends('pdf.layout')
@php
    $m = fn ($v) => number_format((float) $v, 2);
    $q3 = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
    $tone = ['Draft' => 'neutral', 'Sent' => 'warning', 'Accepted' => 'success', 'Rejected' => 'danger', 'Expired' => 'danger', 'Converted' => 'success'];
    $doc = [
        'title'  => 'Quotation',
        'number' => $quotation->quotation_no . ' · Revision ' . $revisionNo,
        'date'   => $date($quotation->quotation_date),
        'status' => $isOld ? ['Superseded', 'warning'] : [$quotation->quotation_status, $tone[$quotation->quotation_status] ?? 'neutral'],
        'meta'   => ['Valid until' => $date($totals->valid_until ?? $quotation->valid_until)],
        'signatures' => [
            'Prepared by' => $quotation->created_by_name ?: $pdfCo['name'],
            'Accepted by · ' . $quotation->customer_name => '',
            'Authorised signature · ' . $pdfCo['name'] => '',
        ],
    ];
@endphp

@section('styles')
    <style>
        .q-thumb { width: 38px; height: 38px; }
        .q-noimg { width: 38px; height: 38px; background: {{ $pdfT['zebra'] === '#ffffff' ? $pdfT['accent_soft'] : $pdfT['zebra'] }}; border: 0.6px solid {{ $pdfT['rule'] }}; }
        .pdf-table td.q-mid { vertical-align: middle; }
        .q-name { font-weight: bold; color: {{ $pdfT['ink'] }}; }
        .q-old { margin: 0 0 12px 0; padding: 7px 10px; border: 0.8px dashed {{ $pdfT['warning'] }}; color: {{ $pdfT['warning'] }}; font-weight: bold; }
    </style>
@endsection

@section('content')
    @include('pdf.partials.party', ['parties' => [
        ['label' => 'Quote for', 'name' => $quotation->customer_name, 'lines' => [
            $quotation->customer_address,
            trim($quotation->customer_phone . ($quotation->customer_phone && $quotation->customer_email ? '  ·  ' : '') . $quotation->customer_email),
        ]],
        ['label' => 'Details', 'kv' => [
            'Quotation date' => $date($quotation->quotation_date),
            'Valid until'    => $date($totals->valid_until ?? $quotation->valid_until),
            'Revision'       => $revisionNo,
            'Prepared by'    => $quotation->created_by_name ?: $pdfCo['name'],
        ]],
    ]])

    @if ($quotation->title)
        <table class="pdf-kv"><tr><td class="k">Subject</td><td class="v b">{{ $quotation->title }}</td></tr></table>
    @endif

    @if ($isOld)
        <div class="q-old">This is revision {{ $revisionNo }}. It has been replaced by revision {{ $quotation->revision_no }} — prices below are no longer current.</div>
    @endif

    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 50px;"></th>
                <th>Product</th>
                <th class="num" style="width: 9%;">Qty</th>
                <th class="num" style="width: 13%;">Unit Price</th>
                <th class="num" style="width: 11%;">Discount</th>
                <th class="num" style="width: 14%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $i => $l)
                <tr>
                    <td class="muted q-mid">{{ $i + 1 }}</td>
                    <td class="q-mid">
                        @if ($l->thumb)
                            <img src="{{ $l->thumb }}" class="q-thumb" alt="">
                        @else
                            <div class="q-noimg"></div>
                        @endif
                    </td>
                    <td class="q-mid">
                        <div class="q-name">{{ $l->item_name }}</div>
                        @if (!empty($l->description))<div class="sub">{{ $l->description }}</div>@endif
                    </td>
                    <td class="num q-mid">{{ $q3($l->qty) }}</td>
                    <td class="num q-mid">{{ $m($l->unit_price) }}</td>
                    <td class="num q-mid">{{ (float) $l->discount ? $m($l->discount) : '-' }}</td>
                    <td class="num q-mid b">{{ $m($l->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $rows = [['Subtotal', $totals->subtotal]];
        if ((float) $totals->discount_amount) {
            $rows[] = ['Discount', '− ' . $m($totals->discount_amount)];
        }
        if ((float) $totals->tax_amount) {
            $rows[] = ['VAT ' . (float) $totals->tax_percent . '%', $totals->tax_amount];
        }
    @endphp
    @include('pdf.partials.totals', ['box' => [
        'rows'  => $rows,
        'grand' => ['Total', $totals->grand_total],
        'words' => $totals->grand_total,
        'notes' => ['Note', (string) $quotation->notes],
    ]])

    @include('pdf.partials.notes', ['note' => ['Terms & conditions', (string) $quotation->terms]])
@endsection
