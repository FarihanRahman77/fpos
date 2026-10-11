@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Sale Return
@endsection

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header d-flex justify-content-between">

            <h5 class="mb-0">
                Sale Return
            </h5>

            <a href="{{ route('sales.returns.index') }}"
               class="btn btn-secondary btn-sm">
                Back
            </a>

        </div>

        <div class="card-body">

            <div id="returnContent">

                <div class="text-center py-5">
                    Loading...
                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@push('scripts')

<script>

let returnItems = [];

$(document).ready(function () {

    loadReturnData();

});


function loadReturnData() {

    $.ajax({

        url: "{{ route('sales.return.data', $sale->id) }}",

        type: "GET",

        success: function (response) {

            if (!response.status) {

                toastr.error(response.message);

                return;

            }

            renderReturnData(response);

        },

        error: function (xhr) {

            toastr.error(
                xhr.responseJSON?.message ||
                'Unable to load sale data.'
            );

        }

    });

}


function renderReturnData(response) {

    let sale = response.sale;

    let customer = response.customer;

    let items = response.items || [];

    let html = `

        <div class="row mb-4">

            <div class="col-md-2">

                <strong>Invoice</strong>

                <div>
                    ${escapeHtml(sale.invoice_no)}
                </div>

            </div>

            <div class="col-md-2">

                <strong>Customer</strong>

                <div>
                    ${escapeHtml(customer?.name || 'Walk-In Customer')}
                </div>

            </div>

            <div class="col-md-2">

                <strong>Sale Date</strong>

                <div>
                    ${escapeHtml(sale.sale_date)}
                </div>

            </div>
            <div class="col-md-2">

                <strong>Return Date</strong>

                <div>
                    <input type="date" name='datetime' id='datetime' class="form-control" value="{{ date('Y-m-d') }}">
                </div>

            </div>

            <div class="col-md-2">

                <strong>Grand Total</strong>

                <div>
                    ${parseFloat(sale.grand_total || 0).toFixed(2)}
                </div>

            </div>

        </div>

        <div class="table-responsive">

            <table class="table table-bordered">

                <thead>

                <tr>

                    <th>#</th>
                    <th>Product</th>
                    <th>Variant</th>
                    <th>Sold Qty</th>
                    <th>Returned Qty</th>
                    <th>Remaining</th>
                    <th>Return Qty</th>
                    <th>Price</th>
                    <th>Total</th>

                </tr>

                </thead>

                <tbody>
    `;


    items.forEach(function (item, index) {

        html += `

            <tr>

                <td>
                    ${index + 1}
                </td>

                <td>
                    ${escapeHtml(item.product_name || '-')}
                </td>

                <td>
                    ${escapeHtml(item.variant_name || '-')}
                </td>

                <td>
                    ${item.sold_qty || 0}
                </td>

                <td>
                    ${item.returned_qty || 0}
                </td>

                <td>
                    ${item.remaining_qty}
                </td>

                <td>
                    <input type="hidden" name="sale_item_id" value="${item.sale_item_id}">

                    <input type="number"
                           min="0"
                           max="${item.remaining_qty}"
                           step="0.01"
                           class="form-control returnQty"
                           data-index="${index}"
                           value="0">

                </td>

                <td>
                    ${parseFloat(item.sale_price || 0).toFixed(2)}
                </td>

                <td class="returnTotal">
                    0.00
                </td>

            </tr>
        `;

    });


    html += `

                </tbody>

            </table>

        </div>

        <div class="row">

            <div class="col-md-8">

                <label class="form-label">
                    Remarks
                </label>

                <textarea id="returnRemarks"
                          class="form-control"
                          rows="3"></textarea>

            </div>

            <div class="col-md-4">

                <div class="card">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <strong>Return Total</strong>

                            <strong id="returnGrandTotal">
                                0.00
                            </strong>

                        </div>

                        <button type="button"
                                class="btn btn-primary w-100 mt-3"
                                id="saveReturnBtn">
                            Save Return
                        </button>

                    </div>

                </div>

            </div>

        </div>

    `;

    $('#returnContent').html(html);

    returnItems = items;

}


$(document).on('input', '.returnQty', function () {

    let index = $(this).data('index');

    let item = returnItems[index];

    if (!item) {
        return;
    }

    let qty = parseFloat($(this).val() || 0);

    let max =
        parseFloat(item.remaining_qty || 0);

    if (qty > max) {

        qty = max;

        $(this).val(qty);

        toastr.warning(
            'Return quantity cannot exceed remaining quantity.'
        );

    }

    item.return_qty = qty;

    let total =
        qty *
        parseFloat(item.sale_price || 0);

    $(this)
        .closest('tr')
        .find('.returnTotal')
        .text(total.toFixed(2));

    calculateReturnTotal();

});


function calculateReturnTotal() {

    let total = 0;

    returnItems.forEach(function (item) {

        total +=
            parseFloat(item.return_qty || 0) *
            parseFloat(item.sale_price || 0);

    });

    $('#returnGrandTotal')
        .text(total.toFixed(2));

}


$(document).on('click', '#saveReturnBtn', function () {

    let selectedItems = [];

    returnItems.forEach(function (item) {

        let qty =
            parseFloat(item.return_qty || 0);

        if (qty > 0) {

            selectedItems.push({

                sale_item_id: item.sale_item_id,

                product_id: item.product_id,

                product_variant_id:
                    item.product_variant_id || null,

                qty: qty

            });

        }

    });


    if (selectedItems.length === 0) {

        toastr.error(
            'Please select at least one item to return.'
        );

        return;

    }


    Swal.fire({

        title: 'Save Sale Return?',

        text: 'Do you want to save this sale return?',

        icon: 'question',

        showCancelButton: true,

        confirmButtonText: 'Yes, Save',

        cancelButtonText: 'Cancel'

    }).then(function (result) {

        if (!result.isConfirmed) {
            return;
        }


        $('#saveReturnBtn')
            .prop('disabled', true)
            .text('Saving...');


        $.ajax({

            url: "{{ route('sales.return.store') }}",

            type: "POST",

            data: {

                _token: "{{ csrf_token() }}",

                sale_id: "{{ $sale->id }}",

                datetime: $('#datetime').val(),

                remarks: $('#returnRemarks').val(),

                items: selectedItems

            },

            success: function (response) {
               // alert(JSON.stringify(response));
                if (response.status) {

                    toastr.success(response.message);

                    if (response.invoice_url) {

                        window.open(
                            response.invoice_url,
                            '_blank'
                        );

                    }

                    setTimeout(function () {

                        window.location.href =
                            "{{ route('sales.returns.index') }}";

                    }, 700);

                } else {

                    toastr.error(response.message);

                    $('#saveReturnBtn')
                        .prop('disabled', false)
                        .text('Save Return');

                }

            },

            error: function (xhr) {
                //alert(JSON.stringify(xhr));
                toastr.error(
                    xhr.responseJSON?.message ||
                    'Unable to save return.'
                );

                $('#saveReturnBtn')
                    .prop('disabled', false)
                    .text('Save Return');

            }

        });

    });

});


function escapeHtml(value) {

    return $('<div>')
        .text(value ?? '')
        .html();

}

</script>

@endpush