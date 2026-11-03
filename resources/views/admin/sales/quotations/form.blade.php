@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@php $editing = (bool) $quotation; @endphp

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - {{ $editing ? 'Re-price ' . $quotation->quotation_no : 'New Quotation' }}
@endsection

@push('styles')
    <style>
        .q-lines td, .q-lines th { vertical-align: middle; }
        .q-lines tbody td { min-width: 0; padding: var(--space-sm); }
        .q-thumb { width: 44px; height: 44px; border-radius: var(--radius-md); object-fit: cover; background: var(--color-surface-soft); border: 1px solid var(--color-border); flex: 0 0 auto; }
        .q-thumb-empty { display: inline-flex; align-items: center; justify-content: center; color: var(--color-text-muted); }
        .q-was { font-size: var(--text-micro); color: var(--color-text-muted); }
        .q-was.up { color: var(--color-danger); }
        .q-was.down { color: var(--color-success); }
        .q-totals td { padding: var(--space-xs) 0; }
        .q-grand { font-size: var(--text-heading-md); font-weight: var(--font-weight-light); font-feature-settings: var(--font-feature-numeric); }
        .q-empty { border: 1px dashed var(--color-border-input); border-radius: var(--radius-lg); padding: var(--space-xl); text-align: center; color: var(--color-text-muted); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">{{ $editing ? 'Re-price ' . $quotation->quotation_no : 'New Quotation' }}</h4>
                <small class="text-muted">
                    @if ($editing)
                        Now at revision {{ $quotation->revision_no }}. Changing products, prices, quantities, discount, VAT or validity saves revision {{ $quotation->revision_no + 1 }}; the old prices stay in the history.
                    @else
                        Pick the customer and add products. Prices start at each product's sale price and can be changed.
                    @endif
                </small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ $editing ? route('admin.quotations.show', $quotation->id) : route('admin.quotations.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <form id="quoteForm" autocomplete="off" novalidate>
            <input type="hidden" name="id" value="{{ $quotation->id ?? '' }}">

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card acc-card mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                                    <select name="customer_id" id="customer_id" class="form-select">
                                        @if ($customer)<option value="{{ $customer->id }}" selected>{{ $customer->name }}{{ $customer->phone ? ' · ' . $customer->phone : '' }}</option>@endif
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="title">Subject</label>
                                    <input type="text" name="title" id="title" class="form-control" maxlength="200" value="{{ $quotation->title ?? '' }}" placeholder="e.g. Office phones for Q4">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label" for="quotation_date">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="quotation_date" id="quotation_date" class="form-control" value="{{ $quotation->quotation_date ?? now()->toDateString() }}">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label" for="valid_until">Valid Until</label>
                                    <input type="date" name="valid_until" id="valid_until" class="form-control"
                                        value="{{ $editing ? $quotation->valid_until : now()->addDays($defaults['valid_days'])->toDateString() }}">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                @if ($editing)
                                    <div class="col-md-6">
                                        <label class="form-label" for="reason">Reason for re-pricing</label>
                                        <input type="text" name="reason" id="reason" class="form-control" maxlength="255" placeholder="e.g. Customer asked for a better price on 10+ units">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card acc-card mb-3">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                                <div class="flex-fill" style="min-width: 260px;">
                                    <label class="form-label" for="productPicker">Add product</label>
                                    <select id="productPicker" class="form-select"></select>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table q-lines mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th style="width: 110px;" class="text-end">Qty</th>
                                            <th style="width: 140px;" class="text-end">Unit Price</th>
                                            <th style="width: 120px;" class="text-end">Discount</th>
                                            <th style="width: 130px;" class="text-end">Amount</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="lines"></tbody>
                                </table>
                            </div>
                            <div class="q-empty mt-2" id="noLines">Search a product above to add it to the quotation.</div>
                            <div class="text-danger small mt-2" id="lines_error"></div>
                        </div>
                    </div>

                    <div class="card acc-card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="notes">Note to customer</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="4" maxlength="2000" placeholder="Shown on the quotation">{{ $quotation->notes ?? '' }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="terms">Terms &amp; conditions</label>
                                    <textarea name="terms" id="terms" class="form-control" rows="4" maxlength="5000">{{ $editing ? $quotation->terms : $defaults['terms'] }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card acc-card" style="position: sticky; top: var(--space-lg);">
                        <div class="card-body">
                            <h6 class="mb-3">Summary</h6>
                            <table class="w-100 q-totals">
                                <tr><td class="text-muted">Subtotal</td><td class="text-end" id="tSubtotal">0.00</td></tr>
                                <tr>
                                    <td class="text-muted"><label for="discount_amount">Discount</label></td>
                                    <td class="text-end" style="width: 140px;"><input type="number" step="0.01" min="0" name="discount_amount" id="discount_amount"
                                        class="form-control form-control-sm text-end" value="{{ (float) ($quotation->discount_amount ?? 0) ?: '' }}" placeholder="0.00"></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><label for="tax_percent">VAT %</label></td>
                                    <td class="text-end"><input type="number" step="0.01" min="0" max="100" name="tax_percent" id="tax_percent"
                                        class="form-control form-control-sm text-end" value="{{ (float) $defaults['tax_percent'] ?: '' }}" placeholder="0"></td>
                                </tr>
                                <tr><td class="text-muted">VAT</td><td class="text-end" id="tTax">0.00</td></tr>
                            </table>
                            <hr>
                            <div class="d-flex align-items-end justify-content-between">
                                <span class="text-muted">Total</span>
                                <span class="q-grand" id="tGrand">0.00</span>
                            </div>
                            @if ($editing)
                                <div class="small mt-1 text-end" id="tChange"></div>
                            @endif
                            <div class="text-danger small mt-3" id="form_error"></div>
                            <button type="submit" class="btn btn-primary w-100 mt-3" id="saveBtn">
                                <i class="fas fa-save"></i> {{ $editing ? 'Save Re-price' : 'Create Quotation' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        const imgBase = "{{ asset('uploads/products') }}/";
        const variantUrl = "{{ url('admin/sales/products/variants') }}/";
        const existing = @json($items);
        const oldTotal = {{ $editing ? (float) $quotation->grand_total : 'null' }};
        let lineIndex = 0;

        function money(v) { return Voucher.money(v); }

        function thumb(image) {
            return image ? '<img src="' + imgBase + Voucher.esc(image) + '" class="q-thumb" alt="">'
                : '<span class="q-thumb q-thumb-empty"><i class="fas fa-box"></i></span>';
        }

        // One quotation line. d: product_id, product_variant_id, name, image, sku, qty, unit_price, discount, description, was
        function addLine(d) {
            $('#lines_error').text(''); // a line now exists, so "Add at least one product." no longer applies
            const i = lineIndex++;
            const $row = $('<tr class="q-line"></tr>').attr('data-i', i);
            $row.html(
                '<td><div class="d-flex gap-2 align-items-start">' + thumb(d.image) + '<div class="flex-fill">' +
                    '<div class="fw-semibold">' + Voucher.esc(d.name) + '</div>' +
                    (d.sku ? '<div class="text-muted small">' + Voucher.esc(d.sku) + '</div>' : '') +
                    '<input type="hidden" name="items[' + i + '][product_id]" value="' + d.product_id + '">' +
                    '<select name="items[' + i + '][product_variant_id]" class="form-select form-select-sm mt-1 q-variant d-none"><option value="">No variant</option></select>' +
                    '<input type="text" name="items[' + i + '][description]" class="form-control form-control-sm mt-1" maxlength="500" placeholder="Description (optional)" value="' + Voucher.esc(d.description || '') + '">' +
                    '<div class="text-danger small field-error"></div>' +
                '</div></div></td>' +
                '<td><input type="number" step="any" min="0.001" name="items[' + i + '][qty]" class="form-control form-control-sm text-end q-qty" value="' + (d.qty || 1) + '"><div class="text-danger small field-error"></div></td>' +
                '<td><input type="number" step="0.01" min="0" name="items[' + i + '][unit_price]" class="form-control form-control-sm text-end q-price" value="' + (d.unit_price || 0) + '">' +
                    (d.was !== undefined ? '<div class="q-was text-end" data-was="' + d.was + '"></div>' : '') + '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="number" step="0.01" min="0" name="items[' + i + '][discount]" class="form-control form-control-sm text-end q-disc" value="' + (parseFloat(d.discount) > 0 ? d.discount : '') + '" placeholder="0.00"><div class="text-danger small field-error"></div></td>' +
                '<td class="text-end fw-semibold q-amount">0.00</td>' +
                '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger q-remove" title="Remove" aria-label="Remove line"><i class="fas fa-times"></i></button></td>'
            );
            $('#lines').append($row);

            // Variants: load them; choosing one takes its sale price.
            $.ajax({
                url: variantUrl + d.product_id,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    const variants = response.variants || [];
                    if (!variants.length) return;
                    const $v = $row.find('.q-variant').removeClass('d-none');
                    variants.forEach(function(v) {
                        $v.append($('<option>').val(v.id).text(v.attribute_name || ('Variant #' + v.id)).attr('data-price', v.sale_price));
                    });
                    $v.val(d.product_variant_id ? String(d.product_variant_id) : '');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });

            recalc();
        }

        function recalc() {
            let subtotal = 0;
            $('#lines .q-line').each(function() {
                const $r = $(this);
                const qty = parseFloat($r.find('.q-qty').val()) || 0;
                const price = parseFloat($r.find('.q-price').val()) || 0;
                const disc = parseFloat($r.find('.q-disc').val()) || 0;
                const amount = qty * price - disc;
                subtotal += amount;
                $r.find('.q-amount').text(money(amount));

                const $was = $r.find('.q-was');
                if ($was.length) {
                    const was = parseFloat($was.data('was'));
                    if (Math.abs(was - price) > 0.004) {
                        const pct = was > 0 ? (price - was) / was * 100 : 0;
                        $was.toggleClass('up', price > was).toggleClass('down', price < was)
                            .text('was ' + money(was) + ' (' + (pct > 0 ? '+' : '') + pct.toFixed(1) + '%)');
                    } else {
                        $was.removeClass('up down').text('unchanged');
                    }
                }
            });

            const discount = parseFloat($('#discount_amount').val()) || 0;
            const taxPct = parseFloat($('#tax_percent').val()) || 0;
            const tax = Math.max(subtotal - discount, 0) * taxPct / 100;
            const grand = subtotal - discount + tax;

            $('#tSubtotal').text(money(subtotal));
            $('#tTax').text(money(tax));
            $('#tGrand').text(money(grand));
            $('#noLines').toggleClass('d-none', $('#lines .q-line').length > 0);

            if (oldTotal !== null) {
                const diff = grand - oldTotal;
                $('#tChange').html(Math.abs(diff) < 0.005 ? '<span class="text-muted">Same as revision total ' + money(oldTotal) + '</span>'
                    : '<span class="' + (diff > 0 ? 'text-danger' : 'text-success') + '">' + (diff > 0 ? '▲ +' : '▼ −') + money(Math.abs(diff)) +
                      ' vs ' + money(oldTotal) + '</span>');
            }
        }

        $(document).ready(function() {
            $('#customer_id').select2({
                theme: 'bootstrap-5', width: '100%', placeholder: 'Search customer by name, phone or code',
                ajax: { url: "{{ route('admin.quotations.customers') }}", dataType: 'json', delay: 250, data: function(p) { return { q: p.term || '' }; } }
            });

            $('#productPicker').select2({
                theme: 'bootstrap-5', width: '100%', placeholder: 'Search product by name, SKU or barcode',
                ajax: {
                    url: "{{ route('sales.products.search') }}", dataType: 'json', delay: 250,
                    data: function(p) { return { search: p.term || '' }; },
                    processResults: function(res) {
                        return { results: (res.products || []).map(function(p) {
                            return { id: p.id, text: p.name + (p.sku ? ' · ' + p.sku : '') + ' — ' + money(p.sale_price), product: p };
                        }) };
                    }
                },
                templateResult: function(item) {
                    if (!item.product) return item.text;
                    return $('<div class="d-flex align-items-center gap-2">' + thumb(item.product.image) +
                        '<div><div>' + Voucher.esc(item.product.name) + '</div><div class="small text-muted">' + money(item.product.sale_price) +
                        ' · stock ' + (parseFloat(item.product.current_stock) || 0) +
                        (item.product.in_stock === false ? ' <span class="badge bg-danger-subtle text-danger-emphasis">Out of stock</span>' : '') +
                        '</div></div></div>');
                }
            }).on('select2:select', function(e) {
                const p = e.params.data.product;
                addLine({ product_id: p.id, name: p.name, image: p.image, sku: p.sku, qty: 1, unit_price: p.sale_price });
                $(this).val(null).trigger('change');
            });

            existing.forEach(function(l) {
                addLine({
                    product_id: l.product_id, product_variant_id: l.product_variant_id, name: l.item_name.split(' — ')[0], image: l.image, sku: l.sku,
                    qty: parseFloat(l.qty), unit_price: l.unit_price, discount: l.discount, description: l.description, was: parseFloat(l.unit_price)
                });
            });
            recalc();

            $('#lines').on('input', '.q-qty, .q-price, .q-disc', recalc)
                .on('change', '.q-variant', function() {
                    const price = $(this).find('option:selected').data('price');
                    if (price !== undefined) $(this).closest('tr').find('.q-price').val(price);
                    recalc();
                })
                .on('click', '.q-remove', function() { $(this).closest('tr').remove(); recalc(); });
            $('#discount_amount, #tax_percent').on('input', recalc);

            $('#quoteForm').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                Voucher.clearErrors($form);
                $('#form_error, #lines_error').text('');
                if (!$('#lines .q-line').length) {
                    $('#lines_error').text('Add at least one product.');
                    return;
                }
                // Quantity must be more than zero on every line (the server refuses it too).
                let badQty = 0;
                $('#lines .q-line').each(function() {
                    const $q = $(this).find('.q-qty');
                    const qty = parseFloat($q.val());
                    const bad = !(qty > 0);
                    $q.toggleClass('is-invalid', bad).next('.field-error').text(bad ? 'Quantity must be more than zero.' : '');
                    if (bad) badQty++;
                });
                if (badQty) {
                    $('#lines_error').text(badQty + (badQty === 1 ? ' line has' : ' lines have') + ' a quantity of zero or less. Enter a quantity more than zero.');
                    $('#lines .q-qty.is-invalid').first().trigger('focus');
                    return;
                }
                const $btn = $('#saveBtn').prop('disabled', true);
                $.ajax({
                    url: "{{ route('admin.quotations.store') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $form.serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        window.location.href = response.redirect;
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors, $('#form_error'));
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            $('#form_error').text(xhr.responseJSON.message);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save.');
                        }
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endpush
