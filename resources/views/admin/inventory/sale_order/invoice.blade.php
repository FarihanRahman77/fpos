<!DOCTYPE html>
<html>

<head>

    <meta charset="utf-8">

    <title>
        Sale Order Invoice -{{ $order->order_no }}
    </title>

    <style>

        @page {
            margin: 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
        }

        .title {
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

        .right {
            text-align: right;
        }

        .summary {
            width: 40%;
            margin-left: auto;
            margin-top: 20px;
        }

    </style>

</head>

<body>

<div class="header">

    <div class="company-name">
        {{ $generalSetting->company_name ?? '' }}
    </div>

    <div>
        {{ $generalSetting->address ?? '' }}
    </div>

    <div class="title">
        SALES ORDER
    </div>

</div>


<table>

    <tr>

        <td>
            <strong>Order No:</strong>
            {{ $order->order_no }}
        </td>

        <td>
            <strong>Order Date:</strong>
            {{ \Carbon\Carbon::parse($order->order_date)->format('d-m-Y') }}
        </td>

    </tr>

    <tr>

        <td>
            <strong>Customer:</strong>
            {{ $customer->name ?? '-' }}
        </td>

        <td>
            <strong>Delivery Date:</strong>

            @if($order->delivery_date)
                {{ \Carbon\Carbon::parse($order->delivery_date)->format('d-m-Y') }}
            @else
                -
            @endif

        </td>

    </tr>

</table>


<br>


<table>

    <thead>

    <tr>

        <th>#</th>
        <th>Product</th>
        <th>Variant</th>
        <th>Qty</th>
        <th>Price</th>
        <th>Discount</th>
        <th>Total</th>

    </tr>

    </thead>

    <tbody>

    @foreach($items as $key => $item)

        <tr>

            <td>
                {{ $key + 1 }}
            </td>

            <td>
                {{ $item->product_name ?? '-' }}
            </td>

            <td>
                {{ $item->variant_name ?? '-' }}
            </td>

            <td class="right">
                {{ number_format($item->qty, 2) }}
            </td>

            <td class="right">
                {{ number_format($item->sale_price, 2) }}
            </td>

            <td class="right">
                {{ number_format($item->discount_amount, 2) }}
            </td>

            <td class="right">
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

        <td class="right">
            {{ number_format($order->subtotal, 2) }}
        </td>

    </tr>

    <tr>

        <td>
            <strong>Discount</strong>
        </td>

        <td class="right">
            {{ number_format($order->discount_amount, 2) }}
        </td>

    </tr>

    <tr>

        <td>
            <strong>Grand Total</strong>
        </td>

        <td class="right">
            <strong>
                {{ number_format($order->grand_total, 2) }}
            </strong>
        </td>

    </tr>

    <tr>

        <td>
            <strong>Advance Paid</strong>
        </td>

        <td class="right">
            {{ number_format($order->advance_paid, 2) }}
        </td>

    </tr>

    <tr>

        <td>
            <strong>Due</strong>
        </td>

        <td class="right">
            {{ number_format($order->due_amount, 2) }}
        </td>

    </tr>

</table>

</body>

</html>
