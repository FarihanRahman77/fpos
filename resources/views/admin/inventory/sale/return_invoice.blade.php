<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Return {{ $saleReturn->return_no }}</title>
    @include('admin.inventory.partials.invoice-style')
</head>
<body>
    @php $m = fn ($v) => number_format((float) $v, 2); @endphp

    @include('admin.inventory.partials.invoice-header', ['title' => 'Sales Return', 'number' => $saleReturn->return_no])

    <table class="inv-band">
        <tr>
            <td class="col" style="width: 50%;">
                <div class="lbl">Customer</div>
                <div class="name">{{ $customer->name ?? 'Walk-In Customer' }}</div>
            </td>
            <td class="col" style="width: 50%;">
                <div class="lbl">Details</div>
                <table class="inv-kv">
                    <tr><td class="k">Return No</td><td>{{ $saleReturn->return_no }}</td></tr>
                    <tr><td class="k">Original Invoice</td><td>{{ $sale->invoice_no ?? '-' }}</td></tr>
                    <tr><td class="k">Date</td><td>{{ \Carbon\Carbon::parse($saleReturn->datetime)->format('d-m-Y H:i') }}</td></tr>
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
                <th class="r" style="width: 15%;">Price</th>
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
                    <td class="r">{{ $m($item->total) }}</td>
                </tr>
            @endforeach
            <tr class="end"><td colspan="6"></td></tr>
        </tbody>
    </table>

    <table class="inv-bottom">
        <tr>
            <td style="width: 55%; padding-right: 30px;" class="inv-msg">
            </td>
            <td style="width: 45%;">
                <table class="inv-totals">
                    <tr><td class="k">Subtotal</td><td class="r">{{ $m($saleReturn->subtotal) }}</td></tr>
                    <tr class="total"><td>Grand Total</td><td class="r">{{ $m($saleReturn->grand_total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
