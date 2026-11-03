@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Loans & Advances
@endsection

@push('styles')
    <style>
        .loan-preview {
            padding: var(--space-md);
            border-radius: var(--radius-md);
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-size: var(--text-caption);
        }
        .loan-kv { font-size: var(--text-caption); }
        .loan-kv .k { color: var(--color-text-muted); }
        .loan-table td, .loan-table th { min-width: 0; padding: .35rem 8px; font-size: var(--text-caption); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Loans &amp; Advance Salary</h4>
                <small class="text-muted">Money given to employees, recovered by monthly installments from the salary sheet (or repaid in cash).</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <select id="filterStatus" class="form-select" style="width: 150px;" aria-label="Status">
                    <option value="">All loans</option>
                    <option value="Running" selected>Running</option>
                    <option value="Closed">Closed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <button type="button" class="btn btn-primary" id="newLoanBtn"><i class="fas fa-plus"></i> New Loan / Advance</button>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="loanTable" class="table table-hover align-middle w-100 acc-table" data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" width="4%">SL.</th>
                                <th>Loan No.</th>
                                <th>Employee</th>
                                <th>Issued</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Installment</th>
                                <th class="text-end">Recovered</th>
                                <th class="text-end">Balance</th>
                                <th>Status</th>
                                <th data-orderable="false" width="6%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- New loan --}}
    <div class="modal fade" id="loanModal" tabindex="-1" aria-labelledby="loanTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="loanForm" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="loanTitle">New Loan / Advance Salary</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="employee_id">Employee <span class="text-danger">*</span></label>
                                <select name="employee_id" id="employee_id" class="form-select">
                                    <option value="">Select employee</option>
                                    @foreach ($employees as $e)
                                        <option value="{{ $e->id }}" data-net="{{ (float) $e->net_salary }}">{{ $e->name }} ({{ $e->employee_code }}){{ $e->department ? ' · ' . $e->department : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="loan_type">Type</label>
                                <select name="loan_type" id="loan_type" class="form-select">
                                    <option value="Advance Salary">Advance Salary</option>
                                    <option value="Loan">Loan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="amount">Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="amount" class="form-control" min="1" step="0.01">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="issue_date">Issue Date <span class="text-danger">*</span></label>
                                <input type="date" name="issue_date" id="issue_date" class="form-control" value="{{ now()->toDateString() }}">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payment_account_id">Paid From <span class="text-danger">*</span></label>
                                <select name="payment_account_id" id="payment_account_id" class="form-select">
                                    <option value="">Select cash / bank</option>
                                    @foreach ($paymentAccounts as $a)
                                        <option value="{{ $a->id }}" @selected($ledgerDefaults['payment'] == $a->id)>{{ $a->account_name }}{{ $a->group_name ? ' · ' . $a->group_name : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="installments">Installments (months) <span class="text-danger">*</span></label>
                                <input type="number" name="installments" id="installments" class="form-control" min="1" max="240" step="1" value="1">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="start_month">First Deduction Month <span class="text-danger">*</span></label>
                                <input type="month" name="start_month" id="start_month" class="form-control" value="{{ now()->format('Y-m') }}">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="loan_account_id">Employee Loan / Advance Ledger <span class="text-danger">*</span></label>
                                <select name="loan_account_id" id="loan_account_id" class="form-select">
                                    <option value="">Select asset ledger</option>
                                    @foreach ($loanAccounts as $a)
                                        <option value="{{ $a->id }}" @selected($ledgerDefaults['loan'] == $a->id)>{{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Only employee loan / advance ledgers are listed, e.g. “Employee Loans &amp; Advances” (add one in <a href="{{ route('admin.chart-of-accounts.page') }}" target="_blank">Chart of Accounts</a>).</small>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="loan_remarks">Remarks</label>
                                <input type="text" name="remarks" id="loan_remarks" class="form-control" maxlength="255">
                            </div>
                            <div class="col-12"><div class="loan-preview" id="loanPreview">Enter the amount and installments.</div></div>
                        </div>
                        <div class="text-danger small mt-2" id="loan_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="loanSubmit">Issue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Reschedule --}}
    <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-labelledby="rsTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="rescheduleForm" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="rsTitle">Change Schedule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted" id="rsIntro"></p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="rs_installments">Remaining Installments <span class="text-danger">*</span></label>
                                <input type="number" name="installments" id="rs_installments" class="form-control" min="1" max="240" step="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="rs_start">Next Deduction Month <span class="text-danger">*</span></label>
                                <input type="month" name="start_month" id="rs_start" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="rs_remarks">Reason</label>
                                <input type="text" name="remarks" id="rs_remarks" class="form-control" maxlength="255" placeholder="e.g. Skip Eid month">
                            </div>
                            <div class="col-12"><div class="loan-preview" id="rsPreview"></div></div>
                        </div>
                        <div class="text-danger small mt-2" id="rs_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="rsSubmit">Save Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Repay --}}
    <div class="modal fade" id="repayModal" tabindex="-1" aria-labelledby="rpTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="repayForm" autocomplete="off" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="rpTitle">Cash Repayment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted" id="rpIntro"></p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="rp_amount">Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="rp_amount" class="form-control" min="0.01" step="0.01">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="rp_date">Date <span class="text-danger">*</span></label>
                                <input type="date" name="txn_date" id="rp_date" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="rp_account">Received In <span class="text-danger">*</span></label>
                                <select name="payment_account_id" id="rp_account" class="form-select">
                                    <option value="">Select cash / bank</option>
                                    @foreach ($paymentAccounts as $a)
                                        <option value="{{ $a->id }}" @selected($ledgerDefaults['payment'] == $a->id)>{{ $a->account_name }}{{ $a->group_name ? ' · ' . $a->group_name : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="rp_remarks">Remarks</label>
                                <input type="text" name="remarks" id="rp_remarks" class="form-control" maxlength="255">
                            </div>
                        </div>
                        <div class="text-danger small mt-2" id="rp_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="rpSubmit">Save Repayment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- View --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTitle">Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const listUrl = "{{ route('admin.employee-loans.list') }}";
        const base = "{{ url('admin/employee-loans') }}";
        const esc = Voucher.esc, money = Voucher.money;

        function monthName(ym) { return new Date(ym + '-01T00:00:00').toLocaleString('en', { month: 'short', year: 'numeric' }); }
        function addMonths(ym, n) { const d = new Date(ym + '-01T00:00:00'); d.setMonth(d.getMonth() + n); return d.toISOString().slice(0, 7); }

        function post($form, url, $btn, label, $err, modal) {
            $btn.prop('disabled', true);
            Voucher.clearErrors($form);
            $err.text('');
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $form.serialize(),
                success: function(response) {
                    // alert(JSON.stringify(response));
                    toastr.success(response.message);
                    modal.hide();
                    Loan.reload();
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        Voucher.showErrors($form, xhr.responseJSON.errors, $err);
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        $err.text(xhr.responseJSON.message);
                    } else {
                        Voucher.ajaxError(xhr, 'Unable to save.');
                    }
                },
                complete: function() { $btn.prop('disabled', false).text(label); }
            });
        }

        const Loan = {
            table: null, current: null,
            reload: function() { Loan.table.ajax.url(listUrl + '?loan_status=' + $('#filterStatus').val()).load(null, false); },

            fetch: function(id, then) {
                $.ajax({
                    url: base + '/' + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        Loan.current = response;
                        then(response);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the loan.');
                    }
                });
            },

            view: function(id) {
                Loan.fetch(id, function(res) {
                    const l = res.data;
                    const left = parseFloat(l.amount) - parseFloat(l.recovered);
                    const kv = function(k, v) { return '<div class="col-6 col-md-3 loan-kv"><div class="k">' + k + '</div><div>' + v + '</div></div>'; };
                    const txns = res.transactions.map(function(t) {
                        return '<tr class="' + (t.txn_status === 'Reversed' ? 'text-muted text-decoration-line-through' : '') + '"><td>' + esc(t.txn_date) + '</td><td>' + esc(t.txn_type) + '</td>' +
                            '<td>' + esc(t.run_no || t.voucher_no || '') + (t.account ? ' · ' + esc(t.account) : '') + '</td>' +
                            '<td class="text-end">' + (parseFloat(t.amount) ? money(t.amount) : '') + '</td><td>' + esc(t.remarks || '') + '</td></tr>';
                    }).join('');
                    const sched = res.schedule.map(function(s, i) {
                        return '<tr><td>' + (i + 1) + '</td><td>' + esc(s.month) + '</td><td class="text-end">' + money(s.amount) + '</td><td class="text-end">' + money(s.balance) + '</td></tr>';
                    }).join('');
                    $('#viewTitle').text(l.loan_no + ' — ' + l.loan_type);
                    $('#viewBody').html(
                        '<div class="row g-3 mb-3">' +
                            kv('Employee', esc(l.name) + ' <span class="text-muted">' + esc(l.employee_code || '') + '</span>') +
                            kv('Issued', esc(l.issue_date) + ' · ' + esc(l.paid_from || '')) +
                            kv('Amount', money(l.amount)) + kv('Balance', '<b>' + money(left) + '</b>') +
                            kv('Installment', money(l.installment_amount) + ' × ' + l.installments) +
                            kv('Starts', monthName(l.start_month.slice(0, 7))) + kv('Recovered', money(l.recovered)) + kv('Status', esc(l.loan_status)) +
                        '</div>' +
                        (sched ? '<h6>Upcoming installments</h6><div class="table-responsive mb-3"><table class="table table-sm table-bordered loan-table"><thead><tr><th>#</th><th>Salary Month</th><th class="text-end">Installment</th><th class="text-end">Balance After</th></tr></thead><tbody>' + sched + '</tbody></table></div>' : '') +
                        '<h6>History</h6><div class="table-responsive"><table class="table table-sm table-bordered loan-table"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th class="text-end">Amount</th><th>Remarks</th></tr></thead><tbody>' + txns + '</tbody></table></div>'
                    );
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();
                });
            },

            reschedule: function(id) {
                Loan.fetch(id, function(res) {
                    const l = res.data;
                    Loan.left = parseFloat(l.amount) - parseFloat(l.recovered);
                    $('#rsTitle').text('Change Schedule — ' + l.loan_no);
                    $('#rsIntro').text(l.name + ' still owes ' + money(Loan.left) + '. Spread it over a new number of months and choose the next month to deduct from.');
                    $('#rs_installments').val(Math.max(1, res.schedule.length || 1));
                    $('#rs_start').val(res.schedule.length ? res.schedule[0].ym : new Date().toISOString().slice(0, 7));
                    $('#rs_remarks').val('');
                    $('#rs_error').text('');
                    $('#rescheduleForm').data('id', id);
                    Loan.rsPreview();
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('rescheduleModal')).show();
                });
            },
            rsPreview: function() {
                const n = parseInt($('#rs_installments').val()) || 0, start = $('#rs_start').val();
                if (!n || !start) { $('#rsPreview').text(''); return; }
                const each = Math.ceil(Loan.left / n * 100) / 100;
                $('#rsPreview').text(n + ' × ' + money(each) + ' from ' + monthName(start) + ' to ' + monthName(addMonths(start, n - 1)));
            },

            repay: function(id) {
                Loan.fetch(id, function(res) {
                    const l = res.data;
                    const left = parseFloat(l.amount) - parseFloat(l.recovered);
                    $('#rpTitle').text('Cash Repayment — ' + l.loan_no);
                    $('#rpIntro').text(l.name + ' owes ' + money(left) + '. A repayment lowers the balance; the monthly installment stays the same, so the loan ends sooner.');
                    $('#rp_amount').val(left.toFixed(2)).attr('max', left.toFixed(2));
                    $('#rp_remarks').val('');
                    $('#rp_error').text('');
                    $('#repayForm').data('id', id);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('repayModal')).show();
                });
            },

            cancel: function(id) {
                Swal.fire({
                    title: 'Cancel this loan?', text: 'The payout is reversed (money back to the cash / bank account). Reason:',
                    input: 'text', icon: 'warning', showCancelButton: true, confirmButtonText: 'Cancel loan', cancelButtonText: 'Keep', reverseButtons: true
                }).then(function(r) {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: base + '/' + id + '/cancel',
                        type: 'POST',
                        dataType: 'json',
                        data: { reason: r.value },
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            Loan.reload();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to cancel.');
                        }
                    });
                });
            }
        };

        function loanPreview() {
            const amount = parseFloat($('#amount').val()) || 0, n = parseInt($('#installments').val()) || 0, start = $('#start_month').val();
            if (!amount || !n || !start) { $('#loanPreview').text('Enter the amount and installments.'); return; }
            const each = Math.ceil(amount / n * 100) / 100;
            const net = parseFloat($('#employee_id option:selected').data('net')) || 0;
            $('#loanPreview').text(n + ' × ' + money(each) + ' deducted from salary, ' + monthName(start) + ' to ' + monthName(addMonths(start, n - 1)) +
                (net && each > net ? ' — more than the monthly net salary (' + money(net) + '); installments are capped at what the salary allows.' : '.'));
        }

        $(document).ready(function() {
            Voucher.keepSerial($('#loanTable'));
            Loan.table = $('#loanTable').DataTable({ ajax: listUrl + '?loan_status=Running', processing: true });
            $('#filterStatus').on('change', Loan.reload);

            const loanModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loanModal'));
            Voucher.initSelect2($('#employee_id, #payment_account_id, #loan_account_id'), $('#loanModal'));
            Voucher.initSelect2($('#rp_account'), $('#repayModal'));

            $('#newLoanBtn').on('click', function() {
                $('#loanForm')[0].reset();
                $('#employee_id').val('').trigger('change.select2');
                $('#payment_account_id, #loan_account_id').trigger('change.select2');
                Voucher.clearErrors($('#loanForm'));
                $('#loan_error').text('');
                loanPreview();
                loanModal.show();
            });
            $('#amount, #installments, #start_month, #employee_id').on('input change', loanPreview);
            $('#loan_type').on('change', function() { if ($(this).val() === 'Advance Salary') $('#installments').val(1); loanPreview(); });

            $('#loanForm').on('submit', function(e) {
                e.preventDefault();
                post($(this), base, $('#loanSubmit').text('Saving...'), 'Issue', $('#loan_error'), loanModal);
            });
            $('#rs_installments, #rs_start').on('input change', Loan.rsPreview);
            $('#rescheduleForm').on('submit', function(e) {
                e.preventDefault();
                post($(this), base + '/' + $(this).data('id') + '/reschedule', $('#rsSubmit').text('Saving...'), 'Save Schedule', $('#rs_error'),
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('rescheduleModal')));
            });
            $('#repayForm').on('submit', function(e) {
                e.preventDefault();
                post($(this), base + '/' + $(this).data('id') + '/repay', $('#rpSubmit').text('Saving...'), 'Save Repayment', $('#rp_error'),
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('repayModal')));
            });
        });
    </script>
@endpush
