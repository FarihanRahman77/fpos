@extends('pdf.layout')
@php
    $m = fn ($v) => number_format((float) $v, 2);
    $isCash = ($saleReturn->refund_mode ?? 'Credit') === 'Cash';
    $doc = [
        'title'  => 'Sales Return',
        'number' => $saleReturn->return_no,
        'date'   => \Carbon\Carbon::parse($saleReturn->datetime)->format('d M Y'),
        'status' => $isCash ? ['Cash refund', 'success'] : ['Customer credit', 'neutral'],
        'meta'   => ['Original invoice' => $sale->invoice_no ?? '-'],
        'signatures' => ['Prepared by' => $preparedBy ?? (auth()->user()->name ?? ''), 'Customer signature' => '', 'Authorised signature' => ''],
    ];
@endphp

@section('content')
    @include('pdf.partials.party', ['parties' => [
        ['label' => 'Customer', 'name' => $customer->name ?? 'Walk-In Customer', 'lines' => [
            !empty($customer->phone) ? 'Phone: ' . $customer->phone : null,
            $customer->address ?? null,
        ]],
        ['label' => 'Details', 'kv' => [
            'Return No'        => $saleReturn->return_no,
            'Original Invoice' => $sale->invoice_no ?? '-',
            'Date'             => \Carbon\Carbon::parse($saleReturn->datetime)->format('d-m-Y H:i'),
        ]],
    ]])

    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th>Product</th>
                <th>Variant</th>
                <th class="num" style="width: 10%;">Qty</th>
                <th class="num" style="width: 15%;">Price</th>
                <th class="num" style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $key => $item)
                <tr>
                    <td class="muted">{{ $key + 1 }}</td>
                    <td>{{ $item->product_name ?? '-' }}</td>
                    <td>{{ $item->variant_name ?? '-' }}</td>
                    <td class="num">{{ number_format($item->qty, 2) }}</td>
                    <td class="num">{{ $m($item->sale_price) }}</td>
                    <td class="num">{{ $m($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $rows = [['Subtotal', $saleReturn->subtotal]];
        if ((float) ($saleReturn->vat_amount ?? 0) > 0) {
            $rows[] = ['VAT' . (($sale->vat_inclusive ?? 'No') === 'Yes' ? ' (included in prices)' : ' (included)'), $saleReturn->vat_amount];
        }
    @endphp
    @include('pdf.partials.totals', ['box' => [
        'rows'  => $rows,
        'grand' => ['Grand Total', $saleReturn->grand_total],
        'after' => [['Refund', $isCash ? 'Cash ' . $m($saleReturn->refund_amount) : 'Customer credit', 'strong']],
        'words' => $saleReturn->grand_total,
    ]])
@endsection
