@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Create Sale
@endsection

@section('content')

    <div class="container-fluid">

        <form id="saleForm">

            <input type="hidden" name="sale_type" id="sale_type" value="{{ $sale_type }}">

            <div class="row">

                <div class="col-md-12">

                    <div class="card mb-3">

                        <div class="card-header">
                            <h5 class="mb-0">{{ $sale_type }} Sale Information</h5>
                        </div>

                        <div class="card-body">

                            <div class="row">

                                <div class="col-md-4">

                                    <label class="form-label">
                                        Sale Type
                                    </label>

                                    <input type="text" class="form-control" value="{{ $sale_type }}" placeholder="Sale type" readonly>

                                </div>

                                <div class="col-md-4">

                                    <label class="form-label">
                                        Sale Date
                                    </label>

                                    <input type="date" name="sale_date" id="sale_date" class="form-control"
                                        value="{{ date('Y-m-d') }}">

                                </div>

                                @if ($sale_type == 'Wholesale')
                                    <div class="col-md-4">

                                        <label class="form-label">
                                            Customer
                                        </label>

                                        <select name="customer_id" id="customer_id" class="form-select select2">

                                            <option value="">
                                                Select Customer
                                            </option>

                                            @foreach ($customers as $customer)
                                                <option value="{{ $customer->id }}">
                                                    {{ $customer->name }}
                                                    @if ($customer->phone)
                                                        - {{ $customer->phone }}
                                                    @endif
                                                </option>
                                            @endforeach

                                        </select>

                                    </div>
                                @else
                                    <div class="col-md-4">

                                        <label class="form-label">
                                            Customer Phone
                                        </label>

                                        <input type="text" id="customer_phone" name="customer_phone" class="form-control"
                                            placeholder="Customer phone">

                                    </div>
                                @endif

                            </div>

                            @if ($sale_type == 'Walk-In')
                                <div class="row mt-3">

                                    <div class="col-md-4">

                                        <label class="form-label">
                                            Customer Name
                                        </label>

                                        <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="Enter customer name">

                                    </div>

                                    <div class="col-md-4">

                                        <label class="form-label">
                                            Address
                                        </label>

                                        <input type="text" id="customer_address" name="customer_address"
                                            class="form-control" placeholder="Enter customer address">

                                    </div>

                                    <div class="col-md-4">

                                        <label class="form-label">
                                            Current Due
                                        </label>

                                        <input type="text" id="customer_due" class="form-control" value="0.00"
                                            placeholder="0.00" readonly>

                                    </div>

                                </div>
                            @else
                                <div class="row mt-3">

                                    <div class="col-md-4">

                                        <label class="form-label">
                                            Current Due
                                        </label>

                                        <input type="text" id="customer_due" class="form-control" value="0.00"
                                            placeholder="0.00" readonly>

                                    </div>

                                </div>
                            @endif

                        </div>

                    </div>


                    <div class="card">

                        <div class="card-header">

                            <div class="row align-items-end">

                                <div class="col-md-8">

                                    <label class="form-label">
                                        Search Product
                                    </label>

                                    <select id="productSearch" class="form-select  select2">
                                        <option value="">Select Product</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>

                                </div>

                                <div class="col-md-4 text-end">

                                    <span class="badge bg-secondary" id="cartCount">
                                        0 Items
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div class="card-body">

                            <div class="table-responsive">

                                <table class="table table-bordered" id="saleCartTable">

                                    <thead>

                                        <tr>
                                            <th>Product</th>
                                            <th>Variant</th>
                                            <th>Stock</th>
                                            <th width="90">Qty</th>
                                            <th width="120">Price</th>
                                            <th width="130">Discount</th>
                                            <th width="130">Subtotal</th>
                                            <th width="130">Total</th>
                                            <th width="60"></th>
                                        </tr>

                                    </thead>

                                    <tbody id="saleCartBody">

                                        <tr id="emptyCartRow">

                                            <td colspan="9" class="text-center text-muted">

                                                No products added.

                                            </td>

                                        </tr>

                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="7" class="text-end">Subtotal</td>
                                            <td>
                                                <input type="text" id="subtotal" class="form-control text-end"
                                                    value="0.00" placeholder="0.00" readonly>
                                            </td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="text-end">Discount Type</td>
                                            <td>
                                                <select name="discount_type" id="discount_type" class="form-select">
                                                    <option value="Amount"> Amount</option>
                                                    <option value="Percentage"> Percentage</option>
                                                </select>
                                            </td>
                                            <td class="text-end">Discount</td>
                                            <td>
                                                <input type="number" step="0.01" min="0"
                                                    name="discount_value" id="discount_value" class="form-control"
                                                    value="0" placeholder="0.00">
                                            </td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="7" class="text-end">Discount Amount</td>
                                            <td>
                                                <input type="text" id="discount_amount" class="form-control"
                                                    value="0.00" placeholder="0.00" readonly>
                                            </td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="7" class="text-end">Grand Total</td>
                                            <td>
                                                <input type="text" id="grand_total" class="form-control fw-bold"
                                                    value="0.00" placeholder="0.00" readonly>
                                            </td>
                                            <td></td>
                                        </tr>

                                        <tr>
                                            <td colspan="7" class="text-end">
                                                <strong>Payment Method</strong>
                                            </td>

                                            <td>
                                                <select id="payment_method" name="payment_method" class="form-control">

                                                    <option value="">
                                                        Select Payment Method
                                                    </option>
                                                    @foreach ($childCoas as $childCoa)
                                                        <option value="{{ $childCoa->id }}">{{ $childCoa->account_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td></td>

                                        </tr>
                                        <tr>

                                            <td colspan="7" class="text-end">
                                                <strong>Payment Account</strong>
                                            </td>

                                            <td>

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
                                            <td colspan="7" class="text-end">Paid Amount</td>
                                            <td>
                                                <input type="number" step="0.01" min="0" name="paid_amount"
                                                    id="paid_amount" class="form-control" value="0" placeholder="0.00">
                                            </td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="7" class="text-end">Due Amount</td>
                                            <td>
                                                <input type="text" id="due_amount" class="form-control"
                                                    value="0.00" placeholder="0.00" readonly>
                                            </td>
                                            <td></td>
                                        </tr>

                                    </tfoot>
                                </table>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-md-7"></div>
                <div class="col-md-5">
                    <div class="mt-3">
                        <label class="form-label">
                            Remarks
                        </label>
                        <textarea name="remarks" id="remarks" class="form-control" rows="3" placeholder="Remarks"></textarea>
                    </div>

                    <div class="d-flex justify-content-between mt-4">

                        <a href="{{ route('sales.index', ['type' => $sale_type]) }}" class="btn btn-secondary">
                            Cancel
                        </a>

                        <button type="submit" class="btn btn-primary" id="saveSaleBtn">
                            Save Sale
                        </button>

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
        let saleItems = [];

        $(document).ready(function() {

            $('.select2').select2({
                width: '100%'
            });





            $('#customer_id').on('change', function() {
                let id = $(this).val();
                if (!id) {
                    $('#customer_due').val('0.00');
                    return;
                }
                $.ajax({
                    url: "{{ url('/admin/sales/customer-due') }}/" + id,
                    type: 'GET',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (response.status) {
                            $('#customer_due').val(
                                parseFloat(response.due || 0).toFixed(2)
                            );
                        }
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                    }

                });

            });


            $('#customer_phone').on('input', function() {

                let phone = $.trim($(this).val());

                if (!phone) {
                    return;
                }

                $.ajax({

                    url: "{{ url('/admin/sales/customer-by-phone') }}/" +
                        encodeURIComponent(phone),

                    type: 'GET',

                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (response.status && response.customer) {

                            $('#customer_name')
                                .val(response.customer.name || '');

                            $('#customer_address')
                                .val(response.customer.address || '');

                            $('#customer_due').val(parseFloat(response.current_due || 0)
                                .toFixed(2));

                        }

                    },
                    error: function(xhr) {
                        //alert(JSON.stringify(xhr));
                    }

                });

            });


            $('#discount_value').on('input', calculateTotals);

            $('#discount_type').on('change', calculateTotals);

            $('#paid_amount').on('input', calculateTotals);


            $('#saleForm').on('submit', function(e) {

                e.preventDefault();

                if (saleItems.length === 0) {

                    toastr.error('Please add at least one product.');

                    return;

                }

                calculateTotals();

                Swal.fire({

                    title: 'Save Sale?',

                    text: 'Do you want to save this sale?',

                    icon: 'question',

                    showCancelButton: true,

                    confirmButtonText: 'Yes, Save',

                    cancelButtonText: 'Cancel'

                }).then(function(result) {

                    if (!result.isConfirmed) {
                        return;
                    }

                    saveSale();

                });

            });

        });

        $('#productSearch').on('change', function() {
            var product_id = $(this).val();
            addProduct(product_id);
        });


        function addProduct(productId) {

            $.ajax({

                url: "{{ url('/admin/sales/products/variants') }}/" +
                    productId,

                type: 'GET',

                success: function(response) {

                    if (!response.status) {

                        toastr.error(response.message);

                        return;

                    }

                    let product = response.data.product;

                    let variants = response.data.variants || [];

                    let existing = saleItems.find(function(item) {

                        return parseInt(item.product_id) ===
                            parseInt(product.id) &&
                            !item.product_variant_id;

                    });

                    if (existing) {

                        existing.qty++;

                        renderCart();

                        return;

                    }


                    if (variants.length > 0) {

                        let variant = variants[0];

                        addSaleItem(
                            product,
                            variant
                        );

                    } else {

                        addSaleItem(
                            product,
                            null
                        );

                    }

                },

                error: function(xhr) {

                    toastr.error(
                        xhr.responseJSON?.message ||
                        'Unable to load product.'
                    );

                }

            });

        }


        function addSaleItem(product, variant) {

            let variantId = variant ? variant.id : '';

            let stock = variant ?
                parseFloat(variant.stock || 0) :
                parseFloat(product.stock || 0);

            let salePrice = variant ?
                parseFloat(
                    variant.sale_price ??
                    product.sale_price ??
                    0
                ) :
                parseFloat(product.sale_price || 0);


            let existing = saleItems.find(function(item) {

                return parseInt(item.product_id) === parseInt(product.id) &&
                    String(item.product_variant_id || '') ===
                    String(variantId);

            });


            if (existing) {

                existing.qty++;

            } else {

                saleItems.push({

                    product_id: product.id,

                    product_variant_id: variantId,

                    product_name: product.name,

                    variant_name: variant ?
                        (
                            variant.attribute_name ||
                            variant.variant_name ||
                            '-'
                        ) :
                        '-',

                    stock: stock,

                    qty: 1,

                    sale_price: salePrice,

                    discount_type: 'Amount',

                    discount_value: 0,

                    discount_amount: 0,

                    subtotal: salePrice,

                    total: salePrice

                });

            }

            renderCart();

        }


        function renderCart() {

            let tbody = $('#saleCartBody');

            tbody.empty();

            if (saleItems.length === 0) {

                tbody.html(`
            <tr id="emptyCartRow">
                <td colspan="9"
                    class="text-center text-muted">
                    No products added.
                </td>
            </tr>
        `);

                calculateTotals();

                return;

            }


            saleItems.forEach(function(item, index) {

                let subtotal =
                    parseFloat(item.qty || 0) *
                    parseFloat(item.sale_price || 0);

                let discountAmount =
                    calculateItemDiscount(item, subtotal);

                let total =
                    Math.max(0, subtotal - discountAmount);

                item.subtotal = subtotal;
                item.discount_amount = discountAmount;
                item.total = total;


                let variantHtml = '';

                if (item.product_variant_id) {

                    variantHtml = `
                <span class="badge bg-info">
                    ${escapeHtml(item.variant_name)}
                </span>
            `;

                } else {

                    variantHtml = '-';

                }


                tbody.append(`

            <tr>

                <td>
                    ${escapeHtml(item.product_name)}
                </td>

                <td>
                    ${variantHtml}
                </td>

                <td>
                    <span class="stock-value">
                        ${item.stock}
                    </span>
                </td>

                <td>

                    <input type="number"
                           min="0.01"
                           step="0.01"
                           class="form-control form-control-sm itemQty"
                           data-index="${index}"
                           value="${item.qty}"
                           placeholder="1">

                </td>

                <td>

                    <input type="number"
                           min="0"
                           step="0.01"
                           class="form-control form-control-sm itemPrice"
                           data-index="${index}"
                           value="${item.sale_price}"
                           placeholder="0.00">

                </td>

                <td>

                    <div class="input-group input-group-sm">

                        <select class="form-select itemDiscountType"
                                data-index="${index}">

                            <option value="Amount"
                                ${item.discount_type === 'Amount' ? 'selected' : ''}>
                                Amount
                            </option>

                            <option value="Percentage"
                                ${item.discount_type === 'Percentage' ? 'selected' : ''}>
                                %
                            </option>

                        </select>

                        <input type="number"
                               min="0"
                               step="0.01"
                               class="form-control itemDiscountValue"
                               data-index="${index}"
                               value="${item.discount_value}"
                               placeholder="0.00">

                    </div>

                </td>

                <td>
                    ${subtotal.toFixed(2)}
                </td>

                <td>
                    <strong>
                        ${total.toFixed(2)}
                    </strong>
                </td>

                <td>

                    <button type="button"
                            class="btn btn-sm btn-danger removeSaleItem"
                            data-index="${index}">

                        <i class="bi bi-trash"></i>

                    </button>

                </td>

            </tr>

        `);

            });


            $('#cartCount').text(
                saleItems.length + ' Items'
            );

            calculateTotals();

        }


        function calculateItemDiscount(item, subtotal) {

            let value =
                parseFloat(item.discount_value || 0);

            if (value <= 0) {
                return 0;
            }

            if (item.discount_type === 'Percentage') {

                return Math.min(
                    subtotal,
                    subtotal * value / 100
                );

            }

            return Math.min(
                subtotal,
                value
            );

        }


        $(document).on('input change',
            '.itemQty, .itemPrice, .itemDiscountType, .itemDiscountValue',
            function() {

                let index = $(this).data('index');

                if (typeof index === 'undefined') {
                    return;
                }

                let item = saleItems[index];

                if (!item) {
                    return;
                }

                if ($(this).hasClass('itemQty')) {

                    let qty = parseFloat($(this).val() || 0);

                    if (qty > parseFloat(item.stock || 0)) {

                        toastr.error(
                            'Quantity cannot exceed available stock.'
                        );

                        qty = parseFloat(item.stock || 0);

                        $(this).val(qty);

                    }

                    item.qty = qty;

                }

                if ($(this).hasClass('itemPrice')) {

                    item.sale_price =
                        parseFloat($(this).val() || 0);

                }

                if ($(this).hasClass('itemDiscountType')) {

                    item.discount_type =
                        $(this).val();

                }

                if ($(this).hasClass('itemDiscountValue')) {

                    item.discount_value =
                        parseFloat($(this).val() || 0);

                }

                renderCart();

            });


        $(document).on('click', '.removeSaleItem', function() {

            let index = $(this).data('index');

            saleItems.splice(index, 1);

            renderCart();

        });


        function calculateTotals() {

            let subtotal = 0;

            saleItems.forEach(function(item) {

                let itemSubtotal =
                    parseFloat(item.qty || 0) *
                    parseFloat(item.sale_price || 0);

                subtotal += itemSubtotal;

            });


            let discountValue =
                parseFloat($('#discount_value').val() || 0);

            let discountType =
                $('#discount_type').val();

            let discountAmount = 0;

            if (discountValue > 0) {

                if (discountType === 'Percentage') {

                    discountAmount =
                        subtotal * discountValue / 100;

                } else {

                    discountAmount =
                        discountValue;

                }

                discountAmount =
                    Math.min(
                        subtotal,
                        discountAmount
                    );

            }


            let grandTotal =
                Math.max(
                    0,
                    subtotal - discountAmount
                );


            let paid =
                parseFloat($('#paid_amount').val() || 0);

            if (paid > grandTotal) {

                paid = grandTotal;

                $('#paid_amount').val(
                    paid.toFixed(2)
                );

            }


            let due =
                Math.max(
                    0,
                    grandTotal - paid
                );


            $('#subtotal').val(
                subtotal.toFixed(2)
            );

            $('#discount_amount').val(
                discountAmount.toFixed(2)
            );

            $('#grand_total').val(
                grandTotal.toFixed(2)
            );

            $('#due_amount').val(
                due.toFixed(2)
            );

        }


        function saveSale() {

            let data = $('#saleForm').serializeArray();

            data.push({
                name: 'items',
                value: JSON.stringify(saleItems)
            });


            $('#saveSaleBtn')
                .prop('disabled', true)
                .text('Saving...');


            $.ajax({

                url: "{{ route('sales.store') }}",

                type: 'POST',

                data: $.param(data) +
                    '&_token={{ csrf_token() }}',

                success: function(response) {
                    // alert(JSON.stringify(response));
                    if (response.status) {

                        toastr.success(response.message);

                        if (response.invoice_url) {

                            window.open(
                                response.invoice_url,
                                '_blank'
                            );

                        }

                        setTimeout(function() {

                            window.location.href =
                                "{{ route('sales.index') }}" +
                                "?type=" +
                                encodeURIComponent(
                                    $('#sale_type').val()
                                );

                        }, 700);

                    } else {

                        toastr.error(response.message);

                        $('#saveSaleBtn')
                            .prop('disabled', false)
                            .text('Save Sale');

                    }

                },

                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    let message =
                        xhr.responseJSON?.message ||
                        'Unable to save sale.';

                    if (xhr.responseJSON?.errors) {

                        let errors =
                            xhr.responseJSON.errors;

                        Object.keys(errors).forEach(function(key) {

                            toastr.error(
                                errors[key][0]
                            );

                        });

                    } else {

                        toastr.error(message);

                    }

                    $('#saveSaleBtn')
                        .prop('disabled', false)
                        .text('Save Sale');

                }

            });

        }


        function escapeHtml(value) {

            return $('<div>')
                .text(value ?? '')
                .html();

        }

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
