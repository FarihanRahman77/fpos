@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} POS
@endsection

@push('styles')
    <style>
        .pos-page {
            --pos-radius: 14px;
            --pos-panel-height: calc(100vh - 150px);
        }

        .pos-panel {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: var(--pos-radius);
            box-shadow: var(--admin-shadow);
            height: var(--pos-panel-height);
            min-height: 580px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .pos-panel-head {
            padding: 14px 16px 10px;
            border-bottom: 1px solid var(--admin-border);
        }

        .pos-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
            color: var(--admin-text);
        }

        .pos-subtitle {
            font-size: .78rem;
            color: var(--admin-muted);
        }

        /* ---------- Panel header ---------- */
        .pos-head-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
            color: #fff;
            font-size: 1.05rem;
            box-shadow: 0 4px 12px rgba(145, 97, 0, .25);
        }

        /* ---------- Search & filters ---------- */
        .pos-search {
            position: relative;
            flex: 1;
        }

        .pos-search>i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--admin-muted);
        }

        .pos-search input {
            padding-left: 40px;
            padding-right: 44px;
            height: 42px;
            border-radius: 10px;
        }

        .pos-search kbd {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: .7rem;
            background: var(--admin-surface-soft);
            color: var(--admin-muted);
            border: 1px solid var(--admin-border);
        }

        .pos-filter-toggle {
            position: relative;
            height: 42px;
            border-radius: 10px;
            border: 1px solid var(--admin-border);
            background: var(--admin-surface);
            color: var(--admin-text);
            padding: 0 14px;
            font-size: .85rem;
            font-weight: 600;
            white-space: nowrap;
            transition: all .15s ease;
        }

        .pos-filter-toggle:hover,
        .pos-filter-toggle[aria-expanded="true"] {
            border-color: var(--admin-primary);
            color: var(--admin-primary);
        }

        .pos-filter-count {
            position: absolute;
            top: -7px;
            right: -7px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 999px;
            background: var(--admin-danger);
            color: #fff;
            font-size: .68rem;
            line-height: 20px;
            text-align: center;
        }

        .pos-filters {
            margin-top: 10px;
            padding: 12px;
            border: 1px dashed var(--admin-border);
            border-radius: 12px;
            background: var(--admin-surface-soft);
        }

        .pos-filters .form-select,
        .pos-filters .form-control {
            height: 34px;
            font-size: .8rem;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .pos-stock-toggle {
            display: flex;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            overflow: hidden;
            height: 34px;
        }

        .pos-stock-toggle button {
            flex: 1;
            border: 0;
            background: var(--admin-surface);
            color: var(--admin-muted);
            font-size: .75rem;
            font-weight: 600;
            transition: all .15s ease;
        }

        .pos-stock-toggle button+button {
            border-left: 1px solid var(--admin-border);
        }

        .pos-stock-toggle button.active {
            background: var(--admin-primary);
            color: #fff;
        }

        .pos-categories {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 10px 0 2px;
            scrollbar-width: thin;
        }

        .pos-cat {
            flex: 0 0 auto;
            border: 1px solid var(--admin-border);
            background: var(--admin-surface-soft);
            color: var(--admin-text);
            border-radius: 999px;
            padding: 5px 14px;
            font-size: .8rem;
            font-weight: 500;
            transition: all .15s ease;
        }

        .pos-cat:hover {
            border-color: var(--admin-primary);
        }

        .pos-cat.active {
            background: var(--admin-primary);
            border-color: var(--admin-primary);
            color: #fff;
            box-shadow: 0 3px 10px rgba(145, 97, 0, .25);
        }

        .pos-result-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .75rem;
            color: var(--admin-muted);
            margin-bottom: 10px;
        }

        .pos-result-bar a {
            color: var(--admin-primary);
            text-decoration: none;
            font-weight: 600;
        }

        /* ---------- Product grid (5 per row on a normal half-screen panel) ---------- */
        .pos-grid-wrap {
            flex: 1;
            overflow-y: auto;
            padding: 12px 14px;
            background: var(--admin-surface-soft);
            container-type: inline-size;
        }

        .pos-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        @container (min-width: 240px) {
            .pos-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @container (min-width: 360px) {
            .pos-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @container (min-width: 460px) {
            .pos-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .pos-product {
            position: relative;
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            text-align: left;
            padding: 0;
            color: var(--admin-text);
            box-shadow: var(--admin-shadow-sm);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .pos-product:hover,
        .pos-product:focus-visible {
            transform: translateY(-3px);
            box-shadow: var(--admin-shadow-lg);
            border-color: var(--admin-primary);
            outline: none;
        }

        .pos-product.in-cart {
            border-color: var(--admin-primary);
            box-shadow: var(--admin-ring);
        }

        .pos-product.out-of-stock {
            cursor: not-allowed;
        }

        .pos-product.out-of-stock .pos-product-img img {
            filter: grayscale(1);
            opacity: .5;
        }

        .pos-product.out-of-stock:hover {
            transform: none;
            box-shadow: var(--admin-shadow-sm);
            border-color: var(--admin-border);
        }

        .pos-product-img {
            position: relative;
            aspect-ratio: 1 / 1;
            width: 100%;
            background: radial-gradient(circle at 50% 35%, var(--admin-surface) 0%, var(--admin-surface-soft) 75%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .pos-product-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 6px;
            transition: transform .25s ease;
        }

        .pos-product:hover .pos-product-img img {
            transform: scale(1.06);
        }

        .pos-product-add {
            position: absolute;
            right: 6px;
            bottom: 6px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--admin-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            box-shadow: 0 3px 8px rgba(0, 0, 0, .2);
            opacity: 0;
            transform: scale(.7);
            transition: all .18s ease;
        }

        .pos-product:hover .pos-product-add {
            opacity: 1;
            transform: scale(1);
        }

        .pos-product.out-of-stock .pos-product-add {
            display: none;
        }

        .pos-product-initial {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--admin-primary);
            opacity: .6;
        }

        .pos-product-body {
            padding: 6px 8px 8px;
            border-top: 1px solid var(--admin-border);
        }

        .pos-product-brand {
            font-size: .62rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--admin-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pos-product-name {
            font-size: .74rem;
            font-weight: 600;
            line-height: 1.25;
            height: 2.5em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .pos-product-price {
            display: block;
            margin-top: 4px;
            font-weight: 700;
            color: var(--admin-primary);
            font-size: .8rem;
        }

        .pos-product-stock {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: .65rem;
            color: var(--admin-muted);
        }

        .pos-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--admin-success);
            flex: 0 0 6px;
        }

        .pos-dot.out {
            background: var(--admin-danger);
        }

        .pos-badge {
            position: absolute;
            top: 6px;
            z-index: 1;
            font-size: .6rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 999px;
            backdrop-filter: blur(4px);
        }

        .pos-badge-variant {
            left: 6px;
            background: rgba(23, 27, 34, .72);
            color: #fff;
        }

        .pos-badge-out {
            left: 6px;
            background: var(--admin-danger);
            color: #fff;
        }

        .pos-badge-qty {
            right: 6px;
            background: var(--admin-primary);
            color: #fff;
            min-width: 22px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .2);
        }

        .pos-empty {
            text-align: center;
            color: var(--admin-muted);
            padding: 60px 10px;
        }

        .pos-empty i {
            font-size: 2.4rem;
            display: block;
            margin-bottom: 8px;
            opacity: .5;
        }

        /* ---------- Cart ---------- */
        .pos-customer {
            padding: 10px 16px;
            border-top: 1px solid var(--admin-border);
            background: var(--admin-surface-soft);
        }

        .pos-customer .form-control,
        .pos-summary .form-control,
        .pos-summary .form-select {
            height: 36px;
            font-size: .85rem;
        }

        .pos-due-chip {
            font-size: .75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(180, 35, 24, .1);
            color: var(--admin-danger);
        }

        .pos-due-chip.zero {
            background: rgba(22, 115, 75, .1);
            color: var(--admin-success);
        }

        .pos-cart-items {
            flex: 1;
            min-height: 120px;
            overflow-y: auto;
            padding: 2px 16px;
        }

        .pos-cart-items .pos-empty {
            padding: 24px 10px;
        }

        .pos-cart-item {
            display: flex;
            gap: 8px;
            padding: 7px 0;
            border-bottom: 1px dashed var(--admin-border);
            animation: posFadeIn .2s ease;
        }

        .pos-cart-item:last-child {
            border-bottom: 0;
        }

        @keyframes posFadeIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .pos-cart-thumb {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            border-radius: 10px;
            overflow: hidden;
            background: var(--admin-surface-soft);
            border: 1px solid var(--admin-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--admin-primary);
        }

        .pos-cart-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pos-cart-info {
            flex: 1;
            min-width: 0;
        }

        .pos-cart-name {
            font-weight: 600;
            font-size: .85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pos-cart-variant {
            font-size: .7rem;
            font-weight: 600;
            background: rgba(145, 97, 0, .12);
            color: var(--admin-primary);
            border-radius: 6px;
            padding: 1px 7px;
            margin-left: 4px;
        }

        .pos-cart-controls {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        .pos-cart-controls .cartVariant {
            width: auto;
            max-width: 110px;
        }

        .pos-cart-controls .form-control,
        .pos-cart-controls .form-select {
            height: 30px;
            font-size: .78rem;
            padding: 2px 6px;
        }

        .pos-qty {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            overflow: hidden;
        }

        .pos-qty button {
            width: 28px;
            height: 28px;
            border: 0;
            background: var(--admin-surface-soft);
            color: var(--admin-text);
            font-weight: 700;
        }

        .pos-qty button:hover {
            background: var(--admin-primary);
            color: #fff;
        }

        .pos-qty input {
            width: 46px;
            height: 28px;
            border: 0;
            text-align: center;
            font-size: .8rem;
            background: transparent;
            color: var(--admin-text);
            -moz-appearance: textfield;
        }

        .pos-qty input::-webkit-outer-spin-button,
        .pos-qty input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .pos-cart-total {
            text-align: right;
            min-width: 78px;
        }

        .pos-cart-total strong {
            display: block;
            font-size: .9rem;
        }

        .pos-cart-total small {
            color: var(--admin-muted);
            font-size: .7rem;
        }

        .pos-remove {
            border: 0;
            background: transparent;
            color: var(--admin-muted);
            padding: 0;
            margin-top: 4px;
            font-size: .85rem;
        }

        .pos-remove:hover {
            color: var(--admin-danger);
        }

        .pos-summary {
            border-top: 1px solid var(--admin-border);
            padding: 12px 16px 14px;
            background: var(--admin-surface-soft);
        }

        .pos-sum-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .85rem;
            margin-bottom: 4px;
        }

        .pos-sum-row span:first-child {
            color: var(--admin-muted);
        }

        .pos-grand {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            margin: 8px 0 10px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
            color: #fff;
        }

        .pos-grand span {
            font-size: .85rem;
            opacity: .9;
        }

        .pos-grand strong {
            font-size: 1.45rem;
            letter-spacing: .3px;
        }

        .pos-label {
            font-size: .72rem;
            font-weight: 600;
            color: var(--admin-muted);
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .pos-checkout {
            height: 48px;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 12px;
        }

        /* ---------- Variant modal ---------- */
        .pos-variant-modal .modal-content {
            border-radius: 16px;
            border: 0;
            background: var(--admin-surface);
            color: var(--admin-text);
        }

        .pos-variant-hero {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .pos-variant-hero .pos-cart-thumb {
            width: 72px;
            height: 72px;
            flex-basis: 72px;
            font-size: 1.5rem;
        }

        .pos-variant-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px;
        }

        .pos-variant-option {
            border: 2px solid var(--admin-border);
            border-radius: 12px;
            padding: 10px 12px;
            background: var(--admin-surface);
            color: var(--admin-text);
            text-align: left;
            transition: all .15s ease;
        }

        .pos-variant-option:hover:not(:disabled) {
            border-color: var(--admin-primary);
        }

        .pos-variant-option.active {
            border-color: var(--admin-primary);
            background: rgba(145, 97, 0, .08);
            box-shadow: var(--admin-ring);
        }

        .pos-variant-option:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .pos-variant-option .v-name {
            font-weight: 600;
            font-size: .85rem;
        }

        .pos-variant-option .v-price {
            color: var(--admin-primary);
            font-weight: 700;
            font-size: .85rem;
        }

        .pos-variant-option .v-stock {
            font-size: .7rem;
            color: var(--admin-muted);
        }

        @media (max-width: 767.98px) {
            .pos-panel {
                height: auto;
                min-height: 0;
            }

            .pos-grid-wrap {
                max-height: 60vh;
            }

            .pos-cart-items {
                max-height: 50vh;
            }
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid pos-page">
        <form id="posForm" autocomplete="off">
            <input type="hidden" name="sale_type" value="Walk-In">

            <div class="row g-3">

                {{-- ================= Products ================= --}}
                <div class="col-md-6">
                    <div class="pos-panel">
                        <div class="pos-panel-head">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pos-head-icon"><i class="bi bi-grid-3x3-gap-fill"></i></span>
                                    <div>
                                        <h5 class="pos-title">Products</h5>
                                        <div class="pos-subtitle">{{ count($products) }} products in catalogue</div>
                                    </div>
                                </div>
                                <a href="{{ route('sales.index', ['type' => 'Walk-In']) }}"
                                    class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-list-ul me-1"></i> Sales List
                                </a>
                            </div>

                            <div class="d-flex gap-2">
                                <div class="pos-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="posSearch" class="form-control"
                                        placeholder="Search by name, SKU or scan barcode…">
                                    <kbd>F2</kbd>
                                </div>
                                <button type="button" class="pos-filter-toggle" data-bs-toggle="collapse"
                                    data-bs-target="#posFilters" aria-expanded="false" aria-controls="posFilters">
                                    <i class="bi bi-sliders me-1"></i> Filters
                                    <span class="pos-filter-count d-none" id="filterCount">0</span>
                                </button>
                            </div>

                            <div class="collapse" id="posFilters">
                                <div class="pos-filters">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="pos-label">Brand</div>
                                            <select id="filterBrand" class="form-select">
                                                <option value="">All Brands</option>
                                                @foreach ($brands as $brand)
                                                    <option value="{{ $brand['id'] }}">{{ $brand['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <div class="pos-label">Category</div>
                                            <select id="filterCategory" class="form-select">
                                                <option value="">All Categories</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <div class="pos-label">Sale Price</div>
                                            <div class="input-group">
                                                <input type="number" id="filterPriceMin" class="form-control"
                                                    min="0" step="0.01" placeholder="Min">
                                                <input type="number" id="filterPriceMax" class="form-control"
                                                    min="0" step="0.01" placeholder="Max">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="pos-label">Stock</div>
                                            <div class="pos-stock-toggle" id="filterStock">
                                                <button type="button" class="active" data-stock="">All</button>
                                                <button type="button" data-stock="in">In Stock</button>
                                                <button type="button" data-stock="out">Out</button>
                                            </div>
                                        </div>
                                        <div class="col-8">
                                            <div class="pos-label">Sort By</div>
                                            <select id="sortBy" class="form-select">
                                                <option value="name">Name (A–Z)</option>
                                                <option value="price_asc">Price: Low to High</option>
                                                <option value="price_desc">Price: High to Low</option>
                                                <option value="stock_desc">Stock: High to Low</option>
                                            </select>
                                        </div>
                                        <div class="col-4 d-flex align-items-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger w-100"
                                                id="resetFilters" style="height: 34px;">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pos-categories" id="posCategories">
                                <button type="button" class="pos-cat active" data-category="">
                                    <i class="bi bi-grid me-1"></i>All
                                </button>
                                @foreach ($categories as $category)
                                    <button type="button" class="pos-cat" data-category="{{ $category['id'] }}">
                                        {{ $category['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="pos-grid-wrap">
                            <div class="pos-result-bar">
                                <span>Showing <strong id="productCount">0</strong> of {{ count($products) }}</span>
                                <a href="#" id="clearFiltersLink" class="d-none">Clear filters</a>
                            </div>
                            <div class="pos-grid" id="posGrid"></div>
                        </div>
                    </div>
                </div>

                {{-- ================= Cart ================= --}}
                <div class="col-md-6">
                    <div class="pos-panel">
                        <div class="pos-panel-head d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="pos-title"><i class="bi bi-cart3 me-1"></i> Current Sale</h5>
                                <div class="pos-subtitle"><span id="cartCount">0</span> items in cart</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <input type="date" name="sale_date" id="sale_date"
                                    class="form-control form-control-sm" value="{{ date('Y-m-d') }}" style="width: 150px;">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="clearCartBtn"
                                    title="Clear cart">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Items --}}
                        <div class="pos-cart-items" id="cartItems"></div>

                        {{-- Customer --}}
                        <div class="pos-customer">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="pos-label">Phone <span class="text-danger">*</span></div>
                                    <input type="text" id="customer_phone" name="customer_phone" class="form-control"
                                        placeholder="Customer phone">
                                </div>
                                <div class="col-6">
                                    <div class="pos-label">Name</div>
                                    <input type="text" id="customer_name" name="customer_name" class="form-control"
                                        placeholder="Walk-In Customer">
                                </div>
                                <div class="col-8">
                                    <input type="text" id="customer_address" name="customer_address" class="form-control"
                                        placeholder="Address (optional)">
                                </div>
                                <div class="col-4 d-flex align-items-center justify-content-end">
                                    <span class="pos-due-chip zero" id="customerDueChip">Due: 0.00</span>
                                </div>
                            </div>
                        </div>

                        {{-- Summary --}}
                        <div class="pos-summary">
                            <div class="pos-sum-row">
                                <span>Subtotal</span>
                                <strong id="subtotalText">0.00</strong>
                            </div>
                            <div class="pos-sum-row">
                                <span>Discount</span>
                                <div class="d-flex gap-1 align-items-center">
                                    <select name="discount_type" id="discount_type" class="form-select"
                                        style="width: 70px; height: 30px; font-size: .8rem; padding: 2px 6px;">
                                        <option value="Amount">৳</option>
                                        <option value="Percentage">%</option>
                                    </select>
                                    <input type="number" step="0.01" min="0" name="discount_value" id="discount_value"
                                        class="form-control text-end" value="0"
                                        style="width: 90px; height: 30px; font-size: .8rem;">
                                    <strong class="text-danger ms-2" style="min-width: 70px; text-align: right;">
                                        -<span id="discountText">0.00</span>
                                    </strong>
                                </div>
                            </div>

                            <div class="pos-grand">
                                <span>Grand Total</span>
                                <strong id="grandTotalText">0.00</strong>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="pos-label">Payment Method</div>
                                    <select id="payment_method" name="payment_method" class="form-select">
                                        <option value="">Select Method</option>
                                        @foreach ($childCoas as $childCoa)
                                            <option value="{{ $childCoa->id }}">{{ $childCoa->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <div class="pos-label">Payment Account</div>
                                    <select id="payment_method_account" name="payment_method_account"
                                        class="form-select">
                                        <option value="">Select Account</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <div class="pos-label d-flex justify-content-between">
                                        <span>Paid Amount</span>
                                        <a href="#" id="payFullBtn" class="text-decoration-none">Full</a>
                                    </div>
                                    <input type="number" step="0.01" min="0" id="paid_amount"
                                        class="form-control text-end fw-bold" value="0">
                                </div>
                                <div class="col-6">
                                    <div class="pos-label" id="balanceLabel">Due</div>
                                    <input type="text" id="balance_amount" class="form-control text-end fw-bold"
                                        value="0.00" readonly>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="remarks" id="remarks" class="form-control"
                                        placeholder="Remarks (optional)">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100 pos-checkout" id="checkoutBtn">
                                        <i class="bi bi-check2-circle me-1"></i>
                                        Complete Sale
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    {{-- ================= Variant Modal ================= --}}
    <div class="modal fade pos-variant-modal" id="variantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <div class="pos-variant-hero">
                        <div class="pos-cart-thumb" id="vmThumb"></div>
                        <div>
                            <h5 class="mb-1 fw-bold" id="vmName"></h5>
                            <div class="pos-subtitle" id="vmSku"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="pos-label mb-2">Choose Variant</div>
                    <div class="pos-variant-list" id="vmVariants"></div>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="pos-qty">
                            <button type="button" id="vmMinus">−</button>
                            <input type="number" id="vmQty" value="1" min="1" step="1">
                            <button type="button" id="vmPlus">+</button>
                        </div>
                        <div>
                            <div class="pos-subtitle">Price</div>
                            <strong class="text-primary" id="vmPrice">0.00</strong>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary px-4" id="vmAddBtn">
                        <i class="bi bi-cart-plus me-1"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const POS_PRODUCTS = @json($products);
        const VARIANT_BARCODES = @json($variantBarcodes);
        const VARIANTS_URL = "{{ url('/admin/sales/products/variants') }}/";

        let cart = [];
        let activeCategory = '';
        let modalState = {
            product: null,
            variants: [],
            selected: null
        };
        let variantModal;

        $(document).ready(function() {
            variantModal = new bootstrap.Modal(document.getElementById('variantModal'));

            renderProducts();
            renderCart();

            /* ---------- Product filtering ---------- */
            // An exact barcode match is added straight to the cart (scanner or typed)
            $('#posSearch').on('input', function() {
                if (!handleScan($.trim($(this).val()), false)) {
                    renderProducts();
                }
            });

            $('#posSearch').on('keydown', function(e) {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                if (!handleScan($.trim($(this).val()), true) && $.trim($(this).val())) {
                    toastr.warning('No product found for this code.');
                }
            });

            /* ---------- Filters ---------- */
            $(document).on('click', '.pos-cat', function() {
                setCategory(String($(this).data('category') || ''));
            });

            $('#filterCategory').on('change', function() {
                setCategory($(this).val());
            });

            $('#filterBrand, #sortBy').on('change', renderProducts);
            $('#filterPriceMin, #filterPriceMax').on('input', renderProducts);

            $('#filterStock button').on('click', function() {
                $('#filterStock button').removeClass('active');
                $(this).addClass('active');
                renderProducts();
            });

            $('#resetFilters, #clearFiltersLink').on('click', function(e) {
                e.preventDefault();
                $('#filterBrand, #filterPriceMin, #filterPriceMax').val('');
                $('#sortBy').val('name');
                $('#filterStock button').removeClass('active').first().addClass('active');
                $('#posSearch').val('');
                setCategory('');
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'F2') {
                    e.preventDefault();
                    $('#posSearch').focus().select();
                }
            });

            /* ---------- Product click ---------- */
            $(document).on('click', '.pos-product', function() {
                let product = findProduct($(this).data('id'));
                if (!product) {
                    return;
                }
                if (product.variant_count > 0) {
                    openVariantModal(product);
                    return;
                }
                if (product.current_stock <= 0) {
                    toastr.warning('This product is out of stock.');
                    return;
                }
                addToCart(product, null, 1);
            });

            /* ---------- Variant modal ---------- */
            $(document).on('click', '.pos-variant-option', function() {
                let id = String($(this).data('id'));
                modalState.selected = modalState.variants.find(v => String(v.id) === id) || null;
                renderVariantOptions();
            });

            $('#vmMinus').on('click', function() {
                $('#vmQty').val(Math.max(1, (parseFloat($('#vmQty').val()) || 1) - 1));
            });

            $('#vmPlus').on('click', function() {
                $('#vmQty').val((parseFloat($('#vmQty').val()) || 0) + 1);
            });

            $('#vmAddBtn').on('click', function() {
                if (!modalState.selected) {
                    toastr.warning('Please choose a variant.');
                    return;
                }
                let qty = parseFloat($('#vmQty').val()) || 1;
                if (addToCart(modalState.product, modalState.selected, qty, modalState.variants)) {
                    variantModal.hide();
                }
            });

            /* ---------- Cart interactions ---------- */
            $(document).on('click', '.cartMinus, .cartPlus', function() {
                let item = cart[$(this).data('index')];
                if (!item) {
                    return;
                }
                let qty = item.qty + ($(this).hasClass('cartPlus') ? 1 : -1);
                if (qty <= 0) {
                    cart.splice($(this).data('index'), 1);
                } else {
                    setItemQty(item, qty);
                }
                renderCart();
            });

            $(document).on('change', '.cartQty', function() {
                let item = cart[$(this).data('index')];
                if (!item) {
                    return;
                }
                setItemQty(item, parseFloat($(this).val()) || 0);
                renderCart();
            });

            $(document).on('change', '.cartPrice, .cartDiscType, .cartDiscValue', function() {
                let item = cart[$(this).data('index')];
                if (!item) {
                    return;
                }
                if ($(this).hasClass('cartPrice')) {
                    item.sale_price = Math.max(0, parseFloat($(this).val()) || 0);
                }
                if ($(this).hasClass('cartDiscType')) {
                    item.discount_type = $(this).val();
                }
                if ($(this).hasClass('cartDiscValue')) {
                    item.discount_value = Math.max(0, parseFloat($(this).val()) || 0);
                }
                renderCart();
            });

            $(document).on('change', '.cartVariant', function() {
                let item = cart[$(this).data('index')];
                let product = item ? findProduct(item.product_id) : null;
                let variant = item ? item.variants.find(v => String(v.id) === String($(this).val())) : null;
                if (!item || !product || !variant) {
                    return;
                }
                if (findCartItem(item.product_id, variant.id)) {
                    toastr.warning('This variant is already in the cart.');
                    renderCart();
                    return;
                }
                applyVariant(item, product, variant);
                if (item.qty > item.stock) {
                    toastr.warning('Quantity adjusted to available stock.');
                    item.qty = item.stock;
                }
                renderCart();
            });

            $(document).on('click', '.cartRemove', function() {
                cart.splice($(this).data('index'), 1);
                renderCart();
            });

            $('#clearCartBtn').on('click', function() {
                if (cart.length === 0) {
                    return;
                }
                Swal.fire({
                    title: 'Clear cart?',
                    text: 'All items will be removed.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, clear'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        cart = [];
                        renderCart();
                    }
                });
            });

            /* ---------- Totals ---------- */
            $('#discount_value, #paid_amount').on('input', calculateTotals);
            $('#discount_type').on('change', calculateTotals);

            $('#payFullBtn').on('click', function(e) {
                e.preventDefault();
                $('#paid_amount').val(calculateTotals().grandTotal.toFixed(2));
                calculateTotals();
            });

            /* ---------- Customer lookup ---------- */
            let phoneTimer;
            $('#customer_phone').on('input', function() {
                clearTimeout(phoneTimer);
                let phone = $.trim($(this).val());
                if (phone.length < 6) {
                    setCustomerDue(0);
                    return;
                }
                phoneTimer = setTimeout(function() {
                    $.ajax({
                        url: "{{ url('/admin/sales/customer-by-phone') }}/" + encodeURIComponent(phone),
                        type: 'GET',
                        success: function(response) {
                            if (response.status && response.customer) {
                                $('#customer_name').val(response.customer.name || '');
                                $('#customer_address').val(response.customer.address || '');
                                setCustomerDue(response.current_due);
                            } else {
                                setCustomerDue(0);
                            }
                        }
                    });
                }, 350);
            });

            /* ---------- Payment account ---------- */
            $('#payment_method').on('change', function() {
                $.ajax({
                    url: "{{ route('admin.getCoaChilds') }}",
                    type: 'GET',
                    data: {
                        parent_id: $(this).val()
                    },
                    success: function(response) {
                        $('#payment_method_account').html(response);
                    }
                });
            });

            /* ---------- Checkout ---------- */
            // Enter inside a field applies the value instead of submitting the sale
            $('#posForm').on('keydown', 'input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).trigger('change');
                }
            });

            $('#posForm').on('submit', function(e) {
                e.preventDefault();

                if (cart.length === 0) {
                    toastr.error('Please add at least one product.');
                    return;
                }

                if ($.trim($('#customer_phone').val()).length < 6) {
                    toastr.error('Please enter a valid customer phone.');
                    $('#customer_phone').focus();
                    return;
                }

                let totals = calculateTotals();

                Swal.fire({
                    title: 'Complete Sale?',
                    html: `Grand Total: <b>${totals.grandTotal.toFixed(2)}</b><br>` +
                        `Paid: <b>${Math.min(totals.paid, totals.grandTotal).toFixed(2)}</b>` +
                        (totals.change > 0 ? `<br>Change: <b>${totals.change.toFixed(2)}</b>` : ''),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Complete',
                    cancelButtonText: 'Cancel'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        saveSale(totals);
                    }
                });
            });
        });

        /* ================= Products ================= */

        function findProduct(id) {
            return POS_PRODUCTS.find(p => parseInt(p.id) === parseInt(id));
        }

        function setCategory(categoryId) {
            activeCategory = String(categoryId || '');
            $('.pos-cat').removeClass('active')
                .filter((i, el) => String($(el).data('category') || '') === activeCategory).addClass('active');
            $('#filterCategory').val(activeCategory);
            renderProducts();
        }

        function currentFilters() {
            let min = parseFloat($('#filterPriceMin').val());
            let max = parseFloat($('#filterPriceMax').val());
            return {
                term: $.trim($('#posSearch').val()).toLowerCase(),
                category: activeCategory,
                brand: String($('#filterBrand').val() || ''),
                min: isNaN(min) ? null : min,
                max: isNaN(max) ? null : max,
                stock: String($('#filterStock button.active').data('stock') || ''),
                sort: $('#sortBy').val()
            };
        }

        function renderProducts() {
            let f = currentFilters();

            let list = POS_PRODUCTS.filter(function(p) {
                let price = parseFloat(p.sale_price || 0);
                if (f.category && String(p.category_id) !== f.category) return false;
                if (f.brand && String(p.brand_id) !== f.brand) return false;
                if (f.min !== null && price < f.min) return false;
                if (f.max !== null && price > f.max) return false;
                if (f.stock === 'in' && p.current_stock <= 0) return false;
                if (f.stock === 'out' && p.current_stock > 0) return false;
                if (!f.term) return true;
                return [p.name, p.sku, p.barcode, p.brand_name].some(v => String(v || '').toLowerCase().includes(f.term));
            });

            let sorters = {
                name: (a, b) => String(a.name).localeCompare(String(b.name)),
                price_asc: (a, b) => a.sale_price - b.sale_price,
                price_desc: (a, b) => b.sale_price - a.sale_price,
                stock_desc: (a, b) => b.current_stock - a.current_stock
            };
            list.sort(sorters[f.sort] || sorters.name);

            // Panel filters (search and category chips are visible on their own)
            let panelCount = [f.brand, f.min !== null, f.max !== null, f.stock, f.sort !== 'name']
                .filter(Boolean).length;
            $('#filterCount').text(panelCount).toggleClass('d-none', panelCount === 0);
            $('#clearFiltersLink').toggleClass('d-none', panelCount === 0 && !f.category && !f.term);

            $('#productCount').text(list.length);

            if (list.length === 0) {
                $('#posGrid').html(`
                    <div class="pos-empty" style="grid-column: 1 / -1;">
                        <i class="bi bi-box-seam"></i>
                        No products found.
                    </div>`);
                return;
            }

            $('#posGrid').html(list.map(function(p) {
                let inCart = cart.filter(i => parseInt(i.product_id) === parseInt(p.id))
                    .reduce((sum, i) => sum + i.qty, 0);
                let outOfStock = p.current_stock <= 0;

                let badge = outOfStock ?
                    `<span class="pos-badge pos-badge-out">Out</span>` :
                    (p.variant_count > 0 ?
                        `<span class="pos-badge pos-badge-variant" title="${p.variant_count} variants"><i class="bi bi-layers me-1"></i>${p.variant_count}</span>` :
                        '');

                return `
                    <button type="button" class="pos-product ${outOfStock ? 'out-of-stock' : ''} ${inCart > 0 ? 'in-cart' : ''}"
                        data-id="${p.id}" title="${escapeHtml(p.name)}">
                        ${badge}
                        ${inCart > 0 ? `<span class="pos-badge pos-badge-qty">${formatQty(inCart)}</span>` : ''}
                        <div class="pos-product-img">
                            ${productImage(p)}
                            <span class="pos-product-add"><i class="bi bi-plus-lg"></i></span>
                        </div>
                        <div class="pos-product-body">
                            <div class="pos-product-brand">${escapeHtml(p.brand_name || p.category_name || '')}&nbsp;</div>
                            <div class="pos-product-name">${escapeHtml(p.name)}</div>
                            <span class="pos-product-price">${money(p.sale_price)}</span>
                            <span class="pos-product-stock">
                                <span class="pos-dot ${outOfStock ? 'out' : ''}"></span>
                                ${outOfStock ? 'Out of stock' : 'Stock ' + formatQty(p.current_stock)}
                            </span>
                        </div>
                    </button>`;
            }).join(''));
        }

        function productImage(p) {
            let initial = escapeHtml((p.name || '?').charAt(0).toUpperCase());
            if (!p.image_url) {
                return `<span class="pos-product-initial">${initial}</span>`;
            }
            return `<img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name)}" loading="lazy"
                        data-initial="${initial}" onerror="imageFallback(this)">`;
        }

        function imageFallback(img) {
            $(img).replaceWith($('<span class="pos-product-initial">').text($(img).data('initial')));
        }

        /**
         * Resolve a scanned/typed code and add it to the cart.
         * Variant barcode → that variant. Single product barcode → that product.
         * With allowSku (Enter key) SKU matches and variant-product barcodes are accepted too.
         * Returns true when the code was handled.
         */
        function handleScan(code, allowSku) {
            if (!code) {
                return false;
            }
            let lower = code.toLowerCase();

            let clearSearch = function() {
                $('#posSearch').val('');
                renderProducts();
            };

            if (VARIANT_BARCODES[code]) {
                let product = findProduct(VARIANT_BARCODES[code]);
                if (!product) {
                    return false;
                }
                clearSearch();
                fetchVariants(product, function(variants) {
                    let variant = variants.find(v => String(v.barcode) === code);
                    if (variant) {
                        addToCart(product, variant, 1, variants);
                    }
                });
                return true;
            }

            let product = POS_PRODUCTS.find(p =>
                String(p.barcode || '').toLowerCase() === lower ||
                (allowSku && String(p.sku || '').toLowerCase() === lower));

            if (!product) {
                return false;
            }

            if (product.variant_count > 0) {
                // A product-level barcode does not identify a variant; let the cashier pick one
                if (!allowSku) {
                    return false;
                }
                clearSearch();
                openVariantModal(product);
                return true;
            }

            clearSearch();
            if (product.current_stock <= 0) {
                toastr.warning('This product is out of stock.');
            } else {
                addToCart(product, null, 1);
            }
            return true;
        }

        /* ================= Variant modal ================= */

        function fetchVariants(product, callback) {
            $.ajax({
                url: VARIANTS_URL + product.id,
                type: 'GET',
                success: function(response) {
                    if (!response.status) {
                        toastr.error(response.message || 'Unable to load variants.');
                        return;
                    }
                    callback(response.data.variants || []);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Unable to load variants.');
                }
            });
        }

        function openVariantModal(product) {
            fetchVariants(product, function(variants) {
                if (variants.length === 0) {
                    addToCart(product, null, 1);
                    return;
                }

                let inStock = variants.filter(v => parseFloat(v.current_stock || 0) > 0);

                // Preselect the first in-stock variant that is not in the cart yet
                modalState = {
                    product: product,
                    variants: variants,
                    selected: inStock.find(v => !findCartItem(product.id, v.id)) || inStock[0] || null
                };

                $('#vmThumb').html(productImage(product));
                $('#vmName').text(product.name);
                $('#vmSku').text(product.sku ? 'SKU: ' + product.sku : '');
                $('#vmQty').val(1);

                renderVariantOptions();
                variantModal.show();
            });
        }

        function renderVariantOptions() {
            let product = modalState.product;

            $('#vmVariants').html(modalState.variants.map(function(v) {
                let stock = parseFloat(v.current_stock || 0);
                let price = variantPrice(product, v);
                let active = modalState.selected && String(modalState.selected.id) === String(v.id);
                return `
                    <button type="button" class="pos-variant-option ${active ? 'active' : ''}"
                        data-id="${v.id}" ${stock <= 0 ? 'disabled' : ''}>
                        <div class="v-name">${escapeHtml(v.attribute_name || v.variant_name || 'Variant #' + v.id)}</div>
                        <div class="v-price">${money(price)}</div>
                        <div class="v-stock">${stock > 0 ? 'Stock: ' + formatQty(stock) : 'Out of stock'}</div>
                        ${findCartItem(product.id, v.id) ?
                            `<div class="v-stock text-success fw-semibold"><i class="bi bi-cart-check"></i> In cart: ${formatQty(findCartItem(product.id, v.id).qty)}</div>` : ''}
                    </button>`;
            }).join(''));

            $('#vmPrice').text(modalState.selected ? money(variantPrice(product, modalState.selected)) : '—');
            $('#vmAddBtn').prop('disabled', !modalState.selected);
        }

        function variantPrice(product, variant) {
            let price = parseFloat(variant.sale_price);
            return isNaN(price) || price <= 0 ? parseFloat(product.sale_price || 0) : price;
        }

        /* ================= Cart ================= */

        function findCartItem(productId, variantId) {
            return cart.find(i => parseInt(i.product_id) === parseInt(productId) &&
                String(i.product_variant_id) === String(variantId || ''));
        }

        function variantLabel(variant) {
            return variant.attribute_name || variant.variant_name || 'Variant #' + variant.id;
        }

        function applyVariant(item, product, variant) {
            item.product_variant_id = String(variant.id);
            item.variant_name = variantLabel(variant);
            item.stock = parseFloat(variant.current_stock || 0);
            item.sale_price = variantPrice(product, variant);
        }

        /**
         * Each variant is its own cart line; adding the same product/variant again increases its quantity.
         */
        function addToCart(product, variant, qty, variants) {
            let variantId = variant ? String(variant.id) : '';
            let stock = variant ? parseFloat(variant.current_stock || 0) : parseFloat(product.current_stock || 0);
            let existing = findCartItem(product.id, variantId);

            let newQty = (existing ? existing.qty : 0) + qty;

            if (newQty > stock) {
                toastr.error('Quantity cannot exceed available stock (' + formatQty(stock) + ').');
                return false;
            }

            if (existing) {
                existing.qty = newQty;
            } else {
                cart.push({
                    product_id: product.id,
                    product_variant_id: variantId,
                    product_name: product.name,
                    variant_name: variant ? variantLabel(variant) : '',
                    variants: variants || [],
                    image_url: product.image_url,
                    stock: stock,
                    qty: qty,
                    sale_price: variant ? variantPrice(product, variant) : parseFloat(product.sale_price || 0),
                    discount_type: 'Amount',
                    discount_value: 0
                });
            }

            renderCart();
            return true;
        }

        function setItemQty(item, qty) {
            if (qty > item.stock) {
                toastr.error('Quantity cannot exceed available stock.');
                qty = item.stock;
            }
            item.qty = Math.max(0.01, qty);
        }

        function itemTotals(item) {
            let subtotal = item.qty * item.sale_price;
            let discount = item.discount_type === 'Percentage' ?
                subtotal * item.discount_value / 100 :
                item.discount_value;
            discount = Math.min(subtotal, Math.max(0, discount));
            return {
                subtotal: subtotal,
                discount: discount,
                total: subtotal - discount
            };
        }

        function renderCart() {
            let container = $('#cartItems');

            $('#cartCount').text(cart.length);

            if (cart.length === 0) {
                container.html(`
                    <div class="pos-empty">
                        <i class="bi bi-cart-x"></i>
                        Cart is empty.<br>
                        <small>Click a product to add it.</small>
                    </div>`);
                calculateTotals();
                renderProducts();
                return;
            }

            container.html(cart.map(function(item, index) {
                let t = itemTotals(item);
                return `
                    <div class="pos-cart-item">
                        <div class="pos-cart-thumb">${productImage({name: item.product_name, image_url: item.image_url})}</div>
                        <div class="pos-cart-info">
                            <div class="pos-cart-name">
                                ${escapeHtml(item.product_name)}
                                ${item.variant_name && !(item.variants && item.variants.length > 1) ?
                                    `<span class="pos-cart-variant">${escapeHtml(item.variant_name)}</span>` : ''}
                            </div>
                            <div class="pos-cart-controls">
                                ${item.variants && item.variants.length > 1 ? `
                                <select class="form-select cartVariant" data-index="${index}" title="Change variant">
                                    ${item.variants.map(v => `
                                        <option value="${v.id}" ${String(v.id) === item.product_variant_id ? 'selected' : ''}
                                            ${parseFloat(v.current_stock || 0) <= 0 ? 'disabled' : ''}>
                                            ${escapeHtml(variantLabel(v))}
                                        </option>`).join('')}
                                </select>` : ''}
                                <div class="pos-qty">
                                    <button type="button" class="cartMinus" data-index="${index}">−</button>
                                    <input type="number" class="cartQty" data-index="${index}" value="${item.qty}" min="0.01" step="1">
                                    <button type="button" class="cartPlus" data-index="${index}">+</button>
                                </div>
                                <span class="text-muted small">×</span>
                                <input type="number" class="form-control cartPrice" data-index="${index}"
                                    value="${item.sale_price}" min="0" step="0.01" style="width: 80px;" title="Unit price">
                                <div class="input-group" style="width: 120px;" title="Item discount">
                                    <select class="form-select cartDiscType" data-index="${index}" style="max-width: 46px;">
                                        <option value="Amount" ${item.discount_type === 'Amount' ? 'selected' : ''}>৳</option>
                                        <option value="Percentage" ${item.discount_type === 'Percentage' ? 'selected' : ''}>%</option>
                                    </select>
                                    <input type="number" class="form-control cartDiscValue" data-index="${index}"
                                        value="${item.discount_value}" min="0" step="0.01" placeholder="Disc">
                                </div>
                            </div>
                        </div>
                        <div class="pos-cart-total">
                            <strong>${money(t.total)}</strong>
                            ${t.discount > 0 ? `<small><s>${money(t.subtotal)}</s></small>` : `<small>Stock ${formatQty(item.stock)}</small>`}
                            <div><button type="button" class="pos-remove cartRemove" data-index="${index}" title="Remove">
                                <i class="bi bi-x-circle"></i></button></div>
                        </div>
                    </div>`;
            }).join(''));

            calculateTotals();
            renderProducts();
        }

        function calculateTotals() {
            let subtotal = cart.reduce((sum, item) => sum + itemTotals(item).total, 0);

            let discountValue = parseFloat($('#discount_value').val()) || 0;
            let discountAmount = $('#discount_type').val() === 'Percentage' ?
                subtotal * discountValue / 100 :
                discountValue;
            discountAmount = Math.min(subtotal, Math.max(0, discountAmount));

            let grandTotal = Math.max(0, subtotal - discountAmount);
            let paid = parseFloat($('#paid_amount').val()) || 0;
            let due = Math.max(0, grandTotal - paid);
            let change = Math.max(0, paid - grandTotal);

            $('#subtotalText').text(money(subtotal));
            $('#discountText').text(money(discountAmount));
            $('#grandTotalText').text(money(grandTotal));

            if (change > 0) {
                $('#balanceLabel').text('Change').addClass('text-success');
                $('#balance_amount').val(money(change)).removeClass('text-danger').addClass('text-success');
            } else {
                $('#balanceLabel').text('Due').removeClass('text-success');
                $('#balance_amount').val(money(due)).toggleClass('text-danger', due > 0).removeClass('text-success');
            }

            return {
                subtotal,
                discountAmount,
                grandTotal,
                paid,
                due,
                change
            };
        }

        function setCustomerDue(due) {
            due = parseFloat(due) || 0;
            $('#customerDueChip').text('Due: ' + money(due)).toggleClass('zero', due <= 0);
        }

        /* ================= Save (SaleController@store) ================= */

        function saveSale(totals) {
            let data = $('#posForm').serializeArray();

            data.push({
                name: 'paid_amount',
                value: Math.min(totals.paid, totals.grandTotal).toFixed(2)
            });

            data.push({
                name: 'items',
                value: JSON.stringify(cart.map(item => ({
                    product_id: item.product_id,
                    product_variant_id: item.product_variant_id,
                    qty: item.qty,
                    sale_price: item.sale_price,
                    discount_type: item.discount_type,
                    discount_value: item.discount_value
                })))
            });

            data.push({
                name: '_token',
                value: '{{ csrf_token() }}'
            });

            let btn = $('#checkoutBtn');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing…');

            $.ajax({
                url: "{{ route('sales.store') }}",
                type: 'POST',
                data: $.param(data),
                success: function(response) {
                    if (!response.status) {
                        toastr.error(response.message);
                        return;
                    }

                    toastr.success(response.message);

                    if (response.invoice_url) {
                        window.open(response.invoice_url, '_blank');
                    }

                    // Reflect sold quantities on product cards
                    cart.forEach(function(item) {
                        let product = findProduct(item.product_id);
                        if (product) {
                            product.current_stock = Math.max(0, product.current_stock - item.qty);
                        }
                    });

                    resetPos();
                },
                error: function(xhr) {
                    let errors = xhr.responseJSON?.errors;
                    if (errors) {
                        Object.keys(errors).forEach(key => toastr.error(errors[key][0]));
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Unable to save sale.');
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Complete Sale');
                }
            });
        }

        function resetPos() {
            cart = [];
            $('#customer_phone, #customer_name, #customer_address, #remarks').val('');
            $('#discount_type').val('Amount');
            $('#discount_value, #paid_amount').val(0);
            setCustomerDue(0);
            renderCart();
            $('#posSearch').val('').focus();
            renderProducts();
        }

        /* ================= Helpers ================= */

        function money(value) {
            return (parseFloat(value) || 0).toFixed(2);
        }

        function formatQty(value) {
            let n = parseFloat(value) || 0;
            return Number.isInteger(n) ? n : n.toFixed(2);
        }

        function escapeHtml(value) {
            return $('<div>').text(value ?? '').html();
        }
    </script>
@endpush
