@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Stock Transfers
@endsection

@push('styles')
    <style>
        .line-table td, .line-table th { min-width: 0; padding: .4rem 8px; font-size: var(--text-caption); vertical-align: middle; }
        .line-table input { max-width: 120px; margin-left: auto; text-align: right; }
        .line-table .have { font-size: var(--text-micro); color: var(--color-text-muted); }
        .line-table .over { color: var(--color-danger); font-weight: var(--font-weight-medium); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Stock Transfers</h4>
                <small class="text-muted">Send stock to another branch. It leaves the sending branch at once and arrives when the receiving branch confirms it.</small>
            </div>
            <div class="ms-auto text-end">
                <button type="button" class="btn btn-primary" id="newTransfer" @disabled($branches->isEmpty())><i class="bi bi-arrow-left-right"></i> New Transfer</button>
                @if ($branches->isEmpty())
                    <div class="small text-muted mt-1">There is no other active branch to send stock to. Add one in System &rsaquo; Branches.</div>
                @endif
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="transferTable" class="table table-hover align-middle w-100 acc-table" data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" width="4%">SL.</th>
                                <th>No.</th>
                                <th>Date</th>
                                <th>From → To</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Value</th>
                                <th>Status</th>
                                <th data-orderable="false" width="6%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="trTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="transferForm" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="trTitle">New Stock Transfer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="from_branch_id">From <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" value="{{ $activeName }}" readonly aria-label="Sending branch">
                                <input type="hidden" name="from_branch_id" id="from_branch_id" value="{{ $active }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="to_branch_id">To <span class="text-danger">*</span></label>
                                <select name="to_branch_id" id="to_branch_id" class="form-select">
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="transfer_date">Date <span class="text-danger">*</span></label>
                                <input type="date" name="transfer_date" id="transfer_date" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="pick">Add product</label>
                                <select id="pick" class="form-select"><option value="">Search products with stock at the sending branch</option></select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered line-table mb-0">
                                <thead><tr><th>Product</th><th class="text-end">In Stock</th><th class="text-end">Send Qty</th><th style="width: 40px;"></th></tr></thead>
                                <tbody id="lines"><tr class="empty"><td colspan="4" class="text-center text-muted py-3">No products added.</td></tr></tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="tr_remarks">Remarks</label>
                            <input type="text" name="remarks" id="tr_remarks" class="form-control" maxlength="500">
                        </div>
                        <div class="text-danger small mt-2" id="tr_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="trSubmit">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTitle">Transfer</h5>
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
            list: "{{ route('admin.stock.transfers.list') }}",
            products: "{{ route('admin.stock.products') }}",
            store: "{{ route('admin.stock.transfers.store') }}",
            base: "{{ url('admin/stock/transfers') }}"
        };
        const esc = Voucher.esc, money = Voucher.money;
        const qtyText = function(v) { return String(parseFloat(parseFloat(v || 0).toFixed(3))); };
        let catalogue = [], seq = 1000;

        function loadCatalogue() {
            $.ajax({
                url: R.products,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    catalogue = response.data.filter(function(p) { return p.qty > 0; });
                    $('#pick').html('<option value="">Search products with stock at the sending branch</option>' + catalogue.map(function(p, i) {
                        return '<option value="' + i + '">' + esc(p.label) + ' — ' + qtyText(p.qty) + ' in stock</option>';
                    }).join('')).val('').trigger('change.select2');
                    $('#lines').html('<tr class="empty"><td colspan="4" class="text-center text-muted py-3">No products added.</td></tr>');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        const Transfer = {
            table: null,
            reload: function() { Transfer.table.ajax.reload(null, false); },
            view: function(id) {
                $.ajax({
                    url: R.base + '/' + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const t = response.data;
                        $('#viewTitle').text(t.transfer_no + ' — ' + t.transfer_status);
                        $('#viewBody').html(
                            '<p class="text-muted small mb-3">' + esc(t.from_name) + ' → ' + esc(t.to_name) + ' · sent ' + esc(t.transfer_date) +
                            (t.received_date ? ' · received ' + esc(t.received_date) : '') + ' · by ' + esc(t.created_by_name || '-') + '</p>' +
                            '<table class="table table-sm table-bordered line-table"><thead><tr><th>Product</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Unit Cost</th></tr></thead><tbody>' +
                            response.items.map(function(i) {
                                return '<tr><td>' + esc(i.name) + (i.variant ? ' (' + esc(i.variant) + ')' : '') + '</td><td>' + esc(i.batch_no || '-') +
                                    (i.expiry_date ? ' <span class="text-muted">exp ' + esc(i.expiry_date) + '</span>' : '') + '</td><td class="text-end">' + qtyText(i.qty) +
                                    '</td><td class="text-end">' + money(i.unit_cost) + '</td></tr>';
                            }).join('') + '</tbody></table>' + (t.remarks ? '<p class="small">' + esc(t.remarks) + '</p>' : '')
                        );
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                    }
                });
            },
            receive: function(id) {
                Swal.fire({ title: 'Receive this transfer?', text: 'The stock is added to the receiving branch.', showCancelButton: true, confirmButtonText: 'Receive' })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: R.base + '/' + id + '/receive',
                            type: 'POST',
                            dataType: 'json',
                            data: {},
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                Transfer.reload();
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to receive.');
                            }
                        });
                    });
            },
            cancel: function(id) {
                Swal.fire({ title: 'Cancel this transfer?', text: 'The stock goes back to the sending branch.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Cancel transfer', cancelButtonText: 'Keep' })
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
                                Transfer.reload();
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
            Voucher.keepSerial($('#transferTable'));
            Transfer.table = $('#transferTable').DataTable({ ajax: R.list, processing: true });

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('transferModal'));
            Voucher.initSelect2($('#to_branch_id, #pick'), $('#transferModal'));

            $('#newTransfer').on('click', function() {
                $('#tr_error').text('');
                $('#tr_remarks').val('');
                $('#to_branch_id').val($('#to_branch_id option').first().val()).trigger('change.select2');
                loadCatalogue();
                modal.show();
            });

            $('#pick').on('change', function() {
                const p = catalogue[$(this).val()];
                if (!p) return;
                $('#lines .empty').remove();
                const key = p.id + ':' + (p.variant_id || 0);
                if ($('#lines tr[data-key="' + key + '"]').length) { $(this).val('').trigger('change.select2'); return; }
                const n = seq++;
                $('#lines').append('<tr data-key="' + key + '"><td>' + esc(p.label) + (p.track_batch === 'Yes' ? ' <span class="badge bg-light text-dark">batches FEFO</span>' : '') +
                    '<input type="hidden" name="items[' + n + '][product_id]" value="' + p.id + '">' +
                    '<input type="hidden" name="items[' + n + '][product_variant_id]" value="' + (p.variant_id || '') + '"></td>' +
                    '<td class="text-end">' + qtyText(p.qty) + '</td>' +
                    '<td><input type="number" class="form-control form-control-sm qty" name="items[' + n + '][qty]" min="0" step="any" value="1" data-have="' + p.qty + '"></td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger rm" aria-label="Remove"><i class="bi bi-x"></i></button></td></tr>');
                $(this).val('').trigger('change.select2');
            });
            $(document).on('input', '#lines .qty', function() {
                $(this).toggleClass('over', (parseFloat($(this).val()) || 0) > parseFloat($(this).data('have')));
            });
            $(document).on('click', '#lines .rm', function() { $(this).closest('tr').remove(); });

            $('#transferForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#trSubmit').prop('disabled', true).text('Sending...');
                $('#tr_error').text('');
                $.ajax({
                    url: R.store,
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        modal.hide();
                        Transfer.reload();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        $('#tr_error').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save the transfer.');
                    },
                    complete: function() { $btn.prop('disabled', false).text('Send'); }
                });
            });
        });
    </script>
@endpush
