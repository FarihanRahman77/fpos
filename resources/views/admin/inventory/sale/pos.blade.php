@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? '' }} POS
@endsection

@push('styles')
    <style>
        /* ==================================================================
           POS — two panels: products (left) and the current sale (right).
           Tokens only (see CLAUDE.md). Large screens: both panels fill the
           viewport and scroll inside. Below 992px they stack, and the total +
           Complete Sale bar sticks to the bottom of the screen.
           ================================================================== */
        .pos-page {
            --pos-panel-height: calc(100vh - 68px - (2 * var(--space-xxl)));
            --pos-control: var(--control-height-sm);
        }

        .pos-panel {
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-1);
        }

        @media (min-width: 992px) {
            .pos-panel {
                height: var(--pos-panel-height);
                min-height: 560px;
                overflow: hidden;
            }
        }

        .pos-panel-head {
            padding: var(--space-lg) var(--space-lg) var(--space-md);
            border-bottom: 1px solid var(--color-border);
        }

        .pos-head-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: var(--space-sm) var(--space-md);
        }

        .pos-head-row > :first-child {
            flex: 1 1 auto;
            min-width: 0;
        }

        .pos-title {
            margin: 0;
            font-size: var(--text-heading-sm);
            font-weight: var(--font-weight-regular);
            line-height: var(--leading-heading-sm);
            color: var(--color-text);
        }

        .pos-subtitle {
            font-size: var(--text-caption);
            color: var(--color-text-muted);
        }

        .pos-head-icon {
            width: var(--control-height-sm);
            height: var(--control-height-sm);
            flex: 0 0 auto;
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-size: var(--text-body-lg);
        }

        .pos-label {
            margin-bottom: var(--space-xs);
            font-size: var(--text-micro);
            font-weight: var(--font-weight-medium);
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--color-text-muted);
        }

        /* ---------- Search & filters ---------- */
        .pos-search-row {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-md);
        }

        .pos-search {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
        }

        .pos-search > i {
            position: absolute;
            left: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-muted);
            pointer-events: none;
        }

        .pos-search input {
            padding-left: calc(var(--space-md) * 3);
            padding-right: calc(var(--space-md) * 3.5);
        }

        .pos-search kbd {
            position: absolute;
            right: var(--space-sm);
            top: 50%;
            transform: translateY(-50%);
            padding: var(--space-xxs) var(--space-xs);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xs);
            background: var(--color-surface-soft);
            color: var(--color-text-muted);
            font-size: var(--text-micro);
        }

        .pos-filter-toggle {
            position: relative;
            flex: 0 0 auto;
        }

        .pos-filter-count {
            position: absolute;
            top: calc(-1 * var(--space-xs));
            right: calc(-1 * var(--space-xs));
            min-width: 20px;
            height: 20px;
            padding: 0 var(--space-xs);
            border-radius: var(--radius-pill);
            background: var(--color-button);
            color: var(--color-button-text);
            font-size: var(--text-micro);
            line-height: 20px;
            text-align: center;
        }

        .pos-filters {
            margin-top: var(--space-md);
            padding: var(--space-md);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface-soft);
        }

        .pos-filters .form-select,
        .pos-filters .form-control,
        .pos-filters .btn {
            min-height: var(--pos-control);
            height: var(--pos-control);
            padding-top: var(--space-xs);
            padding-bottom: var(--space-xs);
            font-size: var(--text-caption);
        }

        .pos-stock-toggle {
            display: flex;
            height: var(--pos-control);
            border: 1px solid var(--color-border-input);
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--color-surface);
        }

        .pos-stock-toggle button {
            flex: 1;
            border: 0;
            background: transparent;
            color: var(--color-text-secondary);
            font-size: var(--text-caption);
        }

        .pos-stock-toggle button + button {
            border-left: 1px solid var(--color-border-input);
        }

        .pos-stock-toggle button.active {
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-weight: var(--font-weight-medium);
        }

        .pos-categories {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-md);
            padding-bottom: var(--space-xxs);
            overflow-x: auto;
            scrollbar-width: thin;
        }

        .pos-cat {
            flex: 0 0 auto;
            min-height: var(--pos-control);
            padding: 0 var(--space-lg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-button);
            background: var(--color-surface);
            color: var(--color-text-secondary);
            font-size: var(--text-caption);
            transition: border-color .15s ease, background-color .15s ease, color .15s ease;
        }

        .pos-cat:hover {
            border-color: var(--color-accent);
            color: var(--color-accent-ink);
        }

        .pos-cat.active {
            border-color: var(--color-accent);
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-weight: var(--font-weight-medium);
        }

        /* ---------- Product grid ---------- */
        .pos-grid-wrap {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: var(--space-md) var(--space-lg) var(--space-lg);
            background: var(--color-surface-soft);
        }

        @media (max-width: 991.98px) {
            .pos-grid-wrap {
                max-height: 62vh;
                border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            }
        }

        .pos-result-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-sm);
            font-size: var(--text-caption);
            color: var(--color-text-muted);
        }

        .pos-result-bar a {
            font-weight: var(--font-weight-medium);
        }

        .pos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(136px, 1fr));
            gap: var(--space-md);
        }

        @media (max-width: 575.98px) {
            .pos-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: var(--space-sm);
            }
        }

        .pos-product {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: 0;
            overflow: hidden;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            color: var(--color-text);
            text-align: left;
            cursor: pointer;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .pos-product:hover {
            border-color: var(--color-accent);
            box-shadow: var(--shadow-2);
        }

        .pos-product:focus-visible {
            outline: none;
            border-color: var(--color-focus);
            box-shadow: var(--shadow-focus);
        }

        .pos-product.in-cart {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 1px var(--color-accent);
        }

        .pos-product.out-of-stock {
            cursor: not-allowed;
        }

        .pos-product.out-of-stock:hover {
            border-color: var(--color-border);
            box-shadow: none;
        }

        .pos-product.out-of-stock .pos-product-img img {
            filter: grayscale(1);
            opacity: .5;
        }

        .pos-product-img {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 4 / 3;
            width: 100%;
            overflow: hidden;
            background: var(--color-surface-soft);
        }

        .pos-product-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: var(--space-sm);
        }

        .pos-product-initial {
            font-size: var(--text-display-md);
            font-weight: var(--font-weight-light);
            color: var(--color-accent);
        }

        .pos-product-add {
            position: absolute;
            right: var(--space-sm);
            bottom: var(--space-sm);
            width: 32px;
            height: 32px;
            border-radius: var(--radius-pill);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-button);
            color: var(--color-button-text);
            box-shadow: var(--shadow-2);
            opacity: 0;
            transition: opacity .15s ease;
        }

        .pos-product:hover .pos-product-add,
        .pos-product:focus-visible .pos-product-add {
            opacity: 1;
        }

        /* Touch screens have no hover: keep the add button visible. */
        @media (hover: none) {
            .pos-product-add {
                opacity: 1;
            }
        }

        .pos-product.out-of-stock .pos-product-add {
            display: none;
        }

        .pos-product-body {
            display: flex;
            flex-direction: column;
            gap: var(--space-xxs);
            padding: var(--space-sm) var(--space-md) var(--space-md);
            border-top: 1px solid var(--color-border);
        }

        .pos-product-brand {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-size: var(--text-micro);
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--color-text-muted);
        }

        .pos-product-name {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            min-height: calc(2 * var(--leading-caption) * var(--text-caption));
            overflow: hidden;
            font-size: var(--text-caption);
            font-weight: var(--font-weight-medium);
            line-height: var(--leading-caption);
        }

        .pos-product-price {
            margin-top: var(--space-xs);
            font-size: var(--text-body-tabular);
            font-weight: var(--font-weight-medium);
            font-feature-settings: var(--font-feature-numeric);
            color: var(--color-text);
        }

        .pos-product-stock {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: var(--text-micro);
            color: var(--color-text-muted);
        }

        .pos-dot {
            width: 6px;
            height: 6px;
            flex: 0 0 6px;
            border-radius: 50%;
            background: var(--color-success);
        }

        .pos-dot.out {
            background: var(--color-danger);
        }

        .pos-badge {
            position: absolute;
            top: var(--space-sm);
            z-index: 1;
            padding: var(--space-xxs) var(--space-sm);
            border-radius: var(--radius-pill);
            font-size: var(--text-micro);
            font-weight: var(--font-weight-medium);
            line-height: 1.3;
        }

        .pos-badge-variant {
            left: var(--space-sm);
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            color: var(--color-text-secondary);
        }

        .pos-badge-out {
            left: var(--space-sm);
            background: var(--color-danger-soft);
            color: var(--color-danger);
        }

        .pos-badge-qty {
            right: var(--space-sm);
            min-width: 24px;
            text-align: center;
            background: var(--color-button);
            color: var(--color-button-text);
        }

        .pos-empty {
            padding: var(--space-huge) var(--space-md);
            text-align: center;
            color: var(--color-text-muted);
        }

        .pos-empty i {
            display: block;
            margin-bottom: var(--space-sm);
            font-size: var(--text-display-lg);
            color: var(--color-border-input);
        }

        /* ---------- Cart panel ---------- */
        .pos-cart-head-actions {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .pos-cart-head-actions .form-control {
            width: 150px;
            min-height: var(--pos-control);
            height: var(--pos-control);
            padding-top: var(--space-xs);
            padding-bottom: var(--space-xs);
            font-size: var(--text-caption);
        }

        .pos-cart-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
        }

        .pos-cart-items {
            padding: 0 var(--space-lg);
        }

        .pos-cart-items .pos-empty {
            padding: var(--space-xl) var(--space-md);
        }

        /* one cart line: [thumb] [name] [total]  /  [controls] */
        .pos-cart-item {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr) auto;
            grid-template-areas:
                "thumb info total"
                "thumb controls controls";
            gap: var(--space-xs) var(--space-md);
            padding: var(--space-md) 0;
            border-bottom: 1px solid var(--color-border);
        }

        .pos-cart-item:last-child {
            border-bottom: 0;
        }

        .pos-cart-thumb {
            grid-area: thumb;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface-soft);
            color: var(--color-accent);
        }

        .pos-cart-thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .pos-cart-thumb .pos-product-initial {
            font-size: var(--text-heading-sm);
        }

        .pos-cart-info {
            grid-area: info;
            min-width: 0;
        }

        .pos-cart-name {
            font-size: var(--text-caption);
            font-weight: var(--font-weight-medium);
            line-height: var(--leading-caption);
        }

        .pos-cart-variant {
            display: inline-block;
            margin-left: var(--space-xs);
            padding: 0 var(--space-sm);
            border-radius: var(--radius-pill);
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-size: var(--text-micro);
            font-weight: var(--font-weight-medium);
        }

        .pos-cart-total {
            grid-area: total;
            text-align: right;
            white-space: nowrap;
        }

        .pos-cart-total strong {
            display: block;
            font-size: var(--text-body-tabular);
            font-weight: var(--font-weight-medium);
            font-feature-settings: var(--font-feature-numeric);
        }

        .pos-cart-total small {
            font-size: var(--text-micro);
            color: var(--color-text-muted);
        }

        .pos-cart-controls {
            grid-area: controls;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: var(--space-sm);
        }

        .pos-cart-controls .form-control,
        .pos-cart-controls .form-select {
            min-height: var(--pos-control);
            height: var(--pos-control);
            padding: var(--space-xs) var(--space-sm);
            font-size: var(--text-caption);
        }

        /* fixed rows: [variant] / [qty × price] / [discount ... remove] */
        .pos-cart-controls .cartVariant {
            flex: 1 1 100%;
            padding-right: calc(var(--space-sm) * 3.5);
        }

        .pos-break {
            flex-basis: 100%;
            height: 0;
            margin-bottom: calc(-1 * var(--space-sm));   /* the empty line would otherwise add a second row gap */
        }

        /* price and discount stretch/shrink so a narrow cart never pushes them onto their own line */
        .pos-cart-controls .cartPrice {
            flex: 1 1 72px;
            min-width: 72px;
            max-width: 120px;
            text-align: right;
        }

        .pos-times {
            color: var(--color-text-muted);
            font-size: var(--text-caption);
        }

        .pos-disc {
            display: flex;
            flex: 0 1 132px;
            min-width: 108px;
        }

        .pos-disc .form-select {
            flex: 0 0 52px;
            width: 52px;
            /* Inter has no Bengali Taka (৳): name system fonts that do, for this symbol only */
            font-family: var(--font-sans), "Nirmala UI", "Noto Sans Bengali", "Vrinda", sans-serif;
            font-size: var(--text-body-tabular);
            padding-right: var(--space-lg) !important;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            background-position: right var(--space-xs) center;
            background-size: 10px 8px;
        }

        .pos-disc .form-control {
            flex: 1 1 auto;
            min-width: 0;
            margin-left: -1px;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            text-align: right;
        }

        .pos-qty {
            display: inline-flex;
            align-items: center;
            height: var(--pos-control);
            overflow: hidden;
            border: 1px solid var(--color-border-input);
            border-radius: var(--radius-sm);
            background: var(--color-surface);
        }

        .pos-qty button {
            width: var(--pos-control);
            height: 100%;
            border: 0;
            background: var(--color-surface-soft);
            color: var(--color-text);
            font-size: var(--text-body-lg);
            line-height: 1;
        }

        .pos-qty button:hover {
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
        }

        .pos-qty input {
            width: 48px;
            height: 100%;
            border: 0;
            background: transparent;
            color: var(--color-text);
            text-align: center;
            font-size: var(--text-caption);
            font-feature-settings: var(--font-feature-numeric);
            -moz-appearance: textfield;
        }

        .pos-qty input:focus {
            outline: none;
            box-shadow: inset 0 0 0 2px var(--color-focus-ring);
        }

        .pos-qty input::-webkit-outer-spin-button,
        .pos-qty input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .pos-remove {
            margin-top: var(--space-xs);
            padding: var(--space-xs);
            border: 0;
            border-radius: var(--radius-button);
            background: transparent;
            color: var(--color-text-muted);
            font-size: var(--text-body-lg);
            line-height: 1;
        }

        .pos-remove:hover {
            background: var(--color-danger-soft);
            color: var(--color-danger);
        }

        /* customer, discount, payment */
        .pos-section {
            padding: var(--space-md) var(--space-lg);
            border-top: 1px solid var(--color-border);
        }

        .pos-section .form-control,
        .pos-section .form-select {
            min-height: 40px;
            height: 40px;
            padding-top: var(--space-xs);
            padding-bottom: var(--space-xs);
            font-size: var(--text-body-tabular);
        }

        .pos-due-chip {
            display: inline-block;
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-pill);
            background: var(--color-danger-soft);
            color: var(--color-danger);
            font-size: var(--text-caption);
            font-weight: var(--font-weight-medium);
            font-feature-settings: var(--font-feature-numeric);
            white-space: nowrap;
        }

        .pos-due-chip.zero {
            background: var(--color-success-soft);
            color: var(--color-success);
        }

        .pos-sum-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-md);
            min-height: 40px;
            font-size: var(--text-body-tabular);
        }

        .pos-sum-row > span:first-child {
            color: var(--color-text-muted);
        }

        .pos-sum-row strong {
            font-weight: var(--font-weight-medium);
            font-feature-settings: var(--font-feature-numeric);
        }

        .pos-sum-row > div {
            min-width: 0;
        }

        .pos-sum-row .pos-disc {
            flex: 0 1 150px;
            min-width: 100px;
        }

        .pos-sum-row .pos-disc .form-select {
            flex-basis: 56px;
            width: 56px;
        }

        .pos-discount-amount {
            min-width: 56px;
            text-align: right;
            white-space: nowrap;
            color: var(--color-danger);
        }

        .pos-redeem { width: 96px; min-height: var(--pos-control); height: var(--pos-control); text-align: right; }
        .pos-credit-hint { display: block; margin-top: var(--space-xxs); font-size: var(--text-micro); color: var(--color-text-muted); }
        .pos-credit-hint.over { color: var(--color-danger); font-weight: var(--font-weight-medium); }

        .pos-pay-full {
            font-weight: var(--font-weight-medium);
            text-transform: none;
            letter-spacing: 0;
        }

        /* footer: always visible */
        .pos-cart-footer {
            flex: 0 0 auto;
            padding: var(--space-md) var(--space-lg) var(--space-lg);
            border-top: 1px solid var(--color-border);
            background: var(--color-surface);
            /* hints that the cart above scrolls */
            box-shadow: 0 -10px 16px -14px color-mix(in srgb, var(--color-ink) 35%, transparent);
        }

        .pos-grand {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }

        .pos-grand span {
            font-size: var(--text-body-tabular);
            color: var(--color-text-muted);
        }

        .pos-grand strong {
            font-size: var(--text-display-md);
            font-weight: var(--font-weight-light);
            letter-spacing: var(--tracking-display-md);
            line-height: 1;
            font-feature-settings: var(--font-feature-numeric);
            color: var(--color-text);
        }

        .pos-checkout {
            width: 100%;
            min-height: 52px;
            font-size: var(--text-button-md);
        }

        @media (max-width: 991.98px) {
            .pos-cart-footer {
                position: sticky;
                bottom: 0;
                z-index: 5;
                border-radius: 0 0 var(--radius-lg) var(--radius-lg);
                box-shadow: 0 -8px 24px color-mix(in srgb, var(--color-ink) 8%, transparent);
            }
        }

        @media (max-width: 575.98px) {
            .pos-panel-head,
            .pos-section,
            .pos-cart-footer {
                padding-left: var(--space-md);
                padding-right: var(--space-md);
            }

            .pos-cart-items {
                padding: 0 var(--space-md);
            }

            .pos-grid-wrap {
                padding: var(--space-sm) var(--space-md) var(--space-md);
            }

            .pos-grand strong {
                font-size: var(--text-heading-lg);
            }

            .pos-cart-head-actions .form-control {
                width: 132px;
            }
        }

        /* ---------- Variant modal ---------- */
        .pos-variant-hero {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .pos-variant-hero .pos-cart-thumb {
            width: 64px;
            height: 64px;
        }

        .pos-variant-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: var(--space-sm);
        }

        .pos-variant-option {
            display: flex;
            flex-direction: column;
            gap: var(--space-xxs);
            min-height: var(--control-height);
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--color-border-input);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            text-align: left;
            transition: border-color .15s ease, background-color .15s ease;
        }

        .pos-variant-option:hover:not(:disabled) {
            border-color: var(--color-accent);
        }

        .pos-variant-option.active {
            border-color: var(--color-accent);
            background: var(--color-accent-soft);
            box-shadow: 0 0 0 1px var(--color-accent);
        }

        .pos-variant-option:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .pos-variant-option .v-name {
            font-size: var(--text-caption);
            font-weight: var(--font-weight-medium);
        }

        .pos-variant-option .v-price {
            font-size: var(--text-caption);
            font-weight: var(--font-weight-medium);
            font-feature-settings: var(--font-feature-numeric);
            color: var(--color-accent-ink);
        }

        .pos-variant-option .v-stock {
            font-size: var(--text-micro);
            color: var(--color-text-muted);
        }

        .pos-variant-modal .pos-qty {
            height: var(--control-height);
        }

        .pos-variant-modal .pos-qty button {
            width: var(--control-height);
        }

        .pos-vm-price {
            font-size: var(--text-heading-sm);
            font-weight: var(--font-weight-medium);
            font-feature-settings: var(--font-feature-numeric);
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid pos-page">
        <form id="posForm" autocomplete="off" novalidate>
            <input type="hidden" name="sale_type" value="Walk-In">

            <div class="row g-3">

                {{-- ================= Products ================= --}}
                <div class="col-lg-7 col-xxl-8">
                    <div class="pos-panel">
                        <div class="pos-panel-head">
                            <div class="pos-head-row">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pos-head-icon" aria-hidden="true"><i class="bi bi-grid-3x3-gap"></i></span>
                                    <div class="min-w-0">
                                        <h5 class="pos-title">Products</h5>
                                        <div class="pos-subtitle">{{ count($products) }} products in catalogue</div>
                                    </div>
                                </div>
                                <a href="{{ route('sales.index', ['type' => 'Walk-In']) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-list-ul"></i> Sales List
                                </a>
                            </div>

                            <div class="pos-search-row">
                                <div class="pos-search">
                                    <i class="bi bi-search" aria-hidden="true"></i>
                                    <input type="text" id="posSearch" class="form-control" aria-label="Search products"
                                        placeholder="Search name, SKU or scan barcode…">
                                    <kbd aria-hidden="true">F2</kbd>
                                </div>
                                <button type="button" class="btn btn-outline-secondary pos-filter-toggle" data-bs-toggle="collapse"
                                    data-bs-target="#posFilters" aria-expanded="false" aria-controls="posFilters">
                                    <i class="bi bi-sliders"></i> <span class="d-none d-sm-inline">Filters</span>
                                    <span class="pos-filter-count d-none" id="filterCount">0</span>
                                </button>
                            </div>

                            <div class="collapse" id="posFilters">
                                <div class="pos-filters">
                                    <div class="row g-2">
                                        <div class="col-sm-6">
                                            <label class="pos-label" for="filterBrand">Brand</label>
                                            <select id="filterBrand" class="form-select">
                                                <option value="">All Brands</option>
                                                @foreach ($brands as $brand)
                                                    <option value="{{ $brand['id'] }}">{{ $brand['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="pos-label" for="filterCategory">Category</label>
                                            <select id="filterCategory" class="form-select">
                                                <option value="">All Categories</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="pos-label" for="filterPriceMin">Sale Price</label>
                                            <div class="input-group">
                                                <input type="number" id="filterPriceMin" class="form-control" min="0" step="0.01" placeholder="Min">
                                                <input type="number" id="filterPriceMax" class="form-control" min="0" step="0.01" placeholder="Max" aria-label="Maximum price">
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="pos-label">Stock</div>
                                            <div class="pos-stock-toggle" id="filterStock" role="group" aria-label="Stock">
                                                <button type="button" class="active" data-stock="">All</button>
                                                <button type="button" data-stock="in">In Stock</button>
                                                <button type="button" data-stock="out">Out</button>
                                            </div>
                                        </div>
                                        <div class="col-8">
                                            <label class="pos-label" for="sortBy">Sort By</label>
                                            <select id="sortBy" class="form-select">
                                                <option value="name">Name (A–Z)</option>
                                                <option value="price_asc">Price: Low to High</option>
                                                <option value="price_desc">Price: High to Low</option>
                                                <option value="stock_desc">Stock: High to Low</option>
                                            </select>
                                        </div>
                                        <div class="col-4 d-flex align-items-end">
                                            <button type="button" class="btn btn-outline-secondary w-100" id="resetFilters">
                                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pos-categories" id="posCategories" role="group" aria-label="Categories">
                                <button type="button" class="pos-cat active" data-category="">All</button>
                                @foreach ($categories as $category)
                                    <button type="button" class="pos-cat" data-category="{{ $category['id'] }}">{{ $category['name'] }}</button>
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

                {{-- ================= Current sale ================= --}}
                <div class="col-lg-5 col-xxl-4">
                    <div class="pos-panel">
                        <div class="pos-panel-head">
                            <div class="pos-head-row">
                                <div>
                                    <h5 class="pos-title">Current Sale</h5>
                                    <div class="pos-subtitle"><span id="cartCount">0</span> item(s) in cart</div>
                                </div>
                                <div class="pos-cart-head-actions">
                                    <input type="date" name="sale_date" id="sale_date" class="form-control" value="{{ date('Y-m-d') }}" aria-label="Sale date">
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="clearCartBtn" title="Clear cart" aria-label="Clear cart">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="pos-cart-scroll">
                            {{-- Items --}}
                            <div class="pos-cart-items" id="cartItems"></div>

                            {{-- Customer --}}
                            <div class="pos-section">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="pos-label" for="customer_phone">Phone <span class="text-danger">*</span></label>
                                        <input type="text" id="customer_phone" name="customer_phone" class="form-control" placeholder="Customer phone" inputmode="tel">
                                    </div>
                                    <div class="col-6">
                                        <label class="pos-label" for="customer_name">Name</label>
                                        <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="Walk-In Customer">
                                    </div>
                                    <div class="col">
                                        <input type="text" id="customer_address" name="customer_address" class="form-control" placeholder="Address (optional)" aria-label="Address">
                                    </div>
                                    <div class="col-auto d-flex align-items-center">
                                        <span class="pos-due-chip zero" id="customerDueChip">Due: 0.00</span>
                                        <span class="pos-credit-hint d-none" id="creditHint"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Totals & payment --}}
                            <div class="pos-section">
                                <div class="pos-sum-row">
                                    <span>Subtotal</span>
                                    <strong id="subtotalText">0.00</strong>
                                </div>
                                <div class="pos-sum-row">
                                    <span>Discount</span>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="pos-disc">
                                            <select name="discount_type" id="discount_type" class="form-select" aria-label="Discount type">
                                                <option value="Amount">৳</option>
                                                <option value="Percentage">%</option>
                                            </select>
                                            <input type="number" step="0.01" min="0" name="discount_value" id="discount_value"
                                                class="form-control" value="0" aria-label="Discount">
                                        </div>
                                        <strong class="pos-discount-amount">−<span id="discountText">0.00</span></strong>
                                    </div>
                                </div>

                                @if ($programs['vat_enabled'])
                                    <div class="pos-sum-row">
                                        <span>VAT{{ $programs['vat_inclusive'] ? ' (included)' : '' }}</span>
                                        <strong id="vatText">0.00</strong>
                                    </div>
                                @endif
                                @if ($programs['loyalty'])
                                    <div class="pos-sum-row d-none" id="loyaltyRow">
                                        <span>Points <small class="text-muted" id="pointsHave"></small></span>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="number" name="redeem_points" id="redeem_points" class="form-control pos-redeem" min="0" step="1"
                                                placeholder="Redeem" aria-label="Points to redeem">
                                            <strong class="pos-discount-amount">−<span id="loyaltyText">0.00</span></strong>
                                        </div>
                                    </div>
                                @endif
                                <div class="row g-2 mt-1">
                                    <div class="col-6">
                                        <label class="pos-label" for="payment_method">Payment Method</label>
                                        <select id="payment_method" name="payment_method" class="form-select">
                                            <option value="">Select Method</option>
                                            @foreach ($childCoas as $childCoa)
                                                <option value="{{ $childCoa->id }}">{{ $childCoa->account_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="pos-label" for="payment_method_account">Payment Account</label>
                                        <select id="payment_method_account" name="payment_method_account" class="form-select">
                                            <option value="">Select Account</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <div class="pos-label d-flex justify-content-between">
                                            <label for="paid_amount" class="mb-0">Paid Amount</label>
                                            <a href="#" id="payFullBtn" class="pos-pay-full">Pay full</a>
                                        </div>
                                        <input type="number" step="0.01" min="0" id="paid_amount" class="form-control text-end fw-semibold" value="0" inputmode="decimal">
                                    </div>
                                    <div class="col-6">
                                        <label class="pos-label" id="balanceLabel" for="balance_amount">Due</label>
                                        <input type="text" id="balance_amount" class="form-control text-end fw-semibold" value="0.00" readonly>
                                    </div>
                                    <div class="col-12">
                                        <input type="text" name="remarks" id="remarks" class="form-control" placeholder="Remarks (optional)" aria-label="Remarks">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Always visible --}}
                        <div class="pos-cart-footer">
                            <div class="pos-grand">
                                <span>Grand Total</span>
                                <strong id="grandTotalText">0.00</strong>
                            </div>
                            <button type="submit" class="btn btn-primary pos-checkout" id="checkoutBtn">
                                <i class="bi bi-check2-circle me-1"></i>
                                Complete Sale
                            </button>
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
                            <strong class="pos-vm-price" id="vmPrice">0.00</strong>
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
        const PROGRAMS = @json($programs);
        let customerProgram = null, customerDue = 0, lastTotals = null;
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

            $(document).on('input', '#redeem_points', calculateTotals);

            /* ---------- Customer lookup ---------- */
            let phoneTimer;
            $('#customer_phone').on('input', function() {
                clearTimeout(phoneTimer);
                let phone = $.trim($(this).val());
                if (phone.length < 6) {
                    setCustomerDue(0);
                    setCustomerProgram(null);
                    return;
                }
                phoneTimer = setTimeout(function() {
                    $.ajax({
                        url: "{{ url('/admin/sales/customer-by-phone') }}/" + encodeURIComponent(phone),
                        type: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            if (response.status && response.customer) {
                                $('#customer_name').val(response.customer.name || '');
                                $('#customer_address').val(response.customer.address || '');
                                setCustomerDue(response.current_due);
                                setCustomerProgram(response.programs || null);
                            } else {
                                setCustomerDue(0);
                                setCustomerProgram(null);
                            }
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                        }
                    });
                }, 350);
            });

            /* ---------- Payment account ---------- */
            $('#payment_method').on('change', function() {
                $.ajax({
                    url: "{{ route('admin.getCoaChilds') }}",
                    type: 'GET',
                    dataType: 'html',
                    data: {
                        parent_id: $(this).val()
                    },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        $('#payment_method_account').html(response);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
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
            // Products that can be sold now come first; out-of-stock ones go to the bottom, each group in the chosen order.
            let sorter = sorters[f.sort] || sorters.name;
            list.sort((a, b) => ((b.current_stock > 0) - (a.current_stock > 0)) || sorter(a, b));

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
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    if (!response.status) {
                        toastr.error(response.message || 'Unable to load variants.');
                        return;
                    }
                    callback(response.data.variants || []);
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
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
                        </div>
                        <div class="pos-cart-total">
                            <strong>${money(t.total)}</strong>
                            ${t.discount > 0 ? `<small><s>${money(t.subtotal)}</s></small>` : `<small>Stock ${formatQty(item.stock)}</small>`}
                        </div>
                        <div class="pos-cart-controls">
                            ${item.variants && item.variants.length > 1 ? `
                            <select class="form-select cartVariant" data-index="${index}" title="Change variant" aria-label="Variant">
                                ${item.variants.map(v => `
                                    <option value="${v.id}" ${String(v.id) === item.product_variant_id ? 'selected' : ''}
                                        ${parseFloat(v.current_stock || 0) <= 0 ? 'disabled' : ''}>
                                        ${escapeHtml(variantLabel(v))}
                                    </option>`).join('')}
                            </select>` : ''}
                            <div class="pos-qty">
                                <button type="button" class="cartMinus" data-index="${index}" aria-label="Decrease quantity">−</button>
                                <input type="number" class="cartQty" data-index="${index}" value="${item.qty}" min="0" step="any" aria-label="Quantity">
                                <button type="button" class="cartPlus" data-index="${index}" aria-label="Increase quantity">+</button>
                            </div>
                            <span class="pos-times" aria-hidden="true">×</span>
                            <input type="number" class="form-control cartPrice" data-index="${index}"
                                value="${item.sale_price}" min="0" step="0.01" title="Unit price" aria-label="Unit price">
                            <span class="pos-break" aria-hidden="true"></span>
                            <div class="pos-disc" title="Item discount">
                                <select class="form-select cartDiscType" data-index="${index}" aria-label="Discount type">
                                    <option value="Amount" ${item.discount_type === 'Amount' ? 'selected' : ''}>৳</option>
                                    <option value="Percentage" ${item.discount_type === 'Percentage' ? 'selected' : ''}>%</option>
                                </select>
                                <input type="number" class="form-control cartDiscValue" data-index="${index}"
                                    value="${item.discount_value}" min="0" step="0.01" placeholder="Disc" aria-label="Discount">
                            </div>
                            <button type="button" class="pos-remove cartRemove ms-auto" data-index="${index}" title="Remove" aria-label="Remove item">
                                <i class="bi bi-x-circle"></i></button>
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

            // VAT (same rule as the server): rate per product, else the default; spread the sale discount over the lines.
            let vat = 0;
            if (PROGRAMS.vat_enabled && subtotal > 0) {
                const share = Math.max(0, 1 - discountAmount / subtotal);
                cart.forEach(function(item) {
                    const p = POS_PRODUCTS.find(function(x) { return x.id == item.product_id; }) || {};
                    const rate = p.vat_percent === null || p.vat_percent === undefined ? PROGRAMS.vat_rate : parseFloat(p.vat_percent);
                    const base = itemTotals(item).total * share;
                    vat += PROGRAMS.vat_inclusive ? base - base / (1 + rate / 100) : base * rate / 100;
                });
                vat = Math.round(vat * 100) / 100;
                if (!PROGRAMS.vat_inclusive) grandTotal += vat;
            }
            $('#vatText').text(money(vat));

            // Loyalty points as money off (capped like the server).
            let loyalty = 0;
            if (customerProgram && customerProgram.loyalty_enabled) {
                const want = Math.min(parseFloat($('#redeem_points').val()) || 0, customerProgram.loyalty_points);
                if (want >= customerProgram.min_redeem) {
                    loyalty = Math.min(want * customerProgram.point_value, grandTotal * customerProgram.max_percent / 100);
                }
                grandTotal -= loyalty;
            }
            $('#loyaltyText').text(money(loyalty));

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

            lastTotals = { subtotal, discountAmount, grandTotal, paid, due, change };
            if (typeof creditHint === 'function' && customerProgram) {
                const limit = customerProgram.credit_limit;
                const after = customerDue + due;
                if (limit && PROGRAMS.credit_mode !== 'off') {
                    $('#creditHint').removeClass('d-none').toggleClass('over', after > limit)
                        .text('Credit limit ' + money(limit) + (after > limit ? ' — over by ' + money(after - limit) : ''));
                }
            }

            return lastTotals;
        }

        function setCustomerProgram(p) {
            customerProgram = p;
            const show = !!(p && p.loyalty_enabled && p.loyalty_points > 0);
            $('#loyaltyRow').toggleClass('d-none', !show);
            $('#pointsHave').text(show ? '(' + Math.floor(p.loyalty_points) + ' = ' + money(p.loyalty_points * p.point_value) + ')' : '');
            if (!show) $('#redeem_points').val('');
            creditHint();
            calculateTotals();
        }

        function creditHint() {
            const limit = customerProgram ? customerProgram.credit_limit : 0;
            if (!limit || PROGRAMS.credit_mode === 'off') { $('#creditHint').addClass('d-none'); return; }
            const t = calculateTotalsQuiet();
            const after = customerDue + (t ? t.due : 0);
            $('#creditHint').removeClass('d-none').toggleClass('over', after > limit)
                .text('Credit limit ' + money(limit) + (after > limit ? ' — over by ' + money(after - limit) : ''));
        }

        function calculateTotalsQuiet() { try { return lastTotals; } catch (e) { return null; } }

        function setCustomerDue(due) {
            due = parseFloat(due) || 0;
            customerDue = due;
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
                dataType: 'json',
                data: $.param(data),
                success: function(response) {
                    // alert(JSON.stringify(response));
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
                    // alert(JSON.stringify(xhr));
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
            $('#redeem_points').val('');
            setCustomerDue(0);
            setCustomerProgram(null);   // the previous customer's loyalty/credit must not carry into the next sale
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

        // Also escapes quotes: the result is used inside attributes (title="", alt="", src="").
        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
        }
    </script>
@endpush
