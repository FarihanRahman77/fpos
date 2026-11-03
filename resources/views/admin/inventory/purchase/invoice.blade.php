@extends('pdf.layout')
@php
    $m = function ($v) { return number_format((float) $v, 2); };
    $t = function ($v, $d = 2) { return rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.'); };
    $cur = $pdfCo['currency'] ?: 'BDT';

    $grossItems   = (float) $items->sum('subtotal');
    $itemDiscount = (float) $items->sum('discount_amount');

    if ((float) $purchase->due_amount <= 0) {
        $state = ['Paid', 'success'];
    } elseif ((float) $purchase->paid_amount > 0) {
        $state = ['Partly paid', 'warning'];
    } else {
        $state = ['Unpaid', 'danger'];
    }
    $doc = [
        'title'  => 'Purchase Invoice',
        'number' => $purchase->invoice_no,
        'date'   => $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y') : null,
        'status' => $state,
        'meta'   => array_filter(['Supplier invoice' => $purchase->supplier_invoice_no ?: null]),
        'signatures' => ['Prepared by' => $preparedBy ?? '', 'Received by' => '', 'Supplier signature' => '', 'Authorised signature' => ''],
    ];
@endphp

@section('content')
    @include('pdf.partials.party', ['parties' => [
        ['label' => 'Supplier', 'name' => $supplier->name ?? '-', 'lines' => [
            !empty($supplier->phone) ? 'Phone: ' . $supplier->phone : null,
            $supplier->email ?? null,
            $supplier->address ?? null,
            !empty($supplier->bin) ? 'BIN: ' . $supplier->bin : null,
        ]],
        ['label' => 'Invoice details', 'kv' => [
            'Invoice no'       => $purchase->invoice_no,
            'Supplier invoice' => $purchase->supplier_invoice_no ?: null,
            'Purchase date'    => $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y') : '-',
            'Payment method'   => ($paymentLabel ?? '') ?: '-',
            'Prepared by'      => $preparedBy ?? '-',
        ]],
    ]])

    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th>Item description</th>
                <th class="num" style="width: 9%;">Qty</th>
                <th class="num" style="width: 15%;">Unit price</th>
                <th class="num" style="width: 13%;">Discount</th>
                <th class="num" style="width: 16%;">Total ({{ $cur }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $i => $item)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td>
                        {{ $item->product_name }}{{ !empty($item->variant_name) ? ' (' . $item->variant_name . ')' : '' }}
                        @php $code = ($item->variant_barcode ?? null) ?: (($item->barcode ?? null) ?: ($item->sku ?? null)); @endphp
                        @if ($code)<div class="sub">Code: {{ $code }}</div>@endif
                    </td>
                    <td class="num">{{ $t($item->qty, 4) }}</td>
                    <td class="num">{{ $m($item->purchase_price) }}</td>
                    <td class="num">
                        {{ $m($item->discount_amount) }}
                        @if ($item->discount_type === 'Percentage' && (float) $item->discount_value > 0)
                            <div class="sub">{{ $t($item->discount_value) }}%</div>
                        @endif
                    </td>
                    <td class="num">{{ $m($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $rows = [
            ['Items total', $grossItems],
            ['Item discount', '- ' . $m($itemDiscount)],
            ['Subtotal', $purchase->subtotal, 'strong'],
            ['Invoice discount' . ($purchase->discount_type === 'Percentage' && (float) $purchase->discount_value > 0 ? ' (' . $t($purchase->discount_value) . '%)' : ''), '- ' . $m($purchase->discount_amount)],
        ];
        if ((float) $purchase->vat_amount > 0) {
            $rows[] = ['VAT' . ((float) $purchase->vat_percent > 0 ? ' (' . $t($purchase->vat_percent) . '%)' : ''), '+ ' . $m($purchase->vat_amount)];
        }
    @endphp
    @include('pdf.partials.totals', ['box' => [
        'rows'     => $rows,
        'grand'    => ['Grand total', $purchase->grand_total],
        'after'    => [
            ['Paid (' . ((($paymentLabel ?? '') ?: '-')) . ')', $purchase->paid_amount],
            ['Balance due', $purchase->due_amount, (float) $purchase->due_amount <= 0 ? 'due clear' : 'due'],
        ],
        'words'    => $purchase->grand_total,
        'currency' => $cur,
        'notes'    => ['Remarks', (string) $purchase->remarks],
        'left'     => '<div class="small muted">This is a computer-generated document. Please retain it for your records.</div>',
    ]])
@endsection
