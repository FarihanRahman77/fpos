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
                                        @if ($programs['vat_enabled'])
                                            <tr>
                                                <td colspan="7" class="text-end">VAT{{ $programs['vat_inclusive'] ? ' (included in prices)' : '' }}</td>
                                                <td>
                                                    <input type="text" id="vat_amount" class="form-control text-end"
                                                        value="0.00" placeholder="0.00" readonly aria-label="VAT">
                                                </td>
                                                <td></td>
                                            </tr>
                                        @endif
                                        @if ($programs['loyalty'])
                                            <tr id="loyaltyRow" class="d-none">
                                                <td colspan="5" class="text-end">
                                                    Redeem Points <small class="text-muted" id="pointsHave"></small>
                                                </td>
                                                <td>
                                                    <input type="number" name="redeem_points" id="redeem_points" class="form-control"
                                                        min="0" step="1" placeholder="Points" aria-label="Points to redeem">
                                                </td>
                                                <td class="text-end">Points Discount</td>
                                                <td>
                                                    <input type="text" id="loyalty_discount" class="form-control text-end"
                                                        value="0.00" placeholder="0.00" readonly aria-label="Points discount">
                                                </td>
                                                <td></td>
                                            </tr>
                                        @endif
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
                                            <td>
                                                <button type="button" class="btn btn-sm btn-secondary" id="payFullBtn"
                                                    title="Pay the full grand total">Full</button>
                                            </td>
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
        const PROGRAMS = @json($programs);
        let customerProgram = null;

        $(document).ready(function() {

            $('.select2').select2({
                width: '100%'
            });





            $('#customer_id').on('change', function() {
                let id = $(this).val();
                if (!id) {
                    $('#customer_due').val('0.00');
                    setCustomerProgram(null);
                    return;
                }
                $.ajax({
                    url: "{{ url('/admin/sales/customer-due') }}/" + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (response.status) {
                            $('#customer_due').val(
                                parseFloat(response.due || 0).toFixed(2)
                            );
                            setCustomerProgram(response.programs || null);
                        } else {
                            setCustomerProgram(null);
                        }
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        setCustomerProgram(null);
                        toastr.error(xhr.responseJSON?.message || 'Unable to load the customer.');
                    }

                });

            });


            // Look the number up once typing pauses (400 ms) and only for a full number (11+ digits), like POS.
            // A number that is not found clears what an earlier lookup filled in, so the previous
            // customer's name, address, due and points never stay on a new customer.
            let phoneTimer = null;
            let lookupFilled = false;

            function resetLookedUpCustomer() {
                if (lookupFilled) {
                    $('#customer_name').val('');
                    $('#customer_address').val('');
                    lookupFilled = false;
                }
                $('#customer_due').val('0.00');
                setCustomerProgram(null);
            }

            $('#customer_phone').on('input', function() {
                clearTimeout(phoneTimer);

                let phone = $.trim($(this).val());

                if (phone.replace(/\D/g, '').length < 11) {
                    resetLookedUpCustomer();
                    return;
                }

                phoneTimer = setTimeout(function() {
                    $.ajax({
                        url: "{{ url('/admin/sales/customer-by-phone') }}/" + encodeURIComponent(phone),
                        type: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            if ($.trim($('#customer_phone').val()) !== phone) {
                                return;   // the number changed while this lookup was running
                            }
                            if (response.status && response.customer) {
                                $('#customer_name').val(response.customer.name || '');
                                $('#customer_address').val(response.customer.address || '');
                                $('#customer_due').val(parseFloat(response.current_due || 0).toFixed(2));
                                lookupFilled = true;
                                setCustomerProgram(response.programs || null);
                            } else {
                                resetLookedUpCustomer();
                            }
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            resetLookedUpCustomer();
                            toastr.error(xhr.responseJSON?.message || 'Unable to look up the customer.');
                        }
                    });
                }, 400);
            });


            $('#discount_value').on('input', calculateTotals);

            $('#discount_type').on('change', calculateTotals);

            $('#paid_amount').on('input', calculateTotals);

            $(document).on('input', '#redeem_points', calculateTotals);

            $('#payFullBtn').on('click', function() {
                $('#paid_amount').val(calculateTotals().grandTotal.toFixed(2));
                calculateTotals();
            });


            $('#saleForm').on('submit', function(e) {

                e.preventDefault();

                if (saleItems.length === 0) {

                    toastr.error('Please add at least one product.');

                    return;

                }

                let badQty = saleItems.findIndex(function(item) {
                    return !(parseFloat(item.qty) > 0);
                });

                $('.itemQty').removeClass('is-invalid');

                if (badQty !== -1) {

                    toastr.error('Quantity must be more than zero for "' + escapeHtml(saleItems[badQty].product_name) + '".');

                    $('.itemQty[data-index="' + badQty + '"]').addClass('is-invalid').trigger('focus');

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

            if (!product_id) {
                return;
            }

            addProduct(product_id);

            // Reset (UI only) so the same product can be selected again
            $(this).val('').trigger('change.select2');
        });


        function addProduct(productId) {

            $.ajax({

                url: "{{ url('/admin/sales/products/variants') }}/" +
                    productId,

                type: 'GET',

                dataType: 'json',

                success: function(response) {
                    // alert(JSON.stringify(response));

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

                        // Pick the first variant not already in the cart,
                        // otherwise fall back to the first one.
                        let variant = variants.find(function(v) {

                            return !saleItems.some(function(item) {
                                return parseInt(item.product_id) === parseInt(product.id) &&
                                    String(item.product_variant_id || '') === String(v.id);
                            });

                        }) || variants[0];

                        addSaleItem(
                            product,
                            variant,
                            variants
                        );

                    } else {

                        addSaleItem(
                            product,
                            null
                        );

                    }

                },

                error: function(xhr) {
                    // alert(JSON.stringify(xhr));

                    toastr.error(
                        xhr.responseJSON?.message ||
                        'Unable to load product.'
                    );

                }

            });

        }


        function addSaleItem(product, variant, variants) {

            let variantId = variant ? variant.id : '';

            let stock = variant ?
                parseFloat(variant.current_stock || 0) :
                parseFloat(product.current_stock || 0);

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

                    variants: variants || [],

                    product_sale_price: parseFloat(product.sale_price || 0),

                    // null = the default VAT rate (same rule as CustomerProgramController::vat())
                    vat_percent: product.vat_percent === null || product.vat_percent === undefined ? null : parseFloat(product.vat_percent),

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

                if (item.variants && item.variants.length > 0) {

                    variantHtml =
                        `<select class="form-select form-select-sm itemVariant" data-index="${index}">` +
                        item.variants.map(function(v) {
                            return `<option value="${v.id}"
                                ${String(v.id) === String(item.product_variant_id) ? 'selected' : ''}>
                                ${escapeHtml(v.attribute_name || v.variant_name || 'Variant #' + v.id)}
                            </option>`;
                        }).join('') +
                        `</select>`;

                } else if (item.product_variant_id) {

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


        $(document).on('change', '.itemVariant', function() {

            let index = $(this).data('index');

            let item = saleItems[index];

            if (!item) {
                return;
            }

            let variantId = String($(this).val());

            let variant = item.variants.find(function(v) {
                return String(v.id) === variantId;
            });

            if (!variant) {
                return;
            }

            let duplicate = saleItems.some(function(other, i) {
                return i !== index &&
                    parseInt(other.product_id) === parseInt(item.product_id) &&
                    String(other.product_variant_id || '') === variantId;
            });

            if (duplicate) {

                toastr.warning('This variant is already in the cart.');

                renderCart();

                return;

            }

            item.product_variant_id = variant.id;

            item.variant_name = variant.attribute_name || variant.variant_name || '-';

            item.stock = parseFloat(variant.current_stock || 0);

            item.sale_price = parseFloat(
                variant.sale_price ?? item.product_sale_price ?? 0
            );

            if (parseFloat(item.qty || 0) > item.stock) {

                toastr.warning('Quantity adjusted to available stock.');

                item.qty = item.stock;

            }

            renderCart();

        });


        $(document).on('input change',
            '.itemQty, .itemPrice, .itemDiscountType, .itemDiscountValue',
            function(e) {

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

                // While typing, update only this row and the totals: re-rendering the table on every
                // keystroke dropped the focus after the first digit (and swallowed a typed decimal point).
                if (e.type === 'input' && $(this).is('input')) {
                    let sub = parseFloat(item.qty || 0) * parseFloat(item.sale_price || 0);
                    let disc = calculateItemDiscount(item, sub);
                    item.subtotal = sub;
                    item.discount_amount = disc;
                    item.total = Math.max(0, sub - disc);
                    let $cells = $(this).closest('tr').children('td');
                    $cells.eq(6).text(sub.toFixed(2));
                    $cells.eq(7).html('<strong>' + item.total.toFixed(2) + '</strong>');
                    calculateTotals();
                    return;
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

                // Line discounts come off first — the same subtotal SaleController::store() saves.
                subtotal += Math.max(0, itemSubtotal - calculateItemDiscount(item, itemSubtotal));

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

            // VAT (same rule as the server / POS): rate per product, else the default; the sale discount is
            // spread over the lines. Added on top, or only shown when it is already inside the prices.
            let vat = 0;
            if (PROGRAMS.vat_enabled && subtotal > 0) {
                const share = Math.max(0, 1 - discountAmount / subtotal);
                saleItems.forEach(function(item) {
                    const lineSub = parseFloat(item.qty || 0) * parseFloat(item.sale_price || 0);
                    const base = Math.max(0, lineSub - calculateItemDiscount(item, lineSub)) * share;
                    const rate = item.vat_percent === null || item.vat_percent === undefined || isNaN(item.vat_percent) ?
                        PROGRAMS.vat_rate : item.vat_percent;
                    vat += PROGRAMS.vat_inclusive ? base - base / (1 + rate / 100) : base * rate / 100;
                });
                vat = Math.round(vat * 100) / 100;
                if (!PROGRAMS.vat_inclusive) grandTotal += vat;
            }
            $('#vat_amount').val(vat.toFixed(2));

            // Loyalty points as money off (capped like CustomerProgramController::redeemValue()).
            let loyalty = 0;
            if (customerProgram && customerProgram.loyalty_enabled) {
                const want = Math.min(parseFloat($('#redeem_points').val()) || 0, customerProgram.loyalty_points);
                if (want > 0 && want >= customerProgram.min_redeem) {
                    loyalty = Math.min(want * customerProgram.point_value, grandTotal * customerProgram.max_percent / 100);
                    loyalty = Math.round(loyalty * 100) / 100;
                }
                grandTotal -= loyalty;
            }
            $('#loyalty_discount').val(loyalty.toFixed(2));

            grandTotal = Math.round(grandTotal * 100) / 100;


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

            return { subtotal, discountAmount, vat, loyalty, grandTotal, paid, due };

        }


        // The selected customer's loyalty points (from customerByPhone / customerDue), or null.
        function setCustomerProgram(p) {
            customerProgram = p;
            const show = !!(PROGRAMS.loyalty && p && p.loyalty_enabled && p.loyalty_points > 0);
            $('#loyaltyRow').toggleClass('d-none', !show);
            $('#pointsHave').text(show ? '(has ' + Math.floor(p.loyalty_points) + ', min ' + p.min_redeem + ')' : '');
            if (!show) $('#redeem_points').val('');
            calculateTotals();
        }


        function saveSale() {

            let data = $('#saleForm').serializeArray();

            data.push({
                name: 'items',
                value: JSON.stringify(saleItems.map(function(item) {
                    let {
                        variants,
                        product_sale_price,
                        ...rest
                    } = item;
                    return rest;
                }))
            });


            $('#saveSaleBtn')
                .prop('disabled', true)
                .text('Saving...');


            $.ajax({

                url: "{{ route('sales.store') }}",

                type: 'POST',

                dataType: 'json',

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

            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

        }

        $('#payment_method').on('change', function() {
            var payment_method = $(this).val();

            $.ajax({
                url: "{{ route('admin.getCoaChilds') }}",
                type: 'GET',
                dataType: 'html',
                data: {
                    parent_id: payment_method
                },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    $('#payment_method_account').html(response);
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                }
            });
        });
    </script>
@endpush
