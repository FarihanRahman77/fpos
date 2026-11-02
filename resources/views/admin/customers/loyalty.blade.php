@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Customer Points & Credit
@endsection

@push('styles')
    <style>
        .loy-table td, .loy-table th { min-width: 0; padding: .45rem 10px; font-size: var(--text-caption); vertical-align: middle; }
        .loy-table .num { text-align: right; white-space: nowrap; font-feature-settings: var(--font-feature-numeric); }
        .loy-pos { color: var(--color-success); }
        .loy-neg { color: var(--color-danger); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Customer Points &amp; Credit</h4>
                <small class="text-muted">Loyalty balance and credit limit of every customer.
                    @unless ($enabled) <b>Loyalty is off</b> — <a href="{{ route('admin.customers.settings') }}">turn it on</a>.@endunless</small>
            </div>
            <div class="ms-auto">
                <input type="search" id="search" class="form-control" placeholder="Search name or phone" style="width: 240px;" aria-label="Search customers">
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered loy-table mb-0">
                        <thead><tr><th>Customer</th><th>Type</th><th class="num">Points</th><th class="num">Worth</th><th class="num">Earned</th><th class="num">Redeemed</th><th class="num">Credit Limit</th><th>Last Activity</th><th style="width: 120px;"></th></tr></thead>
                        <tbody id="rows"><tr><td colspan="9" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="histModal" tabindex="-1" aria-labelledby="histTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="histTitle">Points</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="adjForm" class="row g-2 align-items-end mb-3" autocomplete="off">
                        <div class="col-md-3">
                            <label class="form-label" for="adj_points">Add / remove points</label>
                            <input type="number" id="adj_points" name="points" class="form-control" step="1" placeholder="e.g. 50 or -20">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="adj_limit">Credit limit</label>
                            <input type="number" id="adj_limit" name="credit_limit" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="adj_remarks">Reason</label>
                            <input type="text" id="adj_remarks" name="remarks" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Save</button></div>
                    </form>
                    <table class="table table-sm table-bordered loy-table">
                        <thead><tr><th>Date</th><th>Type</th><th>Invoice</th><th class="num">Points</th><th class="num">Balance</th><th class="num">Amount</th><th>Note</th></tr></thead>
                        <tbody id="histRows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const esc = Voucher.esc, money = Voucher.money;
        let all = [], pointValue = 1, current = null;

        function render() {
            const q = $('#search').val().toLowerCase();
            const rows = all.filter(function(c) { return !q || (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q); });
            $('#rows').html(rows.length ? rows.map(function(c) {
                return '<tr><td><span class="fw-semibold">' + esc(c.name) + '</span><div class="text-muted small">' + esc(c.phone || '') + '</div></td><td>' + esc(c.type) + '</td>' +
                    '<td class="num fw-semibold">' + parseFloat(c.loyalty_points) + '</td><td class="num">' + money(c.loyalty_points * pointValue) + '</td>' +
                    '<td class="num">' + parseFloat(c.earned) + '</td><td class="num">' + parseFloat(c.redeemed) + '</td>' +
                    '<td class="num">' + (parseFloat(c.credit_limit) ? money(c.credit_limit) : '<span class="text-muted">none</span>') + '</td>' +
                    '<td>' + esc(c.last_at || '-') + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-secondary open" data-id="' + c.id + '">History / adjust</button></td></tr>';
            }).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">No customers.</td></tr>');
        }

        function load() {
            $.ajax({
                url: "{{ route('admin.customers.loyalty.list') }}",
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    all = response.data;
                    pointValue = response.point_value;
                    render();
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        function openHistory(id) {
            current = all.find(function(c) { return c.id == id; });
            $('#histTitle').text(current.name + ' — ' + parseFloat(current.loyalty_points) + ' points');
            $('#adj_points').val('');
            $('#adj_remarks').val('');
            $('#adj_limit').val(parseFloat(current.credit_limit) || '');
            $.ajax({
                url: "{{ url('admin/customer-programs/loyalty') }}/" + id,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    $('#histRows').html(response.data.length ? response.data.map(function(t) {
                        const p = parseFloat(t.points);
                        return '<tr><td>' + esc((t.created_at || '').slice(0, 16)) + '</td><td>' + esc(t.txn_type) + '</td><td>' + esc(t.invoice_no || '-') + '</td>' +
                            '<td class="num ' + (p >= 0 ? 'loy-pos' : 'loy-neg') + '">' + (p > 0 ? '+' : '') + p + '</td><td class="num">' + parseFloat(t.balance_after) + '</td>' +
                            '<td class="num">' + money(t.amount) + '</td><td>' + esc(t.remarks || '') + (t.by_name ? ' <span class="text-muted">· ' + esc(t.by_name) + '</span>' : '') + '</td></tr>';
                    }).join('') : '<tr><td colspan="7" class="text-center text-muted">No points activity yet.</td></tr>');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('histModal')).show();
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        $(document).ready(function() {
            $('#search').on('input', render);
            $(document).on('click', '.open', function() { openHistory($(this).data('id')); });
            $('#adjForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{ url('admin/customer-programs/loyalty') }}/" + current.id + '/adjust',
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        load();
                        setTimeout(function() { openHistory(current.id); }, 300);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to save.');
                    }
                });
            });
            load();
        });
    </script>
@endpush
