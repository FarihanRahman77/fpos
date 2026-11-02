<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Order {{ $order->order_no }}</title>
    @include('admin.inventory.partials.invoice-style')
</head>
<body>
    @php $m = fn ($v) => number_format((float) $v, 2); @endphp

    @include('admin.inventory.partials.invoice-header', ['title' => 'Sales Order', 'number' => $order->order_no])

    <table class="inv-band">
        <tr>
            <td class="col" style="width: 50%;">
                <div class="lbl">Customer</div>
                <div class="name">{{ $customer->name ?? '-' }}</div>
            </td>
            <td class="col" style="width: 50%;">
                <div class="lbl">Details</div>
                <table class="inv-kv">
                    <tr><td class="k">Order No</td><td>{{ $order->order_no }}</td></tr>
                    <tr><td class="k">Order Date</td><td>{{ \Carbon\Carbon::parse($order->order_date)->format('d-m-Y') }}</td></tr>
                    <tr><td class="k">Delivery Date</td><td>{{ $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('d-m-Y') : '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="inv-items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th>Product</th>
                <th>Variant</th>
                <th class="r" style="width: 10%;">Qty</th>
                <th class="r" style="width: 14%;">Price</th>
                <th class="r" style="width: 13%;">Discount</th>
                <th class="r" style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $key => $item)
                <tr>
                    <td class="muted">{{ $key + 1 }}</td>
                    <td class="pname">{{ $item->product_name ?? '-' }}</td>
                    <td>{{ $item->variant_name ?? '-' }}</td>
                    <td class="r">{{ number_format($item->qty, 2) }}</td>
                    <td class="r">{{ $m($item->sale_price) }}</td>
                    <td class="r">{{ $m($item->discount_amount) }}</td>
                    <td class="r">{{ $m($item->total) }}</td>
                </tr>
            @endforeach
            <tr class="end"><td colspan="7"></td></tr>
        </tbody>
    </table>

    <table class="inv-bottom">
        <tr>
            <td style="width: 55%; padding-right: 30px;" class="inv-msg">
            </td>
            <td style="width: 45%;">
                <table class="inv-totals">
                    <tr><td class="k">Subtotal</td><td class="r">{{ $m($order->subtotal) }}</td></tr>
                    <tr><td class="k">Discount</td><td class="r">{{ $m($order->discount_amount) }}</td></tr>
                    <tr class="total"><td>Grand Total</td><td class="r">{{ $m($order->grand_total) }}</td></tr>
                    <tr class="after"><td class="k">Advance Paid</td><td class="r">{{ $m($order->advance_paid) }}</td></tr>
                    <tr class="due {{ (float) $order->due_amount <= 0 ? 'clear' : '' }}"><td>Due</td><td class="r">{{ $m($order->due_amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
