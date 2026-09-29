@php
    $g = fn($key) => data_get($generalSetting ?? null, $key);
    $companyName = $g('company_name') ?: config('app.name');

    $m = function ($v) { return number_format((float) $v, 2); };
    $t = function ($v, $d = 2) { return rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.'); };

    $grossItems   = (float) $items->sum('subtotal');
    $itemDiscount = (float) $items->sum('discount_amount');

    if ((float) $purchase->due_amount <= 0) {
        $state = ['PAID', '#0e5a4f', '#e3f1ed'];
    } elseif ((float) $purchase->paid_amount > 0) {
        $state = ['PARTLY PAID', '#a86400', '#fbf0dc'];
    } else {
        $state = ['UNPAID', '#a3341f', '#f9e6e2'];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $purchase->invoice_no }}</title>
<style>
    @page { margin: 30px 36px 62px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 1.5; color: #1c2626; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .serif { font-family: 'DejaVu Serif', serif; }
    .muted { color: #6a7573; }
    .r { text-align: right; }
    .c { text-align: center; }
    .b { font-weight: bold; }

    .topbar { height: 7px; background: #0e5a4f; }
    .topbar-thin { height: 2px; background: #c9a24a; margin-bottom: 24px; }

    .company { font-size: 18px; font-weight: bold; line-height: 1.2; color: #0e5a4f; }
    .company-line { font-size: 9px; color: #6a7573; margin-top: 2px; }

    .doc-title { font-size: 24px; line-height: 1.1; letter-spacing: 1px; color: #1c2626; }
    .doc-no { font-size: 12px; font-weight: bold; margin-top: 6px; color: #0e5a4f; }
    .badge { display: inline-block; margin-top: 8px; padding: 3px 12px; border: 1px solid; border-radius: 10px; font-size: 8.5px; font-weight: bold; letter-spacing: .5px; }

    .cards { margin: 26px 0 22px; }
    .card { border: 1px solid #e2e6e3; background: #f7f9f7; padding: 12px 14px; }
    .card-title { font-size: 8.5px; font-weight: bold; color: #0e5a4f; letter-spacing: .5px; margin-bottom: 6px; padding-bottom: 5px; border-bottom: 1px solid #dfe5e1; }
    .card .name { font-size: 12px; font-weight: bold; margin-bottom: 2px; }
    .kv td { padding: 2px 0; }
    .kv .k { color: #6a7573; width: 42%; }

    .items th { background: #0e5a4f; color: #ffffff; padding: 9px 8px; font-size: 9px; text-align: left; font-weight: bold; }
    .items th.r { text-align: right; }
    .items td { padding: 9px 8px; border-bottom: 1px solid #e2e6e3; }
    .items .alt td { background: #f7f9f7; }
    .items .pname { font-weight: bold; font-size: 10.5px; }
    .items .sub { font-size: 8.5px; color: #6a7573; margin-top: 1px; }

    .words { background: #f7f9f7; border-left: 3px solid #c9a24a; padding: 10px 14px; }
    .words .lbl { font-size: 8.5px; color: #6a7573; margin-bottom: 2px; }
    .words .txt { font-size: 10.5px; font-weight: bold; }
    .remarks { margin-top: 14px; }
    .remarks .lbl { font-size: 8.5px; color: #6a7573; margin-bottom: 2px; }

    .totals td { padding: 6px 0; }
    .totals .line td { border-bottom: 1px solid #eef1ef; }
    .totals .grand td { background: #0e5a4f; color: #ffffff; padding: 11px 14px; font-size: 13px; font-weight: bold; }
    .totals .due td { padding-top: 10px; font-weight: bold; font-size: 11px; color: #a3341f; }
    .totals .due.clear td { color: #0e5a4f; }

    .sign td { padding-top: 54px; }
    .sign .line { border-top: 1px solid #1c2626; padding-top: 5px; font-size: 9px; color: #6a7573; text-align: center; }

    .note { margin-top: 26px; font-size: 8.5px; color: #6a7573; text-align: center; }

    .footer { position: fixed; left: 0; right: 0; bottom: -40px; border-top: 1px solid #e2e6e3; padding-top: 7px; font-size: 8.5px; color: #6a7573; }
    .pn:before { content: counter(page); }
</style>
</head>
<body>

<div class="topbar"></div>
<div class="topbar-thin"></div>

{{-- Header --}}
<table>
    <tr>
        <td style="width:56%">
            <div class="company serif">{{ $companyName }}</div>
            @if($g('branch'))<div class="company-line b">{{ $g('branch') }}</div>@endif
            @if($g('address'))<div class="company-line">{{ $g('address') }}</div>@endif
            @if($g('phone'))<div class="company-line">Tel: {{ $g('phone') }}</div>@endif
            @if($g('email') || $g('website'))
                <div class="company-line">{{ $g('email') }}{{ $g('email') && $g('website') ? '  |  ' : '' }}{{ $g('website') }}</div>
            @endif
            @if($g('bin'))<div class="company-line b">BIN: {{ $g('bin') }}</div>@endif
        </td>
        <td class="r" style="width:44%">
            <div class="doc-title serif">PURCHASE INVOICE</div>
            <div class="doc-no">{{ $purchase->invoice_no }}</div>
            <span class="badge" style="color: {{ $state[1] }}; border-color: {{ $state[1] }}; background: {{ $state[2] }};">{{ $state[0] }}</span>
        </td>
    </tr>
</table>

{{-- Supplier + invoice details --}}
<table class="cards">
    <tr>
        <td style="width:50%; padding-right:8px;">
            <div class="card">
                <div class="card-title">SUPPLIER</div>
                <div class="name">{{ $supplier->name ?? '-' }}</div>
                @if(!empty($supplier->phone))<div class="muted">Phone: {{ $supplier->phone }}</div>@endif
                @if(!empty($supplier->email))<div class="muted">{{ $supplier->email }}</div>@endif
                @if(!empty($supplier->address))<div class="muted">{{ $supplier->address }}</div>@endif
                @if(!empty($supplier->bin))<div class="muted">BIN: {{ $supplier->bin }}</div>@endif
            </div>
        </td>
        <td style="width:50%; padding-left:8px;">
            <div class="card">
                <div class="card-title">INVOICE DETAILS</div>
                <table class="kv">
                    <tr><td class="k">Invoice no</td><td class="b">{{ $purchase->invoice_no }}</td></tr>
                    <tr><td class="k">Purchase date</td><td class="b">{{ $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d M Y') : '-' }}</td></tr>
                    <tr><td class="k">Payment method</td><td class="b">{{ $purchase->payment_method ?: '-' }}</td></tr>
                    <tr><td class="k">Prepared by</td><td class="b">{{ $preparedBy ?? '-' }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- Items --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th>Item description</th>
            <th class="r" style="width:9%">Qty</th>
            <th class="r" style="width:15%">Unit price</th>
            <th class="r" style="width:13%">Discount</th>
            <th class="r" style="width:16%">Total (BDT)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $i => $item)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
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
                <td class="r b">{{ $m($item->total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Words + totals --}}
<table style="margin-top:22px; page-break-inside: avoid;">
    <tr>
        <td style="width:54%; padding-right:28px;">
            <div class="words">
                <div class="lbl">Amount in words</div>
                <div class="txt">BDT {{ $amountInWords }} Only</div>
            </div>
            @if($purchase->remarks)
                <div class="remarks">
                    <div class="lbl">Remarks</div>
                    <div>{!! nl2br(e($purchase->remarks)) !!}</div>
                </div>
            @endif
        </td>
        <td style="width:46%">
            <table class="totals">
                <tr class="line"><td class="muted">Items total</td><td class="r">{{ $m($grossItems) }}</td></tr>
                <tr class="line"><td class="muted">Item discount</td><td class="r">- {{ $m($itemDiscount) }}</td></tr>
                <tr class="line"><td class="b">Subtotal</td><td class="r b">{{ $m($purchase->subtotal) }}</td></tr>
                <tr class="line">
                    <td class="muted">Invoice discount{{ $purchase->discount_type === 'Percentage' && (float) $purchase->discount_value > 0 ? ' (' . $t($purchase->discount_value) . '%)' : '' }}</td>
                    <td class="r">- {{ $m($purchase->discount_amount) }}</td>
                </tr>
                <tr class="grand">
                    <td class="serif">Grand total</td>
                    <td class="r serif">BDT {{ $m($purchase->grand_total) }}</td>
                </tr>
                <tr><td class="muted" style="padding-top:10px;">Paid ({{ $purchase->payment_method ?: '-' }})</td><td class="r" style="padding-top:10px;">{{ $m($purchase->paid_amount) }}</td></tr>
                <tr class="due {{ (float) $purchase->due_amount <= 0 ? 'clear' : '' }}"><td>Balance due</td><td class="r">{{ $m($purchase->due_amount) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- Signatures --}}
<table class="sign" style="page-break-inside: avoid;">
    <tr>
        <td style="width:29%"><div class="line">Prepared by</div></td>
        <td style="width:6.5%"></td>
        <td style="width:29%"><div class="line">Received by</div></td>
        <td style="width:6.5%"></td>
        <td style="width:29%"><div class="line">Supplier signature</div></td>
    </tr>
</table>

<div class="note">This is a computer-generated document. Please retain it for your records.</div>

<div class="footer">
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
