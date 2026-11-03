@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Suspense
@endsection

@push('styles')
    <style>
        .sus-help {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-size: var(--text-caption);
        }
        .sus-balance { font-size: var(--text-caption); color: var(--color-text-muted); }
        .sus-balance b { color: var(--color-text); font-feature-settings: var(--font-feature-numeric); }
        .sus-kv { font-size: var(--text-caption); }
        .sus-kv .k { color: var(--color-text-muted); }
        .sus-stmt td, .sus-stmt th { font-size: var(--text-caption); padding: .35rem 8px; }
        .sus-stmt .num { text-align: right; white-space: nowrap; font-feature-settings: var(--font-feature-numeric); }
        .sus-stmt tr.sus-open td, .sus-stmt tr.sus-total td { background: var(--color-surface-soft); font-weight: var(--font-weight-medium); }
        .sus-neg { color: var(--color-danger); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Suspense</h4>
                <small class="text-muted">Advances to employees and investors (paid, returned, adjusted), employee deposits, and investor capital in / out.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" id="refreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
                <button type="button" class="btn btn-outline-secondary" id="statementBtn" disabled><i class="fas fa-file-invoice"></i> Statement</button>
                @perm('suspense.create')
                    <button type="button" class="btn btn-primary" id="newBtn"><i class="fas fa-plus"></i> New Entry</button>
                @endperm
            </div>
        </div>

        {{-- Cards: the selected person's totals, or the filtered entries' totals --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card acc-stat h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-hand-holding-usd"></i></div>
                    <div><div class="text-muted small">Paid / Withdrawn</div><h5 class="mb-0" id="sumPaid">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card acc-stat h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-undo"></i></div>
                    <div><div class="text-muted small">Returned</div><h5 class="mb-0" id="sumReturned">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card acc-stat h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-balance-scale"></i></div>
                    <div><div class="text-muted small">Adjusted</div><h5 class="mb-0" id="sumAdjusted">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card acc-stat h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-hourglass-half"></i></div>
                    <div><div class="text-muted small" id="sumOutstandingLabel">Outstanding</div><h5 class="mb-0" id="sumOutstanding">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-md-4 col-xl-2 sus-capital">
                <div class="card acc-stat h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-piggy-bank"></i></div>
                    <div><div class="text-muted small" id="sumInvestedLabel">Capital Invested</div><h5 class="mb-0" id="sumInvested">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-md-4 col-xl-2 sus-capital">
                <div class="card acc-stat h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-sign-out-alt"></i></div>
                    <div><div class="text-muted small">Capital Withdrawn</div><h5 class="mb-0" id="sumCapitalOut">0.00</h5><div class="text-muted small" id="sumCapital"></div></div>
                </div></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="fPersonType">Person Type</label>
                        <select id="fPersonType" class="form-select">
                            <option value="">All</option>
                            <option value="Investor">Investor</option>
                            <option value="Employee">Employee</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="fPerson">Person</label>
                        <select id="fPerson" class="form-select">
                            <option value="">All persons</option>
                            <optgroup label="Investors">
                                @foreach ($investors as $p)
                                    <option value="Investor-{{ $p->id }}" @selected($preset === 'Investor-' . $p->id)>{{ $p->name }} ({{ $p->code }}){{ $p->status === 'Active' ? '' : ' · inactive' }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Employees">
                                @foreach ($employees as $e)
                                    <option value="Employee-{{ $e->id }}" @selected($preset === 'Employee-' . $e->id)>{{ $e->name }} ({{ $e->employee_code }}){{ $e->status === 'Active' ? '' : ' · inactive' }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="fType">Type</label>
                        <select id="fType" class="form-select">
                            <option value="">All types</option>
                            @foreach (array_keys($types) as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="fFrom">From</label>
                        <input type="date" id="fFrom" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="fTo">To</label>
                        <input type="date" id="fTo" class="form-control">
                    </div>
                    <div class="col-12 col-md-1 d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary flex-fill" id="resetFilters" title="Reset filters" aria-label="Reset filters"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="susTable" class="table table-hover align-middle w-100 acc-table" data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" width="4%">SL.</th>
                                <th>Voucher</th>
                                <th>Date</th>
                                <th>Person</th>
                                <th>Type</th>
                                <th>Account / Ledger</th>
                                <th>Purpose</th>
                                <th class="text-end">Amount</th>
                                <th data-orderable="false" width="6%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- New / edit entry --}}
    <div class="modal fade" id="entryModal" tabindex="-1" aria-labelledby="entryTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="entryForm" autocomplete="off" novalidate>
                    <input type="hidden" name="id" id="e_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="entryTitle">New Suspense Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="e_person_type">Person Type <span class="text-danger">*</span></label>
                                <select name="person_type" id="e_person_type" class="form-select">
                                    <option value="Employee">Employee</option>
                                    <option value="Investor">Investor</option>
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="e_person_id" id="e_person_label">Employee <span class="text-danger">*</span></label>
                                <select name="person_id" id="e_person_id" class="form-select"></select>
                                <div class="sus-balance mt-1" id="e_balance"></div>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="e_txn_type">Transaction <span class="text-danger">*</span></label>
                                <select name="txn_type" id="e_txn_type" class="form-select"></select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="e_txn_date">Date <span class="text-danger">*</span></label>
                                <input type="date" name="txn_date" id="e_txn_date" class="form-control">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="e_amount">Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="e_amount" class="form-control" min="0.01" step="0.01">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-12"><div class="sus-help" id="e_help"></div></div>
                            <div class="col-md-6" id="e_account_wrap">
                                <label class="form-label" for="e_account_id" id="e_account_label">Paid From <span class="text-danger">*</span></label>
                                <select name="account_id" id="e_account_id" class="form-select">
                                    <option value="">Select cash / bank</option>
                                    @foreach ($cashAccounts as $a)
                                        <option value="{{ $a->id }}">{{ $a->account_name }}{{ $a->group_name ? ' · ' . $a->group_name : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6" id="e_adjust_wrap">
                                <label class="form-label" for="e_adjust_account_id" id="e_adjust_label">Spent On (Ledger) <span class="text-danger">*</span></label>
                                <select name="adjust_account_id" id="e_adjust_account_id" class="form-select"></select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="e_purpose">Purpose / Remarks</label>
                                <input type="text" name="purpose" id="e_purpose" class="form-control" maxlength="255" placeholder="e.g. Office trip to Chattogram">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="e_reference">Reference</label>
                                <input type="text" name="reference" id="e_reference" class="form-control" maxlength="100">
                                <div class="text-danger small field-error"></div>
                            </div>
                        </div>
                        <div class="text-danger small mt-2" id="e_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="e_submit">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- View one entry --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTitle">Suspense Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
            </div>
        </div>
    </div>

    {{-- Statement of one person --}}
    <div class="modal fade" id="stmtModal" tabindex="-1" aria-labelledby="stmtTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stmtTitle">Suspense Statement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="s_from">From</label>
                            <input type="date" id="s_from" class="form-control">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="s_to">To</label>
                            <input type="date" id="s_to" class="form-control">
                        </div>
                        <div class="col-12 col-md-6 d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" id="s_load"><i class="fas fa-search"></i> Show</button>
                            <a class="btn btn-outline-secondary" id="s_pdf" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> PDF</a>
                            <a class="btn btn-outline-secondary" id="s_excel"><i class="fas fa-file-excel"></i> Excel</a>
                        </div>
                    </div>
                    <div class="row g-3 mb-3" id="s_cards"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered sus-stmt mb-0">
                            <thead id="s_head"></thead>
                            <tbody id="s_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const SUS = {
            listUrl: "{{ route('admin.suspense.list') }}",
            showUrl: "{{ route('admin.suspense.show') }}",
            balanceUrl: "{{ route('admin.suspense.balance') }}",
            storeUrl: "{{ route('admin.suspense.store') }}",
            deleteUrl: "{{ route('admin.suspense.delete') }}",
            stmtUrl: "{{ route('admin.suspense.statement') }}",
            pdfUrl: "{{ route('admin.suspense.statement.pdf') }}",
            excelUrl: "{{ route('admin.suspense.statement.excel') }}",
            types: @json($types),
            help: @json($help),
            investors: @json($investors->where('status', 'Active')->values()),
            employees: @json($employees->whereIn('id', $ownEmployees)->values()),
            adjust: @json($adjustAccounts),
            ledgers: @json($ledgers),
            canCreate: @json(\App\Http\Controllers\Admin\PermissionController::allows(['suspense.create', 'suspense.edit']))
        };
        const esc = Voucher.esc, money = Voucher.money;

        const Suspense = {
            table: null, editing: null, stmt: null,

            filters: function() {
                return { person_type: $('#fPersonType').val(), person: $('#fPerson').val(), txn_type: $('#fType').val(), from: $('#fFrom').val(), to: $('#fTo').val() };
            },
            reload: function() {
                Suspense.table.ajax.url(SUS.listUrl + '?' + $.param(Suspense.filters())).load(null, false);
                $('#statementBtn').prop('disabled', !$('#fPerson').val());
            },
            cards: function(s) {
                if (!s) return;
                $('#sumPaid').text(money(s.paid));
                $('#sumReturned').text(money(s.returned));
                $('#sumAdjusted').text(money(s.adjusted));
                $('#sumOutstanding').text(money(s.outstanding)).toggleClass('sus-neg', s.outstanding < 0);
                $('#sumOutstandingLabel').text(s.outstanding < 0 ? 'Owed to employee' : 'Outstanding');
                $('.sus-capital').toggle(s.person_type !== 'Employee');
                $('#sumInvestedLabel').text(s.opening_capital ? 'Capital Invested (incl. opening ' + money(s.opening_capital) + ')' : 'Capital Invested');
                $('#sumInvested').text(money(s.opening_capital + s.invested));
                $('#sumCapitalOut').text(money(s.capital_withdrawn));
                $('#sumCapital').text('Capital: ' + money(s.capital));
            },

            /* ---------- form ---------- */
            personOptions: function(type, selected, label) {
                const list = type === 'Investor' ? SUS.investors : SUS.employees;
                const $s = $('#e_person_id').empty().append(new Option('Select ' + type.toLowerCase(), ''));
                list.forEach(function(p) { $s.append(new Option(p.name + ' (' + (p.code || p.employee_code || '') + ')', p.id)); });
                if (selected && !$s.find('option[value="' + selected + '"]').length) $s.append(new Option(label || ('#' + selected), selected));
                $s.val(selected ? String(selected) : '');
                Voucher.refresh($s);
                $('#e_person_label').html(type + ' <span class="text-danger">*</span>');
                const $t = $('#e_txn_type').empty();
                Object.keys(SUS.types).forEach(function(k) {
                    if (SUS.types[k][0] === null || SUS.types[k][0] === type) $t.append(new Option(k, k));
                });
            },
            adjustOptions: function(personType, txnType, selected) {
                const allowed = txnType === 'Capital Withdrawal' ? ['Equity'] : (personType === 'Investor' ? ['Expense', 'Equity'] : ['Expense']);
                const $s = $('#e_adjust_account_id').empty().append(new Option('Select ledger', ''));
                ['Expense', 'Equity'].filter(function(t) { return allowed.indexOf(t) >= 0; }).forEach(function(t) {
                    const $g = $('<optgroup>').attr('label', t === 'Expense' ? 'Expense ledgers' : 'Equity (capital / drawings)');
                    SUS.adjust.filter(function(a) { return a.account_type === t; }).forEach(function(a) {
                        $g.append(new Option(a.account_code + ' - ' + a.account_name + (a.group_name ? ' · ' + a.group_name : ''), a.id));
                    });
                    $s.append($g);
                });
                let pick = selected;
                if (!pick && txnType === 'Capital Withdrawal') pick = SUS.ledgers.capital;
                $s.val(pick ? String(pick) : '');
                Voucher.refresh($s);
            },
            typeChanged: function(selectedAdjust) {
                const pt = $('#e_person_type').val(), tt = $('#e_txn_type').val(), money_ = SUS.types[tt] ? SUS.types[tt][3] : null;
                $('#e_help').text(SUS.help[tt] || '');
                $('#e_account_wrap').toggle(money_ !== null);
                $('#e_account_label').html((money_ === 'out' ? 'Paid From' : 'Received In') + ' <span class="text-danger">*</span>');
                const adj = tt === 'Adjustment' || tt === 'Capital Withdrawal';
                $('#e_adjust_wrap').toggle(adj);
                $('#e_adjust_label').html(tt === 'Capital Withdrawal' ? 'Debit Ledger (Equity)' : 'Spent On (Ledger) <span class="text-danger">*</span>');
                if (adj) Suspense.adjustOptions(pt, tt, selectedAdjust);
            },
            balance: function() {
                const pt = $('#e_person_type').val(), id = $('#e_person_id').val();
                if (!id) { $('#e_balance').text(''); return; }
                $.ajax({
                    url: SUS.balanceUrl,
                    type: 'GET',
                    dataType: 'json',
                    data: { person_type: pt, person_id: id, except_id: Suspense.editing || '' },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const o = response.own;
                        let html = 'Outstanding in ' + esc(response.branch) + ': <b class="' + (o.outstanding < 0 ? 'sus-neg' : '') + '">' + money(o.outstanding) + '</b>';
                        if (pt === 'Investor') html += ' · Capital: <b>' + money(response.all.capital) + '</b>';
                        if (Suspense.editing) html += ' <span class="text-muted">(without this entry)</span>';
                        $('#e_balance').html(html);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        $('#e_balance').text('');
                    }
                });
            },
            open: function(data) {
                const $f = $('#entryForm');
                $f[0].reset();
                Voucher.clearErrors($f);
                $('#e_error').text('');
                Suspense.editing = data ? data.id : null;
                $('#e_id').val(data ? data.id : '');
                const pt = data ? data.person_type : ($('#fPerson').val() ? $('#fPerson').val().split('-')[0] : ($('#fPersonType').val() || 'Employee'));
                const pid = data ? data.person_id : ($('#fPerson').val() && $('#fPerson').val().split('-')[0] === pt ? $('#fPerson').val().split('-')[1] : '');
                $('#e_person_type').val(pt).prop('disabled', !!data);
                Suspense.personOptions(pt, pid, data ? data.person_name : null);
                $('#e_person_id').prop('disabled', !!data);
                $('#e_txn_type').val(data ? data.txn_type : $('#e_txn_type option:first').val());
                $('#e_txn_date').val(data ? data.txn_date : Voucher.todayLocal());
                $('#e_amount').val(data ? data.amount : '');
                Voucher.setValue($('#e_account_id'), data ? data.account_id : '', data ? data.account_name : '');
                $('#e_purpose').val(data ? data.purpose : '');
                $('#e_reference').val(data ? data.reference : '');
                Suspense.typeChanged(data ? data.adjust_account_id : null);
                $('#entryTitle').text(data ? 'Edit ' + data.voucher_no : 'New Suspense Entry');
                Suspense.balance();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('entryModal')).show();
            },
            fetch: function(id, then) {
                $.ajax({
                    url: SUS.showUrl,
                    type: 'GET',
                    dataType: 'json',
                    data: { id: id },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        then(response.data);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the entry.');
                    }
                });
            },
            edit: function(id) { Suspense.fetch(id, Suspense.open); },
            view: function(id) {
                Suspense.fetch(id, function(d) {
                    const kv = function(k, v) { return '<div class="col-6 sus-kv"><div class="k">' + k + '</div><div>' + v + '</div></div>'; };
                    $('#viewTitle').text(d.voucher_no + ' — ' + d.txn_type);
                    $('#viewBody').html('<div class="row g-3">' +
                        kv(esc(d.person_type), esc(d.person_name) + ' <span class="text-muted">' + esc(d.person_code || '') + '</span>') +
                        kv('Date', esc(d.txn_date_label)) + kv('Amount', '<b>' + money(d.amount) + '</b>') + kv('Branch', esc(d.branch)) +
                        kv('Debit', esc(d.dr_name || '-')) + kv('Credit', esc(d.cr_name || '-')) +
                        kv('Purpose', esc(d.purpose || '-')) + kv('Reference', esc(d.reference || '-')) + kv('Entered by', esc(d.created_by || '-')) +
                        '</div>');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();
                });
            },
            remove: function(id) {
                Voucher.confirmDelete('Delete this entry?', 'Its posting is reversed (cash / bank and ledgers return to what they were).', SUS.deleteUrl, id, Suspense.reload);
            },

            /* ---------- statement ---------- */
            statement: function(personType, personId) {
                Suspense.stmt = { person_type: personType, person_id: personId };
                $('#s_from').val($('#fFrom').val());
                $('#s_to').val($('#fTo').val());
                Suspense.loadStatement();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('stmtModal')).show();
            },
            loadStatement: function() {
                const q = $.extend({}, Suspense.stmt, { from: $('#s_from').val(), to: $('#s_to').val() });
                $('#s_pdf').attr('href', SUS.pdfUrl + '?' + $.param(q));
                $('#s_excel').attr('href', SUS.excelUrl + '?' + $.param(q));
                $('#s_body').html('<tr><td class="text-center text-muted">Loading...</td></tr>');
                $.ajax({
                    url: SUS.stmtUrl,
                    type: 'GET',
                    dataType: 'json',
                    data: q,
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        Suspense.renderStatement(response.data);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        $('#s_body').html('');
                        Voucher.ajaxError(xhr, 'Unable to load the statement.');
                    }
                });
            },
            renderStatement: function(s) {
                const inv = s.investor, branch = s.lines.length && s.lines[0].branch !== '';
                const n = function(v) { return v ? money(v) : ''; };
                const bal = function(v) { return '<span class="' + (v < 0 ? 'sus-neg' : '') + '">' + money(v) + '</span>'; };
                $('#stmtTitle').text('Suspense Statement — ' + s.person.name + (s.person.code ? ' (' + s.person.code + ')' : ''));
                const card = function(label, value) {
                    return '<div class="col-6 col-md-3"><div class="card acc-stat h-100"><div class="card-body"><div class="text-muted small">' + label + '</div><h6 class="mb-0">' + value + '</h6></div></div></div>';
                };
                $('#s_cards').html(card('Opening outstanding', bal(s.opening.balance)) + card('Paid / withdrawn', money(s.totals.advance)) +
                    card('Returned / adjusted', money(s.totals.settled)) + card('Closing outstanding', bal(s.closing.balance)) +
                    (inv ? card('Opening capital', money(s.opening.capital)) + card('Capital in', money(s.totals.capital_in)) +
                        card('Capital out', money(s.totals.capital_out)) + card('Closing capital', money(s.closing.capital)) : ''));
                $('#s_head').html('<tr><th>Date</th><th>Voucher</th><th>Type</th><th>Particulars</th>' + (branch ? '<th>Branch</th>' : '') +
                    '<th class="num">Paid / Withdrawn</th><th class="num">Returned / Adjusted</th><th class="num">Outstanding</th>' +
                    (inv ? '<th class="num">Capital +/−</th><th class="num">Capital</th>' : '') + '</tr>');
                const span = 4 + (branch ? 1 : 0);
                let rows = '<tr class="sus-open"><td colspan="' + span + '">Opening balance</td><td></td><td></td><td class="num">' + bal(s.opening.balance) + '</td>' +
                    (inv ? '<td></td><td class="num">' + money(s.opening.capital) + '</td>' : '') + '</tr>';
                s.lines.forEach(function(l) {
                    rows += '<tr><td class="text-nowrap">' + esc(l.date) + '</td><td>' + esc(l.voucher) + '</td><td>' + esc(l.type) + '</td><td>' + esc(l.particulars) + '</td>' +
                        (branch ? '<td>' + esc(l.branch) + '</td>' : '') +
                        '<td class="num">' + n(l.advance) + '</td><td class="num">' + n(l.settled) + '</td><td class="num">' + bal(l.balance) + '</td>' +
                        (inv ? '<td class="num">' + (l.capital_change ? (l.capital_change > 0 ? '+' : '−') + money(Math.abs(l.capital_change)) : '') + '</td><td class="num">' + money(l.capital) + '</td>' : '') + '</tr>';
                });
                if (!s.lines.length) rows += '<tr><td colspan="' + (span + 3 + (inv ? 2 : 0)) + '" class="text-center text-muted">No entries in this period</td></tr>';
                rows += '<tr class="sus-total"><td colspan="' + span + '">Total / Closing</td><td class="num">' + money(s.totals.advance) + '</td><td class="num">' + money(s.totals.settled) +
                    '</td><td class="num">' + bal(s.closing.balance) + '</td>' +
                    (inv ? '<td class="num">' + money(s.totals.capital_in - s.totals.capital_out) + '</td><td class="num">' + money(s.closing.capital) + '</td>' : '') + '</tr>';
                $('#s_body').html(rows);
            }
        };

        $(document).ready(function() {
            Voucher.keepSerial($('#susTable'));
            Voucher.initSelect2($('#fPerson'));
            Voucher.initSelect2($('#e_person_id, #e_account_id, #e_adjust_account_id'), $('#entryModal'));

            Suspense.table = $('#susTable').DataTable({
                ajax: {
                    url: SUS.listUrl + '?' + $.param(Suspense.filters()),
                    dataSrc: function(json) { Suspense.cards(json.summary); return json.data || []; }
                },
                processing: true
            });
            $('#statementBtn').prop('disabled', !$('#fPerson').val());

            $('#fPersonType, #fPerson, #fType, #fFrom, #fTo').on('change', Suspense.reload);
            $('#refreshBtn').on('click', Suspense.reload);
            $('#resetFilters').on('click', function() {
                $('#fPersonType, #fType, #fFrom, #fTo').val('');
                $('#fPerson').val('').trigger('change.select2');
                Suspense.reload();
            });
            $('#statementBtn').on('click', function() {
                const v = ($('#fPerson').val() || '').split('-');
                if (v.length === 2) Suspense.statement(v[0], parseInt(v[1]));
            });
            $('#s_load').on('click', Suspense.loadStatement);

            $('#newBtn').on('click', function() { Suspense.open(null); });
            $('#e_person_type').on('change', function() {
                Suspense.personOptions($(this).val(), '');
                Suspense.typeChanged(null);
                Suspense.balance();
            });
            $('#e_person_id').on('change', Suspense.balance);
            $('#e_txn_type').on('change', function() { Suspense.typeChanged(null); });

            $('#entryForm').on('submit', function(e) {
                e.preventDefault();
                const $f = $(this), $btn = $('#e_submit').prop('disabled', true).text('Saving...');
                Voucher.clearErrors($f);
                $('#e_error').text('');
                // disabled fields (person on edit) are not serialized: send them anyway
                const data = $f.serializeArray().concat([
                    { name: 'person_type', value: $('#e_person_type').val() },
                    { name: 'person_id', value: $('#e_person_id').val() }
                ]).filter(function(x, i, all) { return all.findIndex(function(y) { return y.name === x.name; }) === i; });
                $.ajax({
                    url: SUS.storeUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: $.param(data),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('entryModal')).hide();
                        Suspense.reload();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($f, xhr.responseJSON.errors, $('#e_error'));
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            $('#e_error').text(xhr.responseJSON.message);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false).text('Save'); }
                });
            });
        });
    </script>
@endpush
