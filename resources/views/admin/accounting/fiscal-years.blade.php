@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Fiscal Years
@endsection

@push('styles')
    <style>
        .fy-table td, .fy-table th { vertical-align: middle; }
        .fy-num { text-align: right; white-space: nowrap; font-feature-settings: var(--font-feature-numeric); }
        .fy-preview { background: var(--color-surface-soft); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-md) var(--space-lg); }
        .fy-preview dl { margin: 0; display: grid; grid-template-columns: 1fr auto; gap: var(--space-xxs) var(--space-lg); }
        .fy-preview dt { font-weight: var(--font-weight-regular); color: var(--color-text-secondary); }
        .fy-preview dd { margin: 0; text-align: right; font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); }
        .fy-preview .fy-net { border-top: 1px solid var(--color-border); padding-top: var(--space-xxs); color: var(--color-text); }
        .fy-ledgers { max-height: 220px; overflow: auto; font-size: var(--text-caption); }
        .fy-ledgers td { padding: var(--space-xxs) var(--space-sm); min-width: 0; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Fiscal Years</h4>
                <small class="text-muted">Year-end closing moves each year's Income and Expense ledger balances into a retained earnings ledger. Years are closed in order; only the last closed year can be reopened.</small>
            </div>
            <div class="ms-auto">
                <button type="button" class="btn btn-primary" id="newBtn"><i class="fas fa-plus"></i> New Fiscal Year</button>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table acc-table fy-table mb-0">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="fy-num">Net profit posted</th>
                                <th>Retained earnings</th>
                                <th>Closed</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fyBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    Closing moves the year's net profit or loss (all branches) to the retained earnings ledger you choose: from the day
                    after the year end the Trial Balance and Balance Sheet show it in retained earnings instead of the Income and Expense
                    lines, and a snapshot of every balance at the year end is saved. Entries dated inside a closed year are refused.
                    Reopening the most recently closed year puts its profit back in the Income and Expense lines.
                </p>
            </div>
        </div>
    </div>

    {{-- New year --}}
    <div class="modal fade" id="yearModal" tabindex="-1" aria-labelledby="yearTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="yearForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="yearTitle">New Fiscal Year</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="fy_name">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fy_name" name="name" maxlength="60" placeholder="FY 2026">
                            <div class="invalid-feedback field-error"></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="fy_start">First day <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fy_start" name="start_date">
                                <div class="invalid-feedback field-error"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="fy_end">Last day <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fy_end" name="end_date">
                                <div class="invalid-feedback field-error"></div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="fy_remarks">Remarks</label>
                            <input type="text" class="form-control" id="fy_remarks" name="remarks" maxlength="500">
                            <div class="invalid-feedback field-error"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="yearSave">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Close year --}}
    <div class="modal fade" id="closeModal" tabindex="-1" aria-labelledby="closeTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="closeForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="closeTitle">Close Fiscal Year</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="fy-preview mb-3">
                            <dl>
                                <dt>Income ledger balances</dt><dd id="pvIncome">0.00</dd>
                                <dt>Expense ledger balances</dt><dd id="pvExpense">0.00</dd>
                                <dt class="fy-net">Net profit / (loss) to post</dt><dd class="fy-net" id="pvNet">0.00</dd>
                                <dt>Profit &amp; Loss statement, all branches (for reference)</dt><dd id="pvReport">0.00</dd>
                            </dl>
                        </div>
                        <div class="fy-ledgers mb-3">
                            <table class="table table-sm mb-0"><tbody id="pvLedgers"></tbody></table>
                        </div>
                        <label class="form-label" for="re_account">Retained earnings ledger (Equity) <span class="text-danger">*</span></label>
                        <select class="form-select" id="re_account" name="retained_earnings_account_id">
                            <option value="">Select ledger</option>
                            @foreach ($equityLedgers as $l)
                                <option value="{{ $l->id }}">{{ $l->account_name }} ({{ $l->account_code }})</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback field-error"></div>
                        @if ($equityLedgers->isEmpty())
                            <div class="text-danger small mt-1">There is no Equity ledger yet. Add one (e.g. Retained Earnings) under Equity in the Chart of Accounts.</div>
                        @endif
                        <p class="text-muted small mt-3 mb-0">The profit moved is the year's Income less Expense balances since the previous closing, from the same ledger as the Profit &amp; Loss statement (sales, cost of goods sold, expenses, payroll, journals), all branches.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="closeSave"><i class="fas fa-lock"></i> Close year</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Snapshot --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTitle">Closing snapshot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const FY = {
            urls: {
                list: "{{ route('admin.accounting.fiscal-years.list') }}",
                store: "{{ route('admin.accounting.fiscal-years.store') }}",
                base: "{{ url('admin/accounting/fiscal-years') }}"
            },
            closingId: null,
            rows: {},

            load: function() {
                $.ajax({
                    url: FY.urls.list,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const esc = Voucher.esc;
                        if (!response.data.length) {
                            $('#fyBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No fiscal years yet. Add one to start.</td></tr>');
                            return;
                        }
                        FY.rows = {};
                        $('#fyBody').html(response.data.map(function(y) {
                            FY.rows[y.id] = y;
                            const badge = y.status === 'Closed' ? 'bg-secondary' : 'bg-success';
                            let actions = '';
                            if (y.status === 'Closed') actions += '<button class="btn btn-sm btn-outline-secondary" onclick="FY.view(' + y.id + ')"><i class="fas fa-eye"></i> Snapshot</button> ';
                            if (y.can_close) actions += '<button class="btn btn-sm btn-primary" onclick="FY.openClose(' + y.id + ')"><i class="fas fa-lock"></i> Close</button> ';
                            if (y.can_reopen) actions += '<button class="btn btn-sm btn-outline-warning" onclick="FY.reopen(' + y.id + ')"><i class="fas fa-lock-open"></i> Reopen</button> ';
                            if (y.can_delete) actions += '<button class="btn btn-sm btn-outline-danger" onclick="FY.remove(' + y.id + ')" title="Delete"><i class="fas fa-trash"></i></button>';
                            return '<tr>'
                                + '<td class="fw-semibold">' + esc(y.name) + '</td>'
                                + '<td>' + esc(y.start) + ' – ' + esc(y.end) + '</td>'
                                + '<td><span class="badge ' + badge + '">' + esc(y.status) + '</span></td>'
                                + '<td class="fy-num">' + (y.net_profit !== null ? esc(y.net_profit) : '<span class="text-muted">-</span>') + '</td>'
                                + '<td>' + (y.re_account ? esc(y.re_account) : '<span class="text-muted">-</span>') + '</td>'
                                + '<td class="small">' + (y.closed ? esc(y.closed) : '<span class="text-muted">-</span>') + '</td>'
                                + '<td class="text-end text-nowrap">' + actions + '</td>'
                                + '</tr>';
                        }).join(''));
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the fiscal years.');
                    }
                });
            },

            openClose: function(id) {
                FY.closingId = id;
                Voucher.clearErrors($('#closeForm'));
                $('#closeTitle').text('Close ' + FY.rows[id].name);
                $('#pvIncome, #pvExpense, #pvNet, #pvReport').text('...');
                $('#pvLedgers').html('');
                $.ajax({
                    url: FY.urls.base + '/' + id + '/preview',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const d = response.data;
                        $('#pvIncome').text(d.income);
                        $('#pvExpense').text(d.expense);
                        $('#pvNet').text(d.net);
                        $('#pvReport').text(d.report_net);
                        $('#pvLedgers').html(d.ledgers.length ? d.ledgers.map(function(l) {
                            return '<tr><td class="text-muted">' + Voucher.esc(l.code) + '</td><td>' + Voucher.esc(l.name)
                                + '</td><td>' + Voucher.esc(l.type) + '</td><td class="fy-num">' + Voucher.esc(l.balance) + '</td></tr>';
                        }).join('') : '<tr><td class="text-muted text-center">No Income or Expense ledger has a balance at the year end.</td></tr>');
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to prepare the closing.');
                    }
                });
                bootstrap.Modal.getOrCreateInstance(document.getElementById('closeModal')).show();
            },

            view: function(id) {
                $.ajax({
                    url: FY.urls.base + '/' + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const d = response.data, esc = Voucher.esc;
                        $('#viewTitle').text(d.name + ' closing snapshot');
                        $('#viewBody').html('<p class="mb-2">Net profit posted: <strong>' + esc(d.net_profit) + '</strong> to <strong>' + esc(d.re_account || '-') + '</strong></p>'
                            + '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Code</th><th>Ledger</th><th>Type</th>'
                            + '<th class="fy-num">Balance at year end</th><th class="fy-num">Closing entry</th><th class="fy-num">After closing</th></tr></thead><tbody>'
                            + d.rows.map(function(r) {
                                return '<tr><td class="text-muted">' + esc(r.code) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.type) + '</td><td class="fy-num">'
                                    + esc(r.balance) + '</td><td class="fy-num">' + esc(r.delta) + '</td><td class="fy-num">' + esc(r.closed) + '</td></tr>';
                            }).join('') + '</tbody></table></div>');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the snapshot.');
                    }
                });
            },

            confirm: function(title, text, button, run) {
                if (typeof Swal === 'undefined') { if (confirm(title + ' ' + text)) run(); return; }
                Swal.fire({ titleText: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonText: button, reverseButtons: true })
                    .then(function(r) { if (r.isConfirmed) run(); });
            },

            post: function(url, done) {
                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (response.status) { toastr.success(response.message); FY.load(); if (done) done(); }
                        else toastr.error(response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Something went wrong.');
                    }
                });
            },

            reopen: function(id) {
                FY.confirm('Reopen ' + FY.rows[id].name + '?', 'The closing entries are reversed: Income and Expense ledgers get their balances back and retained earnings goes back down.',
                    'Yes, reopen', function() { FY.post(FY.urls.base + '/' + id + '/reopen'); });
            },

            remove: function(id) {
                FY.confirm('Delete ' + FY.rows[id].name + '?', 'Only the year record is deleted; no ledger is changed.',
                    'Yes, delete', function() { FY.post(FY.urls.base + '/' + id + '/delete'); });
            }
        };

        $(document).ready(function() {
            Voucher.initSelect2($('#re_account'), $('#closeModal'));

            $('#newBtn').on('click', function() {
                const $f = $('#yearForm');
                $f[0].reset();
                Voucher.clearErrors($f);
                const y = new Date().getFullYear();
                $('#fy_name').val('FY ' + y);
                $('#fy_start').val(y + '-01-01');
                $('#fy_end').val(y + '-12-31');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('yearModal')).show();
            });

            $('#yearForm').on('submit', function(e) {
                e.preventDefault();
                const $f = $(this), $btn = $('#yearSave');
                Voucher.clearErrors($f);
                $btn.prop('disabled', true);
                $.ajax({
                    url: FY.urls.store,
                    type: 'POST',
                    dataType: 'json',
                    data: $f.serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('yearModal')).hide();
                        FY.load();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) Voucher.showErrors($f, xhr.responseJSON.errors);
                        else Voucher.ajaxError(xhr, 'Unable to save the fiscal year.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            $('#closeForm').on('submit', function(e) {
                e.preventDefault();
                const $f = $(this), $btn = $('#closeSave');
                Voucher.clearErrors($f);
                FY.confirm('Close ' + FY.rows[FY.closingId].name + '?', 'Net ' + $('#pvNet').text() + ' will be posted to the retained earnings ledger and the Income / Expense ledgers zeroed as at the year end.',
                    'Yes, close', function() {
                        $btn.prop('disabled', true);
                        $.ajax({
                            url: FY.urls.base + '/' + FY.closingId + '/close',
                            type: 'POST',
                            dataType: 'json',
                            data: $f.serialize(),
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                if (!response.status) { toastr.error(response.message); return; }
                                toastr.success(response.message);
                                bootstrap.Modal.getOrCreateInstance(document.getElementById('closeModal')).hide();
                                FY.load();
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) Voucher.showErrors($f, xhr.responseJSON.errors);
                                else Voucher.ajaxError(xhr, 'Unable to close the fiscal year.');
                            },
                            complete: function() { $btn.prop('disabled', false); }
                        });
                    });
            });

            FY.load();
        });
    </script>
@endpush
