<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $sale->invoice_no }}</title>
    @include('admin.inventory.partials.invoice-style')
</head>
<body>
    @php $m = fn ($v) => number_format((float) $v, 2); @endphp

    @include('admin.inventory.partials.invoice-header', ['title' => 'Sales Invoice', 'number' => $sale->invoice_no])

    <table class="inv-band">
        <tr>
            <td class="col" style="width: 33%;">
                <div class="lbl">Bill to</div>
                <div class="name">{{ $customer->name ?? 'Walk-In Customer' }}</div>
                @if (!empty($customer->phone))<div class="line">Phone: {{ $customer->phone }}</div>@endif
            </td>
            <td class="col" style="width: 27%;">
                <div class="lbl">Sale type</div>
                <div class="name">{{ $sale->sale_type }}</div>
                @if (!empty($customer->phone))
                    <div class="lbl" style="margin-top: 8px;">Payment</div>
                    <div class="line">{{ $sale->payment_method }}</div>
                @endif
            </td>
            <td class="col" style="width: 40%;">
                <div class="lbl">Details</div>
                <table class="inv-kv">
                    <tr><td class="k">Invoice #</td><td>{{ $sale->invoice_no }}</td></tr>
                    <tr><td class="k">Invoice date</td><td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y') }}</td></tr>
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
                    <tr><td class="k">Subtotal</td><td class="r">{{ $m($sale->subtotal) }}</td></tr>
                    <tr><td class="k">Discount</td><td class="r">{{ $m($sale->discount_amount) }}</td></tr>
                    @if ((float) ($sale->vat_amount ?? 0) > 0)
                        <tr><td class="k">VAT{{ ($sale->vat_inclusive ?? 'No') === 'Yes' ? ' (included)' : '' }}</td><td class="r">{{ $m($sale->vat_amount) }}</td></tr>
                    @endif
                    @if ((float) ($sale->loyalty_discount ?? 0) > 0)
                        <tr><td class="k">Points redeemed ({{ rtrim(rtrim(number_format((float) $sale->loyalty_points_redeemed, 2), '0'), '.') }})</td><td class="r">− {{ $m($sale->loyalty_discount) }}</td></tr>
                    @endif
                    <tr class="total"><td>Grand Total</td><td class="r">{{ $m($sale->grand_total) }}</td></tr>
                    <tr class="after"><td class="k">Paid</td><td class="r">{{ $m($sale->paid_amount) }}</td></tr>
                    <tr class="due {{ (float) $sale->due_amount <= 0 ? 'clear' : '' }}"><td>Due</td><td class="r">{{ $m($sale->due_amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="inv-sign">
        <tr>
            <td style="width: 35%;"><div class="line">Prepared By: {{ auth()->user()->name ?? '' }}</div></td>
            <td style="width: 30%;"></td>
            <td style="width: 35%;"><div class="line">Customer Signature</div></td>
        </tr>
    </table>
</body>
</html>
