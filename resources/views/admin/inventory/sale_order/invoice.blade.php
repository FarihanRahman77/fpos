@extends('pdf.layout')
@php
    $m = fn ($v) => number_format((float) $v, 2);
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $invoices = $invoices ?? collect();
    $tone = ['Pending' => 'warning', 'Partial' => 'warning', 'Confirmed' => 'neutral', 'Delivered' => 'success', 'Cancelled' => 'danger'];
    $doc = [
        'title'  => 'Sales Order',
        'number' => $order->order_no,
        'date'   => \Carbon\Carbon::parse($order->order_date)->format('d M Y'),
        'status' => [$order->order_status === 'Partial' ? 'Partially delivered' : $order->order_status, $tone[$order->order_status] ?? 'neutral'],
        'meta'   => ['Delivery date' => $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('d M Y') : '-'],
        'signatures' => ['Prepared by' => $preparedBy ?? (auth()->user()->name ?? ''), 'Customer signature' => '', 'Authorised signature' => ''],
    ];
@endphp

@section('content')
    @include('pdf.partials.party', ['parties' => [
        ['label' => 'Customer', 'name' => $customer->name ?? '-', 'lines' => [
            !empty($customer->phone) ? 'Phone: ' . $customer->phone : null,
            $customer->address ?? null,
        ]],
        ['label' => 'Details', 'kv' => [
            'Order No'      => $order->order_no,
            'Order Date'    => \Carbon\Carbon::parse($order->order_date)->format('d-m-Y'),
            'Delivery Date' => $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('d-m-Y') : '-',
            'Status'        => $order->order_status,
        ]],
    ]])

    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th>Product</th>
                <th style="width: 11%;">Variant</th>
                <th class="num" style="width: 8%;">Qty</th>
                <th class="num" style="width: 9%;">Delivered</th>
                <th class="num" style="width: 9%;">Remaining</th>
                <th class="num" style="width: 12%;">Price</th>
                <th class="num" style="width: 11%;">Discount</th>
                <th class="num" style="width: 13%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $key => $item)
                <tr>
                    <td class="muted">{{ $key + 1 }}</td>
                    <td>{{ $item->product_name ?? '-' }}</td>
                    <td>{{ $item->variant_name ?? '-' }}</td>
                    <td class="num">{{ $q($item->qty) }}</td>
                    <td class="num">{{ $q($item->delivery_qty ?? 0) }}</td>
                    <td class="num">{{ $q(max(0, (float) $item->qty - (float) ($item->delivery_qty ?? 0))) }}</td>
                    <td class="num">{{ $m($item->sale_price) }}</td>
                    <td class="num">{{ $m($item->discount_amount) }}</td>
                    <td class="num">{{ $m($item->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $rows = [['Subtotal', $order->subtotal], ['Discount', $order->discount_amount]];
        if ((float) ($order->vat_amount ?? 0) > 0) {
            $rows[] = ['VAT' . (($order->vat_inclusive ?? 'No') === 'Yes' ? ' (included)' : ''), $order->vat_amount];
        }
        $after = [['Advance Paid', $order->advance_paid]];
        if ($invoices->isNotEmpty()) {
            $after[] = ['Delivered (invoiced)', $invoices->sum('grand_total')];
        }
        $after[] = ['Left to pay', $order->due_amount, (float) $order->due_amount <= 0 ? 'due clear' : 'due'];
        $invoiceLines = $invoices->map(fn ($inv) => $inv->invoice_no . ' · ' . \Carbon\Carbon::parse($inv->sale_date)->format('d-m-Y') . ' · ' . $m($inv->grand_total))->implode("\n");
    @endphp
    @include('pdf.partials.totals', ['box' => [
        'rows'  => $rows,
        'grand' => ['Grand Total', $order->grand_total],
        'after' => $after,
        'words' => $order->grand_total,
        'notes' => ['Delivery invoices', $invoiceLines],
    ]])
@endsection
