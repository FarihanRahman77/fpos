@extends('admin.master')

@section('title')
    {{ Session::get('companySettings')[0]['name'] ?? '' }} Products
@endsection

@section('content')

<div class="container-fluid">

    <!-- PAGE HEADER -->
    <div class="row mb-3">

        <div class="col-md-6">
            <h4 class="mb-0">Products</h4>
        </div>

        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" id="addProductBtn">
                <i class="fas fa-plus"></i> Add Product
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
                            <th width="3%" class="text-center">SL#</th>
                            <th width="8%" class="text-center">Image</th>
                            <th width="42%" class="text-center">Product Info</th>
                            <th width="15%" class="text-center">Stock</th>
                            <th width="16%" class="text-center">Price</th>
                            <th width="8%" class="text-center">Status</th>
                            <th width="8%" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

            </div>
        </div>
    </div>

</div>


<!-- ========================================================= -->
<!-- PRODUCT MODAL -->
<!-- ========================================================= -->

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true">

    <div class="modal-dialog modal-xl">

        <div class="modal-content">

            <form id="productForm" enctype="multipart/form-data">

                @csrf

                <input type="hidden" name="product_id" id="product_id">

                <!-- HEADER -->
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- BODY -->
                <div class="modal-body">

                    <div class="row">

                        <!-- PRODUCT INFORMATION -->
                        <div class="col-md-8">

                            <div class="row">

                                <!-- NAME -->
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">
                                            Product Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control border-dark" name="name" id="name"
                                            placeholder="Product Name" autocomplete="off" required>
                                    </div>
                                </div>

                                <!-- SLUG -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="slug_preview" class="form-label">Slug</label>
                                        <input type="text" class="form-control border-dark" id="slug_preview"
                                            placeholder="Slug" readonly>
                                    </div>
                                </div>

                                <!-- BRAND -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="brand_id" class="form-label">Brand</label>
                                        <select name="brand_id" id="brand_id" class="form-control border-dark select2">
                                            <option value="">Select Brand</option>
                                            @foreach ($brands as $brand)
                                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- CATEGORY -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select name="category_id" id="category_id" class="form-control border-dark select2">
                                            <option value="">Select Category</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- UNIT -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="unit_id" class="form-label">Unit</label>
                                        <select name="unit_id" id="unit_id" class="form-control border-dark select2">
                                            <option value="">Select Unit</option>
                                            @foreach ($units as $unit)
                                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                            </div>

                        </div>


                        <!-- IMAGE -->
                        <div class="col-md-4">

                            <div class="mb-3">
                                <label for="image" class="form-label">Product Image</label>
                                <input type="file" class="form-control border-dark" name="image" id="image"
                                    accept=".jpg,.jpeg,.png,.webp">
                            </div>

                            <div class="text-center mt-2">
                                <img src="" id="imagePreview" width="160" height="160"
                                    style="display:none; object-fit:contain; border:1px solid #ddd; padding:4px; border-radius:4px;"
                                    alt="Product Image">
                            </div>

                        </div>


                        <div class="col-md-12"><hr></div>


                        <!-- PURCHASE PRICE -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="purchase_price" class="form-label">
                                    Purchase Price <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" class="form-control border-dark"
                                    name="purchase_price" id="purchase_price" value="0" required>
                            </div>
                        </div>

                        <!-- SALE PRICE -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="sale_price" class="form-label">
                                    Current Sale Price <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" class="form-control border-dark"
                                    name="sale_price" id="sale_price" value="0" required>
                            </div>
                        </div>

                        <!-- OLD SALE PRICE -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="old_sale_price" class="form-label">Old Sale Price</label>
                                <input type="number" step="0.01" min="0" class="form-control border-dark"
                                    name="old_sale_price" id="old_sale_price" value="0">
                            </div>
                        </div>

                        <!-- DISCOUNT -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="discount" class="form-label">Discount</label>
                                <input type="number" step="0.01" min="0" class="form-control border-dark"
                                    name="discount" id="discount" value="0">
                            </div>
                        </div>

                        <!-- STOCK ALERT -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="stock_alert" class="form-label">Stock Alert</label>
                                <input type="number" step="0.001" min="0" class="form-control border-dark"
                                    name="stock_alert" id="stock_alert" value="0">
                            </div>
                        </div>

                        <!-- OPENING STOCK -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="opening_stock" class="form-label">Opening Stock</label>
                                <input type="number" step="0.001" min="0" class="form-control border-dark"
                                    name="opening_stock" id="opening_stock" value="0">
                                <div id="currentStockText" class="form-text text-primary fw-bold"></div>
                            </div>
                        </div>

                        <!-- SKU -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="sku" class="form-label">
                                    SKU <small class="text-muted">(Automatic)</small>
                                </label>
                                <input type="text" class="form-control border-dark" name="sku" id="sku"
                                    autocomplete="off" readonly>
                            </div>
                        </div>

                        <!-- BARCODE -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="barcode" class="form-label">
                                    Barcode <small class="text-muted">(Automatic)</small>
                                </label>
                                <input type="text" class="form-control border-dark" name="barcode" id="barcode"
                                    autocomplete="off" readonly>
                            </div>
                        </div>


                        <!-- ATTRIBUTES -->
                        <div class="col-md-12">

                            <hr>

                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0">Attributes & Variants</h6>
                                <button type="button" class="btn btn-sm btn-primary ms-auto" id="addAttributeBtn">
                                    <i class="fas fa-plus"></i> Add Attribute Group
                                </button>
                            </div>

                            <div id="attributeContainer"></div>

                        </div>


                        <!-- SPECIFICATIONS -->
                        <div class="col-md-12 mt-2">

                            <hr>

                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0">Specifications</h6>
                                <button type="button" class="btn btn-sm btn-primary ms-auto" id="addSpecBtn">
                                    <i class="fas fa-plus"></i> Add Specification
                                </button>
                            </div>

                            <div id="specContainer"></div>

                        </div>


                        <!-- DESCRIPTION -->
                        <div class="col-md-12 mt-2">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control border-dark"
                                    rows="3" placeholder="Description"></textarea>
                            </div>
                        </div>

                    </div>

                </div>


                <!-- FOOTER -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="saveProductBtn">Save</button>
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
                <h5 class="modal-title">Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                Are you sure you want to delete this product?
                <input type="hidden" id="delete_product_id">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteProduct">Delete</button>
            </div>

        </div>

    </div>

</div>

@endsection


@push('scripts')

<script>

"use strict";


/*
|--------------------------------------------------------------------------
| GLOBAL VARIABLES
|--------------------------------------------------------------------------
*/

let attributeIndex = 0;
let specIndex = 0;

let editMode = false;
let editingProductId = null;

let editRequest = null;
let productTable = null;

const attributeUrl = "{{ url('products/attributes') }}";
const barcodeUrl   = "{{ route('products.generate.variant.barcodes') }}";
const codesUrl     = "{{ route('products.generate.codes') }}";
const currency     = "{{ Session::get('companySettings')[0]['currency'] ?? '' }}";

function csrfToken() {
    return $('meta[name="csrf-token"]').attr('content') || "{{ csrf_token() }}";
}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


/*
|--------------------------------------------------------------------------
| BOOTSTRAP MODALS
|--------------------------------------------------------------------------
*/

function getProductModal() {
    const element = document.getElementById('productModal');
    return element ? bootstrap.Modal.getOrCreateInstance(element) : null;
}

function getDeleteModal() {
    const element = document.getElementById('deleteProductModal');
    return element ? bootstrap.Modal.getOrCreateInstance(element) : null;
}


/*
|--------------------------------------------------------------------------
| SELECT2
|--------------------------------------------------------------------------
*/

function initializeSelect2($container) {

    if (!$container || !$container.length) {
        $container = $('#productModal');
    }

    $container.find('select.select2').each(function () {

        const $select = $(this);

        if ($select.hasClass('select2-hidden-accessible')) {
            return;
        }

        $select.select2({
            width: '100%',
            dropdownParent: $('#productModal')
        });
    });
}

function destroySelect2($scope) {

    $scope.find('select.select2').each(function () {

        const $select = $(this);

        if ($select.hasClass('select2-hidden-accessible')) {
            try {
                $select.select2('destroy');
            } catch (error) {}
        }
    });
}


/*
|--------------------------------------------------------------------------
| RESET FORM
|--------------------------------------------------------------------------
*/

function resetProductForm() {

    editMode = false;
    editingProductId = null;

    if (editRequest) {
        try { editRequest.abort(); } catch (error) {}
        editRequest = null;
    }

    const form = $('#productForm')[0];

    if (form) {
        form.reset();
    }

    destroySelect2($('#attributeContainer'));

    $('#attributeContainer').empty();
    $('#specContainer').empty();

    attributeIndex = 0;
    specIndex = 0;

    $('#brand_id').val('').trigger('change.select2');
    $('#category_id').val('').trigger('change.select2');
    $('#unit_id').val('').trigger('change.select2');

    $('#product_id').val('');
    $('#image').val('');
    $('#imagePreview').attr('src', '').hide();

    $('#opening_stock').val(0).prop('disabled', false);
    $('#currentStockText').text('');

    $('#slug_preview').val('');
    $('#sku').val('');
    $('#barcode').val('');

    $('#productModalTitle').text('Add Product');
    $('#saveProductBtn').text('Save').prop('disabled', false);
}


/*
|--------------------------------------------------------------------------
| ADD ATTRIBUTE ROW
|--------------------------------------------------------------------------
*/

function addAttributeRow(selectedType = '', selectedAttributeIds = null, existingVariants = []) {

    const index = attributeIndex++;

    const html = `

        <div class="row attribute-row border rounded p-3 mb-3 bg-light" data-index="${index}">

            <div class="col-md-4">
                <div class="mb-2">
                    <label class="form-label fw-bold">Attribute Type</label>
                    <select name="attributes[${index}][attribute_type_id]"
                        class="form-control border-dark attribute-type">
                        <option value="">Select Attribute Type</option>
                        @foreach ($attributeTypes as $type)
                            <option value="{{ $type->id }}"
                                ${String(selectedType) === String({{ $type->id }}) ? 'selected' : ''}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-7">
                <div class="mb-2">
                    <label class="form-label fw-bold">Attributes</label>
                    <select name="attributes[${index}][attribute_ids][]"
                        class="form-control border-dark attribute-ids select2" multiple>
                    </select>
                </div>
            </div>

            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-danger removeAttributeBtn mb-2" title="Remove">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="col-md-12">
                <div class="variant-container"></div>
            </div>

        </div>

    `;

    $('#attributeContainer').append(html);

    const $row = $('#attributeContainer').find('.attribute-row').last();

    initializeSelect2($row);

    if (selectedType) {

        $row.find('.attribute-type').val(selectedType).trigger('change.select2');

        loadAttributesForRow($row, selectedAttributeIds, existingVariants);
    }
}


/*
|--------------------------------------------------------------------------
| LOAD ATTRIBUTES
|--------------------------------------------------------------------------
*/

function loadAttributesForRow($row, selectedAttributeIds = null, existingVariants = []) {

    if (!$row || !$row.length) {
        return;
    }

    const attributeTypeId = $row.find('.attribute-type').val();
    const $select = $row.find('.attribute-ids');

    if (!attributeTypeId) {
        clearAttributeSelect($row);
        return;
    }

    const previousRequest = $row.data('attributeRequest');

    if (previousRequest && previousRequest.readyState !== 4) {
        try { previousRequest.abort(); } catch (error) {}
    }

    $row.attr('data-loading-attributes', '1');

    $select.empty().trigger('change.select2');

    const request = $.ajax({
        url: attributeUrl + '/' + attributeTypeId,
        type: 'GET',
        dataType: 'json'
    });

    $row.data('attributeRequest', request);

    request.done(function (response) {

        if (!response || !response.status) {
            toastr.error(response?.message || 'Unable to load attributes.');
            return;
        }

        const attributes = response.data || [];

        let idsToSelect = [];

        /*
         * null  => attribute type just changed: select ALL its attributes.
         * array => edit mode: select only the saved ones.
         */

        if (selectedAttributeIds === null) {

            idsToSelect = attributes.map(function (item) {
                return String(item.id);
            });

        } else {

            if (!Array.isArray(selectedAttributeIds)) {
                selectedAttributeIds = [selectedAttributeIds];
            }

            idsToSelect = selectedAttributeIds.map(function (id) {
                return String(id);
            });
        }

        const availableIds = attributes.map(function (attr) {
            return String(attr.id);
        });

        idsToSelect = idsToSelect.filter(function (id) {
            return availableIds.includes(id);
        });

        $.each(attributes, function (i, attr) {

            if (!attr || attr.id === undefined) {
                return;
            }

            const attrId = String(attr.id);
            const selected = idsToSelect.includes(attrId);

            $select.append(`
                <option value="${escapeHtml(attrId)}" ${selected ? 'selected' : ''}>
                    ${escapeHtml(attr.name || '')}
                </option>
            `);
        });

        $select.val(idsToSelect).trigger('change.select2');

        renderVariants($row, existingVariants);
    });

    request.fail(function (xhr, status) {

        if (status === 'abort') {
            return;
        }

        toastr.error('Unable to load attributes.');
    });

    request.always(function () {

        // ignore an old (aborted) request finishing after a newer one started
        if ($row.data('attributeRequest') !== request) {
            return;
        }

        $row.removeAttr('data-loading-attributes');
        $row.removeData('attributeRequest');
    });
}


/*
|--------------------------------------------------------------------------
| CLEAR ATTRIBUTE
|--------------------------------------------------------------------------
*/

function clearAttributeSelect($row) {

    if (!$row || !$row.length) {
        return;
    }

    $row.removeData('variantCache');

    $row.find('.attribute-ids').empty().val([]).trigger('change.select2');

    $row.find('.variant-container').empty();
}


/*
|--------------------------------------------------------------------------
| ATTRIBUTE TYPE CHANGE
|--------------------------------------------------------------------------
*/

$(document).on('change', '.attribute-type', function () {

    const $row = $(this).closest('.attribute-row');

    // a different type means different variants => forget the old ones
    $row.removeData('variantCache');

    if (!$(this).val()) {
        clearAttributeSelect($row);
        return;
    }

    loadAttributesForRow($row, null, []);
});


/*
|--------------------------------------------------------------------------
| ATTRIBUTE CHANGE (multi select)
|--------------------------------------------------------------------------
|
| Re-render the variant rows but KEEP what is already on screen
| (barcodes, prices, stock, saved-variant ids).
|
*/

$(document).on('change', '.attribute-ids', function () {

    const $row = $(this).closest('.attribute-row');

    if ($row.attr('data-loading-attributes') === '1') {
        return;
    }

    renderVariants($row, collectVariantState($row));
});


/*
|--------------------------------------------------------------------------
| COLLECT VARIANT STATE
|--------------------------------------------------------------------------
|
| Remembers every variant of the row (also the ones that were unselected)
| so selecting an attribute again restores its barcode / prices / id.
|
*/

function collectVariantState($row) {

    const cache = $row.data('variantCache') || {};

    $row.find('.variant-row').each(function () {

        const $r = $(this);

        const attrId = String($r.find('input[name$="[attribute_id]"]').val());

        cache[attrId] = {
            id:             $r.attr('data-variant-id') || null,
            attribute_id:   attrId,
            barcode:        $r.find('.variant_barcode').val(),
            sale_price:     $r.find('input[name$="[sale_price]"]').val(),
            old_sale_price: $r.find('input[name$="[old_sale_price]"]').val(),
            opening_stock:  $r.find('input[name$="[opening_stock]"]').val()
        };
    });

    $row.data('variantCache', cache);

    return Object.values(cache);
}


/*
|--------------------------------------------------------------------------
| RENDER VARIANTS
|--------------------------------------------------------------------------
*/

function renderVariants($row, existingVariants = []) {

    if (!$row || !$row.length) {
        return;
    }

    const $select = $row.find('.attribute-ids');

    const selectedAttributeIds = ($select.val() || []).map(function (id) {
        return String(id);
    });

    const groupIndex = $row.attr('data-index');
    const $container = $row.find('.variant-container');


    /*
     * Variant cache (attribute_id => variant data).
     */

    const cache = $row.data('variantCache') || {};

    $.each(existingVariants || [], function (i, variant) {

        if (
            variant &&
            variant.attribute_id !== null &&
            variant.attribute_id !== undefined
        ) {
            cache[String(variant.attribute_id)] = variant;
        }
    });

    $row.data('variantCache', cache);


    $container.empty();

    if (selectedAttributeIds.length === 0) {
        return;
    }


    let html = `

        <div class="card border-dark mb-2">

            <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">

                <strong>Variant Price & Stock Details</strong>

                <span class="badge bg-secondary">
                    ${selectedAttributeIds.length} Variant(s)
                </span>

            </div>

            <div class="card-body p-2">

                <!-- BULK PRICE / STOCK / BARCODE -->

                <div class="bg-light p-2 mb-3 rounded border border-primary-subtle bulk-apply-box">

                    <div class="d-flex align-items-center mb-1">
                        <small class="fw-bold text-primary">
                            <i class="fas fa-bolt me-1"></i>
                            Apply to All Variants Below (also generates unique barcodes):
                        </small>
                    </div>

                    <div class="row g-2 align-items-end">

                        <div class="col-md-3">
                            <label class="form-label small mb-0 text-muted">Sale Price</label>
                            <input type="number" step="0.01" min="0"
                                class="form-control form-control-sm border-dark bulk-sale-price"
                                placeholder="Sale Price">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small mb-0 text-muted">Old Sale Price</label>
                            <input type="number" step="0.01" min="0"
                                class="form-control form-control-sm border-dark bulk-old-sale-price"
                                placeholder="Old Price">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small mb-0 text-muted">Opening Stock</label>
                            <input type="number" step="0.001" min="0"
                                class="form-control form-control-sm border-dark bulk-opening-stock"
                                placeholder="Opening Stock">
                        </div>

                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-primary w-100 applyBulkVariantBtn">
                                <i class="fas fa-check-double me-1"></i> Apply
                            </button>
                        </div>

                    </div>

                </div>

    `;


    $.each(selectedAttributeIds, function (variantIndex, attributeId) {

        attributeId = String(attributeId);

        const $option = $select.find('option[value="' + attributeId + '"]');

        const attributeName = $option.length ? $option.text().trim() : 'Attribute';

        const existingVariant = cache[attributeId] || {};

        const barcode      = existingVariant.barcode ?? '';
        const salePrice    = existingVariant.sale_price ?? '';
        const oldSalePrice = existingVariant.old_sale_price ?? '';
        const openingStock = existingVariant.opening_stock ?? '';

        const isExisting = Boolean(existingVariant.id);
        const lockStock  = editMode && isExisting;

        html += `

            <div class="row variant-row border-bottom pb-2 mb-2 align-items-end"
                data-variant-id="${escapeHtml(existingVariant.id ?? '')}"
                data-existing="${isExisting ? 1 : 0}">

                <!-- ATTRIBUTE -->
                <div class="col-md-2">

                    <label class="form-label small mb-1">Variant</label>

                    <input type="text" class="form-control form-control-sm border-dark"
                        value="${escapeHtml(attributeName)}" readonly>

                    <input type="hidden"
                        name="attributes_variants[${groupIndex}][${variantIndex}][attribute_id]"
                        value="${escapeHtml(attributeId)}">

                </div>

                <!-- BARCODE -->
                <div class="col-md-3">

                    <label class="form-label small mb-1">
                        Barcode <small class="text-muted">(Automatic)</small>
                    </label>

                    <input type="text"
                        class="form-control form-control-sm border-dark variant_barcode"
                        name="attributes_variants[${groupIndex}][${variantIndex}][barcode]"
                        value="${escapeHtml(barcode)}"
                        placeholder="Click Apply to generate"
                        readonly>

                </div>

                <!-- SALE PRICE -->
                <div class="col-md-2">

                    <label class="form-label small mb-1">Sale Price</label>

                    <input type="number" step="0.01" min="0"
                        class="form-control form-control-sm border-dark"
                        name="attributes_variants[${groupIndex}][${variantIndex}][sale_price]"
                        value="${escapeHtml(salePrice)}"
                        placeholder="Sale Price">

                </div>

                <!-- OLD SALE PRICE -->
                <div class="col-md-2">

                    <label class="form-label small mb-1">Old Sale Price</label>

                    <input type="number" step="0.01" min="0"
                        class="form-control form-control-sm border-dark"
                        name="attributes_variants[${groupIndex}][${variantIndex}][old_sale_price]"
                        value="${escapeHtml(oldSalePrice)}"
                        placeholder="Old Price">

                </div>

                <!-- OPENING STOCK -->
                <div class="col-md-3">

                    <label class="form-label small mb-1">
                        Opening Stock
                        ${lockStock ? '<small class="text-muted">(Locked)</small>' : ''}
                    </label>

                    <input type="number" step="0.001" min="0"
                        class="form-control form-control-sm border-dark variant-opening-stock"
                        name="attributes_variants[${groupIndex}][${variantIndex}][opening_stock]"
                        value="${escapeHtml(openingStock)}"
                        placeholder="Opening Stock"
                        ${lockStock ? 'disabled title="Opening stock cannot be changed after creation"' : ''}>

                </div>

            </div>

        `;
    });


    html += `

            </div>

        </div>

    `;

    $container.html(html);
}


/*
|--------------------------------------------------------------------------
| BULK APPLY  (prices / stock + unique barcodes from the server)
|--------------------------------------------------------------------------
*/

$(document).on('click', '.applyBulkVariantBtn', function (e) {

    e.preventDefault();

    const $btn = $(this);

    if ($btn.prop('disabled')) {
        return;
    }

    const $card    = $btn.closest('.card');
    const $bulkBox = $btn.closest('.bulk-apply-box');

    const salePrice    = $bulkBox.find('.bulk-sale-price').val().trim();
    const oldSalePrice = $bulkBox.find('.bulk-old-sale-price').val().trim();
    const openingStock = $bulkBox.find('.bulk-opening-stock').val().trim();

    const hasValues = salePrice !== '' || oldSalePrice !== '' || openingStock !== '';

    // Barcode inputs of NEW variants only (saved variants keep their barcode)
    const $targets = $card.find('.variant-row[data-existing="0"] .variant_barcode');

    if (!hasValues && $targets.length === 0) {
        toastr.warning('Nothing to apply.');
        return;
    }


    /*
     * 1) Prices / stock (only the ones entered)
     */

    if (hasValues) {

        $card.find('.variant-row').each(function () {

            const $r = $(this);

            if (salePrice !== '') {
                $r.find('input[name$="[sale_price]"]').val(salePrice);
            }

            if (oldSalePrice !== '') {
                $r.find('input[name$="[old_sale_price]"]').val(oldSalePrice);
            }

            if (openingStock !== '') {

                const $stock = $r.find('input[name$="[opening_stock]"]');

                if (!$stock.prop('disabled')) {
                    $stock.val(openingStock);
                }
            }
        });
    }

    if ($targets.length === 0) {
        toastr.success('Values applied to variants.');
        return;
    }


    /*
     * 2) Unique barcodes from the server
     */

    const reserved = [];

    const productBarcode = ($('#barcode').val() || '').trim();

    if (productBarcode) {
        reserved.push(productBarcode);
    }

    // every other barcode on the form (other groups, saved variants)
    $('.variant_barcode').not($targets).each(function () {

        const v = ($(this).val() || '').trim();

        if (v) {
            reserved.push(v);
        }
    });

    const originalHtml = $btn.html();

    $btn.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span> Generating...');

    $.ajax({
        url: barcodeUrl,
        type: 'GET',
        dataType: 'json',
        data: {
            count: $targets.length,
            reserved: reserved
        },
        headers: {
            'X-CSRF-TOKEN': csrfToken()
        }
    })
    .done(function (response) {

        if (
            !response ||
            !response.status ||
            !Array.isArray(response.barcodes) ||
            response.barcodes.length !== $targets.length
        ) {
            toastr.error(response?.message || 'Unable to generate barcodes.');
            return;
        }

        $targets.each(function (i) {
            $(this).val(response.barcodes[i]);
        });

        // keep the new barcodes in the row cache
        collectVariantState($card.closest('.attribute-row'));

        toastr.success('Applied values and generated ' + $targets.length + ' unique barcode(s).');
    })
    .fail(function (xhr) {

        toastr.error(xhr.responseJSON?.message || 'Unable to generate barcodes.');
    })
    .always(function () {

        $btn.prop('disabled', false).html(originalHtml);
    });
});


/*
|--------------------------------------------------------------------------
| ADD / REMOVE ATTRIBUTE GROUP
|--------------------------------------------------------------------------
*/

$(document).on('click', '#addAttributeBtn', function (e) {

    e.preventDefault();

    addAttributeRow();
});


$(document).on('click', '.removeAttributeBtn', function (e) {

    e.preventDefault();

    const $row = $(this).closest('.attribute-row');

    const request = $row.data('attributeRequest');

    if (request && request.readyState !== 4) {
        try { request.abort(); } catch (error) {}
    }

    destroySelect2($row);

    $row.remove();
});


/*
|--------------------------------------------------------------------------
| SPECIFICATIONS
|--------------------------------------------------------------------------
*/

function addSpecRow(specName = '', specValue = '') {

    const index = specIndex++;

    const html = `

        <div class="row spec-row mb-2" data-index="${index}">

            <div class="col-md-5">
                <input type="text" class="form-control border-dark"
                    name="specs[${index}][spec_name]"
                    placeholder="Specification Name"
                    value="${escapeHtml(specName)}">
            </div>

            <div class="col-md-6">
                <input type="text" class="form-control border-dark"
                    name="specs[${index}][spec_value]"
                    placeholder="Specification Value"
                    value="${escapeHtml(specValue)}">
            </div>

            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-danger removeSpecBtn">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

        </div>

    `;

    $('#specContainer').append(html);
}


$(document).on('click', '#addSpecBtn', function (e) {

    e.preventDefault();

    addSpecRow();
});


$(document).on('click', '.removeSpecBtn', function (e) {

    e.preventDefault();

    $(this).closest('.spec-row').remove();
});


/*
|--------------------------------------------------------------------------
| IMAGE PREVIEW
|--------------------------------------------------------------------------
*/

$(document).on('change', '#image', function (event) {

    const file = event.target.files[0];

    if (!file) {
        $('#imagePreview').attr('src', '').hide();
        return;
    }

    if (file.type && !file.type.startsWith('image/')) {

        toastr.error('Please select a valid image.');

        $(this).val('');
        $('#imagePreview').attr('src', '').hide();

        return;
    }

    const reader = new FileReader();

    reader.onload = function (e) {
        $('#imagePreview').attr('src', e.target.result).show();
    };

    reader.readAsDataURL(file);
});


/*
|--------------------------------------------------------------------------
| NAME TO SLUG PREVIEW
|--------------------------------------------------------------------------
*/

$(document).on('input', '#name', function () {

    const slug = $(this).val()
        .toString()
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');

    $('#slug_preview').val(slug);
});


/*
|--------------------------------------------------------------------------
| ADD PRODUCT
|--------------------------------------------------------------------------
*/

$(document).on('click', '#addProductBtn', function (e) {

    e.preventDefault();

    resetProductForm();

    const modal = getProductModal();

    if (modal) {
        modal.show();
    }
});


/*
|--------------------------------------------------------------------------
| EDIT PRODUCT
|--------------------------------------------------------------------------
*/

$(document).on('click', '.editProductBtn', function (e) {

    e.preventDefault();

    const id = $(this).data('id');

    if (!id) {
        toastr.error('Product ID not found.');
        return;
    }

    const $button = $(this);

    if ($button.prop('disabled')) {
        return;
    }

    $button.prop('disabled', true);

    if (editRequest) {
        try { editRequest.abort(); } catch (error) {}
    }

    editMode = true;
    editingProductId = id;

    editRequest = $.ajax({
        url: "{{ url('products/edit') }}/" + id,
        type: 'GET',
        dataType: 'json'
    });

    editRequest.done(function (response) {

        if (!response || !response.status) {

            toastr.error(response?.message || 'Product not found.');

            editMode = false;
            editingProductId = null;

            return;
        }

        const form = $('#productForm')[0];

        if (form) {
            form.reset();
        }

        destroySelect2($('#attributeContainer'));

        $('#attributeContainer').empty();
        $('#specContainer').empty();

        attributeIndex = 0;
        specIndex = 0;

        const product = response.product || {};

        $('#product_id').val(product.id || id);

        $('#name').val(product.name || '').trigger('input');

        $('#brand_id').val(product.brand_id || '').trigger('change.select2');
        $('#category_id').val(product.category_id || '').trigger('change.select2');
        $('#unit_id').val(product.unit_id || '').trigger('change.select2');

        // Automatic SKU + product barcode (read only)
        $('#sku').val(product.sku || '');
        $('#barcode').val(product.barcode || '');

        $('#purchase_price').val(product.purchase_price ?? 0);
        $('#sale_price').val(product.sale_price ?? 0);
        $('#old_sale_price').val(product.old_sale_price ?? 0);
        $('#discount').val(product.discount ?? 0);
        $('#stock_alert').val(product.stock_alert ?? 0);

        $('#description').val(product.description || '');

        // Main opening stock is locked during edit
        $('#opening_stock').val(0).prop('disabled', true);

        $('#currentStockText').text('Current Stock: ' + (response.current_stock ?? 0));

        // Image
        if (product.image) {

            $('#imagePreview')
                .attr('src', "{{ asset('uploads/products') }}/" + product.image)
                .show();

        } else {

            $('#imagePreview').attr('src', '').hide();
        }

        // Attributes & variants
        const groups = Array.isArray(response.attributes) ? response.attributes : [];

        $.each(groups, function (i, group) {

            if (!group) {
                return;
            }

            const typeId = group.attribute_type_id ?? '';

            let attributeIds = group.attribute_ids ?? [];
            let variants     = group.variants ?? [];

            if (!Array.isArray(attributeIds)) {
                attributeIds = [attributeIds];
            }

            if (!Array.isArray(variants)) {
                variants = [];
            }

            addAttributeRow(typeId, attributeIds, variants);
        });

        // Specifications
        const specs = Array.isArray(response.specs) ? response.specs : [];

        $.each(specs, function (i, spec) {

            if (!spec) {
                return;
            }

            addSpecRow(spec.spec_name || '', spec.spec_value || '');
        });

        $('#productModalTitle').text('Edit Product');
        $('#saveProductBtn').text('Update').prop('disabled', false);

        const modal = getProductModal();

        if (modal) {
            modal.show();
        }
    });

    editRequest.fail(function (xhr, status) {

        if (status === 'abort') {
            return;
        }

        editMode = false;
        editingProductId = null;

        toastr.error(xhr.responseJSON?.message || 'Unable to load product.');
    });

    editRequest.always(function () {

        editRequest = null;

        $button.prop('disabled', false);
    });
});


/*
|--------------------------------------------------------------------------
| FORM SUBMIT
|--------------------------------------------------------------------------
*/

$(document).on('submit', '#productForm', function (e) {

    e.preventDefault();

    if ($('#saveProductBtn').prop('disabled')) {
        return;
    }

    if (!$('#name').val().trim()) {

        toastr.error('Product name is required.');

        $('#name').focus();

        return;
    }


    /*
     * Every variant barcode must be unique.
     */

    const seen = {};
    let duplicateBarcode = null;

    $('#attributeContainer .variant_barcode').each(function () {

        const value = ($(this).val() || '').trim();

        if (!value) {
            return;
        }

        if (seen[value]) {
            duplicateBarcode = value;
        }

        seen[value] = true;
    });

    if (duplicateBarcode) {

        toastr.error('Duplicate variant barcode: ' + duplicateBarcode + '. Click Apply to regenerate.');

        return;
    }


    const formData = new FormData(this);

    let url = "{{ url('products/store') }}";

    if (editMode && editingProductId) {
        url = "{{ url('products/update') }}/" + editingProductId;
    }

    $.ajax({

        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,

        headers: {
            'X-CSRF-TOKEN': csrfToken()
        },

        beforeSend: function () {

            $('#saveProductBtn')
                .prop('disabled', true)
                .text(editMode ? 'Updating...' : 'Saving...');
        },

        success: function (response) {

            if (response && response.status) {

                toastr.success(
                    response.message ||
                    (editMode ? 'Product updated successfully.' : 'Product created successfully.')
                );

                const modal = getProductModal();

                if (modal) {
                    modal.hide();
                }

                if (productTable) {
                    productTable.ajax.reload(null, false);
                }

            } else {

                toastr.error(response?.message || 'Something went wrong.');
            }
        },

        error: function (xhr) {

            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {

                $.each(xhr.responseJSON.errors, function (field, messages) {

                    if (Array.isArray(messages)) {

                        $.each(messages, function (i, msg) {
                            toastr.error(msg);
                        });
                    }
                });

            } else {

                toastr.error(xhr.responseJSON?.message || 'Something went wrong.');
            }
        },

        complete: function () {

            $('#saveProductBtn')
                .prop('disabled', false)
                .text(editMode ? 'Update' : 'Save');
        }
    });
});


/*
|--------------------------------------------------------------------------
| MODAL RESET
|--------------------------------------------------------------------------
*/

$(document).on('hidden.bs.modal', '#productModal', function () {

    resetProductForm();
});


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$(document).on('click', '.toggleStatusBtn', function (e) {

    e.preventDefault();

    const id = $(this).data('id');

    if (!id) {
        return;
    }

    const $btn = $(this);

    if ($btn.prop('disabled')) {
        return;
    }

    $btn.prop('disabled', true);

    $.ajax({

        url: "{{ url('products/status') }}/" + id,
        type: 'POST',
        data: { _token: "{{ csrf_token() }}" },
        headers: { 'X-CSRF-TOKEN': csrfToken() },
        dataType: 'json',

        success: function (response) {

            if (response && response.status) {

                toastr.success(response.message || 'Status updated successfully.');

                if (productTable) {
                    productTable.ajax.reload(null, false);
                }

            } else {

                toastr.error(response?.message || 'Unable to update status.');
            }
        },

        error: function (xhr) {

            toastr.error(xhr.responseJSON?.message || 'Unable to update status.');
        },

        complete: function () {

            $btn.prop('disabled', false);
        }
    });
});


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

$(document).on('click', '.deleteProductBtn', function (e) {

    e.preventDefault();

    const id = $(this).data('id');

    if (!id) {
        toastr.error('Product ID not found.');
        return;
    }

    $('#delete_product_id').val(id);

    const modal = getDeleteModal();

    if (modal) {
        modal.show();
    }
});


$(document).on('click', '#confirmDeleteProduct', function (e) {

    e.preventDefault();

    const id = $('#delete_product_id').val();

    if (!id) {
        toastr.error('Product ID not found.');
        return;
    }

    const $button = $(this);

    if ($button.prop('disabled')) {
        return;
    }

    $button.prop('disabled', true).text('Deleting...');

    $.ajax({

        url: "{{ url('products/delete') }}/" + id,
        type: 'POST',
        data: { _token: "{{ csrf_token() }}" },
        headers: { 'X-CSRF-TOKEN': csrfToken() },
        dataType: 'json',

        success: function (response) {

            if (response && response.status) {

                toastr.success(response.message || 'Product deleted successfully.');

                const modal = getDeleteModal();

                if (modal) {
                    modal.hide();
                }

                if (productTable) {
                    productTable.ajax.reload(null, false);
                }

            } else {

                toastr.error(response?.message || 'Unable to delete product.');
            }
        },

        error: function (xhr) {

            toastr.error(xhr.responseJSON?.message || 'Unable to delete product.');
        },

        complete: function () {

            $button.prop('disabled', false).text('Delete');
        }
    });
});


/*
|--------------------------------------------------------------------------
| DATATABLE
|--------------------------------------------------------------------------
*/

$(document).ready(function () {

    initializeSelect2($('#productModal'));

    if ($.fn.DataTable && $('#manageProductTable').length) {

        productTable = $('#manageProductTable').DataTable({

            ajax: {

                url: "{{ route('products.list') }}",
                type: 'GET',

                dataSrc: function (json) {

                    if (!json || json.status === false) {

                        toastr.error(json?.message || 'Unable to load products.');

                        return [];
                    }

                    return json.data || [];
                }
            },

            processing: true,
            pageLength: 25,

            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ],

            order: [[0, 'asc']],

            columns: [

                // SL
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row, meta) {
                        return meta.row + 1;
                    }
                },

                // IMAGE
                {
                    data: 'image',
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {

                        if (data) {

                            return `
                                <img src="{{ asset('uploads/products') }}/${escapeHtml(data)}"
                                    class="product-table-image"
                                    alt="${escapeHtml(row.name || '')}"
                                    width="45" height="45"
                                    style="object-fit:contain; border-radius:4px;"
                                    onerror="this.onerror=null; this.parentElement.innerHTML='-';">
                            `;
                        }

                        return '-';
                    }
                },

                // PRODUCT INFO
                {
                    data: null,
                    render: function (data, type, row) {

                        let html = '<strong class="text-dark">' + escapeHtml(row.name || '') + '</strong>';



                        if (row.category_name) {
                            html += '<br><small class="text-muted"><b>Category:</b> ' + escapeHtml(row.category_name) + '</small>';
                        }

                        if (row.brand_name) {
                            html += '<br><small class="text-muted"><b>Brand:</b> ' + escapeHtml(row.brand_name) + '</small>';
                        }

                        return html;
                    }
                },

                // STOCK
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {

                        const opening  = parseFloat(row.opening_stock || 0);
                        const current  = parseFloat(row.current_stock || 0);
                        const alertQty = parseFloat(row.stock_alert || 0);

                        const currentClass = current <= alertQty
                            ? 'text-danger fw-bold'
                            : 'text-success fw-bold';

                        return `
                            <div class="small text-start d-inline-block">
                                <div><b>Opening:</b> ${opening}</div>
                                <div><b>Alert:</b> ${alertQty}</div>
                                <div><b>Available:</b> <span class="${currentClass}">${current}</span></div>
                            </div>
                        `;
                    }
                },

                // PRICE
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row) {

                        const purchasePrice = parseFloat(row.purchase_price || 0);
                        const salePrice     = parseFloat(row.sale_price || 0);
                        const oldSalePrice  = parseFloat(row.old_sale_price || 0);
                        const discount      = parseFloat(row.discount || 0);

                        let html = '<div class="small text-start d-inline-block">';

                        html += '<div><b>PP:</b> ' + escapeHtml(currency) + purchasePrice.toFixed(2) + '</div>';
                        html += '<div><b>SP:</b> ' + escapeHtml(currency) + salePrice.toFixed(2) + '</div>';

                        if (oldSalePrice > 0) {
                            html += '<div><b>Old:</b> ' + escapeHtml(currency) + oldSalePrice.toFixed(2) + '</div>';
                        }

                        if (discount > 0) {
                            html += '<div><b>Discount:</b> ' + discount.toFixed(2) + '</div>';
                        }

                        html += '</div>';

                        return html;
                    }
                },

                // STATUS
                {
                    data: 'status',
                    className: 'text-center',
                    render: function (data, type, row) {

                        if (data === 'Active') {

                            return `
                                <button type="button" class="btn btn-sm btn-link p-0 toggleStatusBtn"
                                    data-id="${escapeHtml(row.id)}"
                                    title="Active (Click to Deactivate)">
                                    <i class="fas fa-check-circle text-success" style="font-size:18px;"></i>
                                </button>
                            `;
                        }

                        return `
                            <button type="button" class="btn btn-sm btn-link p-0 toggleStatusBtn"
                                data-id="${escapeHtml(row.id)}"
                                title="Inactive (Click to Activate)">
                                <i class="fas fa-times-circle text-danger" style="font-size:18px;"></i>
                            </button>
                        `;
                    }
                },

                // ACTIONS
                {
                    data: 'id',
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    render: function (data) {

                        return `
                            <div class="btn-group">

                                <button type="button" class="btn btn-sm btn-primary editProductBtn"
                                    data-id="${escapeHtml(data)}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button type="button" class="btn btn-sm btn-danger deleteProductBtn"
                                    data-id="${escapeHtml(data)}" title="Delete">
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
    }
});


/*
|--------------------------------------------------------------------------
| AUTO SKU + PRODUCT BARCODE PREVIEW
|--------------------------------------------------------------------------
|
| Only when creating a product. In edit mode the server keeps the
| existing SKU / barcode, so the form must not show different ones.
|
*/

$('#name,#category_id,#brand_id').on('change', function () {

    const name = ($('#name').val() || '').trim();

    if (!name) {
        return;
    }

    $.ajax({

        url: codesUrl,
        type: 'GET',

        data: {
            name: name,
            category_id: $('#category_id').val(),
            brand_id: $('#brand_id').val()
        },

        success: function (response) {

            if (response.status) {
                $('#sku').val(response.sku);
                $('#barcode').val(response.barcode);
            }
        }
    });
});

</script>

@endpush
