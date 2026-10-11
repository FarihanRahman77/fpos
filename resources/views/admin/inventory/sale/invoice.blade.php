<!DOCTYPE html>
<html>
<head>

    <meta charset="utf-8">

    <title>
        Invoice {{ $sale->invoice_no }}
    </title>

    <style>

        @page {
            margin: 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
        }

        .company-info {
            line-height: 18px;
        }

        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 7px;
        }

        th {
            background: #f2f2f2;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .no-border td {
            border: 0;
        }

        .summary {
            width: 45%;
            margin-left: auto;
            margin-top: 15px;
        }

        .summary td {
            padding: 6px;
        }

        .footer {
            margin-top: 50px;
        }

    </style>

</head>

<body>

<div class="header">
    
    <div class="company-name">
        {{ $generalSetting->company_name ?? '' }}
    </div>

    <div class="company-info">
        {{ $generalSetting->address ?? '' }}
    </div>

    @if(!empty($generalSetting?->phone))
        <div>
            Phone: {{ $generalSetting->phone }}
        </div>
    @endif

    <div class="invoice-title">
        SALES INVOICE
    </div>

</div>


<table class="no-border">

    <tr>

        <td>
            <strong>Invoice:</strong>
            {{ $sale->invoice_no }}
        </td>

        <td>
            <strong>Date:</strong>
            {{ \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y') }}
        </td>

    </tr>

    <tr>

        <td>
            <strong>Customer:</strong>
            {{ $customer->name ?? 'Walk-In Customer' }}
        </td>

        <td>
            <strong>Sale Type:</strong>
            {{ $sale->sale_type }}
        </td>

    </tr>

    @if(!empty($customer->phone))

    <tr>

        <td>
            <strong>Phone:</strong>
            {{ $customer->phone }}
        </td>

        <td>
            <strong>Payment:</strong>
            {{ $sale->payment_method }}
        </td>

    </tr>

    @endif

</table>


<table>

    <thead>

    <tr>
        <th width="5%">#</th>
        <th>Product</th>
        <th>Variant</th>
        <th width="10%">Qty</th>
        <th width="15%">Price</th>
        <th width="15%">Discount</th>
        <th width="15%">Total</th>
    </tr>

    </thead>

    <tbody>

    @foreach($items as $key => $item)

        <tr>

            <td class="text-center">
                {{ $key + 1 }}
            </td>

            <td>
                {{ $item->product_name ?? '-' }}
            </td>

            <td>
                {{ $item->variant_name ?? '-' }}
            </td>

            <td class="text-right">
                {{ number_format($item->qty, 2) }}
            </td>

            <td class="text-right">
                {{ number_format($item->sale_price, 2) }}
            </td>

            <td class="text-right">
                {{ number_format($item->discount_amount, 2) }}
            </td>

            <td class="text-right">
                {{ number_format($item->total, 2) }}
            </td>

        </tr>

    @endforeach

    </tbody>

</table>


<table class="summary">

    <tr>
        <td>
            <strong>Subtotal</strong>
        </td>

        <td class="text-right">
            {{ number_format($sale->subtotal, 2) }}
        </td>
    </tr>

    <tr>
        <td>
            <strong>Discount</strong>
        </td>

        <td class="text-right">
            {{ number_format($sale->discount_amount, 2) }}
        </td>
    </tr>

    <tr>
        <td>
            <strong>Grand Total</strong>
        </td>

        <td class="text-right">
            <strong>
                {{ number_format($sale->grand_total, 2) }}
            </strong>
        </td>
    </tr>

    <tr>
        <td>
            <strong>Paid</strong>
        </td>

        <td class="text-right">
            {{ number_format($sale->paid_amount, 2) }}
        </td>
    </tr>

    <tr>
        <td>
            <strong>Due</strong>
        </td>

        <td class="text-right">
            {{ number_format($sale->due_amount, 2) }}
        </td>
    </tr>

</table>


<div class="footer">

    <table class="no-border">

        <tr>

            <td>
                Prepared By:
                {{ auth()->user()->name ?? '' }}
            </td>

            <td class="text-right">
                Customer Signature
            </td>

        </tr>

    </table>

</div>

</body>
</html>