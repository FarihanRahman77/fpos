@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Stock Count & Adjustments
@endsection

@push('styles')
    <style>
        .line-table td, .line-table th { min-width: 0; padding: .4rem 8px; font-size: var(--text-caption); vertical-align: middle; }
        .line-table input.num { max-width: 110px; margin-left: auto; text-align: right; }
        .line-table .diff-up { color: var(--color-success); font-weight: var(--font-weight-medium); }
        .line-table .diff-down { color: var(--color-danger); font-weight: var(--font-weight-medium); }
        .adj-help { font-size: var(--text-caption); color: var(--color-text-muted); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Stock Count &amp; Adjustments</h4>
                <small class="text-muted">Count shelves and post the differences, or write off damaged, expired or lost stock. Working in <b>{{ $branchName }}</b>.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" data-new="Stock Count"><i class="bi bi-clipboard-check"></i> New Stock Count</button>
                <button type="button" class="btn btn-primary" data-new="Damage"><i class="bi bi-plus-lg"></i> New Adjustment</button>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="adjTable" class="table table-hover align-middle w-100 acc-table" data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" width="4%">SL.</th>
                                <th>No.</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th class="text-end">In</th>
                                <th class="text-end">Out</th>
                                <th class="text-end">Value Change</th>
                                <th>Status</th>
                                <th data-orderable="false" width="6%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adjModal" tabindex="-1" aria-labelledby="adjTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="adjForm" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="adjTitle">Stock Count</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adj_id">
                        <div class="row g-3 mb-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label" for="adjustment_type">Type</label>
                                <select name="adjustment_type" id="adjustment_type" class="form-select">
                                    @foreach ($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="adjustment_date">Date</label>
                                <input type="date" name="adjustment_date" id="adjustment_date" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                            <div class="col-md-3 count-only">
                                <label class="form-label" for="category_id">Category</label>
                                <select id="category_id" class="form-select">
                                    <option value="">All categories</option>
                                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-3 count-only">
                                <button type="button" class="btn btn-outline-secondary w-100" id="loadSheet"><i class="bi bi-list-check"></i> Load count sheet</button>
                            </div>
                            <div class="col-md-6 adj-only">
                                <label class="form-label" for="pick">Add product</label>
                                <select id="pick" class="form-select"><option value="">Search products</option></select>
                            </div>
                        </div>
                        <p class="adj-help count-only">Enter what you count on the shelf. Leave blank to skip a line. Difference = counted − system; posting adds or removes the difference.</p>
                        <p class="adj-help adj-only">“Found” adds stock; Damage, Expired, Lost and Correction remove it. For batch-tracked products choose the batch the stock leaves (or is found in).</p>
                        <div class="table-responsive">
                            <table class="table table-bordered line-table mb-0">
                                <thead id="linesHead"></thead>
                                <tbody id="lines"></tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="adj_remarks">Remarks</label>
                            <input type="text" name="remarks" id="adj_remarks" class="form-control" maxlength="500">
                        </div>
                        <div class="text-danger small mt-2" id="adj_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-outline-primary" id="saveDraft">Save draft</button>
                        <button type="button" class="btn btn-primary" id="postAdj">Post to stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTitle">Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const R = {
            list: "{{ route('admin.stock.adjustments.list') }}",
            products: "{{ route('admin.stock.products') }}",
            store: "{{ route('admin.stock.adjustments.store') }}",
            base: "{{ url('admin/stock/adjustments') }}"
        };
        const esc = Voucher.esc, money = Voucher.money;
        const q = function(v) { return String(parseFloat(parseFloat(v || 0).toFixed(3))); };
        let catalogue = [], seq = 1000;

        function isCount() { return $('#adjustment_type').val() === 'Stock Count'; }

        function setMode() {
            $('.count-only').toggleClass('d-none', !isCount());
            $('.adj-only').toggleClass('d-none', isCount());
            $('#linesHead').html(isCount()
                ? '<tr><th>Product</th><th class="text-end">System</th><th class="text-end">Counted</th><th class="text-end">Difference</th><th>Note</th></tr>'
                : '<tr><th>Product</th><th class="text-end">In Stock</th><th class="text-end">Qty</th><th>Note</th><th style="width: 40px;"></th></tr>');
            $('#lines').html('<tr class="empty"><td colspan="5" class="text-center text-muted py-3">' + (isCount() ? 'Load the count sheet.' : 'Add products.') + '</td></tr>');
        }

        function line(p, n, values) {
            values = values || {};
            const hidden = '<input type="hidden" name="items[' + n + '][product_id]" value="' + p.id + '"><input type="hidden" name="items[' + n + '][product_variant_id]" value="' + (p.variant_id || '') + '">';
            const note = '<input type="text" class="form-control form-control-sm" name="items[' + n + '][note]" maxlength="255" value="' + esc(values.note || '') + '">';
            if (isCount()) {
                return '<tr data-sys="' + p.qty + '"><td>' + esc(p.label) + hidden + '</td><td class="text-end">' + q(p.qty) + '</td>' +
                    '<td><input type="number" class="form-control form-control-sm num counted" name="items[' + n + '][counted_qty]" min="0" step="any" value="' + (values.counted ?? '') + '"></td>' +
                    '<td class="text-end diff">-</td><td>' + note + '</td></tr>';
            }
            let batch = '';
            if (p.track_batch === 'Yes') {
                const found = $('#adjustment_type').val() === 'Found';
                batch = '<div class="mt-1"><label class="form-label small mb-0" for="batch_' + n + '">Batch' + (found ? '' : ' <span class="text-danger">*</span>') + '</label>' +
                    '<select class="form-select form-select-sm batch-pick" id="batch_' + n + '" name="items[' + n + '][batch_id]">' +
                    '<option value="">' + (found ? 'No batch (new stock)' : 'Choose batch') + '</option>' +
                    (p.batches || []).map(function(b) {
                        return '<option value="' + b.id + '" data-qty="' + b.qty + '"' + (values.batch_id == b.id ? ' selected' : '') + '>' + esc(b.batch_no) +
                            (b.expiry_date ? ' · exp ' + esc(b.expiry_date) : '') + ' · ' + q(b.qty) + ' in stock</option>';
                    }).join('') + '</select></div>';
            }
            return '<tr data-total="' + p.qty + '"><td>' + esc(p.label) + hidden + batch + '</td><td class="text-end in-stock">' + q(p.qty) + '</td>' +
                '<td><input type="number" class="form-control form-control-sm num" name="items[' + n + '][qty]" min="0" step="any" value="' + (values.qty ?? 1) + '"></td>' +
                '<td>' + note + '</td><td><button type="button" class="btn btn-sm btn-outline-danger rm" aria-label="Remove"><i class="bi bi-x"></i></button></td></tr>';
        }

        function loadCatalogue(done) {
            $.ajax({
                url: R.products,
                type: 'GET',
                dataType: 'json',
                data: { category_id: $('#category_id').val() },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    catalogue = response.data;
                    $('#pick').html('<option value="">Search products</option>' + catalogue.map(function(p, i) {
                        return '<option value="' + i + '">' + esc(p.label) + ' — ' + q(p.qty) + ' in stock</option>';
                    }).join(''));
                    if (done) done();
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        function diffs() {
            $('#lines tr[data-sys]').each(function() {
                const v = $(this).find('.counted').val();
                const $d = $(this).find('.diff').removeClass('diff-up diff-down');
                if (v === '') { $d.text('-'); return; }
                const d = parseFloat(v) - parseFloat($(this).data('sys'));
                $d.text((d > 0 ? '+' : '') + q(d)).addClass(d > 0 ? 'diff-up' : (d < 0 ? 'diff-down' : ''));
            });
        }

        function submit(post) {
            if (!isCount() && $('#adjustment_type').val() !== 'Found') {
                const missing = $('#lines .batch-pick').filter(function() { return !$(this).val(); });
                if (missing.length) {
                    $('#adj_error').text('Choose the batch for every batch-tracked product.');
                    missing.first().trigger('focus');
                    return;
                }
            }
            const $btns = $('#saveDraft, #postAdj').prop('disabled', true);
            $('#adj_error').text('');
            // Count sheet: send only lines with a count.
            const $form = $('#adjForm');
            const skip = $('#lines tr[data-sys]').filter(function() { return $(this).find('.counted').val() === ''; }).find('input').prop('disabled', true);
            $.ajax({
                url: R.store,
                type: 'POST',
                dataType: 'json',
                data: $form.serialize() + (post ? '&post=1' : ''),
                success: function(response) {
                    // alert(JSON.stringify(response));
                    toastr.success(response.message);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('adjModal')).hide();
                    Adjust.table.ajax.reload(null, false);
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    $('#adj_error').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save.');
                },
                complete: function() { skip.prop('disabled', false); $btns.prop('disabled', false); }
            });
        }

        const Adjust = {
            table: null,
            open: function(type) {
                $('#adjForm')[0].reset();
                $('#adj_id').val('');
                $('#adjustment_type').val(type);
                $('#adjTitle').text(type === 'Stock Count' ? 'Stock Count' : 'Stock Adjustment');
                setMode();
                loadCatalogue();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('adjModal')).show();
            },
            view: function(id) {
                $.ajax({
                    url: R.base + '/' + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const a = response.data;
                        $('#viewTitle').text(a.adjustment_no + ' — ' + a.adjustment_type + ' (' + a.adjustment_status + ')');
                        $('#viewBody').html('<p class="text-muted small">' + esc(a.branch) + ' · ' + esc(a.adjustment_date) + (a.remarks ? ' · ' + esc(a.remarks) : '') + '</p>' +
                            '<table class="table table-sm table-bordered line-table"><thead><tr><th>Product</th><th class="text-end">System</th><th class="text-end">Counted</th><th class="text-end">Difference</th><th class="text-end">Value</th><th>Note</th></tr></thead><tbody>' +
                            response.items.map(function(i) {
                                const d = parseFloat(i.difference);
                                return '<tr><td>' + esc(i.name) + (i.variant ? ' (' + esc(i.variant) + ')' : '') + (i.batch_no ? '<div class="text-muted small">Batch ' + esc(i.batch_no) + '</div>' : '') + '</td><td class="text-end">' + q(i.system_qty) + '</td><td class="text-end">' +
                                    (i.counted_qty === null ? '-' : q(i.counted_qty)) + '</td><td class="text-end ' + (d > 0 ? 'diff-up' : (d < 0 ? 'diff-down' : '')) + '">' + (d > 0 ? '+' : '') + q(d) +
                                    '</td><td class="text-end">' + money(d * parseFloat(i.unit_cost)) + '</td><td>' + esc(i.note || '') + '</td></tr>';
                            }).join('') + '</tbody></table>');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                    }
                });
            },
            edit: function(id) {
                $.ajax({
                    url: R.base + '/' + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const a = response.data;
                        Adjust.open(a.adjustment_type);
                        $('#adj_id').val(a.id);
                        $('#adjustment_date').val(a.adjustment_date);
                        $('#adj_remarks').val(a.remarks || '');
                        loadCatalogue(function() {
                            $('#lines').html(response.items.map(function(i, n) {
                                const p = catalogue.find(function(c) { return c.id == i.product_id && (c.variant_id || null) == (i.product_variant_id || null); })
                                    || { id: i.product_id, variant_id: i.product_variant_id, label: i.name, qty: i.system_qty };
                                return line(p, n, { counted: i.counted_qty, qty: Math.abs(i.difference), note: i.note, batch_id: i.batch_id });
                            }).join(''));
                            diffs();
                        });
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                    }
                });
            },
            cancel: function(id) {
                Swal.fire({ title: 'Cancel this adjustment?', text: 'Posted stock movements are reversed.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Cancel it', cancelButtonText: 'Keep' })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: R.base + '/' + id + '/cancel',
                            type: 'POST',
                            dataType: 'json',
                            data: {},
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                Adjust.table.ajax.reload(null, false);
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to cancel.');
                            }
                        });
                    });
            }
        };

        $(document).ready(function() {
            Voucher.keepSerial($('#adjTable'));
            Adjust.table = $('#adjTable').DataTable({ ajax: R.list, processing: true });
            Voucher.initSelect2($('#pick, #category_id'), $('#adjModal'));

            $('[data-new]').on('click', function() { Adjust.open($(this).data('new')); });
            $('#adjustment_type').on('change', setMode);

            $('#loadSheet').on('click', function() {
                loadCatalogue(function() {
                    $('#lines').html(catalogue.length ? catalogue.map(function(p, n) { return line(p, n); }).join('')
                        : '<tr><td colspan="5" class="text-center text-muted py-3">No products.</td></tr>');
                });
            });
            $(document).on('input', '#lines .counted', diffs);

            $('#pick').on('change', function() {
                const p = catalogue[$(this).val()];
                if (!p) return;
                $('#lines .empty').remove();
                $('#lines').append(line(p, seq++));
                $(this).val('').trigger('change.select2');
            });
            $(document).on('click', '#lines .rm', function() { $(this).closest('tr').remove(); });
            $(document).on('change', '#lines .batch-pick', function() {
                const $tr = $(this).closest('tr'), $o = $(this).find('option:selected');
                $tr.find('.in-stock').text(q($(this).val() ? $o.data('qty') : $tr.data('total')));
            });

            $('#saveDraft').on('click', function() { submit(false); });
            $('#postAdj').on('click', function() { submit(true); });
        });
    </script>
@endpush
