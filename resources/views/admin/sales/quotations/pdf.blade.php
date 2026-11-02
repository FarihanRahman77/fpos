<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quotation->quotation_no }}</title>
    @php
        $m = fn ($v) => number_format((float) $v, 2);
        $q3 = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
        $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';
    @endphp
    <style>
        @page { margin: 0 0 54px 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: {{ $pc['ink'] }}; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: {{ $pc['muted'] }}; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .wrap { padding: 0 40px; }

        .band { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; padding: 26px 40px 22px; }
        .band .company { font-size: 18px; font-weight: bold; }
        .band .tag { font-size: 8.5px; opacity: .85; margin-top: 2px; }
        .band .doc { font-size: 26px; letter-spacing: 3px; font-weight: bold; text-align: right; }
        .band .doc-no { text-align: right; font-size: 10px; margin-top: 2px; }
        .accent-line { height: 4px; background: {{ $pc['accent_alt'] }}; }

        .info { margin-top: 22px; }
        .info td { vertical-align: top; }
        .label { font-size: 7.5px; text-transform: uppercase; letter-spacing: 1px; color: {{ $pc['muted'] }}; margin-bottom: 4px; }
        .to-name { font-size: 13px; font-weight: bold; }
        .facts td { padding: 2px 0; }
        .facts .k { color: {{ $pc['muted'] }}; width: 52%; }

        .subject { margin-top: 16px; padding: 10px 14px; background: {{ $pc['soft'] }}; border-left: 3px solid {{ $pc['accent'] }}; font-size: 10px; }

        .items { margin-top: 18px; }
        .items th { background: {{ $pc['soft'] }}; color: {{ $pc['secondary'] }}; font-size: 7.5px; text-transform: uppercase; letter-spacing: .6px;
            padding: 8px 8px; text-align: left; border-bottom: 1.5px solid {{ $pc['border_strong'] }}; }
        .items th.right { text-align: right; }
        .items td { padding: 9px 8px; border-bottom: 1px solid {{ $pc['border'] }}; vertical-align: middle; }
        .items .thumb { width: 38px; height: 38px; border-radius: 4px; }
        .items .noimg { width: 38px; height: 38px; background: {{ $pc['soft'] }}; border: 1px solid {{ $pc['border'] }}; }
        .items .name { font-weight: bold; font-size: 9.5px; }
        .items .desc { color: {{ $pc['muted'] }}; font-size: 8px; margin-top: 1px; }
        .items .num { text-align: right; white-space: nowrap; }

        .totals { width: 46%; margin-left: 54%; margin-top: 12px; }
        .totals td { padding: 5px 8px; }
        .totals .k { color: {{ $pc['muted'] }}; }
        .totals .grand td { background: {{ $pc['brand'] }}; color: {{ $pc['on_brand'] }}; font-size: 12px; font-weight: bold; padding: 9px 8px; }

        .notes { margin-top: 22px; }
        .notes td { vertical-align: top; width: 50%; padding-right: 16px; }
        .notes .body { white-space: pre-line; line-height: 1.5; }

        .sign { margin-top: 40px; }
        .sign td { width: 50%; vertical-align: bottom; }
        .sign .line { border-top: 1px solid {{ $pc['ink'] }}; padding-top: 4px; width: 70%; color: {{ $pc['muted'] }}; }
        .old { margin-top: 14px; padding: 8px 12px; border: 1px dashed {{ $pc['warning'] }}; color: {{ $pc['warning'] }}; background: {{ $pc['warning_soft'] }}; }

        .footer { position: fixed; bottom: -36px; left: 40px; right: 40px; font-size: 7.5px; color: {{ $pc['faint'] }};
            border-top: 1px solid {{ $pc['border'] }}; padding-top: 6px; }
    </style>
</head>
<body>

    <div class="band">
        <table>
            <tr>
                <td style="width: 60%; vertical-align: middle;">
                    <table>
                        <tr>
                            @if ($company['logo'])
                                <td style="width: 70px; vertical-align: middle;"><img src="{{ $company['logo'] }}" style="max-height: 42px; max-width: 52px; background: {{ $pc['paper'] }}; padding: 3px; border-radius: 4px;" alt=""></td>
                            @endif
                            <td style="vertical-align: middle;">
                                <div class="company">{{ $company['name'] }}</div>
                                @if ($company['tagline'])<div class="tag">{{ $company['tagline'] }}</div>@endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="vertical-align: middle;">
                    <div class="doc">QUOTATION</div>
                    <div class="doc-no">{{ $quotation->quotation_no }} · Revision {{ $revisionNo }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="accent-line"></div>

    <div class="wrap">
        <table class="info">
            <tr>
                <td style="width: 55%;">
                    <div class="label">Quote for</div>
                    <div class="to-name">{{ $quotation->customer_name }}</div>
                    @if ($quotation->customer_address)<div class="muted" style="margin-top: 2px;">{{ $quotation->customer_address }}</div>@endif
                    <div class="muted">
                        {{ $quotation->customer_phone }}{{ $quotation->customer_phone && $quotation->customer_email ? '  ·  ' : '' }}{{ $quotation->customer_email }}
                    </div>
                </td>
                <td>
                    <div class="label">Details</div>
                    <table class="facts">
                        <tr><td class="k">Quotation date</td><td class="right bold">{{ $date($quotation->quotation_date) }}</td></tr>
                        <tr><td class="k">Valid until</td><td class="right bold">{{ $date($totals->valid_until ?? $quotation->valid_until) }}</td></tr>
                        <tr><td class="k">Prepared by</td><td class="right">{{ $quotation->created_by_name ?: $company['name'] }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        @if ($quotation->title)
            <div class="subject"><span class="muted">Subject:</span> <span class="bold">{{ $quotation->title }}</span></div>
        @endif

        @if ($isOld)
            <div class="old bold">This is revision {{ $revisionNo }}. It has been replaced by revision {{ $quotation->revision_no }} — prices below are no longer current.</div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 18px;">#</th>
                    <th style="width: 46px;"></th>
                    <th>Product</th>
                    <th class="right" style="width: 50px;">Qty</th>
                    <th class="right" style="width: 78px;">Unit Price</th>
                    <th class="right" style="width: 66px;">Discount</th>
                    <th class="right" style="width: 84px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $i => $l)
                    <tr>
                        <td class="muted">{{ $i + 1 }}</td>
                        <td>@if ($l->thumb)<img src="{{ $l->thumb }}" class="thumb" alt="">@else<div class="noimg"></div>@endif</td>
                        <td>
                            <div class="name">{{ $l->item_name }}</div>
                            @if (!empty($l->description))<div class="desc">{{ $l->description }}</div>@endif
                        </td>
                        <td class="num">{{ $q3($l->qty) }}</td>
                        <td class="num">{{ $m($l->unit_price) }}</td>
                        <td class="num">{{ (float) $l->discount ? $m($l->discount) : '-' }}</td>
                        <td class="num bold">{{ $m($l->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td class="k">Subtotal</td><td class="right">{{ $m($totals->subtotal) }}</td></tr>
            @if ((float) $totals->discount_amount)
                <tr><td class="k">Discount</td><td class="right">− {{ $m($totals->discount_amount) }}</td></tr>
            @endif
            @if ((float) $totals->tax_amount)
                <tr><td class="k">VAT {{ (float) $totals->tax_percent }}%</td><td class="right">{{ $m($totals->tax_amount) }}</td></tr>
            @endif
            <tr class="grand"><td>Total</td><td class="right">{{ $m($totals->grand_total) }}</td></tr>
        </table>

        @if ($quotation->notes || $quotation->terms)
            <table class="notes">
                <tr>
                    @if ($quotation->notes)
                        <td><div class="label">Note</div><div class="body">{{ $quotation->notes }}</div></td>
                    @endif
                    @if ($quotation->terms)
                        <td><div class="label">Terms &amp; conditions</div><div class="body muted">{{ $quotation->terms }}</div></td>
                    @endif
                </tr>
            </table>
        @endif

        <table class="sign">
            <tr>
                <td>
                    @if ($company['signature'])<img src="{{ $company['signature'] }}" style="max-height: 34px; max-width: 120px;" alt=""><br>@endif
                    <div class="line">Authorised signature · {{ $company['name'] }}</div>
                </td>
                <td class="right">
                    <div class="line" style="margin-left: 30%;">Accepted by · {{ $quotation->customer_name }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        {{ $company['name'] }}{{ $company['address'] ? ' · ' . $company['address'] : '' }}{{ $company['phone'] ? ' · ' . $company['phone'] : '' }}{{ $company['email'] ? ' · ' . $company['email'] : '' }}{{ $company['website'] ? ' · ' . $company['website'] : '' }}
        <span style="float: right;">{{ $quotation->quotation_no }} · Rev {{ $revisionNo }} · {{ $printedAt }}</span>
    </div>
</body>
</html>
