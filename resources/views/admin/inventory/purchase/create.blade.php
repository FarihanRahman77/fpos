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

                    success: function(response) {

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

                        console.log('Success:', response);

                        //  alert(JSON.stringify(response));

                        if (response && response.data) {

                            addProductToCart(response.data);

                        } else {

                            toastr.error(
                                'Could not load that product\'s details.'
                            );

                        }

                    },

                    error: function(xhr) {

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
                | Product Has Variants
                |--------------------------------------------------------------------------
                |
                | Add first variant by default.
                | User can change variant in cart.
                |
                */

                let firstVariant = variants[0];


                let existingIndex = cart.findIndex(function(item) {

                    return item.product_id == product.id &&
                        item.product_variant_id == firstVariant.id;

                });


                if (existingIndex >= 0) {

                    cart[existingIndex].qty =
                        parseFloat(cart[existingIndex].qty) + 1;

                } else {

                    cart.push({

                        product_id: product.id,

                        product_variant_id: firstVariant.id,

                        product_name: product.name,

                        sku: product.sku || '',

                        variants: variants,

                        qty: 1,

                        purchase_price: parseFloat(
                            firstVariant.purchase_price ||
                            product.purchase_price ||
                            0
                        ),

                        discount_type: 'Amount',

                        discount_value: 0,

                        discount_amount: 0,

                        subtotal: 0,

                        total: 0

                    });

                }


                renderCart();


                $('#product_id')
                    .val(null)
                    .trigger('change');

            }


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

                            ${variantText}

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
                            ${item.product_name}
                        </strong>


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

                    if (variant.purchase_price !== undefined) {

                        cart[index].purchase_price =
                            parseFloat(variant.purchase_price || 0);

                    }

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

                            discount_value: item.discount_value

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

                        paid_amount: $('#paid_amount').val(),

                        payment_method: $('#payment_method').val(),
                        payment_method_account: $('#payment_method_account').val(),

                        remarks: $('#remarks').val(),

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

                        data: formData,

                        success: function(response) {
                          //  alert(JSON.stringify(response));
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

        });
    </script>
@endpush
