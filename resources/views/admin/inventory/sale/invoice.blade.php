@extends('pdf.layout')
@php
    $m = fn ($v) => number_format((float) $v, 2);
    $due = (float) $sale->due_amount;
    $doc = [
        'title'  => 'Sales Invoice',
        'number' => $sale->invoice_no,
        'date'   => \Carbon\Carbon::parse($sale->sale_date)->format('d M Y'),
        'status' => $due <= 0 ? ['Paid', 'success'] : ((float) $sale->paid_amount > 0 ? ['Partially paid', 'warning'] : ['Due', 'danger']),
        'meta'   => ['Sale type' => $sale->sale_type],
        'signatures' => ['Prepared by' => $preparedBy ?? (auth()->user()->name ?? ''), 'Customer signature' => '', 'Authorised signature' => ''],
    ];
@endphp

@section('content')
    @include('pdf.partials.party', ['parties' => [
        ['label' => 'Bill to', 'name' => $customer->name ?? 'Walk-In Customer', 'lines' => [
            !empty($customer->phone) ? 'Phone: ' . $customer->phone : null,
            $customer->address ?? null,
        ]],
        ['label' => 'Details', 'kv' => [
            'Invoice #'    => $sale->invoice_no,
            'Invoice date' => \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y'),
            'Sale type'    => $sale->sale_type,
            'Payment'      => !empty($customer->phone) ? $sale->payment_method : null,
        ]],
    ]])

    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th>Product</th>
                <th>Variant</th>
                <th class="num" style="width: 10%;">Qty</th>
                <th class="num" style="width: 14%;">Price</th>
                <th class="num" style="width: 13%;">Discount</th>
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
                    <td class="num">{{ $m($item->discount_amount) }}</td>
                    <td class="num">{{ $m($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $rows = [['Subtotal', $sale->subtotal], ['Discount', $sale->discount_amount]];
        if ((float) ($sale->vat_amount ?? 0) > 0) {
            $rows[] = ['VAT' . (($sale->vat_inclusive ?? 'No') === 'Yes' ? ' (included)' : ''), $sale->vat_amount];
        }
        if ((float) ($sale->loyalty_discount ?? 0) > 0) {
            $rows[] = ['Points redeemed (' . rtrim(rtrim(number_format((float) $sale->loyalty_points_redeemed, 2), '0'), '.') . ')', '− ' . $m($sale->loyalty_discount)];
        }
    @endphp
    @include('pdf.partials.totals', ['box' => [
        'rows'  => $rows,
        'grand' => ['Grand Total', $sale->grand_total],
        'after' => [['Paid', $sale->paid_amount], ['Due', $sale->due_amount, $due <= 0 ? 'due clear' : 'due']],
        'words' => $sale->grand_total,
        'notes' => [$pdfCo['terms'] ? 'Terms & conditions' : 'Note', $pdfCo['terms'] ?: $pdfCo['invoice_note']],
    ]])
@endsection
