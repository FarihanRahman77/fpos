@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Create Sale Order
@endsection

@section('content')

<div class="container-fluid">

    <form id="saleOrderForm">

        <div class="row">

            {{-- LEFT SIDE --}}
            <div class="col-md-12">

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Order Information</h5>
                    </div>

                    <div class="card-body">
                        <div class="row">

                            <div class="col-md-4">
                                <label class="form-label">Customer</label>

                                <select name="customer_id" id="customer_id" class="form-select select2" required>
                                    <option value="">Select Customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">
                                            {{ $customer->name }}
                                            @if($customer->phone)
                                                - {{ $customer->phone }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Order Date</label>
                                <input type="date"
                                       name="order_date"
                                       id="order_date"
                                       class="form-control"
                                       value="{{ date('Y-m-d') }}"
                                       required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Delivery Date</label>
                                <input type="date"
                                       name="delivery_date"
                                       id="delivery_date"
                                       class="form-control">
                            </div>

                        </div>
                    </div>
                </div>


                <div class="card">

                    <div class="card-header">
                        <label class="form-label mb-1">Search Product</label>

                        <select id="orderProductSearch" class="form-select select2">
                            <option value="">Select Product</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">

                            <table class="table table-bordered align-middle">
                                <thead>
                                <tr>
                                    <th>Product</th>
                                    <th style="min-width:140px;">Variant</th>
                                    <th style="width:110px;">Qty</th>
                                    <th style="width:120px;">Price</th>
                                    <th style="min-width:200px;">Discount</th>
                                    <th style="width:110px;">Total</th>
                                    <th style="width:50px;"></th>
                                </tr>
                                </thead>

                                <tbody id="orderCartBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        No products added.
                                    </td>
                                </tr>
                                </tbody>

                                <tfoot>

                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Subtotal</td>
                                    <td colspan="3">
                                        <input type="text" id="orderSubtotal" class="form-control" value="0.00" readonly>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan=" 4" class="text-end fw-bold">Discount</td>
                                    <td colspan="3">
                                        <div class="input-group">
                                            <select name="discount_type" id="orderDiscountType" class="form-select" style="max-width:130px;">
                                                <option value="Amount">Amount</option>
                                                <option value="Percentage">Percentage</option>
                                            </select>
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   name="discount_value"
                                                   id="orderDiscountValue"
                                                   class="form-control"
                                                   value="0">
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Discount Amount</td>
                                    <td colspan="3">
                                        <input type="text" id="orderDiscountAmount" class="form-control" value="0.00" readonly>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Grand Total</td>
                                    <td colspan="3">
                                        <input type="text" id="orderGrandTotal" class="form-control fw-bold" value="0.00" readonly>
                                    </td>
                                </tr>
                                <tr>
                                        <td colspan="5" class="text-end">
                                            <strong>Payment Method</strong>
                                        </td>

                                        <td colspan="3">
                                            <select id="payment_method" name="payment_method" class="form-control">

                                                <option value="">
                                                    Select Payment Method
                                                </option>
                                                @foreach ($childCoas as $childCoa)
                                                    <option value="{{ $childCoa->id }}">{{ $childCoa->account_name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Payment Account</strong>
                                    </td>

                                    <td colspan="3">

                                        <select id="payment_method_account" name="payment_method_account"
                                            class="form-control">

                                            <option value="">
                                                Select Payment Method Account
                                            </option>
                                            
                                        </select>
                                    </td>
                                    <td></td>

                                </tr>

                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Advance Paid</td>
                                    <td colspan="3">
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               name="advance_paid"
                                               id="orderAdvancePaid"
                                               class="form-control"
                                               value="0">
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Due Amount</td>
                                    <td colspan="3">
                                        <input type="text" id="orderDueAmount" class="form-control" value="0.00" readonly>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="8">
                                        <label class="form-label">Remarks</label>
                                        <textarea name="remarks" class="form-control" rows="3"></textarea>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="8">
                                        <div class="d-flex justify-content-between">
                                            <a href="{{ route('sales.orders.index') }}" class="btn btn-secondary">
                                                Cancel
                                            </a>

                                            <button type="submit" class="btn btn-primary" id="saveOrderBtn">
                                                Save Order
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                </tfoot>
                            </table>

                        </div>
                    </div>
                </div>

            </div>

        </div>

    </form>

</div>

@endsection


@push('scripts')

<script>

let orderItems = [];

/* =========================================================
   HELPERS
========================================================= */

function toNum(value) {
    let n = parseFloat(value);
    return isNaN(n) ? 0 : n;
}

function escapeHtml(value) {
    return $('<div>').text(value ?? '').html();
}

// Calculates subtotal / discount / total for one cart item
function computeItem(item) {

    let subtotal = toNum(item.qty) * toNum(item.sale_price);

    let discount = item.discount_type === 'Percentage'
        ? subtotal * toNum(item.discount_value) / 100
        : toNum(item.discount_value);

    discount = Math.min(subtotal, discount);

    item.subtotal        = subtotal;
    item.discount_amount = discount;
    item.total           = Math.max(0, subtotal - discount);
}


/* =========================================================
   ADD PRODUCT
========================================================= */

function addOrderProduct(productId) {

    $.ajax({

        url: "{{ url('/admin/sales/products/variants') }}/" + productId,

        type: 'GET',

        success: function (response) {

            if (!response.status) {
                toastr.error(response.message || 'Unable to load product.');
                return;
            }

            let product  = response.data.product;
            let variants = response.data.variants || [];

            // First variant is selected by default. User can change it in the table.
            addOrderItem(product, variants, variants.length > 0 ? variants[0] : null);

        },

        error: function (xhr) {
            toastr.error(xhr.responseJSON?.message || 'Unable to load product.');
        }

    });

}


function addOrderItem(product, variants, variant) {

    let variantId = variant ? variant.id : '';

    // Same product + same variant => just increase qty
    let existing = orderItems.find(function (item) {
        return parseInt(item.product_id) === parseInt(product.id) &&
               String(item.product_variant_id || '') === String(variantId);
    });

    if (existing) {

        existing.qty = toNum(existing.qty) + 1;

    } else {

        orderItems.push({

            product_id: product.id,
            product_variant_id: variantId,
            product_name: product.name,

            // keep all variants so the user can switch inside the table
            product_price: toNum(product.sale_price),
            variants: variants,

            variant_name: variant ? (variant.attribute_name || '-') : '-',

            qty: 1,

            sale_price: toNum(variant?.sale_price ?? product.sale_price ?? 0),

            discount_type: 'Amount',
            discount_value: 0,
            discount_amount: 0,
            subtotal: 0,
            total: 0

        });

    }

    renderOrderCart();

}


/* =========================================================
   RENDER CART
========================================================= */

function renderOrderCart() {

    let tbody = $('#orderCartBody');
    tbody.empty();

    if (orderItems.length === 0) {

        tbody.html(`
            <tr>
                <td colspan="7" class="text-center text-muted">
                    No products added.
                </td>
            </tr>
        `);

        calculateOrderTotals();
        return;
    }

    orderItems.forEach(function (item, index) {

        computeItem(item);

        // Variant cell
        let variantCell = '-';

        if (item.variants && item.variants.length > 0) {

            let options = item.variants.map(function (v) {

                let selected = String(v.id) === String(item.product_variant_id) ? 'selected' : '';
                let stock    = (v.current_stock !== undefined) ? ' (Stock: ' + v.current_stock + ')' : '';

                return `<option value="${v.id}" ${selected}>
                            ${escapeHtml((v.attribute_name || 'Variant #' + v.id) + stock)}
                        </option>`;

            }).join('');

            variantCell = `
                <select class="form-select orderVariant" data-index="${index}">
                    ${options}
                </select>
            `;
        }

        tbody.append(`

            <tr data-index="${index}">

                <td>${escapeHtml(item.product_name)}</td>

                <td>${variantCell}</td>

                <td>
                    <input type="number"
                           min="0.01"
                           step="0.01"
                           class="form-control orderQty"
                           data-index="${index}"
                           value="${item.qty}">
                </td>

                <td>
                    <input type="number"
                           min="0"
                           step="0.01"
                           class="form-control orderPrice"
                           data-index="${index}"
                           value="${item.sale_price}">
                </td>

                <td>
                    <div class="input-group">

                        <select class="form-select orderItemDiscountType"
                                data-index="${index}"
                                style="max-width:90px;">
                            <option value="Amount" ${item.discount_type === 'Amount' ? 'selected' : ''}>Amt</option>
                            <option value="Percentage" ${item.discount_type === 'Percentage' ? 'selected' : ''}>%</option>
                        </select>

                        <input type="number"
                               min="0"
                               step="0.01"
                               class="form-control orderItemDiscountValue"
                               data-index="${index}"
                               value="${item.discount_value}">
                    </div>
                </td>

                <td class="rowTotal fw-bold">${item.total.toFixed(2)}</td>

                <td>
                    <button type="button"
                            class="btn btn-danger btn-sm removeOrderItem"
                            data-index="${index}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>

            </tr>

        `);

    });

    calculateOrderTotals();

}


/* =========================================================
   TOTALS
========================================================= */

function calculateOrderTotals() {

    // Subtotal = sum of item totals (after item discounts) - same as backend
    let subtotal = 0;

    orderItems.forEach(function (item) {
        computeItem(item);
        subtotal += item.total;
    });

    let value = toNum($('#orderDiscountValue').val());
    let type  = $('#orderDiscountType').val();

    let discount = type === 'Percentage'
        ? subtotal * value / 100
        : value;

    discount = Math.min(subtotal, discount);

    let grandTotal = Math.max(0, subtotal - discount);

    let advance = toNum($('#orderAdvancePaid').val());

    if (advance > grandTotal) {
        advance = grandTotal;
        $('#orderAdvancePaid').val(advance.toFixed(2));
    }

    let due = Math.max(0, grandTotal - advance);

    $('#orderSubtotal').val(subtotal.toFixed(2));
    $('#orderDiscountAmount').val(discount.toFixed(2));
    $('#orderGrandTotal').val(grandTotal.toFixed(2));
    $('#orderDueAmount').val(due.toFixed(2));

}


/* =========================================================
   SAVE
========================================================= */

function saveSaleOrder() {

    let formData = $('#saleOrderForm').serializeArray();

    // send only what the server needs
    let payloadItems = orderItems.map(function (item) {
        return {
            product_id: item.product_id,
            product_variant_id: item.product_variant_id,
            qty: item.qty,
            sale_price: item.sale_price,
            discount_type: item.discount_type,
            discount_value: item.discount_value
        };
    });

    formData.push({
        name: 'items',
        value: JSON.stringify(payloadItems)
    });

    $('#saveOrderBtn').prop('disabled', true).text('Saving...');

    $.ajax({

        url: "{{ route('sales.orders.store') }}",

        type: 'POST',

        data: $.param(formData) + '&_token={{ csrf_token() }}',

        success: function (response) {
          //  alert(JSON.stringify(response));
            if (response.status) {

                toastr.success(response.message);

                if (response.order_url) {
                    window.open(response.order_url, '_blank');
                }

                setTimeout(function () {
                    window.location.href = "{{ route('sales.orders.index') }}";
                }, 700);

            } else {

                toastr.error(response.message);
                $('#saveOrderBtn').prop('disabled', false).text('Save Order');

            }

        },

        error: function (xhr) {
          //  alert(JSON.stringify(xhr));
            toastr.error(xhr.responseJSON?.message || 'Unable to save sale order.');
            $('#saveOrderBtn').prop('disabled', false).text('Save Order');

        }

    });

}


/* =========================================================
   EVENTS
========================================================= */

$(document).ready(function () {

    $('.select2').select2({ width: '100%' });

    // ---- Product selected ----
    $('#orderProductSearch').on('change', function () {

        let productId = $(this).val();

        if (!productId) {
            return;
        }

        addOrderProduct(productId);

        // reset so the same product can be selected again
        // (namespaced trigger only refreshes Select2, doesn't re-fire this handler)
        $(this).val('').trigger('change.select2');

    });

    // ---- Order level discount / advance ----
    $('#orderDiscountType, #orderDiscountValue, #orderAdvancePaid')
        .on('input change', calculateOrderTotals);

    // ---- Typing in qty / price / item discount: update row WITHOUT re-rendering (keeps focus) ----
    $('#orderCartBody').on('input', '.orderQty, .orderPrice, .orderItemDiscountValue', function () {

        let index = $(this).data('index');
        let item  = orderItems[index];

        if (!item) return;

        if ($(this).hasClass('orderQty')) {
            item.qty = toNum($(this).val());
        }

        if ($(this).hasClass('orderPrice')) {
            item.sale_price = toNum($(this).val());
        }

        if ($(this).hasClass('orderItemDiscountValue')) {
            item.discount_value = toNum($(this).val());
        }

        computeItem(item);

        $('#orderCartBody tr[data-index="' + index + '"] .rowTotal')
            .text(item.total.toFixed(2));

        calculateOrderTotals();

    });

    // ---- Item discount type changed ----
    $('#orderCartBody').on('change', '.orderItemDiscountType', function () {

        let item = orderItems[$(this).data('index')];

        if (!item) return;

        item.discount_type = $(this).val();

        renderOrderCart();

    });

    // ---- Variant changed ----
    $('#orderCartBody').on('change', '.orderVariant', function () {

        let index = $(this).data('index');
        let item  = orderItems[index];

        if (!item) return;

        let newVariantId = String($(this).val());

        // If another row already has this product + variant, merge into it
        let duplicateIndex = orderItems.findIndex(function (other, i) {
            return i !== index &&
                   parseInt(other.product_id) === parseInt(item.product_id) &&
                   String(other.product_variant_id || '') === newVariantId;
        });

        if (duplicateIndex !== -1) {

            orderItems[duplicateIndex].qty =
                toNum(orderItems[duplicateIndex].qty) + toNum(item.qty);

            orderItems.splice(index, 1);

            toastr.info('Variant already in cart. Quantities merged.');

            renderOrderCart();
            return;
        }

        let variant = item.variants.find(function (v) {
            return String(v.id) === newVariantId;
        });

        if (variant) {
            item.product_variant_id = variant.id;
            item.variant_name       = variant.attribute_name || '-';
            item.sale_price         = toNum(variant.sale_price ?? item.product_price ?? 0);
        }

        renderOrderCart();

    });

    // ---- Remove item ----
    $('#orderCartBody').on('click', '.removeOrderItem', function () {

        orderItems.splice($(this).data('index'), 1);

        renderOrderCart();

    });

    // ---- Submit ----
    $('#saleOrderForm').on('submit', function (e) {

        e.preventDefault();

        if (!$('#customer_id').val()) {
            toastr.error('Please select a customer.');
            return;
        }

        if (orderItems.length === 0) {
            toastr.error('Please add at least one product.');
            return;
        }

        let invalid = orderItems.find(function (item) {
            return toNum(item.qty) <= 0;
        });

        if (invalid) {
            toastr.error('Quantity must be greater than 0 for "' + invalid.product_name + '".');
            return;
        }

        let orderDate    = $('#order_date').val();
        let deliveryDate = $('#delivery_date').val();

        if (deliveryDate && orderDate && deliveryDate < orderDate) {
            toastr.error('Delivery date cannot be before order date.');
            return;
        }

        Swal.fire({
            title: 'Save Sale Order?',
            text: 'Do you want to save this sale order?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Save',
            cancelButtonText: 'Cancel'
        }).then(function (result) {

            if (result.isConfirmed) {
                saveSaleOrder();
            }

        });

    });

});

$('#payment_method').on('change', function() {
    var payment_method = $(this).val();

    $.ajax({
        url: "{{ route('admin.getCoaChilds') }}",
        type: 'GET',
        data: {
            parent_id: payment_method
        },
        success: function(response) {
            //alert(JSON.stringify(response));
            $('#payment_method_account').html(response);
        },
        error: function(xhr) {
            //alert(JSON.stringify(xhr));
        }
    });
});

</script>

@endpush
