@extends('admin.master')

@section('title')
    {{ Session::get('companySettings')[0]['name'] ?? '' }} Barcode Print
@endsection



@push('styles')
<style>
    .select2-container { width: 100% !important; }
    .select2-container .select2-selection--single { height: 42px; padding: 6px 0; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa fa-barcode"></i> Generate &amp; Print Barcodes</h5>
                    <span id="cartCount" class="badge bg-primary">0 labels</span>
                </div>
                <div class="card-body">

                    {{-- Search / Select --}}
                    <div class="mb-3">
                        <label class="form-label">Search Product (name / SKU / barcode)</label>
                        <select id="productSearch" class="form-control" style="width:100%">
                            <option value="">Select Products</option>
                            @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Selecting a product with variants will automatically add all of its variants below.</div>
                    </div>

                    {{-- Cart --}}
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="cartTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Item</th>
                                    <th style="width:140px">Barcode</th>
                                    <th style="width:100px">Price</th>
                                    <th style="width:180px">Quantity</th>
                                    <th style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody id="cartBody">
                                <tr id="emptyRow">
                                    <td colspan="6" class="text-center text-muted py-4">No items added yet. Search and select a product above.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr>

                    {{-- Print settings --}}
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Barcode Type</label>
                            <select id="barcodeType" class="form-select">
                                <option value="C128" selected>Code 128</option>
                                <option value="C39">Code 39</option>
                                <option value="EAN13">EAN-13</option>
                                <option value="UPCA">UPC-A</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Label Width (mm)</label>
                            <input type="number" id="labelWidth" class="form-control" value="40" min="20" max="100">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Label Height (mm)</label>
                            <input type="number" id="labelHeight" class="form-control" value="30" min="15" max="100">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Columns</label>
                            <input type="number" id="columns" class="form-control" value="3" min="1" max="8">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-block">Show</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="showName" checked>
                                <label class="form-check-label" for="showName">Name</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="showPrice" checked>
                                <label class="form-check-label" for="showPrice">Price</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="button" id="clearCart" class="btn btn-outline-danger w-50">Clear</button>
                            <button type="button" id="generatePrint" class="btn btn-primary w-50">
                                <i class="fa fa-print"></i> Print
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- Hidden form used to POST the cart to the print route in a new tab --}}
<form id="printForm" action="{{ route('admin.barcode.print') }}" method="POST" target="_blank">
    @csrf
    <div id="printFormFields"></div>
</form>
@endsection

@push('scripts')
{{-- Remove these two lines if your layout already loads jQuery / Select2 --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>

<script>
(function ($) {
    const cartBody  = document.getElementById('cartBody');
    const emptyRow  = document.getElementById('emptyRow');
    const cartCount = document.getElementById('cartCount');

    // cart = { key: {type, id, name, barcode, price, qty} }
    let cart = {};

    // The select is already pre-populated with every product (id + name) from the
    // server, so Select2 just needs to add search/filter UI on top of it — no ajax.
    $('#productSearch').select2({
        placeholder: 'Select Products',
        width: '100%',
        allowClear: true
    });

    // URL for fetching one product's full details (sku/barcode/price/variants).
    const variantsUrlBase = "{{ url('barcode/variants') }}";

    $('#productSearch').on('select2:select', function (e) {
        const productId = $(this).val();
        if (!productId) return;

        fetchProductDetails(productId);
        $(this).val(null).trigger('change'); // reset dropdown so the same or another product can be picked again
    });

    function fetchProductDetails(productId) {
        $.ajax({

            url: variantsUrlBase + '/' + productId,

            type: 'GET',

            

            success: function (response) {
                alert(JSON.stringify(response));
                if (response && response.data) {

                    addProductToCart(response.data);

                } else {

                    toastr.error('Could not load that product\'s details.');

                }

            },

            error: function (xhr) {
                alert(JSON.stringify(xhr));
                toastr.error('Something went wrong.');

            }

        });
    }

    // If the product has variants, auto-expand ONLY the variants into the cart
    // (each carries its own barcode/price). Otherwise add the base product.
    function addProductToCart(product) {
        if (product.has_variants && product.variants && product.variants.length) {
            product.variants.forEach(function (v) {
                const key = 'variant_' + v.id;
                if (cart[key]) { cart[key].qty += 1; return; }
                cart[key] = {
                    type: 'variant',
                    id: v.id,
                    name: `${product.name} (${v.label})`,
                    barcode: v.barcode || product.sku,
                    price: v.sale_price,
                    qty: 1,
                };
            });
        } else {
            const key = 'product_' + product.id;
            if (cart[key]) { cart[key].qty += 1; renderCart(); return; }
            cart[key] = {
                type: 'product',
                id: product.id,
                name: product.name,
                barcode: product.barcode || product.sku,
                price: product.sale_price,
                qty: 1,
            };
        }
        renderCart();
    }

    function renderCart() {
        const keys = Object.keys(cart);
        emptyRow.style.display = keys.length ? 'none' : '';
        cartBody.querySelectorAll('tr:not(#emptyRow)').forEach(r => r.remove());

        let totalQty = 0;
        keys.forEach((key, idx) => {
            const item = cart[key];
            totalQty += item.qty;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${idx + 1}</td>
                <td>${escapeHtml(item.name)}</td>
                <td>${escapeHtml(item.barcode || '-')}</td>
                <td>${item.price != null ? Number(item.price).toFixed(2) : '-'}</td>
                <td>
                    <div class="input-group input-group-sm" style="max-width:150px">
                        <button class="btn btn-outline-secondary qty-minus" type="button" data-key="${key}">-</button>
                        <input type="number" class="form-control text-center qty-input" min="1" max="500" value="${item.qty}" data-key="${key}">
                        <button class="btn btn-outline-secondary qty-plus" type="button" data-key="${key}">+</button>
                    </div>
                </td>
                <td><button class="btn btn-sm btn-outline-danger remove-item" data-key="${key}"><i class="fa fa-trash"></i></button></td>
            `;
            cartBody.appendChild(row);
        });

        cartCount.textContent = `${totalQty} label${totalQty === 1 ? '' : 's'}`;

        cartBody.querySelectorAll('.qty-minus').forEach(b => b.addEventListener('click', function () {
            const key = this.dataset.key;
            if (cart[key].qty > 1) { cart[key].qty--; renderCart(); }
        }));
        cartBody.querySelectorAll('.qty-plus').forEach(b => b.addEventListener('click', function () {
            const key = this.dataset.key;
            cart[key].qty++; renderCart();
        }));
        cartBody.querySelectorAll('.qty-input').forEach(inp => inp.addEventListener('change', function () {
            const key = this.dataset.key;
            let val = parseInt(this.value, 10);
            if (isNaN(val) || val < 1) val = 1;
            cart[key].qty = val;
            renderCart();
        }));
        cartBody.querySelectorAll('.remove-item').forEach(b => b.addEventListener('click', function () {
            delete cart[this.dataset.key];
            renderCart();
        }));
    }

    document.getElementById('clearCart').addEventListener('click', function () {
        cart = {};
        renderCart();
    });

    document.getElementById('generatePrint').addEventListener('click', function () {
        const keys = Object.keys(cart);
        if (!keys.length) { alert('Add at least one product or variant to the cart first.'); return; }

        const fields = document.getElementById('printFormFields');
        fields.innerHTML = '';

        keys.forEach((key, i) => {
            const item = cart[key];
            appendHidden(fields, `items[${i}][type]`, item.type);
            appendHidden(fields, `items[${i}][id]`, item.id);
            appendHidden(fields, `items[${i}][qty]`, item.qty);
        });

        appendHidden(fields, 'barcode_type', document.getElementById('barcodeType').value);
        appendHidden(fields, 'label_width', document.getElementById('labelWidth').value);
        appendHidden(fields, 'label_height', document.getElementById('labelHeight').value);
        appendHidden(fields, 'columns', document.getElementById('columns').value);
        appendHidden(fields, 'show_name', document.getElementById('showName').checked ? 1 : 0);
        appendHidden(fields, 'show_price', document.getElementById('showPrice').checked ? 1 : 0);

        document.getElementById('printForm').submit();
    });

    function appendHidden(container, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        container.appendChild(input);
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
})(jQuery);
</script>
@endpush
