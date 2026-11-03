@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Add Purchase
@endsection

@section('content')
    <div class="container-fluid">

        <form id="purchaseForm">

            @csrf

            <div class="card">

                <div class="card-header">

                    <h5 class="mb-0">
                        Add Purchase
                    </h5>

                </div>


                <div class="card-body">

                    <div class="row">

                        {{-- Supplier --}}

                        <div class="col-md-4">

                            <label class="form-label">
                                Supplier <span class="text-danger">*</span>
                            </label>

                            <select id="supplier_id" name="supplier_id" class="form-control">

                                <option value="">
                                    Select Supplier
                                </option>

                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">

                                        {{ $supplier->name }}

                                    </option>
                                @endforeach

                            </select>

                        </div>


                        {{-- Supplier Due --}}

                        <div class="col-md-3">

                            <label class="form-label">
                                Current Supplier Due
                            </label>

                            <input type="text" id="supplier_due" class="form-control" value="0.00" placeholder="0.00" readonly>

                        </div>


                        {{-- Purchase Date --}}

                        <div class="col-md-3">

                            <label class="form-label">
                                Purchase Date
                            </label>

                            <input type="date" id="purchase_date" name="purchase_date" class="form-control"
                                value="{{ date('Y-m-d') }}">

                        </div>

                        {{-- Supplier's own invoice / reference number --}}
                        <div class="col-md-2">
                            <label class="form-label" for="supplier_invoice_no">
                                Supplier Invoice No.
                            </label>
                            <input type="text" id="supplier_invoice_no" name="supplier_invoice_no" class="form-control"
                                maxlength="100" placeholder="Optional">
                        </div>

                    </div>


                    <hr>


                    {{-- Product Selection --}}

                    <div class="row align-items-end">

                        <div class="col-md-6">

                            <label class="form-label">
                                Product
                            </label>

                            <select id="product_id" name="product_id" class="form-control">

                                <option value="">
                                    Select Product
                                </option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>

                        </div>
                    </div>


                    <br>


                    {{-- Cart --}}

                    <div class="table-responsive">

                        <table class="table table-bordered" id="purchaseCart">

                            <thead>

                                <tr>

                                    <th width="22%">
                                        Product
                                    </th>

                                    <th width="14%">
                                        Variant
                                    </th>

                                    <th width="10%">
                                        Qty
                                    </th>

                                    <th width="13%">
                                        Purchase Price
                                    </th>

                                    <th width="10%">
                                        Discount Type
                                    </th>

                                    <th width="10%">
                                        Discount
                                    </th>

                                    <th width="17%">
                                        Subtotal
                                    </th>

                                    <th width="1%">
                                        #
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            </tbody>
                            <tfoot>

                                {{-- Subtotal --}}
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Subtotal</strong>
                                    </td>

                                    <td colspan="2">

                                        <input type="text" id="subtotal" class="form-control text-end" value="0.00"
                                            placeholder="0.00" readonly>

                                    </td>
                                    <td></td>
                                </tr>


                                {{-- Discount --}}
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Discount</strong>
                                    </td>

                                    <td colspan="2">

                                        <div class="row g-1">

                                            <div class="col-md-6">

                                                <select id="discount_type" name="discount_type" class="form-control">

                                                    <option value="Amount">
                                                        Amount
                                                    </option>

                                                    <option value="Percentage">
                                                        %
                                                    </option>

                                                </select>

                                            </div>

                                            <div class="col-md-6">

                                                <input type="number" step="0.01" min="0" id="discount_value"
                                                    name="discount_value" class="form-control" value="0" placeholder="0.00">

                                            </div>

                                        </div>

                                    </td>
                                    <td></td>

                                </tr>


                                {{-- Discount Amount --}}
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Discount Amount</strong>
                                    </td>

                                    <td colspan="2">

                                        <input type="text" id="discount_amount" class="form-control text-end"
                                            value="0.00" placeholder="0.00" readonly>

                                    </td>
                                    <td></td>
                                </tr>


                                {{-- Input VAT (when enabled in Customer Programs settings) --}}
                                @if ($purchaseVat)
                                    <tr>
                                        <td colspan="5" class="text-end">
                                            <strong>VAT</strong>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" id="vat_percent" class="form-control text-end" value="0" aria-label="VAT percent">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" id="vat_amount" class="form-control text-end" value="0.00" readonly aria-label="VAT amount">
                                        </td>
                                    </tr>
                                @endif

                                {{-- Grand Total --}}
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Grand Total</strong>
                                    </td>

                                    <td colspan="2">

                                        <input type="text" id="grand_total" class="form-control text-end fw-bold"
                                            value="0.00" placeholder="0.00" readonly>

                                    </td>
                                    <td></td>
                                </tr>


                                {{-- Payment Method --}}
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Payment Method</strong>
                                    </td>

                                    <td colspan="2">
                                        <select id="payment_method" name="payment_method" class="form-control">

                                            <option value="">
                                                Select Payment Method
                                            </option>
                                            @foreach ($childCoas as $childCoa)
                                                <option value="{{ $childCoa->id }}">{{ $childCoa->account_name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td></td>

                                </tr>
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Payment Account</strong>
                                    </td>

                                    <td colspan="2">

                                        <select id="payment_method_account" name="payment_method_account"
                                            class="form-control">

                                            <option value="">
                                                Select Payment Method Account
                                            </option>
                                            {{-- @foreach ($childCoas as $childCoa)
                                            <option value="{{ $childCoa->id }}">{{ $childCoa->account_name }}</option>
                                            @endforeach --}}
                                        </select>
                                    </td>
                                    <td></td>

                                </tr>


                                {{-- Paid Amount --}}
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Paid Amount</strong>
                                    </td>

                                    <td colspan="2">

                                        <input type="number" step="0.01" min="0" id="paid_amount"
                                            name="paid_amount" class="form-control" value="0" placeholder="0.00">

                                    </td>
                                    <td></td>
                                </tr>


                                {{-- Due Amount --}}
                                <tr>

                                    <td colspan="5" class="text-end">
                                        <strong>Due Amount</strong>
                                    </td>

                                    <td colspan="2">

                                        <input type="text" id="due_amount" class="form-control text-end"
                                            value="0.00" placeholder="0.00" readonly>

                                    </td>
                                    <td></td>
                                </tr>

                            </tfoot>
                        </table>

                    </div>


                    <hr>


                    {{-- Summary --}}




                    <div class="row mt-3">

                        <div class="col-md-12">

                            <label class="form-label">
                                Remarks
                            </label>

                            <textarea id="remarks" name="remarks" class="form-control" rows="3" placeholder="Optional note about this purchase"></textarea>

                        </div>

                    </div>

                </div>


                <div class="card-footer text-end">

                    <a href="{{ route('purchases.index') }}" class="btn btn-secondary">

                        Cancel

                    </a>

                    <button type="submit" id="savePurchaseBtn" class="btn btn-success">

                        <i class="fa fa-save"></i>
                        Save Purchase

                    </button>

                </div>

            </div>

        </form>
    </div>

    {{-- Variant picker: which variants of the chosen product to add --}}
    <div class="modal fade" id="variantPicker" tabindex="-1" aria-labelledby="variantPickerTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="variantPickerTitle">Choose variants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Tick the variants to add. Each variant becomes its own line; a variant already in the cart gets the quantity added.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 32px;"><input type="checkbox" class="form-check-input" id="variantPickerAll" aria-label="Tick all variants"></th>
                                    <th>Variant</th>
                                    <th class="text-end">Price</th>
                                    <th style="width: 110px;">Qty</th>
                                </tr>
                            </thead>
                            <tbody id="variantPickerRows"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="variantPickerAddAll">Add all</button>
                    <button type="button" class="btn btn-primary" id="variantPickerAdd">Add selected</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {


            /*
            |--------------------------------------------------------------------------
            | URLs
            |--------------------------------------------------------------------------
            */

            let productSearchUrl =
                "{{ route('purchases.products.search') }}";

            // let variantsUrlBase =
            //     "{{ url('/admin/purchases/products/variants') }}";

            let supplierDueUrlBase =
                "{{ url('/admin/purchases/supplier-due') }}";


            /*
            |--------------------------------------------------------------------------
            | Cart
            |--------------------------------------------------------------------------
            */

            let cart = [];


            /*
            |--------------------------------------------------------------------------
            | Supplier Select2
            |--------------------------------------------------------------------------
            */

            $('#supplier_id').select2({

                placeholder: 'Select Supplier',

                allowClear: true,

                width: '100%'

            });
            $('#product_id').select2({

                placeholder: 'Select product',

                allowClear: true,

                width: '100%'

            });


            $('#product_id').select2({

                placeholder: 'Select Product',

                allowClear: true,

                width: '100%',

                ajax: {

                    url: productSearchUrl,

                    type: 'GET',

                    dataType: 'json',

                    delay: 300,

                    data: function(params) {

                        return {

                            search: params.term || ''

                        };

                    },

                    processResults: function(response) {

                        let results = [];

                        if (response && response.data) {

                            $.each(response.data, function(index, product) {

                                results.push({

                                    id: product.id,

                                    text: product.name +
                                        (
                                            product.sku ?
                                            ' - ' + product.sku :
                                            ''
                                        )

                                });

                            });

                        }

                        return {

                            results: results

                        };

                    },

                    cache: true

                }

            });

            /*
            |--------------------------------------------------------------------------
            | Product Select2
            |--------------------------------------------------------------------------
            */

            // $('#product_id').select2({

            //     placeholder: 'Select Product',

            //     allowClear: true,

            //     width: '100%',

            //     ajax: {

            //         url: productSearchUrl,

            //         type: 'GET',

            //         dataType: 'json',

            //         delay: 300,

            //         data: function (params) {

            //             return {

            //                 search: params.term || ''

            //             };

            //         },

            //         processResults: function (response) {

            //             let results = [];

            //             if (response && response.data) {

            //                 $.each(response.data, function (index, product) {

            //                     results.push({

            //                         id: product.id,

            //                         text:
            //                             product.name +
            //                             (
            //                                 product.sku
            //                                     ? ' - ' + product.sku
            //                                     : ''
            //                             )

            //                     });

            //                 });

            //             }

            //             return {

            //                 results: results

            //             };

            //         },

            //         cache: true

            //     }

            // });


            /*
            |--------------------------------------------------------------------------
            | Supplier Due
            |--------------------------------------------------------------------------
            */

            $('#supplier_id').on('change', function() {

                let supplierId = $(this).val();


                $('#supplier_due').val('0.00');


                if (!supplierId) {

                    return;
                }


                $.ajax({

                    url: supplierDueUrlBase + '/' + supplierId,

                    type: 'GET',

                    dataType: 'json',

                    success: function(response) {
                        // alert(JSON.stringify(response));

                        if (response && response.status) {

                            $('#supplier_due').val(
                                parseFloat(response.due || 0)
                                .toFixed(2)
                            );

                        } else {

                            toastr.error(
                                response.message ||
                                'Could not load supplier due.'
                            );

                        }

                    },

                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));

                        toastr.error(
                            'Something went wrong.'
                        );

                    }

                });

            });


            /*
            |--------------------------------------------------------------------------
            | Add Product
            |--------------------------------------------------------------------------
            */

            $('#product_id').on('change', function() {

                let productId = $(this).val();

                console.log('Selected Product ID:', productId);

                if (!productId || productId === '') {

                    console.log('No product selected.');

                    return;
                }

                var variantsUrlBase = "{{ url('admin/purchases/products/variants') }}";

                var requestUrl = variantsUrlBase + '/' + productId;

                console.log('Request URL:', requestUrl);

                $.ajax({

                    url: requestUrl,

                    type: 'GET',

                    dataType: 'json',

                    success: function(response) {
                        // alert(JSON.stringify(response));

                        console.log('Success:', response);

                        if (response && response.data) {

                            addProductToCart(response.data);

                        } else {

                            toastr.error(
                                'Could not load that product\'s details.'
                            );

                        }

                    },

                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));

                        console.log('AJAX Error:', xhr);

                        console.log('Response:', xhr.responseText);

                        //  alert(xhr.responseText);

                        toastr.error(
                            'Something went wrong.'
                        );

                    }

                });

            });


            /*
            |--------------------------------------------------------------------------
            | Add Product To Cart
            |--------------------------------------------------------------------------
            */

            function addProductToCart(data) {

                let product = data.product;

                let variants = data.variants || [];


                /*
                |--------------------------------------------------------------------------
                | If same product without variant exists
                |--------------------------------------------------------------------------
                */

                if (variants.length === 0) {

                    let existingIndex = cart.findIndex(function(item) {

                        return item.product_id == product.id &&
                            !item.product_variant_id;

                    });


                    if (existingIndex >= 0) {

                        cart[existingIndex].qty =
                            parseFloat(cart[existingIndex].qty) + 1;

                    } else {

                        cart.push({

                            product_id: product.id,

                            product_variant_id: null,

                            product_name: product.name,

                            sku: product.sku || '',

                            qty: 1,

                            purchase_price: parseFloat(product.purchase_price || 0),

                            discount_type: 'Amount',

                            discount_value: 0,

                            track_batch: product.track_batch || 'No',

                            batch_no: '',

                            expiry_date: '',

                            discount_amount: 0,

                            subtotal: 0,

                            total: 0

                        });

                    }


                    renderCart();

                    $('#product_id').val(null).trigger('change');

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Product Has Variants: let the user pick one, several or all of them
                |--------------------------------------------------------------------------
                */

                openVariantPicker(product, variants);

                $('#product_id')
                    .val(null)
                    .trigger('change');

            }


            // Price of a variant line: the variant's own purchase price, else the product's.
            function variantPrice(product, variant) {
                let price = parseFloat(variant && variant.purchase_price);
                return price > 0 ? price : parseFloat(product.purchase_price || 0);
            }

            function variantLabel(variant) {
                return variant.name || variant.variant_name || variant.barcode || ('Variant #' + variant.id);
            }

            // One cart row per variant: the same variant adds to its row, another variant gets a new row.
            function addVariantLine(product, variants, variant, qty) {
                let existingIndex = cart.findIndex(function(item) {
                    return item.product_id == product.id && item.product_variant_id == variant.id;
                });

                if (existingIndex >= 0) {
                    cart[existingIndex].qty = parseFloat(cart[existingIndex].qty) + qty;
                    return;
                }

                cart.push({
                    product_id: product.id,
                    product_variant_id: variant.id,
                    product_name: product.name,
                    product_price: parseFloat(product.purchase_price || 0),
                    sku: product.sku || '',
                    variants: variants,
                    qty: qty,
                    purchase_price: variantPrice(product, variant),
                    discount_type: 'Amount',
                    discount_value: 0,
                    track_batch: product.track_batch || 'No',
                    batch_no: '',
                    expiry_date: '',
                    discount_amount: 0,
                    subtotal: 0,
                    total: 0
                });
            }

            let picker = { product: null, variants: [] };

            function openVariantPicker(product, variants) {
                picker = { product: product, variants: variants };
                $('#variantPickerTitle').text('Choose variants of ' + product.name);
                $('#variantPickerAll').prop('checked', false);
                $('#variantPickerRows').html(variants.map(function(variant, vi) {
                    let inCart = cart.some(function(item) { return item.product_id == product.id && item.product_variant_id == variant.id; });
                    return `
                    <tr>
                        <td><input type="checkbox" class="form-check-input vp-check" id="vp_${vi}" data-vi="${vi}" aria-label="Add ${escapeHtml(variantLabel(variant))}"></td>
                        <td><label for="vp_${vi}" class="mb-0">${escapeHtml(variantLabel(variant))}</label>${inCart ? ' <span class="badge bg-secondary">in cart</span>' : ''}</td>
                        <td class="text-end">${variantPrice(product, variant).toFixed(2)}</td>
                        <td><input type="number" class="form-control form-control-sm text-end vp-qty" data-vi="${vi}" min="0.0001" step="any" value="1" aria-label="Quantity"></td>
                    </tr>`;
                }).join(''));
                bootstrap.Modal.getOrCreateInstance(document.getElementById('variantPicker')).show();
            }

            function addPickedVariants(all) {
                let added = 0;
                $('#variantPickerRows tr').each(function() {
                    let vi = $(this).find('.vp-check').data('vi');
                    if (!all && !$(this).find('.vp-check').is(':checked')) {
                        return;
                    }
                    let qty = parseFloat($(this).find('.vp-qty').val());
                    addVariantLine(picker.product, picker.variants, picker.variants[vi], qty > 0 ? qty : 1);
                    added++;
                });
                if (!added) {
                    toastr.error('Tick at least one variant, or use Add all.');
                    return;
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById('variantPicker')).hide();
                renderCart();
            }

            $('#variantPickerAll').on('change', function() {
                $('#variantPickerRows .vp-check').prop('checked', $(this).is(':checked'));
            });
            $(document).on('input', '#variantPickerRows .vp-qty', function() {
                $(this).closest('tr').find('.vp-check').prop('checked', true);
            });
            $('#variantPickerAdd').on('click', function() { addPickedVariants(false); });
            $('#variantPickerAddAll').on('click', function() { addPickedVariants(true); });


            /*
            |--------------------------------------------------------------------------
            | Render Cart
            |--------------------------------------------------------------------------
            */

            function renderCart() {

                let html = '';


                if (cart.length === 0) {

                    html = `

                <tr>

                    <td colspan="8"
                        class="text-center text-muted">

                        No products added.

                    </td>

                </tr>

            `;

                    $('#purchaseCart tbody').html(html);

                    calculateSummary();

                    return;
                }


                $.each(cart, function(index, item) {


                    /*
                    |--------------------------------------------------------------------------
                    | Variant HTML
                    |--------------------------------------------------------------------------
                    */

                    let variantHtml = '';


                    if (item.variants && item.variants.length > 0) {

                        variantHtml = `

                    <select
                        class="form-control variantSelect"
                        data-index="${index}">

                        <option value="">
                            Select Variant
                        </option>

                `;


                        $.each(item.variants, function(vIndex, variant) {

                            let selected =
                                item.product_variant_id == variant.id ?
                                'selected' :
                                '';


                            let variantText =
                                variant.name ||
                                variant.variant_name ||
                                variant.barcode ||
                                ('Variant #' + variant.id);


                            variantHtml += `

                        <option
                            value="${variant.id}"
                            ${selected}>

                            ${escapeHtml(variantText)}

                        </option>

                    `;

                        });


                        variantHtml += '</select>';

                    } else {

                        variantHtml = `
                    <span class="text-muted">
                        N/A
                    </span>
                `;

                    }


                    html += `

                <tr>

                    <td>

                        <strong>
                            ${escapeHtml(item.product_name)}
                        </strong>

                        ${item.track_batch === 'Yes' ? `
                        <div class="row g-1 mt-1">
                            <div class="col-6">
                                <label class="form-label small mb-0" for="batch_${index}">Batch no.</label>
                                <input type="text" class="form-control form-control-sm batchInput" id="batch_${index}" data-index="${index}"
                                    placeholder="blank = auto" maxlength="60" value="${escapeHtml(item.batch_no || '')}">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-0" for="expiry_${index}">Expiry date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm expiryInput${item.expiry_missing ? ' is-invalid' : ''}" id="expiry_${index}" data-index="${index}"
                                    value="${item.expiry_date || ''}" required>
                            </div>
                            <div class="col-12"><small class="text-muted">Batch-tracked. Leave the batch no. blank to number it automatically (purchase no. + line).</small></div>
                        </div>` : ''}


                    </td>


                    <td>

                        ${variantHtml}

                    </td>


                    <td>

                        <input
                            type="text"
                            step="0.0001"
                            min="0.0001"
                            class="form-control qtyInput"
                            placeholder="0"
                            data-index="${index}"
                            value="${item.qty}">

                    </td>


                    <td>

                        <input
                            type="text"
                            step="0.01"
                            min="0"
                            class="form-control priceInput"
                            placeholder="0.00"
                            data-index="${index}"
                            value="${item.purchase_price}">

                    </td>


                    <td>

                        <select
                            class="form-control itemDiscountType"
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

                    </td>


                    <td>

                        <input
                            type="text"
                            step="0.01"
                            min="0"
                            class="form-control itemDiscount"
                            placeholder="0.00"
                            data-index="${index}"
                            value="${item.discount_value}">

                    </td>


                    <td>

                        <input
                            type="text"
                            class="form-control itemTotal"
                            placeholder="0.00"
                            value="${calculateItemTotal(item).toFixed(2)}"
                            readonly>

                    </td>


                    <td>

                        <button
                            type="button"
                            class="btn btn-sm btn-danger removeItem"
                            data-index="${index}">

                            <i class="fa fa-trash"></i>

                        </button>

                    </td>

                </tr>

            `;

                });


                $('#purchaseCart tbody').html(html);

                $('.variantSelect').select2({
                    placeholder: 'Select Variant',
                    allowClear: true,
                    width: '100%'
                });

                calculateSummary();

            }


            /*
            |--------------------------------------------------------------------------
            | Calculate Item
            |--------------------------------------------------------------------------
            */

            function calculateItemTotal(item) {

                let qty =
                    parseFloat(item.qty || 0);

                let price =
                    parseFloat(item.purchase_price || 0);

                let discountValue =
                    parseFloat(item.discount_value || 0);


                let subtotal = qty * price;

                let discountAmount = 0;


                if (item.discount_type === 'Percentage') {

                    discountAmount =
                        (subtotal * discountValue) / 100;

                } else {

                    discountAmount =
                        discountValue;
                }


                if (discountAmount > subtotal) {

                    discountAmount = subtotal;
                }


                item.discount_amount = discountAmount;

                item.subtotal = subtotal;

                item.total =
                    subtotal - discountAmount;


                return item.total;

            }


            /*
            |--------------------------------------------------------------------------
            | Qty
            |--------------------------------------------------------------------------
            */

            $(document).on('blur', '.qtyInput', function() {

                let index = $(this).data('index');
                let value = $(this).val();


                if (value === '' || /^\d*\.?\d*$/.test(value)) {
                    cart[index].qty = value || 0;
                }
                renderCart();

            });


            /*
            |--------------------------------------------------------------------------
            | Purchase Price
            |--------------------------------------------------------------------------
            */

            $(document).on('blur', '.priceInput', function() {

                let index = $(this).data('index');
                let value = $(this).val();

                if (value === '' || /^\d*\.?\d*$/.test(value)) {
                    cart[index].purchase_price = value || 0;
                }
                renderCart();

            });


            /*
            |--------------------------------------------------------------------------
            | Item Discount Type
            |--------------------------------------------------------------------------
            */

            $(document).on('change', '.itemDiscountType', function() {

                let index = $(this).data('index');

                cart[index].discount_type =
                    $(this).val();

                renderCart();

            });


            /*
            |--------------------------------------------------------------------------
            | Item Discount
            |--------------------------------------------------------------------------
            */

            $(document).on('blur', '.itemDiscount', function() {

                let index = $(this).data('index');

                let value = $(this).val();

                if (value === '' || /^\d*\.?\d*$/.test(value)) {
                    cart[index].discount_value = value || 0;
                }


                renderCart();

            });


            /*
            |--------------------------------------------------------------------------
            | Variant
            |--------------------------------------------------------------------------
            */

            $(document).on('change', '.variantSelect', function() {

                let index = $(this).data('index');

                let variantId = $(this).val();


                cart[index].product_variant_id =
                    variantId ? parseInt(variantId) : null;


                let variant = null;


                if (cart[index].variants) {

                    variant =
                        cart[index].variants.find(function(v) {

                            return v.id == variantId;

                        });

                }


                if (variant) {

                    let price = parseFloat(variant.purchase_price);

                    cart[index].purchase_price =
                        price > 0 ? price : parseFloat(cart[index].product_price || cart[index].purchase_price || 0);

                }

                // Switching to a variant that already has a row: merge into that row.
                let twin = cart.findIndex(function(item, i) {
                    return i != index && item.product_id == cart[index].product_id && variantId && item.product_variant_id == variantId;
                });

                if (twin >= 0) {
                    cart[twin].qty = parseFloat(cart[twin].qty) + parseFloat(cart[index].qty || 0);
                    cart.splice(index, 1);
                    toastr.info('That variant was already in the cart, so the quantities were added together.');
                }


                renderCart();

            });


            /*
            |--------------------------------------------------------------------------
            | Remove
            |--------------------------------------------------------------------------
            */

            $(document).on('click', '.removeItem', function() {

                let index = $(this).data('index');

                cart.splice(index, 1);

                renderCart();

            });


            /*
            |--------------------------------------------------------------------------
            | Header Discount
            |--------------------------------------------------------------------------
            */

            $('#discount_type, #discount_value').on(
                'change input',
                function() {

                    calculateSummary();

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Paid
            |--------------------------------------------------------------------------
            */

            $('#paid_amount').on('input', function() {

                calculateSummary();

            });


            /*
            |--------------------------------------------------------------------------
            | Summary
            |--------------------------------------------------------------------------
            */

            function calculateSummary() {

                let subtotal = 0;


                $.each(cart, function(index, item) {

                    subtotal +=
                        calculateItemTotal(item);

                });


                $('#subtotal').val(
                    subtotal.toFixed(2)
                );


                let discountType =
                    $('#discount_type').val();

                let discountValue =
                    parseFloat(
                        $('#discount_value').val()
                    ) || 0;


                let discountAmount = 0;


                if (discountType === 'Percentage') {

                    discountAmount =
                        (subtotal * discountValue) / 100;

                } else {

                    discountAmount =
                        discountValue;
                }


                if (discountAmount > subtotal) {

                    discountAmount = subtotal;
                }


                let grandTotal =
                    subtotal - discountAmount;

                // Input VAT on the purchase after its discount.
                let vatAmount = Math.round((subtotal - discountAmount) * (parseFloat($('#vat_percent').val()) || 0)) / 100;
                $('#vat_amount').val(vatAmount.toFixed(2));
                grandTotal += vatAmount;


                let paidAmount =
                    parseFloat(
                        $('#paid_amount').val()
                    ) || 0;


                if (paidAmount > grandTotal) {

                    paidAmount = grandTotal;

                    $('#paid_amount').val(
                        paidAmount.toFixed(2)
                    );

                }


                let dueAmount =
                    grandTotal - paidAmount;


                $('#discount_amount').val(
                    discountAmount.toFixed(2)
                );


                $('#grand_total').val(
                    grandTotal.toFixed(2)
                );


                $('#due_amount').val(
                    dueAmount.toFixed(2)
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Submit Purchase
            |--------------------------------------------------------------------------
            */

            $('#purchaseForm').on('submit', function(e) {

                e.preventDefault();

                let supplierId = $('#supplier_id').val();

                if (!supplierId) {

                    toastr.error(
                        'Please select a supplier.'
                    );

                    return;
                }

                if (cart.length === 0) {

                    toastr.error(
                        'Please add at least one product.'
                    );

                    return;
                }

                let invalid = false;

                $.each(cart, function(index, item) {

                    if (
                        !item.qty ||
                        parseFloat(item.qty) <= 0
                    ) {

                        invalid = true;

                        return false;
                    }

                    if (
                        item.purchase_price === undefined ||
                        parseFloat(item.purchase_price) < 0
                    ) {

                        invalid = true;

                        return false;
                    }

                });

                if (invalid) {

                    toastr.error(
                        'Please check product quantity and purchase price.'
                    );

                    return;
                }

                // Batch-tracked products need their expiry date (the batch no. may stay blank: it is generated).
                let noExpiry = cart.filter(function(item) {
                    item.expiry_missing = item.track_batch === 'Yes' && !item.expiry_date;
                    return item.expiry_missing;
                });

                if (noExpiry.length) {

                    renderCart();

                    toastr.error(
                        'Enter the expiry date for ' + noExpiry.map(function(item) { return item.product_name; }).join(', ') + ' (batch-tracked).'
                    );

                    $('.expiryInput.is-invalid').first().trigger('focus');

                    return;
                }
                /*
                |--------------------------------------------------------------------------
                | SweetAlert Confirmation
                |--------------------------------------------------------------------------
                */

                Swal.fire({

                    title: 'Confirm Purchase?',

                    text: 'Are you sure you want to save this purchase?',

                    icon: 'question',

                    showCancelButton: true,

                    confirmButtonText: 'Yes, Save Purchase',

                    cancelButtonText: 'No, Cancel',

                    reverseButtons: true,

                    allowOutsideClick: false,

                    allowEscapeKey: false

                }).then((result) => {

                    /*
                    |--------------------------------------------------------------------------
                    | If user clicks No / Cancel
                    |--------------------------------------------------------------------------
                    */

                    if (!result.isConfirmed) {

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Prepare Items
                    |--------------------------------------------------------------------------
                    */

                    let items = [];

                    $.each(cart, function(index, item) {

                        calculateItemTotal(item);

                        items.push({

                            product_id: item.product_id,

                            product_variant_id: item.product_variant_id,

                            qty: item.qty,

                            purchase_price: item.purchase_price,

                            discount_type: item.discount_type,

                            discount_value: item.discount_value,

                            batch_no: item.batch_no || '',

                            expiry_date: item.expiry_date || ''

                        });

                    });


                    /*
                    |--------------------------------------------------------------------------
                    | Form Data
                    |--------------------------------------------------------------------------
                    */

                    let formData = {

                        _token: "{{ csrf_token() }}",

                        supplier_id: $('#supplier_id').val(),

                        purchase_date: $('#purchase_date').val(),

                        discount_type: $('#discount_type').val(),

                        discount_value: $('#discount_value').val(),

                        vat_percent: $('#vat_percent').val() || 0,

                        paid_amount: $('#paid_amount').val(),

                        payment_method: $('#payment_method').val(),
                        payment_method_account: $('#payment_method_account').val(),

                        remarks: $('#remarks').val(),

                        supplier_invoice_no: $('#supplier_invoice_no').val(),

                        items: items

                    };


                    /*
                    |--------------------------------------------------------------------------
                    | Disable Save Button
                    |--------------------------------------------------------------------------
                    */

                    $('#savePurchaseBtn')
                        .prop('disabled', true);


                    /*
                    |--------------------------------------------------------------------------
                    | Submit AJAX
                    |--------------------------------------------------------------------------
                    */

                    $.ajax({

                        url: "{{ route('purchases.store') }}",

                        type: 'POST',

                        dataType: 'json',

                        data: formData,

                        success: function(response) {
                            // alert(JSON.stringify(response));
                            if (response && response.status) {

                                toastr.success(
                                    response.message
                                );

                                setTimeout(function() {

                                    window.location.href =
                                        response.redirect;

                                }, 700);

                            } else {

                                toastr.error(
                                    response.message ||
                                    'Could not save purchase.'
                                );

                                $('#savePurchaseBtn')
                                    .prop('disabled', false);

                            }

                        },

                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            let message =
                                'Something went wrong.';

                            if (
                                xhr.responseJSON &&
                                xhr.responseJSON.message
                            ) {

                                message =
                                    xhr.responseJSON.message;

                            }

                            toastr.error(message);

                            $('#savePurchaseBtn')
                                .prop('disabled', false);

                        }

                    });

                });

            });


            /*
            |--------------------------------------------------------------------------
            | Initial Cart
            |--------------------------------------------------------------------------
            */

            renderCart();

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

            // Batch / expiry for batch-tracked products (kept on the cart line; the cart is re-rendered often).
            // These must live inside this ready() block: `cart` and calculateSummary() are local to it.
            $(document).on('input change', '.batchInput', function() { cart[$(this).data('index')].batch_no = $(this).val(); });
            $(document).on('input', '#vat_percent', function() { calculateSummary(); });
            $(document).on('change', '.expiryInput', function() {
                let line = cart[$(this).data('index')];
                line.expiry_date = $(this).val();
                line.expiry_missing = false;
                $(this).toggleClass('is-invalid', !line.expiry_date && line.track_batch === 'Yes');
            });

            function escapeHtml(value) {
        // Escapes quotes too: the output also goes into attribute values.
        return String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

        });
    </script>
@endpush
