@extends('admin.master')

@section('title')
    {{ Session::get('companySettings')[0]['name'] ?? '' }} Products
@endsection

@section('content')
 
    <div class="container-fluid">

        <!-- PAGE HEADER -->

        <div class="row mb-3">

            <div class="col-md-6">

                <h4 class="mb-0">
                    Products
                </h4>

            </div>


            <div class="col-md-6 text-end">

                <button type="button" class="btn btn-primary" id="addProductBtn">

                    <i class="fas fa-plus"></i>
                    Add Product

                </button>

            </div>

        </div>


        <!-- PRODUCT TABLE -->

        <div class="card">

            <div class="card-body">

                <div class="table-responsive">

                    <table id="manageProductTable" class="table table-bordered" width="100%">

                        <thead>

                            <tr>

                                <th width="5%" class="text-center">
                                    SL#
                                </th>

                                <th width="8%" class="text-center">
                                    Image
                                </th>

                                <th width="20%" class="text-center">
                                    Product Info
                                </th>

                                <th width="13%" class="text-center">
                                    Attributes
                                </th>

                                <th width="13%" class="text-center">
                                    Specification
                                </th>

                                <th width="10%" class="text-center">
                                    Stock
                                </th>

                                <th width="11%" class="text-center">
                                    Price
                                </th>

                                <th width="5%" class="text-center">
                                    Status
                                </th>

                                <th width="10%" class="text-center">
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody></tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>



    <!-- ========================================================= -->
    <!-- PRODUCT ADD / EDIT MODAL -->
    <!-- ========================================================= -->

    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true">

        <div class="modal-dialog modal-xl">

            <div class="modal-content">

                <form id="productForm" enctype="multipart/form-data">

                    @csrf


                    <input type="hidden" name="product_id" id="product_id">


                    <!-- MODAL HEADER -->

                    <div class="modal-header">

                        <h5 class="modal-title" id="productModalTitle">

                            Add Product

                        </h5>


                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        </button>

                    </div>


                    <!-- MODAL BODY -->

                    <div class="modal-body">

                        <div class="row">


                            <!-- PRODUCT NAME -->

                            <div class="col-md-8">

                                <div class="row">

                                    <div class="col-md-8">

                                        <div class="mb-3">

                                            <label for="name" class="form-label">

                                                Product Name
                                                <span class="required">*</span>

                                            </label>

                                            <input type="text" class="form-control" name="name" id="name"
                                                autocomplete="off" required>

                                        </div>

                                    </div>


                                    <div class="col-md-4">

                                        <div class="mb-3">

                                            <label for="slug_preview" class="form-label">

                                                Slug

                                            </label>

                                            <input type="text" class="form-control" id="slug_preview" readonly>

                                        </div>

                                    </div>


                                    <!-- BRAND -->

                                    <div class="col-md-4">

                                        <div class="mb-3">

                                            <label for="brand_id" class="form-label">

                                                Brand

                                            </label>

                                            <select name="brand_id" id="brand_id" class="form-control">

                                                <option value="">
                                                    Select Brand
                                                </option>

                                                @foreach ($brands as $brand)
                                                    <option value="{{ $brand->id }}">
                                                        {{ $brand->name }}
                                                    </option>
                                                @endforeach

                                            </select>

                                        </div>

                                    </div>


                                    <!-- CATEGORY -->

                                    <div class="col-md-4">

                                        <div class="mb-3">

                                            <label for="category_id" class="form-label">

                                                Category

                                            </label>

                                            <select name="category_id" id="category_id" class="form-control">

                                                <option value="">
                                                    Select Category
                                                </option>

                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}">
                                                        {{ $category->name }}
                                                    </option>
                                                @endforeach

                                            </select>

                                        </div>

                                    </div>


                                    <!-- UNIT -->

                                    <div class="col-md-4">

                                        <div class="mb-3">

                                            <label for="unit_id" class="form-label">

                                                Unit

                                            </label>

                                            <select name="unit_id" id="unit_id" class="form-control">

                                                <option value="">
                                                    Select Unit
                                                </option>

                                                @foreach ($units as $unit)
                                                    <option value="{{ $unit->id }}">
                                                        {{ $unit->name }}
                                                    </option>
                                                @endforeach

                                            </select>

                                        </div>

                                    </div>


                                    <!-- SKU -->

                                    <div class="col-md-6">

                                        <div class="mb-3">

                                            <label for="sku" class="form-label">

                                                SKU

                                            </label>

                                            <input type="text" class="form-control" name="sku" id="sku"
                                                autocomplete="off">

                                        </div>

                                    </div>


                                    <!-- BARCODE -->

                                    <div class="col-md-6">

                                        <div class="mb-3">

                                            <label for="barcode" class="form-label">

                                                Barcode

                                            </label>

                                            <input type="text" class="form-control" name="barcode" id="barcode"
                                                autocomplete="off">

                                        </div>

                                    </div>

                                </div>

                            </div>



                            <!-- IMAGE -->

                            <div class="col-md-4">

                                <div class="mb-3">

                                    <label for="image" class="form-label">

                                        Product Image

                                    </label>

                                    <input type="file" class="form-control" name="image" id="image"
                                        accept=".jpg,.jpeg,.png,.webp">

                                </div>


                                <div class="text-center mt-2">

                                    <img src="" id="imagePreview" class="product-image-preview">

                                </div>

                            </div>



                            <div class="col-md-12">

                                <hr>

                            </div>



                            <!-- PRICES -->

                            <div class="col-md-3">

                                <div class="mb-3">

                                    <label for="purchase_price" class="form-label">

                                        Purchase Price
                                        <span class="required">*</span>

                                    </label>

                                    <input type="number" step="0.01" min="0" class="form-control"
                                        name="purchase_price" id="purchase_price" value="0" required>

                                </div>

                            </div>


                            <div class="col-md-3">

                                <div class="mb-3">

                                    <label for="sale_price" class="form-label">

                                        Sale Price
                                        <span class="required">*</span>

                                    </label>

                                    <input type="number" step="0.01" min="0" class="form-control"
                                        name="sale_price" id="sale_price" value="0" required>

                                </div>

                            </div>


                            <div class="col-md-3">

                                <div class="mb-3">

                                    <label for="old_sale_price" class="form-label">

                                        Old Sale Price

                                    </label>

                                    <input type="number" step="0.01" min="0" class="form-control"
                                        name="old_sale_price" id="old_sale_price" value="0">

                                </div>

                            </div>


                            <div class="col-md-3">

                                <div class="mb-3">

                                    <label for="discount" class="form-label">

                                        Discount

                                    </label>

                                    <input type="number" step="0.01" min="0" class="form-control"
                                        name="discount" id="discount" value="0">

                                </div>

                            </div>



                            <!-- STOCK -->

                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="stock_alert" class="form-label">

                                        Stock Alert

                                    </label>

                                    <input type="number" step="0.001" min="0" class="form-control"
                                        name="stock_alert" id="stock_alert" value="0">

                                </div>

                            </div>


                            <div class="col-md-6">

                                <div class="mb-3">

                                    <label for="opening_stock" class="form-label">

                                        Opening Stock

                                    </label>

                                    <input type="number" step="0.001" min="0" class="form-control"
                                        name="opening_stock" id="opening_stock" value="0">

                                    <div id="currentStockText" class="form-text">
                                    </div>

                                </div>

                            </div>



                            <!-- ATTRIBUTES -->

                            <div class="col-md-12">

                                <hr>


                                <div class="d-flex align-items-center mb-2">

                                    <h6 class="mb-0">
                                        Attributes
                                    </h6>


                                    <button type="button" class="btn btn-sm btn-primary ms-auto" id="addAttributeBtn">

                                        <i class="fas fa-plus"></i>

                                    </button>

                                </div>


                                <div id="attributeContainer"></div>

                            </div>



                            <!-- SPECIFICATIONS -->

                            <div class="col-md-12 mt-2">

                                <hr>


                                <div class="d-flex align-items-center mb-2">

                                    <h6 class="mb-0">
                                        Specifications
                                    </h6>


                                    <button type="button" class="btn btn-sm btn-primary ms-auto" id="addSpecBtn">

                                        <i class="fas fa-plus"></i>

                                    </button>

                                </div>


                                <div id="specContainer"></div>

                            </div>



                            <!-- DESCRIPTION -->

                            <div class="col-md-12 mt-2">

                                <div class="mb-3">

                                    <label for="description" class="form-label">

                                        Description

                                    </label>

                                    <textarea name="description" id="description" class="form-control" rows="4"></textarea>

                                </div>

                            </div>

                        </div>

                    </div>



                    <!-- MODAL FOOTER -->

                    <div class="modal-footer">

                        <!-- LEFT -->

                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                            Close

                        </button>


                        <!-- RIGHT -->

                        <button type="submit" class="btn btn-primary" id="saveProductBtn">

                            Save

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>



    <!-- ========================================================= -->
    <!-- DELETE MODAL -->
    <!-- ========================================================= -->

    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered modal-sm">

            <div class="modal-content">


                <div class="modal-header">

                    <h5 class="modal-title">
                        Delete Product
                    </h5>


                    <button type="button" class="btn-close" data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    Are you sure you want to delete this product?

                    <input type="hidden" id="delete_product_id">

                </div>


                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                        Cancel

                    </button>


                    <button type="button" class="btn btn-danger" id="confirmDeleteProduct">

                        Delete

                    </button>

                </div>

            </div>

        </div>

    </div>
@endsection



@push('scripts')
    <script>
        var productTable;


        /*
        |--------------------------------------------------------------------------
        | DOCUMENT READY
        |--------------------------------------------------------------------------
        */

        $(document).ready(function() {


            /*
            |--------------------------------------------------------------------------
            | SELECT2
            |--------------------------------------------------------------------------
            */

            initializeSelect2();


            /*
            |--------------------------------------------------------------------------
            | DATATABLE
            |--------------------------------------------------------------------------
            */

            productTable = $('#manageProductTable').DataTable({

                ajax: {
                    url: "{{ route('products.list') }}",
                    type: "GET",

                    dataSrc: function(json) {

                        if (json.status === false) {

                            toastr.error(
                                json.message ||
                                'Unable to load products.'
                            );

                            return [];

                        }

                        return json.data || [];

                    }
                },

                processing: true,

                pageLength: 25,

                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],

                order: [
                    [0, 'asc']
                ],

                columns: [

                    {
                        data: null,

                        className: 'text-center',

                        render: function(data, type, row, meta) {

                            return meta.row + 1;

                        }
                    },


                    {
                        data: 'image',

                        className: 'text-center',

                        orderable: false,

                        render: function(data, type, row) {

                            if (data) {

                                return `
                            <img
                                src="{{ asset('uploads/products') }}/${escapeHtml(data)}"
                                class="product-table-image"
                                alt="${escapeHtml(row.name)}"
                            >
                        `;

                            }

                            return '-';

                        }

                    },


                    {
                        data: null,

                        render: function(data, type, row) {

                            let html = '';

                            html += '<b>' +
                                escapeHtml(row.name || '') +
                                '</b>';

                            if (row.sku) {

                                html += '<br><small><b>SKU:</b> ' +
                                    escapeHtml(row.sku) +
                                    '</small>';

                            }

                            if (row.barcode) {

                                html += '<br><small><b>Barcode:</b> ' +
                                    escapeHtml(row.barcode) +
                                    '</small>';

                            }

                            html += '<br><small><b>Category:</b> ' +
                                escapeHtml(row.category_name || '') +
                                '</small>';

                            html += '<br><small><b>Brand:</b> ' +
                                escapeHtml(row.brand_name || '') +
                                '</small>';

                            html += '<br><small><b>Unit:</b> ' +
                                escapeHtml(row.unit_name || '') +
                                '</small>';

                            return html;

                        }

                    },


                    {
                        data: null,

                        render: function(data, type, row) {

                            /*
                             * If controller returns attributes_html,
                             * use it.
                             */

                            if (row.attributes_html) {

                                return row.attributes_html;

                            }

                            return '-';

                        }

                    },


                    {
                        data: null,

                        render: function(data, type, row) {

                            if (row.specifications_html) {

                                return row.specifications_html;

                            }

                            return '-';

                        }

                    },


                    {
                        data: null,

                        className: 'text-center',

                        render: function(data, type, row) {

                            let opening =
                                parseFloat(
                                    row.opening_stock || 0
                                );

                            let current =
                                parseFloat(
                                    row.current_stock || 0
                                );

                            let alert =
                                parseFloat(
                                    row.stock_alert || 0
                                );


                            let currentClass = '';

                            if (current <= alert) {

                                currentClass =
                                    'text-danger fw-bold';

                            }


                            return `
                        <b>Opening:</b> ${opening}
                        <br>
                        <b>Alert:</b> ${alert}
                        <br>
                        <b>Available:</b>
                        <span class="${currentClass}">
                            ${current}
                        </span>
                    `;

                        }

                    },


                    {
                        data: null,

                        className: 'text-center',

                        render: function(data, type, row) {

                            let currency =
                                "{{ Session::get('companySettings')[0]['currency'] ?? '' }}";


                            return `
                        <b>PP:</b>
                        ${escapeHtml(currency)}
                        ${parseFloat(
                            row.purchase_price || 0
                        ).toFixed(2)}

                        <br>

                        <b>SP:</b>
                        ${escapeHtml(currency)}
                        ${parseFloat(
                            row.sale_price || 0
                        ).toFixed(2)}

                        ${
                            parseFloat(row.discount || 0) > 0
                            ?
                            '<br><b>Discount:</b> ' +
                            parseFloat(row.discount).toFixed(2)
                            :
                            ''
                        }
                    `;

                        }

                    },


                    {
                        data: 'status',

                        className: 'text-center',

                        render: function(data) {

                            if (data === 'Active') {

                                return `
                            <i class="fas fa-check-circle text-success"
                               style="font-size:18px; cursor:pointer;"
                               title="Active">
                            </i>
                        `;

                            }

                            return `
                        <i class="fas fa-times-circle text-danger"
                           style="font-size:18px; cursor:pointer;"
                           title="Inactive">
                        </i>
                    `;

                        }

                    },


                    {
                        data: 'id',

                        className: 'text-center',

                        orderable: false,

                        searchable: false,

                        render: function(data, type, row) {

                            return `

                        <div class="btn-group">

                            <button type="button"
                                    class="btn btn-sm btn-primary editProductBtn"
                                    data-id="${data}"
                                    title="Edit">

                                <i class="fas fa-edit"></i>

                            </button>


                            <button type="button"
                                    class="btn btn-sm btn-danger deleteProductBtn"
                                    data-id="${data}"
                                    title="Delete">

                                <i class="fas fa-trash"></i>

                            </button>

                        </div>

                    `;

                        }

                    }

                ],

                language: {

                    processing: 'Loading...',

                    emptyTable: 'No products found.'

                }

            });



            /*
            |--------------------------------------------------------------------------
            | ADD PRODUCT
            |--------------------------------------------------------------------------
            */

            $('#addProductBtn').on('click', function() {

                resetProductForm();

                $('#productModalTitle')
                    .text('Add Product');

                $('#saveProductBtn')
                    .text('Save');

                $('#productModal').modal('show');

            });



            /*
            |--------------------------------------------------------------------------
            | PRODUCT NAME -> SLUG
            |--------------------------------------------------------------------------
            */

            $('#name').on('keyup', function() {

                let value = $(this).val();

                let slug = value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');

                $('#slug_preview').val(slug);

            });



            /*
            |--------------------------------------------------------------------------
            | IMAGE PREVIEW
            |--------------------------------------------------------------------------
            */

            $('#image').on('change', function() {

                let file = this.files[0];

                if (!file) {

                    $('#imagePreview')
                        .attr('src', '')
                        .hide();

                    return;

                }


                let reader = new FileReader();

                reader.onload = function(e) {

                    $('#imagePreview')
                        .attr('src', e.target.result)
                        .show();

                };

                reader.readAsDataURL(file);

            });



            /*
            |--------------------------------------------------------------------------
            | ADD ATTRIBUTE
            |--------------------------------------------------------------------------
            */

            $('#addAttributeBtn').on('click', function() {

                addAttributeRow();

            });



            /*
            |--------------------------------------------------------------------------
            | ADD SPEC
            |--------------------------------------------------------------------------
            */

            $('#addSpecBtn').on('click', function() {

                addSpecRow();

            });



            /*
            |--------------------------------------------------------------------------
            | ATTRIBUTE TYPE CHANGE
            |--------------------------------------------------------------------------
            */

            $(document).on(
                'change',
                '.attribute-type',
                function() {

                    let row =
                        $(this).closest('.attribute-row');

                    let typeId =
                        $(this).val();


                    loadAttributes(
                        row,
                        typeId,
                        ''
                    );

                }
            );



            /*
            |--------------------------------------------------------------------------
            | SAVE PRODUCT
            |--------------------------------------------------------------------------
            */

            $('#productForm').on('submit', function(e) {

                e.preventDefault();


                let form = this;

                let id =
                    $('#product_id').val();


                let url;

                if (id) {

                    url =
                        "{{ url('products/update') }}/" +
                        id;

                } else {

                    url =
                        "{{ route('products.store') }}";

                }


                let formData =
                    new FormData(form);


                /*
                 * Disabled inputs are not included in FormData.
                 * Opening stock is intentionally disabled on edit.
                 */


                $('#saveProductBtn')
                    .prop('disabled', true)
                    .text('Saving...');


                $.ajax({

                    url: url,

                    type: 'POST',

                    data: formData,

                    processData: false,

                    contentType: false,


                    success: function(response) {

                        if (response.status) {

                            $('#productModal')
                                .modal('hide');


                            toastr.success(
                                response.message
                            );


                            productTable
                                .ajax
                                .reload(null, false);


                        } else {

                            toastr.error(
                                response.message ||
                                'Unable to save product.'
                            );

                        }

                    },


                    error: function(xhr) {

                        if (xhr.status === 422) {

                            let errors =
                                xhr.responseJSON.errors;


                            $.each(
                                errors,
                                function(key, value) {

                                    toastr.error(
                                        value[0]
                                    );

                                }
                            );

                        } else {

                            toastr.error(
                                xhr.responseJSON?.message ||
                                'Something went wrong.'
                            );

                        }

                    },


                    complete: function() {

                        $('#saveProductBtn')
                            .prop('disabled', false)
                            .text(
                                id ?
                                'Update' :
                                'Save'
                            );

                    }

                });

            });



            /*
            |--------------------------------------------------------------------------
            | EDIT PRODUCT
            |--------------------------------------------------------------------------
            */

            $(document).on(
                'click',
                '.editProductBtn',
                function() {

                    let id =
                        $(this).data('id');


                    $.ajax({

                        url: "{{ url('products/edit') }}/" +
                            id,

                        type: 'GET',


                        success: function(response) {

                            if (!response.status) {

                                toastr.error(
                                    response.message
                                );

                                return;

                            }


                            resetProductForm();


                            let product =
                                response.product;


                            $('#product_id')
                                .val(product.id);


                            $('#name')
                                .val(product.name)
                                .trigger('keyup');


                            $('#brand_id')
                                .val(product.brand_id)
                                .trigger('change');


                            $('#category_id')
                                .val(product.category_id)
                                .trigger('change');


                            $('#unit_id')
                                .val(product.unit_id)
                                .trigger('change');


                            $('#sku')
                                .val(product.sku);


                            $('#barcode')
                                .val(product.barcode);


                            $('#stock_alert')
                                .val(product.stock_alert);


                            $('#purchase_price')
                                .val(product.purchase_price);


                            $('#sale_price')
                                .val(product.sale_price);


                            $('#old_sale_price')
                                .val(product.old_sale_price);


                            $('#discount')
                                .val(product.discount);


                            $('#description')
                                .val(product.description);


                            /*
                             * Opening stock is not changed from
                             * normal product edit.
                             */

                            $('#opening_stock')
                                .val(0)
                                .prop('disabled', true);


                            $('#currentStockText')
                                .text(
                                    'Current Stock: ' +
                                    (
                                        response.current_stock ??
                                        0
                                    )
                                );


                            /*
                             * IMAGE
                             */

                            if (product.image) {

                                $('#imagePreview')
                                    .attr(
                                        'src',
                                        "{{ asset('uploads/products') }}/" +
                                        product.image
                                    )
                                    .show();

                            }


                            /*
                             * ATTRIBUTES
                             */

                            if (
                                response.attributes &&
                                response.attributes.length
                            ) {

                                $.each(
                                    response.attributes,
                                    function(index, item) {

                                        addAttributeRow(
                                            item.attribute_type_id,
                                            item.attribute_id
                                        );

                                    }
                                );

                            }


                            /*
                             * SPECS
                             */

                            if (
                                response.specs &&
                                response.specs.length
                            ) {

                                $.each(
                                    response.specs,
                                    function(index, item) {

                                        addSpecRow(
                                            item.spec_name,
                                            item.spec_value
                                        );

                                    }
                                );

                            }


                            $('#productModalTitle')
                                .text('Edit Product');


                            $('#saveProductBtn')
                                .text('Update');


                            $('#productModal')
                                .modal('show');

                        },


                        error: function() {

                            toastr.error(
                                'Unable to load product.'
                            );

                        }

                    });

                }
            );



            /*
            |--------------------------------------------------------------------------
            | DELETE BUTTON
            |--------------------------------------------------------------------------
            */

            $(document).on(
                'click',
                '.deleteProductBtn',
                function() {

                    let id =
                        $(this).data('id');


                    $('#delete_product_id')
                        .val(id);


                    $('#deleteProductModal')
                        .modal('show');

                }
            );



            /*
            |--------------------------------------------------------------------------
            | CONFIRM DELETE
            |--------------------------------------------------------------------------
            */

            $('#confirmDeleteProduct').on(
                'click',
                function() {

                    let id =
                        $('#delete_product_id').val();


                    $(this)
                        .prop('disabled', true)
                        .text('Deleting...');


                    $.ajax({

                        url: "{{ url('products/delete') }}/" +
                            id,

                        type: 'POST',

                        data: {
                            _token: "{{ csrf_token() }}"
                        },


                        success: function(response) {

                            if (response.status) {

                                $('#deleteProductModal')
                                    .modal('hide');


                                toastr.success(
                                    response.message
                                );


                                productTable
                                    .ajax
                                    .reload(null, false);

                            } else {

                                toastr.error(
                                    response.message
                                );

                            }

                        },


                        error: function() {

                            toastr.error(
                                'Unable to delete product.'
                            );

                        },


                        complete: function() {

                            $('#confirmDeleteProduct')
                                .prop('disabled', false)
                                .text('Delete');

                        }

                    });

                }
            );



            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $(document).on(
                'click',
                '#manageProductTable tbody td:nth-child(8)',
                function() {

                    let row =
                        productTable
                        .row($(this).closest('tr'))
                        .data();


                    if (!row) {
                        return;
                    }


                    let id =
                        row.id;


                    $.ajax({

                        url: "{{ url('products/status') }}/" +
                            id,

                        type: 'POST',

                        data: {
                            _token: "{{ csrf_token() }}"
                        },


                        success: function(response) {

                            if (response.status) {

                                toastr.success(
                                    response.message
                                );


                                productTable
                                    .ajax
                                    .reload(null, false);

                            } else {

                                toastr.error(
                                    response.message
                                );

                            }

                        }

                    });

                }
            );



            /*
            |--------------------------------------------------------------------------
            | REMOVE ATTRIBUTE
            |--------------------------------------------------------------------------
            */

            $(document).on(
                'click',
                '.removeAttributeBtn',
                function() {

                    $(this)
                        .closest('.attribute-row')
                        .remove();


                    reindexAttributes();

                }
            );



            /*
            |--------------------------------------------------------------------------
            | REMOVE SPEC
            |--------------------------------------------------------------------------
            */

            $(document).on(
                'click',
                '.removeSpecBtn',
                function() {

                    $(this)
                        .closest('.spec-row')
                        .remove();


                    reindexSpecs();

                }
            );



            /*
            |--------------------------------------------------------------------------
            | MODAL HIDDEN
            |--------------------------------------------------------------------------
            */

            $('#productModal').on(
                'hidden.bs.modal',
                function() {

                    resetProductForm();

                }
            );

        });



        /*
        |--------------------------------------------------------------------------
        | INITIALIZE SELECT2
        |--------------------------------------------------------------------------
        */

        function initializeSelect2() {
            $('.select2').select2({

                width: '100%',

                dropdownParent: $('#productModal')

            });
        }



        /*
        |--------------------------------------------------------------------------
        | ADD ATTRIBUTE ROW
        |--------------------------------------------------------------------------
        */

        function addAttributeRow(
            selectedType = '',
            selectedAttribute = ''
        ) {

            let index =
                $('#attributeContainer .attribute-row')
                .length;


            let html = `

        <div class="row attribute-row">

            <div class="col-md-5">

                <select
                    name="attributes[${index}][attribute_type_id]"
                    class="form-control attribute-type">

                    <option value="">
                        Select Attribute Type
                    </option>

                    @foreach ($attributeTypes as $type)

                        <option value="{{ $type->id }}">
                            {{ $type->name }}
                        </option>

                    @endforeach

                </select>

            </div>


            <div class="col-md-5">

                <select
                    name="attributes[${index}][attribute_id]"
                    class="form-control attribute-value">

                    <option value="">
                        Select Attribute
                    </option>

                </select>

            </div>


            <div class="col-md-2">

                <button type="button"
                        class="btn btn-danger w-100 removeAttributeBtn">

                    <i class="fas fa-trash"></i>

                </button>

            </div>

        </div>

    `;


            $('#attributeContainer')
                .append(html);


            let row =
                $('#attributeContainer .attribute-row')
                .last();


            /*
             * Initialize Select2 for new row
             */

            row.find('.attribute-type').select2({

                width: '100%',

                dropdownParent: $('#productModal')

            });


            row.find('.attribute-value').select2({

                width: '100%',

                dropdownParent: $('#productModal')

            });


            if (selectedType) {

                row.find('.attribute-type')
                    .val(selectedType)
                    .trigger('change.select2');


                loadAttributes(
                    row,
                    selectedType,
                    selectedAttribute
                );

            }

        }



        /*
        |--------------------------------------------------------------------------
        | LOAD ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        function loadAttributes(
            row,
            typeId,
            selectedAttribute = ''
        ) {

            let select =
                row.find('.attribute-value');


            select
                .empty()
                .append(
                    '<option value="">Loading...</option>'
                )
                .trigger('change');


            if (!typeId) {

                select
                    .empty()
                    .append(
                        '<option value="">Select Attribute</option>'
                    )
                    .trigger('change');

                return;

            }


            $.ajax({

                url: "{{ url('products/attributes') }}/" +
                    typeId,

                type: 'GET',


                success: function(response) {

                    select
                        .empty()
                        .append(
                            '<option value="">Select Attribute</option>'
                        );


                    if (
                        response.status &&
                        response.data
                    ) {

                        $.each(
                            response.data,
                            function(index, attribute) {

                                select.append(`

                            <option value="${attribute.id}">
                                ${escapeHtml(attribute.name)}
                            </option>

                        `);

                            }
                        );

                    }


                    if (selectedAttribute) {

                        select
                            .val(selectedAttribute);

                    }


                    select.trigger('change');

                },


                error: function() {

                    select
                        .empty()
                        .append(
                            '<option value="">Select Attribute</option>'
                        )
                        .trigger('change');


                    toastr.error(
                        'Unable to load attributes.'
                    );

                }

            });

        }



        /*
        |--------------------------------------------------------------------------
        | REINDEX ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        function reindexAttributes() {

            $('#attributeContainer .attribute-row')
                .each(function(index) {

                    $(this)
                        .find('.attribute-type')
                        .attr(
                            'name',
                            'attributes[' +
                            index +
                            '][attribute_type_id]'
                        );


                    $(this)
                        .find('.attribute-value')
                        .attr(
                            'name',
                            'attributes[' +
                            index +
                            '][attribute_id]'
                        );

                });

        }



        /*
        |--------------------------------------------------------------------------
        | ADD SPECIFICATION ROW
        |--------------------------------------------------------------------------
        */

        function addSpecRow(
            specName = '',
            specValue = ''
        ) {

            let index =
                $('#specContainer .spec-row')
                .length;


            let html = `

        <div class="row spec-row">

            <div class="col-md-5">

                <input type="text"
                       name="specs[${index}][spec_name]"
                       class="form-control"
                       placeholder="Specification Name"
                       value="${escapeHtml(specName)}">

            </div>


            <div class="col-md-5">

                <input type="text"
                       name="specs[${index}][spec_value]"
                       class="form-control"
                       placeholder="Specification Value"
                       value="${escapeHtml(specValue)}">

            </div>


            <div class="col-md-2">

                <button type="button"
                        class="btn btn-danger w-100 removeSpecBtn">

                    <i class="fas fa-trash"></i>

                </button>

            </div>

        </div>

    `;


            $('#specContainer')
                .append(html);

        }



        /*
        |--------------------------------------------------------------------------
        | REINDEX SPECIFICATIONS
        |--------------------------------------------------------------------------
        */

        function reindexSpecs() {

            $('#specContainer .spec-row')
                .each(function(index) {

                    $(this)
                        .find('input')
                        .eq(0)
                        .attr(
                            'name',
                            'specs[' +
                            index +
                            '][spec_name]'
                        );


                    $(this)
                        .find('input')
                        .eq(1)
                        .attr(
                            'name',
                            'specs[' +
                            index +
                            '][spec_value]'
                        );

                });

        }



        /*
        |--------------------------------------------------------------------------
        | RESET FORM
        |--------------------------------------------------------------------------
        */

        function resetProductForm() {

            $('#productForm')[0].reset();


            $('#product_id')
                .val('');


            $('#slug_preview')
                .val('');


            $('#attributeContainer')
                .empty();


            $('#specContainer')
                .empty();


            /*
             * Select2 reset
             */

            $('#brand_id')
                .val('')
                .trigger('change');


            $('#category_id')
                .val('')
                .trigger('change');


            $('#unit_id')
                .val('')
                .trigger('change');


            /*
             * Image
             */

            $('#imagePreview')
                .attr('src', '')
                .hide();


            $('#image')
                .val('');


            /*
             * Opening stock
             */

            $('#opening_stock')
                .val(0)
                .prop('disabled', false);


            $('#currentStockText')
                .text('');


            /*
             * Default values
             */

            $('#purchase_price')
                .val(0);


            $('#sale_price')
                .val(0);


            $('#old_sale_price')
                .val(0);


            $('#discount')
                .val(0);


            $('#stock_alert')
                .val(0);


            /*
             * Button
             */

            $('#saveProductBtn')
                .prop('disabled', false)
                .text('Save');

        }



        /*
        |--------------------------------------------------------------------------
        | HTML ESCAPE
        |--------------------------------------------------------------------------
        */

        function escapeHtml(value) {

            if (
                value === null ||
                value === undefined
            ) {

                return '';

            }


            return $('<div>')
                .text(value)
                .html();

        }
    </script>
@endpush
