@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Batches & Expiry
@endsection

@push('styles')
    <style>
        .exp-table td, .exp-table th { min-width: 0; padding: .45rem 10px; font-size: var(--text-caption); vertical-align: middle; }
        .exp-pill { display: inline-block; padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-size: var(--text-micro); font-weight: var(--font-weight-medium); white-space: nowrap; }
        .exp-pill.bad { background: var(--color-danger-soft); color: var(--color-danger); }
        .exp-pill.warn { background: var(--color-warning-soft); color: var(--color-warning); }
        .exp-pill.ok { background: var(--color-success-soft); color: var(--color-success); }
        .exp-pill.none { background: var(--color-surface-soft); color: var(--color-text-muted); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Batches &amp; Expiry</h4>
                <small class="text-muted">Stock of batch-tracked products by batch. Sales take the earliest expiry first. Turn tracking on per product (Products › Track batches) and enter batch / expiry on purchases.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <select id="state" class="form-select" style="width: 170px;" aria-label="Show">
                    <option value="">All batches in stock</option>
                    <option value="soon">Expiring soon</option>
                    <option value="expired">Expired</option>
                </select>
                <input type="number" id="days" class="form-control" style="width: 110px;" value="30" min="1" max="365" aria-label="Days ahead" title="Days ahead for “expiring soon”">
                <a href="{{ route('admin.stock.adjustments') }}" class="btn btn-outline-secondary"><i class="bi bi-trash3"></i> Write off</a>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-4"><div class="card acc-stat"><div class="card-body"><div class="text-muted small">Expired batches</div><h5 class="mb-0 text-danger" id="sumExpired">0</h5></div></div></div>
            <div class="col-6 col-lg-4"><div class="card acc-stat"><div class="card-body"><div class="text-muted small">Expiring within <span id="sumDays">30</span> days</div><h5 class="mb-0 text-warning" id="sumSoon">0</h5></div></div></div>
            <div class="col-12 col-lg-4"><div class="card acc-stat"><div class="card-body"><div class="text-muted small">Value of expired stock (at cost)</div><h5 class="mb-0" id="sumValue">0.00</h5></div></div></div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered exp-table mb-0">
                        <thead><tr><th>Product</th><th>Batch</th><th>Branch</th><th>Mfg.</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">Value</th><th style="width: 60px;">Action</th></tr></thead>
                        <tbody id="rows"><tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="expiryModal" tabindex="-1" aria-labelledby="expiryTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="expiryForm" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="expiryTitle">Edit batch dates</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="exp_id">
                        <p class="text-muted small mb-3" id="expInfo"></p>
                        <div class="mb-3">
                            <label class="form-label" for="exp_expiry">Expiry date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="exp_expiry" name="expiry_date" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="exp_mfg">Manufacture date</label>
                            <input type="date" class="form-control" id="exp_mfg" name="manufacture_date">
                        </div>
                        <div class="text-danger small mt-2" id="exp_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="expSave">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const esc = Voucher.esc, money = Voucher.money;
        let batchRows = [];

        function editExpiry(i) {
            const b = batchRows[i];
            if (!b) return;
            $('#exp_id').val(b.id);
            $('#exp_expiry').val(b.expiry_date || '');
            $('#exp_mfg').val(b.manufacture_date || '');
            $('#exp_error').text('');
            $('#expInfo').text(b.product + (b.variant ? ' (' + b.variant + ')' : '') + ' · batch ' + b.batch_no);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('expiryModal')).show();
        }

        function load() {
            const days = $('#days').val() || 30;
            $('#sumDays').text(days);
            $.ajax({
                url: "{{ route('admin.stock.batches.list') }}",
                type: 'GET',
                dataType: 'json',
                data: { state: $('#state').val(), days: days },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    $('#sumExpired').text(response.summary.expired);
                    $('#sumSoon').text(response.summary.soon);
                    $('#sumValue').text(money(response.summary.value));
                    batchRows = response.data;
                    $('#rows').html(response.data.length ? response.data.map(function(b, i) {
                        let pill;
                        if (b.days_left === null) pill = '<span class="exp-pill none">No expiry</span>';
                        else if (b.days_left < 0) pill = '<span class="exp-pill bad">Expired ' + (-b.days_left) + ' d ago</span>';
                        else if (b.days_left <= days) pill = '<span class="exp-pill warn">' + b.days_left + ' d left</span>';
                        else pill = '<span class="exp-pill ok">' + b.days_left + ' d left</span>';
                        return '<tr><td>' + esc(b.product) + (b.variant ? ' (' + esc(b.variant) + ')' : '') + '</td><td class="fw-semibold">' + esc(b.batch_no) + '</td>' +
                            '<td>' + esc(b.branch) + '</td><td>' + esc(b.manufacture_date || '-') + '</td><td>' + esc(b.expiry_date || '-') + ' ' + pill + '</td>' +
                            '<td class="text-end">' + parseFloat(parseFloat(b.qty).toFixed(3)) + '</td><td class="text-end">' + money(b.value) + '</td>' +
                            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="editExpiry(' + i + ')" title="Edit expiry" aria-label="Edit expiry of batch ' + esc(b.batch_no) + '"><i class="bi bi-calendar-event"></i></button></td></tr>';
                    }).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No batches to show.</td></tr>');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        $(document).ready(function() {
            $('#state, #days').on('change', load);
            $('#expiryForm').on('submit', function(e) {
                e.preventDefault();
                $('#exp_error').text('');
                if (!$('#exp_expiry').val()) { $('#exp_error').text('Enter the expiry date.'); return; }
                $('#expSave').prop('disabled', true);
                $.ajax({
                    url: "{{ url('admin/stock/batches') }}/" + $('#exp_id').val() + '/expiry',
                    type: 'POST',
                    dataType: 'json',
                    data: { _token: "{{ csrf_token() }}", expiry_date: $('#exp_expiry').val(), manufacture_date: $('#exp_mfg').val() },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('expiryModal')).hide();
                        load();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        $('#exp_error').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save.');
                    },
                    complete: function() { $('#expSave').prop('disabled', false); }
                });
            });
            load();
        });
    </script>
@endpush
