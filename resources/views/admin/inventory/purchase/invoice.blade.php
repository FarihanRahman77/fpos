@php
    $g = fn($key) => data_get($generalSetting ?? null, $key);
    $companyName = $g('company_name') ?: config('app.name');

    $m = function ($v) { return number_format((float) $v, 2); };
    $t = function ($v, $d = 2) { return rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.'); };

    $grossItems   = (float) $items->sum('subtotal');
    $itemDiscount = (float) $items->sum('discount_amount');

    if ((float) $purchase->due_amount <= 0) {
        $state = ['PAID', $pc['success'], $pc['success_soft']];
    } elseif ((float) $purchase->paid_amount > 0) {
        $state = ['PARTLY PAID', $pc['warning'], $pc['warning_soft']];
    } else {
        $state = ['UNPAID', $pc['danger'], $pc['danger_soft']];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $purchase->invoice_no }}</title>
    @include('admin.inventory.partials.invoice-style')
    <style>
        .inv-words { margin-bottom: 12px; }
        .inv-words .txt { font-weight: bold; color: {{ $pc['ink'] }}; }
    </style>
</head>
<body>
    @include('admin.inventory.partials.invoice-header', ['title' => 'Purchase Invoice', 'number' => $purchase->invoice_no, 'state' => $state])

    <table class="inv-band">
        <tr>
            <td class="col" style="width: 50%;">
                <div class="lbl">Supplier</div>
                <div class="name b">{{ $supplier->name ?? '-' }}</div>
                @if(!empty($supplier->phone))<div class="line">Phone: {{ $supplier->phone }}</div>@endif
                @if(!empty($supplier->email))<div class="line">{{ $supplier->email }}</div>@endif
                @if(!empty($supplier->address))<div class="line">{{ $supplier->address }}</div>@endif
                @if(!empty($supplier->bin))<div class="line">BIN: {{ $supplier->bin }}</div>@endif
            </td>
            <td class="col" style="width: 50%;">
                <div class="lbl">Invoice details</div>
                <table class="inv-kv">
                    <tr><td class="k">Invoice no</td><td>{{ $purchase->invoice_no }}</td></tr>
                    <tr><td class="k">Purchase date</td><td>{{ $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y') : '-' }}</td></tr>
                    <tr><td class="k">Payment method</td><td>{{ ($paymentLabel ?? '') ?: '-' }}</td></tr>
                    <tr><td class="k">Prepared by</td><td>{{ $preparedBy ?? '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="inv-items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th>Item description</th>
                <th class="r" style="width: 9%;">Qty</th>
                <th class="r" style="width: 15%;">Unit price</th>
                <th class="r" style="width: 13%;">Discount</th>
                <th class="r" style="width: 16%;">Total (BDT)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td>
                        <div class="pname">{{ $item->product_name }}{{ !empty($item->variant_name) ? ' (' . $item->variant_name . ')' : '' }}</div>
                        @php $code = ($item->variant_barcode ?? null) ?: (($item->barcode ?? null) ?: ($item->sku ?? null)); @endphp
                        @if($code)<div class="sub">Code: {{ $code }}</div>@endif
                    </td>
                    <td class="r">{{ $t($item->qty, 4) }}</td>
                    <td class="r">{{ $m($item->purchase_price) }}</td>
                    <td class="r">
                        {{ $m($item->discount_amount) }}
                        @if($item->discount_type === 'Percentage' && (float) $item->discount_value > 0)
                            <div class="sub">{{ $t($item->discount_value) }}%</div>
                        @endif
                    </td>
                    <td class="r">{{ $m($item->total) }}</td>
                </tr>
            @endforeach
            <tr class="end"><td colspan="6"></td></tr>
        </tbody>
    </table>

    <table class="inv-bottom">
        <tr>
            <td style="width: 54%; padding-right: 30px;" class="inv-msg">
                <div class="inv-words">
                    <div class="lbl">Amount in words</div>
                    <div class="txt">BDT {{ $amountInWords }} Only</div>
                </div>
                @if($purchase->remarks)
                    <div class="lbl">Remarks</div>
                    <div class="txt">{!! nl2br(e($purchase->remarks)) !!}</div>
                @endif
            </td>
            <td style="width: 46%;">
                <table class="inv-totals">
                    <tr><td class="k">Items total</td><td class="r">{{ $m($grossItems) }}</td></tr>
                    <tr><td class="k">Item discount</td><td class="r">- {{ $m($itemDiscount) }}</td></tr>
                    <tr><td class="k">Subtotal</td><td class="r">{{ $m($purchase->subtotal) }}</td></tr>
                    <tr>
                        <td class="k">Invoice discount{{ $purchase->discount_type === 'Percentage' && (float) $purchase->discount_value > 0 ? ' (' . $t($purchase->discount_value) . '%)' : '' }}</td>
                        <td class="r">- {{ $m($purchase->discount_amount) }}</td>
                    </tr>
                    @if((float) $purchase->vat_amount > 0)
                        <tr><td class="k">VAT{{ (float) $purchase->vat_percent > 0 ? ' (' . $t($purchase->vat_percent) . '%)' : '' }}</td><td class="r">+ {{ $m($purchase->vat_amount) }}</td></tr>
                    @endif
                    <tr class="total"><td>Grand total</td><td class="r">BDT {{ $m($purchase->grand_total) }}</td></tr>
                    <tr class="after"><td class="k">Paid ({{ ($paymentLabel ?? '') ?: '-' }})</td><td class="r">{{ $m($purchase->paid_amount) }}</td></tr>
                    <tr class="due {{ (float) $purchase->due_amount <= 0 ? 'clear' : '' }}"><td>Balance due</td><td class="r">{{ $m($purchase->due_amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="inv-sign">
        <tr>
            <td style="width: 29%;"><div class="line">Prepared by</div></td>
            <td style="width: 6.5%;"></td>
            <td style="width: 29%;"><div class="line">Received by</div></td>
            <td style="width: 6.5%;"></td>
            <td style="width: 29%;"><div class="line">Supplier signature</div></td>
        </tr>
    </table>

    <div class="inv-note">This is a computer-generated document. Please retain it for your records.</div>

    <div class="inv-footer">
        <table>
            <tr>
                <td>{{ $companyName }}</td>
                <td class="c">{{ $purchase->invoice_no }}</td>
                <td class="r">Printed {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; Page <span class="pn"></span></td>
            </tr>
        </table>
    </div>
</body>
</html>
