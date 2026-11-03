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

        dataType: 'json',

        success: function(response) {
            // alert(JSON.stringify(response));

            if (!response.status) {

                toastr.error(response.message);

                return;

            }

            renderReturnData(response);

        },

        error: function(xhr) {
            // alert(JSON.stringify(xhr));

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
                    <th>Refund / Unit</th>
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
                           value="0"
                           placeholder="0">

                </td>

                <td>
                    ${parseFloat(item.refund_price ?? item.sale_price ?? 0).toFixed(2)}
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
                          rows="3"
                          placeholder="Optional reason or note for this return"></textarea>

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

                        <div class="mt-3">
                            <div class="form-label mb-1">Refund <span class="text-danger">*</span></div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="refund_mode" id="refundCredit" value="Credit" checked>
                                <label class="form-check-label" for="refundCredit">Keep as customer credit</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="refund_mode" id="refundCash" value="Cash">
                                <label class="form-check-label" for="refundCash">Cash refund now</label>
                            </div>
                            <div class="form-text">Customer balance now: {{ number_format(abs((float) $customerBalance), 2) }} {{ (float) $customerBalance < 0 ? '(credit)' : '(due)' }}. Credit is taken off what the customer owes, or can be paid back later from Payment.</div>
                        </div>

                        <div id="cashRefundFields" class="mt-2 d-none">
                            <label class="form-label" for="refundMethod">Pay from method <span class="text-danger">*</span></label>
                            <select id="refundMethod" class="form-select form-select-sm mb-2">
                                <option value="">Select method</option>
                                @foreach ($payMethods as $pm)
                                    <option value="{{ $pm->id }}">{{ $pm->account_name }}</option>
                                @endforeach
                            </select>
                            <label class="form-label" for="refundAccount">Pay from account <span class="text-danger">*</span></label>
                            <select id="refundAccount" class="form-select form-select-sm mb-2">
                                <option value="">Select method first</option>
                            </select>
                            <label class="form-label" for="refundAmount">Cash refund amount</label>
                            <input type="number" step="0.01" min="0" id="refundAmount" class="form-control form-control-sm" placeholder="Return total">
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
        parseFloat(item.refund_price ?? item.sale_price ?? 0);

    $(this)
        .closest('tr')
        .find('.returnTotal')
        .text(total.toFixed(2));

    calculateReturnTotal();

});


$(document).on('change', 'input[name=refund_mode]', function () {
    $('#cashRefundFields').toggleClass('d-none', $('input[name=refund_mode]:checked').val() !== 'Cash');
});

$(document).on('change', '#refundMethod', function () {
    let $account = $('#refundAccount');
    if (!$(this).val()) {
        $account.html('<option value="">Select method first</option>');
        return;
    }
    $.ajax({
        url: "{{ route('admin.getCoaChilds') }}",
        type: 'GET',
        dataType: 'html',
        data: { parent_id: $(this).val() },
        success: function(response) {
            // alert(JSON.stringify(response));
            $account.html(response);
        },
        error: function(xhr) {
            // alert(JSON.stringify(xhr));
            toastr.error('Could not load payment accounts.');
        }
    });
});

function calculateReturnTotal() {

    let total = 0;

    returnItems.forEach(function (item) {

        total +=
            parseFloat(item.return_qty || 0) *
            parseFloat(item.refund_price ?? item.sale_price ?? 0);

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

    if ($('input[name=refund_mode]:checked').val() === 'Cash' && !$('#refundAccount').val()) {
        toastr.error('Choose the method and account the cash refund is paid from.');
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

            dataType: 'json',

            data: {

                _token: "{{ csrf_token() }}",

                sale_id: "{{ $sale->id }}",

                datetime: $('#datetime').val(),

                remarks: $('#returnRemarks').val(),

                refund_mode: $('input[name=refund_mode]:checked').val() || 'Credit',

                payment_method: $('#refundMethod').val() || '',

                payment_method_account: $('#refundAccount').val() || '',

                refund_amount: $('#refundAmount').val() || '',

                items: selectedItems

            },

            success: function(response) {
                // alert(JSON.stringify(response));
                if (response.status) {

                    toastr.success(response.message);

                    if (response.return_url || response.invoice_url) {

                        window.open(
                            response.return_url || response.invoice_url,
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

            error: function(xhr) {
                // alert(JSON.stringify(xhr));
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
        // Escapes quotes too: the output also goes into attribute values.
        return String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

</script>

@endpush