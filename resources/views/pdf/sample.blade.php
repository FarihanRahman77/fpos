{{-- Sample sales invoice for the template previews on Settings > Theme Settings (SettingController::pdfSampleData()). --}}
@extends('pdf.layout')
@php
    $doc = [
        'title'  => 'Sales Invoice',
        'number' => 'INV-2026-00148',
        'date'   => now()->format('d M Y'),
        'status' => ['Partially paid', 'warning'],
        'meta'   => ['Due date' => now()->addDays(15)->format('d M Y'), 'Sale type' => 'Credit'],
        'signatures' => ['Prepared by' => auth()->user()->name ?? 'Admin', 'Checked by' => '', 'Customer signature' => '', 'Authorised signature' => ''],
    ];
@endphp

@section('content')
    @include('pdf.partials.party', ['parties' => [
        ['label' => 'Bill to', 'name' => 'Rahman Traders', 'lines' => ['House 12, Road 5, Dhanmondi, Dhaka', 'Phone: 01711-000000', 'BIN: 000123456-0101']],
        ['label' => 'Ship to', 'name' => 'Rahman Traders (Warehouse)', 'lines' => ['Plot 44, Tejgaon I/A, Dhaka']],
        ['label' => 'Payment', 'kv' => ['Method' => 'Bank transfer', 'Terms' => 'Net 15', 'Reference' => 'PO-7781']],
    ]])

    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th>Item</th>
                <th style="width: 9%;">Unit</th>
                <th class="num" style="width: 9%;">Qty</th>
                <th class="num" style="width: 13%;">Unit price</th>
                <th class="num" style="width: 11%;">Discount</th>
                <th class="num" style="width: 15%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="muted">{{ $r['n'] }}</td>
                    <td>{{ $r['name'] }}<div class="sub">SKU-{{ 1000 + $r['n'] * 7 }}</div></td>
                    <td>{{ $r['unit'] }}</td>
                    <td class="num">{{ number_format($r['qty'], 2) }}</td>
                    <td class="num">{{ number_format($r['price'], 2) }}</td>
                    <td class="num">{{ number_format($r['disc'], 2) }}</td>
                    <td class="num">{{ number_format($r['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('pdf.partials.totals', ['box' => [
        'rows'  => [['Subtotal', $subtotal], ['Discount', $discount], ['VAT (5%)', $vat]],
        'grand' => ['Grand Total', $grand],
        'after' => [['Paid', $paid], ['Due', $due, 'due']],
        'words' => $grand,
        'notes' => ['Terms & conditions', $pdfCo['terms'] ?: "Goods once sold are not returnable after 7 days.\nPlease quote the invoice number with every payment."],
    ]])
@endsection
