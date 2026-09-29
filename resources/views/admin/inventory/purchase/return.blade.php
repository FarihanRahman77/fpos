@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Purchase Return
@endsection

@section('content')

<div class="container-fluid">

    <div class="row">

        <div class="col-md-12">

            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">

                    <h5 class="mb-0">
                        Purchase Return
                    </h5>

                    <a href="{{ route('purchases.index') }}"
                       class="btn btn-secondary btn-sm">

                        <i class="fa fa-arrow-left me-1"></i>

                        Back

                    </a>

                </div>


                <div class="card-body">

                    <div id="loadingArea"
                         class="text-center py-5">

                        <i class="fa fa-spinner fa-spin fa-2x"></i>

                        <div class="mt-2">
                            Loading purchase...
                        </div>

                    </div>


                    <div id="returnArea"
                         style="display:none;">

                        <div class="row mb-4">

                            <div class="col-md-3">

                                <label class="form-label">
                                    Invoice
                                </label>

                                <input type="text"
                                       id="invoice_no"
                                       class="form-control"
                                       readonly>

                            </div>


                            <div class="col-md-3">

                                <label class="form-label">
                                    Purchase Date
                                </label>

                                <input type="text"
                                       id="purchase_date"
                                       class="form-control"
                                       readonly>

                            </div>


                            <div class="col-md-3">

                                <label class="form-label">
                                    Supplier
                                </label>

                                <input type="text"
                                       id="supplier_name"
                                       class="form-control"
                                       readonly>

                            </div>


                            <div class="col-md-3">

                                <label class="form-label">
                                    Return Date
                                </label>

                                <input type="date"
                                       id="return_date"
                                       class="form-control"
                                       value="{{ date('Y-m-d') }}">

                            </div>

                        </div>


                        <div class="table-responsive">

                            <table class="table table-bordered">

                                <thead>

                                    <tr>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Variant
                                        </th>

                                        <th>
                                            Purchased
                                        </th>

                                        <th>
                                            Returned
                                        </th>

                                        <th>
                                            Remaining
                                        </th>

                                        <th>
                                            Purchase Price
                                        </th>

                                        <th style="width:160px;">
                                            Return Qty
                                        </th>

                                        <th>
                                            Return Amount
                                        </th>

                                    </tr>

                                </thead>


                                <tbody id="returnItems">

                                </tbody>

                            </table>

                        </div>


                        <div class="row justify-content-end">

                            <div class="col-md-5">

                                <div class="card border">

                                    <div class="card-body">

                                        <div class="d-flex justify-content-between mb-2">

                                            <strong>
                                                Subtotal
                                            </strong>

                                            <span id="returnSubtotal">
                                                0.00
                                            </span>

                                        </div>


                                        <div class="d-flex justify-content-between mb-2">

                                            <strong>
                                                Discount
                                            </strong>

                                            <span id="returnDiscount">
                                                0.00
                                            </span>

                                        </div>


                                        <hr>


                                        <div class="d-flex justify-content-between">

                                            <strong>
                                                Return Total
                                            </strong>

                                            <strong id="returnGrandTotal">
                                                0.00
                                            </strong>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div class="mb-3">

                            <label class="form-label">
                                Remarks
                            </label>

                            <textarea
                                id="remarks"
                                class="form-control"
                                rows="3"
                                placeholder="Return remarks"></textarea>

                        </div>


                        <div class="text-end">

                            <button type="button"
                                    id="submitReturn"
                                    class="btn btn-warning">

                                <i class="fa fa-undo me-1"></i>

                                Process Purchase Return

                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection


@push('scripts')

<script>

$(document).ready(function () {

    let purchaseId = "{{ $purchase->id }}";

    let purchaseData = null;


    /*
    |--------------------------------------------------------------------------
    | Load Purchase
    |--------------------------------------------------------------------------
    */

    loadPurchase();


    function loadPurchase() {

        $.ajax({

            url:
                "{{ url('/admin/purchases/return/data') }}/" +
                purchaseId,

            type: "GET",

            success: function (response) {

                if (!response.status) {

                    toastr.error(
                        response.message ||
                        'Could not load purchase.'
                    );

                    return;
                }


                purchaseData = response.data;


                let purchase =
                    response.data.purchase;


                $('#invoice_no').val(
                    purchase.invoice_no
                );


                $('#purchase_date').val(
                    purchase.purchase_date
                );


                /*
                |--------------------------------------------------------------------------
                | Supplier
                |--------------------------------------------------------------------------
                */

                loadSupplier(
                    purchase.supplier_id
                );


                renderItems(
                    response.data.items
                );


                $('#loadingArea').hide();

                $('#returnArea').show();

            },

            error: function (xhr) {

                toastr.error(
                    xhr.responseJSON?.message ||
                    'Something went wrong.'
                );

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Supplier
    |--------------------------------------------------------------------------
    */

    function loadSupplier(supplierId) {

        $.ajax({

            url:
                "{{ url('/admin/suppliers') }}/" +
                supplierId,

            type: "GET",

            success: function (response) {

                if (response.data) {

                    $('#supplier_name').val(
                        response.data.name
                    );

                }

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Render Items
    |--------------------------------------------------------------------------
    */

    function renderItems(items) {

        let html = '';


        items.forEach(function (item, index) {

            let variant =
                item.variant_name ||
                item.variant_barcode ||
                '-';


            let remaining =
                parseFloat(
                    item.remaining_qty
                );


            let purchased =
                parseFloat(
                    item.qty
                );


            let returned =
                parseFloat(
                    item.returned_qty
                );


            let price =
                parseFloat(
                    item.purchase_price
                );


            html += `

                <tr
                    data-purchase-item-id="${item.purchase_item_id}"
                    data-price="${price}"
                    data-max="${remaining}"
                >

                    <td>

                        <strong>
                            ${escapeHtml(item.product_name)}
                        </strong>

                        <br>

                        <small class="text-muted">

                            SKU:
                            ${escapeHtml(item.sku || '-')}

                        </small>

                    </td>


                    <td>

                        ${escapeHtml(variant)}

                    </td>


                    <td>

                        ${purchased.toFixed(4)}

                    </td>


                    <td>

                        ${returned.toFixed(4)}

                    </td>


                    <td>

                        <strong class="text-primary">

                            ${remaining.toFixed(4)}

                        </strong>

                    </td>


                    <td>

                        ${price.toFixed(2)}

                    </td>


                    <td>

                        <input
                            type="number"
                            class="form-control returnQty"
                            min="0"
                            max="${remaining}"
                            step="0.0001"
                            value="0"
                            ${remaining <= 0 ? 'disabled' : ''}
                        >

                    </td>


                    <td class="returnAmount">

                        0.00

                    </td>

                </tr>

            `;

        });


        $('#returnItems').html(html);

        calculateTotal();

    }


    /*
    |--------------------------------------------------------------------------
    | Quantity Change
    |--------------------------------------------------------------------------
    */

    $(document).on(
        'input',
        '.returnQty',
        function () {

            let row =
                $(this).closest('tr');


            let max =
                parseFloat(
                    row.data('max')
                );


            let qty =
                parseFloat(
                    $(this).val()
                ) || 0;


            if (qty < 0) {

                qty = 0;

                $(this).val(0);
            }


            if (qty > max) {

                toastr.warning(
                    'Return quantity cannot exceed remaining quantity.'
                );

                qty = max;

                $(this).val(max);
            }


            let price =
                parseFloat(
                    row.data('price')
                ) || 0;


            let amount =
                qty * price;


            row.find('.returnAmount')
                .text(
                    amount.toFixed(2)
                );


            calculateTotal();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Calculate
    |--------------------------------------------------------------------------
    */

    function calculateTotal() {

        let subtotal = 0;


        $('.returnQty').each(function () {

            let qty =
                parseFloat(
                    $(this).val()
                ) || 0;


            let row =
                $(this).closest('tr');


            let price =
                parseFloat(
                    row.data('price')
                ) || 0;


            subtotal +=
                qty * price;

        });


        /*
        |--------------------------------------------------------------------------
        | Proportional Purchase Discount
        |--------------------------------------------------------------------------
        */

        let purchase =
            purchaseData.purchase;


        let purchaseSubtotal =
            parseFloat(
                purchase.subtotal
            ) || 0;


        let purchaseDiscount =
            parseFloat(
                purchase.discount_amount
            ) || 0;


        let discount = 0;


        if (purchaseSubtotal > 0) {

            discount =
                (
                    purchaseDiscount /
                    purchaseSubtotal
                ) * subtotal;

        }


        if (discount > subtotal) {

            discount = subtotal;
        }


        let grandTotal =
            subtotal - discount;


        $('#returnSubtotal')
            .text(
                subtotal.toFixed(2)
            );


        $('#returnDiscount')
            .text(
                discount.toFixed(2)
            );


        $('#returnGrandTotal')
            .text(
                grandTotal.toFixed(2)
            );

    }


    /*
    |--------------------------------------------------------------------------
    | Submit Return
    |--------------------------------------------------------------------------
    */

    $('#submitReturn').on(
        'click',
        function () {

            let items = [];


            $('.returnQty').each(function () {

                let qty =
                    parseFloat(
                        $(this).val()
                    ) || 0;


                if (qty <= 0) {
                    return;
                }


                let row =
                    $(this).closest('tr');


                items.push({

                    purchase_item_id:
                        row.data(
                            'purchase-item-id'
                        ),

                    qty: qty

                });

            });


            if (items.length === 0) {

                toastr.warning(
                    'Please enter at least one return quantity.'
                );

                return;
            }


            let returnDate =
                $('#return_date').val();


            if (!returnDate) {

                toastr.warning(
                    'Please select return date.'
                );

                return;
            }


            if (!confirm(
                'Are you sure you want to process this purchase return?'
            )) {

                return;
            }


            let button =
                $(this);


            button.prop(
                'disabled',
                true
            );


            button.html(
                '<i class="fa fa-spinner fa-spin me-1"></i> Processing...'
            );


            $.ajax({

                url:
                    "{{ route('purchases.return.store') }}",

                type: "POST",

                data: {

                    _token:
                        "{{ csrf_token() }}",

                    purchase_id:
                        purchaseId,

                    return_date:
                        returnDate,

                    remarks:
                        $('#remarks').val(),

                    items:
                        items

                },

                success: function (response) {

                    if (response.status) {

                        toastr.success(
                            response.message
                        );


                        setTimeout(function () {

                            window.location.href =
                                response.redirect;

                        }, 700);

                    } else {

                        toastr.error(
                            response.message
                        );


                        button.prop(
                            'disabled',
                            false
                        );


                        button.html(
                            '<i class="fa fa-undo me-1"></i> Process Purchase Return'
                        );

                    }

                },

                error: function (xhr) {

                    toastr.error(
                        xhr.responseJSON?.message ||
                        'Something went wrong while processing return.'
                    );


                    button.prop(
                        'disabled',
                        false
                    );


                    button.html(
                        '<i class="fa fa-undo me-1"></i> Process Purchase Return'
                    );

                }

            });

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Escape HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return $('<div>')
            .text(value ?? '')
            .html();

    }

});

</script>

@endpush
