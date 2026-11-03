@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} Products
@endsection

@push('styles')
<style>
    /* Products list: summary tiles, filter bar and the one-value-per-cell table. Tokens only. */
    .prd-stat .card-body { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-md) var(--space-lg); }
    .prd-stat .icon { flex: 0 0 auto; width: var(--control-height); height: var(--control-height); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: var(--text-body-lg); }
    .prd-stat .i1 { background: var(--color-accent-soft); color: var(--color-accent-ink); }
    .prd-stat .i2 { background: var(--color-success-soft); color: var(--color-success); }
    .prd-stat .i3 { background: var(--color-warning-soft); color: var(--color-warning); }
    .prd-stat .i4 { background: var(--color-danger-soft); color: var(--color-danger); }
    .prd-stat .i5 { background: var(--color-info-soft); color: var(--color-info); }
    .prd-stat .min-w-0 { min-width: 0; }
    .prd-stat .label { color: var(--color-text-muted); font-size: var(--text-caption); letter-spacing: var(--tracking-caption); white-space: nowrap; }
    .prd-stat .value { margin: 0; font-size: var(--text-heading-sm); font-weight: var(--font-weight-light); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .prd-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: var(--space-sm) var(--space-md); }
    .prd-filters .f-item { display: flex; flex-direction: column; gap: var(--space-xxs); min-width: 0; }
    .prd-filters .f-item > label { margin: 0; color: var(--color-text-muted); font-size: var(--text-micro); font-weight: var(--font-weight-medium); letter-spacing: var(--tracking-micro-cap); text-transform: uppercase; white-space: nowrap; }
    .prd-filters .f-select { width: 170px; }
    .prd-filters .f-small { width: 120px; }
    .prd-filters .f-range { width: 190px; }
    .prd-filters .f-range .form-control { min-width: 0; text-align: right; font-feature-settings: var(--font-feature-numeric); }
    .prd-filters .f-range .input-group-text { padding-left: var(--space-xs); padding-right: var(--space-xs); color: var(--color-text-muted); }
    .prd-filters .f-actions { margin-left: auto; }
    @media (max-width: 767.98px) {
        .prd-filters .f-item { flex: 1 1 calc(50% - var(--space-md)); }
        .prd-filters .f-select, .prd-filters .f-small, .prd-filters .f-range { width: auto; }
        .prd-filters .f-range { flex-basis: 100%; }
        .prd-filters .f-actions { margin-left: 0; flex: 0 0 auto; }
    }

    .prd-dt-head, .prd-dt-foot { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-sm) var(--space-md); }
    .prd-dt-head { margin-bottom: var(--space-sm); }
    .prd-dt-foot { justify-content: space-between; margin-top: var(--space-sm); }
    .prd-dt-head .dataTables_length, .prd-dt-head .dataTables_filter { float: none; padding: 0; }
    .prd-dt-count { flex: 1 1 auto; min-width: 0; }
    .prd-dt-head .dataTables_filter input { margin-left: 0; }
    @media (max-width: 575.98px) {
        .prd-dt-head .dataTables_filter { flex: 1 1 auto; }
        .prd-dt-head .dataTables_filter label, .prd-dt-head .dataTables_filter input { width: 100%; }
        .prd-dt-count { order: 3; flex-basis: 100%; }
    }
    .prd-count { color: var(--color-text-muted); font-size: var(--text-caption); font-feature-settings: var(--font-feature-numeric); }
    .prd-count strong { color: var(--color-text); font-weight: var(--font-weight-medium); }
    .prd-cols .dropdown-menu { max-height: 360px; overflow-y: auto; min-width: 210px; }
    .prd-cols .dropdown-item { display: flex; align-items: center; gap: var(--space-sm); cursor: pointer; }
    .prd-cols .form-check-input { margin: 0; }

    .prd-scroll { width: 100%; overflow: auto; }
    @media (min-width: 992px) {
        .prd-scroll { max-height: calc(100vh - 230px); min-height: 320px; }
        #manageProductTable > thead > tr > th { position: sticky; top: 0; z-index: 2; }
    }
    #manageProductTable { margin: 0 !important; }
    #manageProductTable > tbody > tr > td { min-width: 0; padding: var(--space-sm) var(--space-md); vertical-align: middle; white-space: nowrap; }
    #manageProductTable > thead > tr > th { padding: var(--space-sm) var(--space-md); }
    #manageProductTable > thead > tr > th.sorting, #manageProductTable > thead > tr > th.sorting_asc, #manageProductTable > thead > tr > th.sorting_desc { padding-right: calc(var(--space-md) * 2 + var(--space-xs)); }
    #manageProductTable td.num, #manageProductTable th.num { text-align: right; font-feature-settings: var(--font-feature-numeric); }
    #manageProductTable .prd-name { display: inline-block; max-width: 240px; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; white-space: nowrap; }
    #manageProductTable .prd-name-btn { padding: 0; border: 0; background: none; color: var(--color-link); text-align: left; font: inherit; }
    #manageProductTable .prd-name-btn:hover { text-decoration: underline; }
    #manageProductTable .prd-clip { display: inline-block; max-width: 150px; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; white-space: nowrap; }
    #manageProductTable .prd-code { font-family: var(--font-mono); font-size: var(--text-caption); }
    #manageProductTable .product-table-image { width: 40px; height: 40px; object-fit: contain; border-radius: var(--radius-sm); background: var(--color-surface-soft); }
    #manageProductTable .prd-noimg { display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; border-radius: var(--radius-sm); background: var(--color-surface-soft); color: var(--color-text-muted); }
    #manageProductTable .muted { color: var(--color-text-muted); }
    #manageProductTable .neg { color: var(--color-danger); }
    #manageProductTable .status-icon { font-size: var(--text-body-lg); }
    #manageProductTable .status-on { color: var(--color-success); }
    #manageProductTable .status-off { color: var(--color-danger); }
</style>
@endpush

@section('content')

<div class="container-fluid">

    <!-- PAGE HEADER -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">Products</h4>

        @perm('products.create')
            <button type="button" class="btn btn-primary" id="addProductBtn">
                <i class="fas fa-plus"></i> Add Product
            </button>
        @endperm
    </div>


    <!-- SUMMARY (reflects the filters) -->
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-3">
        <div class="col">
            <div class="card prd-stat h-100"><div class="card-body">
                <div class="icon i1"><i class="fas fa-boxes-stacked" aria-hidden="true"></i></div>
                <div class="min-w-0"><div class="label">Products</div><h5 class="value" id="sumProducts">0</h5></div>
            </div></div>
        </div>
        <div class="col">
            <div class="card prd-stat h-100"><div class="card-body">
                <div class="icon i2"><i class="fas fa-circle-check" aria-hidden="true"></i></div>
                <div class="min-w-0"><div class="label">In stock</div><h5 class="value" id="sumIn">0</h5></div>
            </div></div>
        </div>
        <div class="col">
            <div class="card prd-stat h-100"><div class="card-body">
                <div class="icon i3"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></div>
                <div class="min-w-0"><div class="label">Low stock</div><h5 class="value" id="sumLow">0</h5></div>
            </div></div>
        </div>
        <div class="col">
            <div class="card prd-stat h-100"><div class="card-body">
                <div class="icon i4"><i class="fas fa-circle-xmark" aria-hidden="true"></i></div>
                <div class="min-w-0"><div class="label">Out of stock</div><h5 class="value" id="sumOut">0</h5></div>
            </div></div>
        </div>
        <div class="col-12 col-md">
            <div class="card prd-stat h-100"><div class="card-body">
                <div class="icon i5"><i class="fas fa-coins" aria-hidden="true"></i></div>
                <div class="min-w-0"><div class="label" title="On-hand quantity × purchase price">Stock value (cost)</div><h5 class="value" id="sumValue">0.00</h5></div>
            </div></div>
        </div>
    </div>


    <!-- PRODUCT TABLE -->
    <div class="card">
        <div class="card-body">

            <!-- FILTERS -->
            <form id="productFilters" class="prd-filters mb-3" autocomplete="off" onsubmit="return false;">

                <div class="f-item f-select">
                    <label for="f_category_id">Category</label>
                    <select id="f_category_id" name="category_id" class="form-select prd-filter-select2">
                        <option value="">All categories</option>
                        @foreach ($filterCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}{{ $category->status === 'Inactive' ? ' (inactive)' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="f-item f-select">
                    <label for="f_brand_id">Brand</label>
                    <select id="f_brand_id" name="brand_id" class="form-select prd-filter-select2">
                        <option value="">All brands</option>
                        @foreach ($filterBrands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}{{ $brand->status === 'Inactive' ? ' (inactive)' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="f-item f-range">
                    <label for="f_purchase_price_min">Purchase price</label>
                    <div class="input-group">
                        <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control prd-price" id="f_purchase_price_min" name="purchase_price_min" placeholder="Min" aria-label="Minimum purchase price">
                        <span class="input-group-text">–</span>
                        <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control prd-price" id="f_purchase_price_max" name="purchase_price_max" placeholder="Max" aria-label="Maximum purchase price">
                    </div>
                </div>

                <div class="f-item f-range">
                    <label for="f_sale_price_min">Sale price</label>
                    <div class="input-group">
                        <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control prd-price" id="f_sale_price_min" name="sale_price_min" placeholder="Min" aria-label="Minimum sale price">
                        <span class="input-group-text">–</span>
                        <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control prd-price" id="f_sale_price_max" name="sale_price_max" placeholder="Max" aria-label="Maximum sale price">
                    </div>
                </div>

                <div class="f-item f-range">
                    <label for="f_old_price_min">Old price</label>
                    <div class="input-group">
                        <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control prd-price" id="f_old_price_min" name="old_price_min" placeholder="Min" aria-label="Minimum old sale price">
                        <span class="input-group-text">–</span>
                        <input type="number" min="0" step="0.01" inputmode="decimal" class="form-control prd-price" id="f_old_price_max" name="old_price_max" placeholder="Max" aria-label="Maximum old sale price">
                    </div>
                </div>

                <div class="f-item f-small">
                    <label for="f_stock_status">Stock</label>
                    <select id="f_stock_status" name="stock_status" class="form-select prd-filter">
                        <option value="">All</option>
                        <option value="in">In stock</option>
                        <option value="low">Low</option>
                        <option value="out">Out</option>
                    </select>
                </div>

                <div class="f-item f-small">
                    <label for="f_status">Status</label>
                    <select id="f_status" name="status" class="form-select prd-filter">
                        <option value="">All</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="f-item f-actions">
                    <button type="button" class="btn btn-outline-dark" id="resetProductFilters">
                        <i class="fas fa-rotate-left" aria-hidden="true"></i> Reset
                    </button>
                </div>

            </form>

            <!-- COUNT + COLUMN PICKER (moved into the DataTables header row on init) -->
            <div class="d-none" id="productToolbarHolder">
                <div class="prd-count" id="productCount" aria-live="polite">Loading…</div>

                <div class="dropdown prd-cols">
                    <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <i class="fas fa-table-columns" aria-hidden="true"></i> Columns
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" id="productColumnMenu"></div>
                </div>
            </div>

            {{-- DataTables puts the table in .prd-scroll.dt-scroll (dom option): horizontal scroll + sticky header. --}}
                <table id="manageProductTable" class="table table-bordered table-hover" width="100%" style="width:100%">
                    <thead>
                        <tr>
                            <th class="text-center">SL</th>
                            <th class="text-center">Image</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Barcode</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Unit</th>
                            <th class="num">Variants</th>
                            <th class="num" title="Purchase price">Purchase</th>
                            <th class="num" title="Current sale price">Sale</th>
                            <th class="num" title="Old sale price">Old price</th>
                            <th class="num">Discount</th>
                            <th class="num" title="(Sale − Purchase) ÷ Sale">Margin %</th>
                            <th class="num" title="Stock on hand in the branches you see">On hand</th>
                            <th class="num">Alert qty</th>
                            <th class="text-center" title="Stock status">Stock</th>
                            <th class="text-center" title="Batch &amp; expiry tracked">Batch</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
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
                                    style="display:none; object-fit:contain; border:1px solid var(--color-border); padding:4px; border-radius:4px;"
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
                                    name="purchase_price" id="purchase_price" value="0" placeholder="0.00" required>
                            </div>
                        </div>

                        <!-- SALE PRICE -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="sale_price" class="form-label">
                                    Current Sale Price <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" class="form-control border-dark"
                                    name="sale_price" id="sale_price" value="0" placeholder="0.00" required>
                            </div>
                        </div>

                        <!-- OLD SALE PRICE -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="old_sale_price" class="form-label">Old Sale Price</label>
                                <input type="number" step="0.01" min="0" class="form-control border-dark"
                                    name="old_sale_price" id="old_sale_price" value="0" placeholder="0.00">
                            </div>
                        </div>

                        <!-- DISCOUNT -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="discount" class="form-label">Discount</label>
                                <input type="number" step="0.01" min="0" class="form-control border-dark"
                                    name="discount" id="discount" value="0" placeholder="0.00">
                            </div>
                        </div>

                        <!-- STOCK ALERT -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="stock_alert" class="form-label">Stock Alert</label>
                                <input type="number" step="0.001" min="0" class="form-control border-dark"
                                    name="stock_alert" id="stock_alert" value="0" placeholder="0">
                            </div>
                        </div>

                        <!-- VAT -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="vat_percent" class="form-label">VAT %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control border-dark"
                                    name="vat_percent" id="vat_percent" placeholder="Default rate" aria-describedby="vatHint">
                                @if ($vatEnabled)
                                    <small id="vatHint" class="text-muted d-block mt-1">Blank = default rate ({{ rtrim(rtrim(number_format($vatRate, 2, '.', ''), '0'), '.') }}%).</small>
                                @else
                                    <small id="vatHint" class="text-warning-emphasis d-block mt-1">
                                        VAT is off for all sales, so this rate is not charged now.
                                        Turn it on in <a href="{{ route('admin.customers.settings') }}">Settings › VAT, Loyalty &amp; Credit</a>.
                                    </small>
                                @endif
                            </div>
                        </div>

                        <!-- BATCH / EXPIRY TRACKING -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="track_batch" class="form-label">Track Batches &amp; Expiry</label>
                                <select class="form-select border-dark" name="track_batch" id="track_batch">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes — batch no. &amp; expiry on purchase</option>
                                </select>
                            </div>
                        </div>

                        <!-- OPENING STOCK -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="opening_stock" class="form-label">Opening Stock</label>
                                <input type="number" step="0.001" min="0" class="form-control border-dark"
                                    name="opening_stock" id="opening_stock" value="0" placeholder="0">
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
                                    autocomplete="off" placeholder="Auto generated" readonly>
                            </div>
                        </div>

                        <!-- BARCODE -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="barcode" class="form-label">
                                    Barcode <small class="text-muted">(Automatic)</small>
                                </label>
                                <input type="text" class="form-control border-dark" name="barcode" id="barcode"
                                    autocomplete="off" placeholder="Auto generated" readonly>
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
        dataType: 'json',
        success: function(response) {
            // alert(JSON.stringify(response));

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
        },
        error: function(xhr, status) {
            // alert(JSON.stringify(xhr));

            if (status === 'abort') {
                return;
            }

            toastr.error('Unable to load attributes.');
        },
        complete: function () {

            // ignore an old (aborted) request finishing after a newer one started
            if ($row.data('attributeRequest') !== request) {
                return;
            }

            $row.removeAttr('data-loading-attributes');
            $row.removeData('attributeRequest');
        }
    });

    $row.data('attributeRequest', request);
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
        // A new variant starts with the product's own prices (edit them per variant if they differ).
        const productPrice = (id) => { const v = parseFloat($('#' + id).val()); return v > 0 ? v : ''; };
        const salePrice    = existingVariant.sale_price ?? productPrice('sale_price');
        const oldSalePrice = existingVariant.old_sale_price ?? productPrice('old_sale_price');
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
                        value="${escapeHtml(attributeName)}" placeholder="Variant name" readonly>

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
        },
        success: function(response) {
            // alert(JSON.stringify(response));

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
        },
        error: function(xhr) {
            // alert(JSON.stringify(xhr));

        toastr.error(xhr.responseJSON?.message || 'Unable to generate barcodes.');
        },
        complete: function () {

        $btn.prop('disabled', false).html(originalHtml);
        }
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
        dataType: 'json',
        success: function(response) {
            // alert(JSON.stringify(response));

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
        $('#track_batch').val(product.track_batch || 'No');
        $('#vat_percent').val(product.vat_percent ?? '');

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
        },
        error: function(xhr, status) {
            // alert(JSON.stringify(xhr));

        if (status === 'abort') {
            return;
        }

        editMode = false;
        editingProductId = null;

        toastr.error(xhr.responseJSON?.message || 'Unable to load product.');
        },
        complete: function () {

        editRequest = null;

        $button.prop('disabled', false);
        }
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
        dataType: 'json',
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

        success: function(response) {
            // alert(JSON.stringify(response));

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

        error: function(xhr) {
            // alert(JSON.stringify(xhr));

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

        success: function(response) {
            // alert(JSON.stringify(response));

            if (response && response.status) {

                toastr.success(response.message || 'Status updated successfully.');

                if (productTable) {
                    productTable.ajax.reload(null, false);
                }

            } else {

                toastr.error(response?.message || 'Unable to update status.');
            }
        },

        error: function(xhr) {
            // alert(JSON.stringify(xhr));

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

        success: function(response) {
            // alert(JSON.stringify(response));

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

        error: function(xhr) {
            // alert(JSON.stringify(xhr));

            toastr.error(xhr.responseJSON?.message || 'Unable to delete product.');
        },

        complete: function () {

            $button.prop('disabled', false).text('Delete');
        }
    });
});


/*
|--------------------------------------------------------------------------
| DATATABLE (server-side: filters, search, order and paging run in
| ProductController::list). One value per cell.
|--------------------------------------------------------------------------
*/

const canEditProduct   = @json(\App\Http\Controllers\Admin\PermissionController::allows('products.edit'));
const canDeleteProduct = @json(\App\Http\Controllers\Admin\PermissionController::allows('products.delete'));
const productImageUrl  = "{{ asset('uploads/products') }}";

// Columns hidden by default (the table is wide); the Columns menu shows them, the choice is remembered per browser.
const productColumnsKey     = 'fpos.products.columns.v1';
const productDefaultHidden  = ['barcode', 'unit_name', 'track_batch'];
const productPriceFilters   = ['purchase_price', 'sale_price', 'old_price'];

function money(value) {

    const number = parseFloat(value);

    if (!isFinite(number)) {
        return '0.00';
    }

    return number.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function qty(value) {

    const number = parseFloat(value);

    if (!isFinite(number)) {
        return '0';
    }

    return number.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

function textCell(value, extraClass) {

    if (value === null || value === undefined || String(value).trim() === '') {
        return '<span class="muted">—</span>';
    }

    return '<span class="prd-clip ' + (extraClass || '') + '" title="' + escapeHtml(value) + '">' + escapeHtml(value) + '</span>';
}

function productFilterParams() {

    const params = {};

    $('#productFilters').find('select[name], input[name]').each(function () {

        const value = ($(this).val() || '').toString().trim();

        if (value !== '') {
            params[this.name] = value;
        }
    });

    return params;
}

// min <= max for each price range; marks the bad pair and returns false.
function productFiltersValid() {

    let valid = true;

    productPriceFilters.forEach(function (key) {

        const $min = $('#f_' + key + '_min');
        const $max = $('#f_' + key + '_max');
        const min  = $min.val() === '' ? null : parseFloat($min.val());
        const max  = $max.val() === '' ? null : parseFloat($max.val());

        const bad = (min !== null && (isNaN(min) || min < 0))
            || (max !== null && (isNaN(max) || max < 0))
            || (min !== null && max !== null && min > max);

        $min.add($max).toggleClass('is-invalid', bad);

        if (bad) {
            valid = false;
        }
    });

    return valid;
}

function reloadProducts() {

    if (productTable && productFiltersValid()) {
        productTable.ajax.reload();
    }
}

function updateProductSummary(json) {

    const summary = (json && json.summary) || {};
    const shown   = parseInt(summary.products || 0, 10);
    const total   = parseInt(summary.total || 0, 10);
    const active  = Object.keys(productFilterParams()).length;

    $('#sumProducts').text(qty(shown));
    $('#sumIn').text(qty(summary.in_stock || 0));
    $('#sumLow').text(qty(summary.low_stock || 0));
    $('#sumOut').text(qty(summary.out_of_stock || 0));
    // Whole units on the tile (it is narrow); the exact figure is in the tooltip.
    const stockValue = parseFloat(summary.stock_value || 0);
    $('#sumValue').text(currency + stockValue.toLocaleString('en-US', { maximumFractionDigits: 0 }))
        .attr('title', currency + money(stockValue));

    let text = '<strong>' + qty(shown) + '</strong> of ' + qty(total) + ' products';

    if (active > 0) {
        text += ' · ' + active + (active === 1 ? ' filter' : ' filters') + ' active';
    }

    $('#productCount').html(text);
    $('#resetProductFilters').toggleClass('btn-outline-dark', active === 0).toggleClass('btn-dark', active > 0);
}

function savedProductColumns() {

    try {
        const saved = JSON.parse(window.localStorage.getItem(productColumnsKey) || 'null');
        return Array.isArray(saved) ? saved : null;
    } catch (e) {
        return null;
    }
}

function buildProductColumnMenu() {

    const $menu = $('#productColumnMenu').empty();

    productTable.columns().every(function (index) {

        const name  = productTable.settings()[0].aoColumns[index].sName;
        const title = $(this.header()).attr('title') || $(this.header()).text().trim();

        if (!name || name === 'actions') {
            return;
        }

        const id = 'prdCol_' + index;

        $menu.append(
            '<label class="dropdown-item" for="' + id + '">' +
                '<input type="checkbox" class="form-check-input prd-col-toggle" id="' + id + '" data-column="' + index + '"' + (this.visible() ? ' checked' : '') + '>' +
                '<span>' + escapeHtml(title || name) + '</span>' +
            '</label>'
        );
    });
}

$(document).on('change', '.prd-col-toggle', function () {

    if (!productTable) {
        return;
    }

    productTable.column($(this).data('column')).visible(this.checked, false);
    productTable.columns.adjust();

    const hidden = [];

    productTable.settings()[0].aoColumns.forEach(function (column, index) {
        if (column.sName && !productTable.column(index).visible()) {
            hidden.push(column.sName);
        }
    });

    try {
        window.localStorage.setItem(productColumnsKey, JSON.stringify(hidden));
    } catch (e) {
        // Storage unavailable (private window): the choice lasts for this page only.
    }
});

let productFilterTimer = null;

$(document).on('input', '#productFilters .prd-price', function () {

    clearTimeout(productFilterTimer);
    productFilterTimer = setTimeout(reloadProducts, 450);
});

$(document).on('change', '#productFilters select', function () {

    clearTimeout(productFilterTimer);
    reloadProducts();
});

$('#resetProductFilters').on('click', function () {

    clearTimeout(productFilterTimer);

    $('#productFilters').find('input').val('').removeClass('is-invalid');
    $('#productFilters').find('select').val('');
    $('#productFilters').find('select.prd-filter-select2').trigger('change.select2');

    reloadProducts();
});

$(document).ready(function () {

    initializeSelect2($('#productModal'));

    $('#productFilters select.prd-filter-select2').each(function () {

        $(this).select2({ width: '100%' });
    });

    if ($.fn.DataTable && $('#manageProductTable').length) {

        const hiddenNames = savedProductColumns() || productDefaultHidden;

        const columns = [

            // SL
            {
                data: null,
                name: '',
                className: 'text-center',
                orderable: false,
                searchable: false,
                render: function (data, type, row, meta) {
                    return meta.settings._iDisplayStart + meta.row + 1;
                }
            },

            // IMAGE
            {
                data: 'image',
                name: 'image',
                className: 'text-center',
                orderable: false,
                render: function (data, type, row) {

                    if (data) {

                        return '<img src="' + productImageUrl + '/' + encodeURIComponent(data) + '"' +
                            ' class="product-table-image" alt="' + escapeHtml(row.name || '') + '" width="40" height="40" loading="lazy"' +
                            ' onerror="this.onerror=null; this.outerHTML=\'<span class=&quot;prd-noimg&quot;><i class=&quot;fas fa-image&quot;></i></span>\';">';
                    }

                    return '<span class="prd-noimg" aria-label="No image"><i class="fas fa-image" aria-hidden="true"></i></span>';
                }
            },

            // PRODUCT NAME
            {
                data: 'name',
                name: 'name',
                render: function (data, type, row) {

                    const name = escapeHtml(data || '');

                    if (canEditProduct) {
                        return '<button type="button" class="prd-name prd-name-btn editProductBtn" data-id="' + escapeHtml(row.id) + '" title="' + name + '">' + name + '</button>';
                    }

                    return '<span class="prd-name" title="' + name + '">' + name + '</span>';
                }
            },

            { data: 'sku',           name: 'sku',           render: function (data) { return textCell(data, 'prd-code'); } },
            { data: 'barcode',       name: 'barcode',       render: function (data) { return textCell(data, 'prd-code'); } },
            { data: 'category_name', name: 'category_name', render: function (data) { return textCell(data); } },
            { data: 'brand_name',    name: 'brand_name',    render: function (data) { return textCell(data); } },
            { data: 'unit_name',     name: 'unit_name',     render: function (data) { return textCell(data); } },

            // VARIANTS (count)
            {
                data: 'variant_count',
                name: 'variant_count',
                className: 'num',
                render: function (data) {
                    const count = parseInt(data || 0, 10);
                    return count > 0 ? String(count) : '<span class="muted" title="No variants">0</span>';
                }
            },

            { data: 'purchase_price', name: 'purchase_price', className: 'num', render: function (data) { return money(data); } },
            { data: 'sale_price',     name: 'sale_price',     className: 'num', render: function (data) { return money(data); } },

            {
                data: 'old_sale_price',
                name: 'old_sale_price',
                className: 'num',
                render: function (data) {
                    return parseFloat(data || 0) > 0 ? money(data) : '<span class="muted">—</span>';
                }
            },

            {
                data: 'discount',
                name: 'discount',
                className: 'num',
                render: function (data) {
                    return parseFloat(data || 0) > 0 ? money(data) : '<span class="muted">—</span>';
                }
            },

            // MARGIN % = (sale − purchase) / sale
            {
                data: 'margin',
                name: 'margin',
                className: 'num',
                render: function (data) {

                    if (data === null || data === undefined || data === '') {
                        return '<span class="muted" title="No sale price">—</span>';
                    }

                    const margin = parseFloat(data);

                    return '<span class="' + (margin < 0 ? 'neg' : '') + '">' + margin.toFixed(2) + '%</span>';
                }
            },

            // STOCK ON HAND (visible branches)
            {
                data: 'current_stock',
                name: 'current_stock',
                className: 'num',
                render: function (data) {
                    const current = parseFloat(data || 0);
                    return '<span class="' + (current < 0 ? 'neg' : '') + '">' + qty(current) + '</span>';
                }
            },

            { data: 'stock_alert', name: 'stock_alert', className: 'num', render: function (data) { return qty(data); } },

            // STOCK STATUS
            {
                data: 'stock_state',
                name: 'stock_state',
                className: 'text-center',
                render: function (data) {

                    if (data === 'out') {
                        return '<span class="badge bg-danger-subtle text-danger-emphasis">Out</span>';
                    }

                    if (data === 'low') {
                        return '<span class="badge bg-warning-subtle text-warning-emphasis">Low</span>';
                    }

                    return '<span class="badge bg-success-subtle text-success-emphasis">In stock</span>';
                }
            },

            // BATCH TRACKED
            {
                data: 'track_batch',
                name: 'track_batch',
                className: 'text-center',
                render: function (data) {
                    return data === 'Yes' ? 'Yes' : '<span class="muted">No</span>';
                }
            },

            // STATUS (click toggles when the user may edit)
            {
                data: 'status',
                name: 'status',
                className: 'text-center',
                render: function (data, type, row) {

                    const active = data === 'Active';
                    const icon   = '<i class="fas ' + (active ? 'fa-check-circle status-on' : 'fa-times-circle status-off') + ' status-icon" aria-hidden="true"></i>';
                    const label  = active ? 'Active' : 'Inactive';

                    if (canEditProduct) {
                        return '<button type="button" class="btn btn-sm btn-link p-0 toggleStatusBtn" data-id="' + escapeHtml(row.id) + '"' +
                            ' title="' + label + ' (click to ' + (active ? 'deactivate' : 'activate') + ')" aria-label="' + label + '">' + icon + '</button>';
                    }

                    return '<span title="' + label + '" aria-label="' + label + '">' + icon + '</span>';
                }
            },

            // ACTIONS
            {
                data: 'id',
                name: 'actions',
                className: 'text-center',
                orderable: false,
                searchable: false,
                render: function (data) {

                    const items = [];

                    if (canEditProduct) {
                        items.push({ label: 'Edit', icon: 'fas fa-edit', class: 'editProductBtn', attrs: { 'data-id': data } });
                    }

                    if (canDeleteProduct) {
                        items.push({ label: 'Delete', icon: 'fas fa-trash', class: 'text-danger deleteProductBtn', attrs: { 'data-id': data } });
                    }

                    return items.length ? actionDropdown(items) : '<span class="muted">—</span>';
                }
            }
        ];

        columns.forEach(function (column) {
            column.visible = !column.name || hiddenNames.indexOf(column.name) === -1;
        });

        productTable = $('#manageProductTable').DataTable({

            serverSide: true,
            processing: true,
            searchDelay: 400,
            autoWidth: false,
            pageLength: 25,

            lengthMenu: [
                [10, 25, 50, 100, 500],
                [10, 25, 50, 100, 500]
            ],

            order: [],

            dom: '<"prd-dt-head"l<"prd-dt-count">f<"prd-dt-cols">>r<"prd-scroll dt-scroll"t><"prd-dt-foot"ip>',

            ajax: {

                url: "{{ route('products.list') }}",
                type: 'GET',
                dataType: 'json',

                data: function (d) {
                    return $.extend(d, productFilterParams());
                },

                dataSrc: function (json) {

                    if (!json || json.status === false) {

                        toastr.error(json?.message || 'Unable to load products.');

                        return [];
                    }

                    updateProductSummary(json);

                    return json.data || [];
                },

                error: function (xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to load products.');
                    $('#manageProductTable_processing').hide();
                }
            },

            columns: columns,

            language: {
                processing: 'Loading…',
                emptyTable: 'No products found.',
                zeroRecords: 'No products match these filters.',
                info: 'Showing _START_–_END_ of _TOTAL_',
                infoFiltered: ''
            },

            initComplete: function () {
                $('.prd-dt-count').append($('#productCount'));
                $('.prd-dt-cols').append($('#productToolbarHolder .prd-cols'));
                buildProductColumnMenu();
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
        dataType: 'json',

        data: {
            name: name,
            category_id: $('#category_id').val(),
            brand_id: $('#brand_id').val()
        },

        success: function(response) {
            // alert(JSON.stringify(response));

            if (response.status) {
                $('#sku').val(response.sku);
                $('#barcode').val(response.barcode);
            }
        },
        error: function(xhr) {
            // alert(JSON.stringify(xhr));
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
        }
    });
});

</script>

@endpush
