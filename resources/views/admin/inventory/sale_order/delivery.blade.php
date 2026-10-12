@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Sale Order Delivery
@endsection


@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <div>

                <h5 class="mb-0">
                    Sale Order Delivery
                </h5>

                <small class="text-muted">
                    Order:
                    <strong>
                        {{ $order->order_no }}
                    </strong>
                </small>

            </div>


            <div>

                <a href="{{ route('sales.orders.index') }}"
                   class="btn btn-secondary btn-sm">

                    <i class="fa fa-arrow-left"></i>

                    Back

                </a>


                <a href="{{ route('sales.order.invoice.pdf', $order->id) }}"
                   target="_blank"
                   class="btn btn-danger btn-sm">

                    <i class="fa fa-file-pdf"></i>

                    Order PDF

                </a>

            </div>

        </div>


        <div class="card-body">


            {{-- =========================================================
                 ORDER INFORMATION
            ========================================================== --}}

            <div class="row mb-4">

                <div class="col-md-3">

                    <label class="form-label">
                        Order No
                    </label>

                    <div class="fw-bold text-primary">
                        {{ $order->order_no }}
                    </div>

                </div>


                <div class="col-md-3">

                    <label class="form-label">
                        Customer
                    </label>

                    <div class="fw-bold">

                        {{ $customer->name ?? '-' }}

                    </div>

                    @if(!empty($customer->phone))

                        <small class="text-muted">

                            {{ $customer->phone }}

                        </small>

                    @endif

                </div>


                <div class="col-md-2">

                    <label class="form-label">
                        Order Date
                    </label>

                    <div>

                        {{ $order->order_date }}

                    </div>

                </div>


                <div class="col-md-2">

                    <label class="form-label">
                        Probable Delivery Date
                    </label>

                    <div>

                        {{ $order->delivery_date ?? '-' }}

                    </div>

                </div>


                <div class="col-md-2">

                    <label class="form-label">
                        Status
                    </label>

                    <div>

                        @if($order->order_status === 'Pending')

                            <span class="badge bg-warning text-dark">
                                Pending
                            </span>

                        @elseif($order->order_status === 'Confirmed')

                            <span class="badge bg-info text-dark">
                                Confirmed
                            </span>

                        @elseif($order->order_status === 'Partial')

                            <span class="badge bg-primary">
                                Partially Delivered
                            </span>

                        @else

                            <span class="badge bg-secondary">
                                {{ $order->order_status }}
                            </span>

                        @endif

                    </div>

                </div>

            </div>


            {{-- =========================================================
                 DELIVERY FORM
            ========================================================== --}}

            <form id="deliveryForm">


                @csrf


                <div class="table-responsive">

                    <table class="table table-bordered table-striped align-middle"
                           id="deliveryItemsTable">

                        <thead>

                        <tr>

                            <th width="40">
                                #
                            </th>

                            <th>
                                Product
                            </th>

                            <th>
                                Variant
                            </th>

                            <th class="text-end">
                                Ordered Qty
                            </th>

                            <th class="text-end">
                                Delivered Qty
                            </th>

                            <th class="text-end">
                                Remaining Qty
                            </th>

                            <th width="150"
                                class="text-end">
                                Delivery Qty
                            </th>

                            <th class="text-end">
                                Sale Price
                            </th>

                            <th class="text-end">
                                Delivery Total
                            </th>

                        </tr>

                        </thead>


                        <tbody>


                        @forelse($items as $index => $item)

                            @php

                                $orderedQty =
                                    (float) $item->qty;

                                $deliveredQty =
                                    (float) ($item->delivery_qty ?? 0);

                                $remainingQty =
                                    max(
                                        0,
                                        $orderedQty -
                                        $deliveredQty
                                    );

                            @endphp


                            <tr class="delivery-row"
                                data-item-id="{{ $item->id }}"
                                data-ordered-qty="{{ $orderedQty }}"
                                data-delivered-qty="{{ $deliveredQty }}"
                                data-remaining-qty="{{ $remainingQty }}"
                                data-price="{{ (float) $item->sale_price }}"
                                data-discount-type="{{ $item->discount_type }}"
                                data-discount-value="{{ (float) $item->discount_value }}"
                                data-discount-amount="{{ (float) $item->discount_amount }}">

                                <td class="text-center">

                                    {{ $index + 1 }}

                                    <input type="hidden"
                                           class="sale-order-item-id"
                                           value="{{ $item->id }}">

                                </td>


                                <td>

                                    <strong>
                                        {{ $item->product_name ?? 'Product #' . $item->product_id }}
                                    </strong>

                                </td>


                                <td>

                                    @if($item->variant_name)

                                        <span class="badge bg-secondary">
                                            {{ $item->variant_name }}
                                        </span>

                                    @else

                                        <span class="text-muted">
                                            -
                                        </span>

                                    @endif

                                </td>


                                <td class="text-end">

                                    {{ number_format(
                                        $orderedQty,
                                        4,
                                        '.',
                                        ''
                                    ) }}

                                </td>


                                <td class="text-end text-success">

                                    {{ number_format(
                                        $deliveredQty,
                                        4,
                                        '.',
                                        ''
                                    ) }}

                                </td>


                                <td class="text-end remaining-qty-cell">

                                    <strong>

                                        {{ number_format(
                                            $remainingQty,
                                            4,
                                            '.',
                                            ''
                                        ) }}

                                    </strong>

                                </td>


                                <td>

                                    <input type="number"
                                           class="form-control form-control-sm text-end delivery-qty"
                                           value="0"
                                           min="0"
                                           max="{{ $remainingQty }}"
                                           step="0.0001"
                                           data-max="{{ $remainingQty }}"
                                           placeholder="0"
                                           {{ $remainingQty <= 0 ? 'disabled' : '' }}>

                                    <small class="text-danger qty-error d-none">
                                        Maximum:
                                        <span class="max-qty-text">
                                            {{ $remainingQty }}
                                        </span>
                                    </small>

                                </td>


                                <td class="text-end">

                                    {{ number_format(
                                        (float) $item->sale_price,
                                        2,
                                        '.',
                                        ''
                                    ) }}

                                </td>


                                <td class="text-end delivery-total">

                                    0.00

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="9"
                                    class="text-center text-muted">

                                    No sale order items found.

                                </td>

                            </tr>

                        @endforelse

                        </tbody>


                        <tfoot>

                        <tr>

                            <th colspan="6"
                                class="text-end">

                                Delivery Subtotal

                            </th>

                            <th></th>

                            <th></th>

                            <th class="text-end"
                                id="deliverySubtotal">

                                0.00

                            </th>

                        </tr>


                        <tr>

                            <th colspan="8"
                                class="text-end">

                                Header Discount

                            </th>

                            <th class="text-end"
                                id="deliveryHeaderDiscount">

                                0.00

                            </th>

                        </tr>


                        <tr>

                            <th colspan="8"
                                class="text-end">

                                Delivery Grand Total

                            </th>

                            <th class="text-end fw-bold text-primary"
                                id="deliveryGrandTotal">

                                0.00

                            </th>

                        </tr>

                        </tfoot>

                    </table>

                </div>


                {{-- =====================================================
                     SUMMARY
                ====================================================== --}}

                <div class="row justify-content-end mt-4">

                    <div class="col-md-5">

                        <div class="card bg-light">

                            <div class="card-body">

                                <div class="d-flex justify-content-between mb-2">

                                    <span>
                                        Order Total
                                    </span>

                                    <strong>
                                        {{ number_format(
                                            (float) $order->grand_total,
                                            2,
                                            '.',
                                            ''
                                        ) }}
                                    </strong>

                                </div>


                                <div class="d-flex justify-content-between mb-2">

                                    <span>
                                        Order Advance
                                    </span>

                                    <strong class="text-success">

                                        {{ number_format(
                                            (float) $order->advance_paid,
                                            2,
                                            '.',
                                            ''
                                        ) }}

                                    </strong>

                                </div>


                                <div class="d-flex justify-content-between">

                                    <span>
                                        Current Delivery Total
                                    </span>

                                    <strong class="text-primary"
                                            id="summaryDeliveryTotal">

                                        0.00

                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- =====================================================
                     BUTTONS
                ====================================================== --}}

                <div class="d-flex justify-content-between mt-4">

                    <a href="{{ route('sales.orders.index') }}"
                       class="btn btn-secondary">

                        <i class="fa fa-times"></i>

                        Cancel

                    </a>


                    <button type="submit"
                            class="btn btn-success"
                            id="saveDeliveryBtn">

                        <i class="fa fa-truck"></i>

                        Deliver Selected Quantity

                    </button>

                </div>


            </form>

        </div>

    </div>

</div>

@endsection


@push('scripts')

<script>

$(document).ready(function () {

    calculateDeliveryTotals();


    /*
    |--------------------------------------------------------------------------
    | Quantity Input
    |--------------------------------------------------------------------------
    */
    $(document).on(
        'input',
        '.delivery-qty',
        function () {

            let input =
                $(this);


            let max =
                parseFloat(
                    input.data('max')
                ) || 0;


            let value =
                parseFloat(
                    input.val()
                );


            if (isNaN(value)) {

                value = 0;
            }


            /*
            |--------------------------------------------------------------------------
            | Negative protection
            |--------------------------------------------------------------------------
            */
            if (value < 0) {

                value = 0;

                input.val(0);
            }


            /*
            |--------------------------------------------------------------------------
            | Maximum protection
            |--------------------------------------------------------------------------
            */
            if (value > max) {

                input.val(max);

                input.addClass(
                    'is-invalid'
                );

                input
                    .closest('td')
                    .find('.qty-error')
                    .removeClass('d-none');

            } else {

                input.removeClass(
                    'is-invalid'
                );

                input
                    .closest('td')
                    .find('.qty-error')
                    .addClass('d-none');
            }


            calculateRowTotal(
                input.closest('tr')
            );

            calculateDeliveryTotals();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Form Submit
    |--------------------------------------------------------------------------
    */
    $('#deliveryForm').on(
        'submit',
        function (e) {

            e.preventDefault();


            let items = [];

            let hasDelivery = false;

            let hasError = false;


            $('.delivery-row').each(
                function () {

                    let row =
                        $(this);


                    let itemId =
                        parseInt(
                            row.data('item-id')
                        );


                    let max =
                        parseFloat(
                            row.data('remaining-qty')
                        ) || 0;


                    let deliveryQty =
                        parseFloat(
                            row.find('.delivery-qty').val()
                        );


                    if (isNaN(deliveryQty)) {

                        deliveryQty = 0;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Frontend Validation
                    |--------------------------------------------------------------------------
                    */
                    if (
                        deliveryQty < 0 ||
                        deliveryQty > max
                    ) {

                        hasError = true;

                        row.find('.delivery-qty')
                            .addClass('is-invalid');

                        row.find('.qty-error')
                            .removeClass('d-none');

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Only send rows with quantity > 0
                    |--------------------------------------------------------------------------
                    */
                    if (deliveryQty > 0) {

                        hasDelivery = true;


                        items.push({

                            sale_order_item_id:
                                itemId,

                            delivery_qty:
                                deliveryQty

                        });
                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Validation Error
            |--------------------------------------------------------------------------
            */
            if (hasError) {

                toastr.error(
                    'Delivery quantity cannot be greater than the remaining quantity.'
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | At least one item
            |--------------------------------------------------------------------------
            */
            if (!hasDelivery) {

                toastr.error(
                    'Please enter at least one delivery quantity.'
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Confirmation
            |--------------------------------------------------------------------------
            */
            Swal.fire({

                title:
                    'Confirm Delivery?',

                text:
                    'Only the entered delivery quantities will be delivered and stock will be deducted.',

                icon:
                    'question',

                showCancelButton:
                    true,

                confirmButtonText:
                    'Yes, Deliver',

                cancelButtonText:
                    'Cancel'

            }).then(function (result) {

                if (!result.isConfirmed) {

                    return;
                }


                saveDelivery(
                    items
                );

            });

        }
    );

});


/*
|--------------------------------------------------------------------------
| Calculate Row Total
|--------------------------------------------------------------------------
*/
function calculateRowTotal(row)
{
    let qty =
        parseFloat(
            row.find('.delivery-qty').val()
        ) || 0;


    let price =
        parseFloat(
            row.data('price')
        ) || 0;


    let discountType =
        row.data('discount-type');


    let discountValue =
        parseFloat(
            row.data('discount-value')
        ) || 0;


    let originalQty =
        parseFloat(
            row.data('ordered-qty')
        ) || 0;


    let originalDiscountAmount =
        parseFloat(
            row.data('discount-amount')
        ) || 0;


    let subtotal =
        qty * price;


    /*
    |--------------------------------------------------------------------------
    | Proportional item discount
    |--------------------------------------------------------------------------
    */
    let discount =
        0;


    let originalSubtotal =
        originalQty * price;


    if (originalSubtotal > 0) {

        discount =
            originalDiscountAmount *
            (
                subtotal /
                originalSubtotal
            );
    }


    if (discount > subtotal) {

        discount =
            subtotal;
    }


    let total =
        subtotal - discount;


    row.find('.delivery-total')
        .text(
            total.toFixed(2)
        );


    return {

        subtotal:
            subtotal,

        discount:
            discount,

        total:
            total

    };
}


/*
|--------------------------------------------------------------------------
| Calculate Delivery Totals
|--------------------------------------------------------------------------
*/
function calculateDeliveryTotals()
{
    let subtotal = 0;

    let itemDiscount = 0;

    let deliveryTotalBeforeHeaderDiscount = 0;


    $('.delivery-row').each(
        function () {

            let result =
                calculateRowTotal(
                    $(this)
                );


            subtotal +=
                result.subtotal;


            itemDiscount +=
                result.discount;


            deliveryTotalBeforeHeaderDiscount +=
                result.total;

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Order Header Discount
    |--------------------------------------------------------------------------
    */
    let orderSubtotal =
        parseFloat(
            "{{ (float) $order->subtotal }}"
        ) || 0;


    let orderHeaderDiscount =
        parseFloat(
            "{{ (float) $order->discount_amount }}"
        ) || 0;


    let headerDiscount = 0;


    if (orderSubtotal > 0) {

        headerDiscount =
            orderHeaderDiscount *
            (
                deliveryTotalBeforeHeaderDiscount /
                orderSubtotal
            );
    }


    if (
        headerDiscount >
        deliveryTotalBeforeHeaderDiscount
    ) {

        headerDiscount =
            deliveryTotalBeforeHeaderDiscount;
    }


    let grandTotal =
        deliveryTotalBeforeHeaderDiscount -
        headerDiscount;


    $('#deliverySubtotal')
        .text(
            subtotal.toFixed(2)
        );


    $('#deliveryHeaderDiscount')
        .text(
            headerDiscount.toFixed(2)
        );


    $('#deliveryGrandTotal')
        .text(
            grandTotal.toFixed(2)
        );


    $('#summaryDeliveryTotal')
        .text(
            grandTotal.toFixed(2)
        );
}


/*
|--------------------------------------------------------------------------
| Save Delivery
|--------------------------------------------------------------------------
*/
function saveDelivery(items)
{
   // alert(items);
    $('#saveDeliveryBtn')
        .prop(
            'disabled',
            true
        )
        .html(
            '<i class="fa fa-spinner fa-spin"></i> Delivering...'
        );


    $.ajax({

        url:
            "{{ route('sales.orders.delivery', $order->id) }}",

        type:
            "POST",

        data: {

            _token:
                "{{ csrf_token() }}",

            items:
                JSON.stringify(items)

        },

        success:
            function (response) {
              //  alert(JSON.stringify(response));
                if (response.status) {

                    toastr.success(
                        response.message
                    );


                    if (
                        response.invoice_url
                    ) {

                        window.open(
                            response.invoice_url,
                            '_blank'
                        );
                    }


                    setTimeout(
                        function () {

                            window.location.href =
                                "{{ route('sales.orders.index') }}";

                        },
                        700
                    );

                } else {

                    toastr.error(
                        response.message
                    );


                    $('#saveDeliveryBtn')
                        .prop(
                            'disabled',
                            false
                        )
                        .html(
                            '<i class="fa fa-truck"></i> Deliver Selected Quantity'
                        );
                }

            },

        error:
            function (xhr) {
               // alert(JSON.stringify(xhr));
                let message =
                    'Unable to deliver Sale Order.';


                if (
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {

                    message =
                        xhr.responseJSON.message;
                }


                toastr.error(
                    message
                );


                $('#saveDeliveryBtn')
                    .prop(
                        'disabled',
                        false
                    )
                    .html(
                        '<i class="fa fa-truck"></i> Deliver Selected Quantity'
                    );

            }

    });
}

</script>

@endpush
