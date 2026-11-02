@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Salary Sheet
@endsection

@push('styles')
    <style>
        .sheet-table { font-size: var(--text-caption); }
        /* beats the global ".table tbody td" (1rem padding, 120px min-width) for a compact sheet */
        .sheet-table tbody td, .sheet-table tfoot td, .sheet-table thead th { min-width: 0; padding: .4rem 10px; white-space: nowrap; }
        .sheet-group td { background: var(--color-surface-soft); font-weight: var(--font-weight-medium); }
        .sheet-total td { font-weight: var(--font-weight-medium); background: var(--color-surface-soft); }
        .sheet-grand td { font-weight: var(--font-weight-medium); background: var(--color-surface-soft); border-top: 2px solid var(--color-border); }
        .sheet-table th.sheet-paid-col, .sheet-table td.sheet-paid-col { background: var(--color-success-soft); }
        .sheet-table td[data-detail] { cursor: help; text-decoration: underline dotted var(--color-text-muted); text-underline-offset: 3px; }
        .sheet-state {
            display: inline-block;
            margin-left: var(--space-xs);
            padding: 0 var(--space-sm);
            border-radius: var(--radius-pill);
            font-size: var(--text-micro);
            font-weight: var(--font-weight-medium);
        }
        .sheet-state-new { background: var(--color-surface-soft); color: var(--color-text-muted); border: 1px dashed var(--color-border-input); }
        .sheet-state-run { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .sheet-state-part { background: var(--color-warning-soft); color: var(--color-warning); }
        .sheet-state-paid { background: var(--color-success-soft); color: var(--color-success); }
        #reportBody { min-height: 120px; position: relative; }
        #reportBody.loading { opacity: .45; pointer-events: none; }

        .run-list { display: flex; flex-wrap: wrap; gap: var(--space-sm); }
        .run-chip {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-xs) var(--space-xs) var(--space-xs) var(--space-md);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-pill);
            background: var(--color-surface);
            font-size: var(--text-caption);
        }
        .run-chip.reversed { opacity: .6; text-decoration: line-through; }
        .run-chip .btn { min-height: 28px; padding: 0 var(--space-sm); font-size: var(--text-micro); }

        .pay-table td, .pay-table th { min-width: 0; padding: .35rem 8px; font-size: var(--text-caption); vertical-align: middle; }
        .pay-table input { max-width: 130px; margin-left: auto; text-align: right; min-height: var(--control-height-sm); height: var(--control-height-sm); }
        .flow-steps { display: flex; flex-wrap: wrap; gap: var(--space-xs) var(--space-lg); font-size: var(--text-caption); color: var(--color-text-muted); }
        .flow-steps b { color: var(--color-text); font-weight: var(--font-weight-medium); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Salary Sheet</h4>
                <small class="text-muted">
                    <b>{{ $payroll->salary_structure }}</b> structure (<a href="{{ route('admin.payroll-setup.index') }}">Payroll Setup</a>) ·
                    <a href="{{ route('admin.payroll-adjustments.index') }}">Bonus &amp; adjustments</a> ·
                    <a href="{{ route('admin.employee-loans.index') }}">Loans &amp; advances</a>
                </small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <a href="{{ route('admin.salary-payments.index') }}" class="btn btn-outline-secondary"><i class="fas fa-history"></i> Payments</a>
                <button type="button" class="btn btn-outline-primary" id="processBtn" disabled><i class="fas fa-lock"></i> Process Salary</button>
                <button type="button" class="btn btn-primary" id="payBtn" disabled><i class="fas fa-money-check-alt"></i> Pay Salary</button>
                <button type="button" class="btn btn-outline-danger" id="pdfBtn" title="Download as PDF"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" class="btn btn-outline-success" id="excelBtn" title="Download as Excel"><i class="fas fa-file-excel"></i> Excel</button>
            </div>
        </div>

        <div class="flow-steps mb-3">
            <span><b>1.</b> Add bonuses, additions, deductions, loans</span>
            <span><b>2.</b> Process Salary — freezes the month and posts it</span>
            <span><b>3.</b> Pay Salary — full or partial, from any cash / bank account, as often as needed</span>
        </div>

        {{-- Filters --}}
        <div class="card acc-card mb-3">
            <div class="card-body">
                <form id="filterForm" class="row g-3 align-items-end" novalidate>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="month">Month <span class="text-danger">*</span></label>
                        <input type="month" id="month" name="month" class="form-control" value="{{ now()->format('Y-m') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="department_id">Department</label>
                        <select id="department_id" name="department_id" class="form-select">
                            <option value="">All departments</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="include_bonus">
                            <label class="form-check-label" for="include_bonus">
                                Add default bonus ({{ (float) $payroll->bonus_percent }}% of gross) for not-processed employees
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill" id="generateBtn"><i class="fas fa-search"></i> Generate</button>
                    </div>
                    <div class="col-12"><div class="text-danger small" id="filterError"></div></div>
                </form>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-users"></i></div>
                    <div><div class="text-muted small">Employees</div><h5 class="mb-0" id="sumEmployees">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-hand-holding-usd"></i></div>
                    <div><div class="text-muted small">Net Payable</div><h5 class="mb-0" id="sumNet">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-check"></i></div>
                    <div><div class="text-muted small">Paid</div><h5 class="mb-0" id="sumPaid">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-hourglass-half"></i></div>
                    <div><div class="text-muted small">Due</div><h5 class="mb-0" id="sumDue">0.00</h5></div>
                </div></div>
            </div>
        </div>

        <div class="card acc-card mb-3 d-none" id="runsCard">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small me-1">Processed this month:</span>
                    <div class="run-list" id="runList"></div>
                </div>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body" id="reportBody">
                <div class="text-center text-muted py-5">Choose the month and press <strong>Generate</strong>.</div>
            </div>
        </div>
    </div>

    {{-- Process salary --}}
    <div class="modal fade" id="processModal" tabindex="-1" aria-labelledby="processTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="processForm" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="processTitle">Process Salary</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3" id="processIntro"></p>
                        <table class="table table-sm mb-3">
                            <tbody>
                                <tr><td>Employees</td><td class="text-end" id="prCount">0</td></tr>
                                <tr><td>Gross salary</td><td class="text-end" id="prGross">0.00</td></tr>
                                <tr><td>Bonus</td><td class="text-end" id="prBonus">0.00</td></tr>
                                <tr><td>Additions</td><td class="text-end" id="prAdd">0.00</td></tr>
                                <tr><td>Deductions &amp; unpaid leave</td><td class="text-end text-danger" id="prDed">0.00</td></tr>
                                <tr><td>PF</td><td class="text-end text-danger" id="prPf">0.00</td></tr>
                                <tr><td>Loan / advance recovery</td><td class="text-end text-danger" id="prLoan">0.00</td></tr>
                                <tr class="fw-semibold"><td>Net payable</td><td class="text-end" id="prNet">0.00</td></tr>
                            </tbody>
                        </table>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="run_date">Posting Date <span class="text-danger">*</span></label>
                                <input type="date" name="run_date" id="run_date" class="form-control" value="{{ now()->toDateString() }}">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="expense_account_id">Salary Expense Ledger <span class="text-danger">*</span></label>
                                <select name="expense_account_id" id="expense_account_id" class="form-select">
                                    <option value="">Select expense ledger</option>
                                    @foreach ($expenseAccounts as $a)
                                        <option value="{{ $a->id }}" @selected($payroll->salary_expense_account_id == $a->id)>{{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payable_account_id">Salary Payable Ledger <span class="text-danger">*</span></label>
                                <select name="payable_account_id" id="payable_account_id" class="form-select">
                                    <option value="">Select liability ledger</option>
                                    @foreach ($liabilityAccounts as $a)
                                        <option value="{{ $a->id }}" @selected(($payroll->salary_payable_account_id ?? null) == $a->id)>{{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6 pr-pf">
                                <label class="form-label" for="pf_account_id">PF Payable Ledger <span class="text-danger">*</span></label>
                                <select name="pf_account_id" id="pf_account_id" class="form-select">
                                    <option value="">Select liability ledger</option>
                                    @foreach ($liabilityAccounts as $a)
                                        <option value="{{ $a->id }}" @selected($payroll->pf_account_id == $a->id)>{{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6 pr-loan">
                                <label class="form-label" for="loan_account_id">Employee Loan Ledger <span class="text-danger">*</span></label>
                                <select name="loan_account_id" id="loan_account_id" class="form-select">
                                    <option value="">Select asset ledger</option>
                                    @foreach ($assetAccounts as $a)
                                        <option value="{{ $a->id }}" @selected(($payroll->loan_account_id ?? null) == $a->id)>{{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Missing a ledger (e.g. "Salary &amp; Wages", "Salary Payable", "Provident Fund Payable", "Employee Loans")? Add it in
                                    <a href="{{ route('admin.chart-of-accounts.page') }}" target="_blank">Chart of Accounts</a> and reopen this window.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="process_remarks">Remarks</label>
                                <input type="text" name="remarks" id="process_remarks" class="form-control" maxlength="500">
                            </div>
                        </div>
                        <div class="text-danger small mt-2" id="process_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="processSubmit">Process</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Pay salary --}}
    <div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="payForm" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="payTitle">Pay Salary</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" id="payment_date" class="form-control" value="{{ now()->toDateString() }}">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="payment_account_id">Paid From <span class="text-danger">*</span></label>
                                <select name="payment_account_id" id="payment_account_id" class="form-select">
                                    <option value="">Select cash / bank / mobile banking</option>
                                    @foreach ($paymentAccounts as $a)
                                        <option value="{{ $a->id }}" @selected($payroll->salary_payment_account_id == $a->id)>{{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="pay_remarks">Remarks</label>
                                <input type="text" name="remarks" id="pay_remarks" class="form-control" maxlength="500">
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="small text-muted">Enter what each employee gets now (any amount up to the due).</span>
                            <div class="ms-auto d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="payAllDue">Fill all due</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="payClear">Clear</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered pay-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th class="text-end">Net Payable</th>
                                        <th class="text-end">Paid</th>
                                        <th class="text-end">Due</th>
                                        <th class="text-end">Pay Now</th>
                                    </tr>
                                </thead>
                                <tbody id="payRows"></tbody>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td colspan="4" class="text-end">Total to pay now</td>
                                        <td class="text-end" id="payTotal">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="text-danger small mt-2" id="pay_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="paySubmit">Post Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const dataUrl = "{{ route('admin.reports.salary-sheet.data') }}";
        const pdfUrl = "{{ route('admin.reports.salary-sheet.pdf') }}";
        const excelUrl = "{{ route('admin.reports.salary-sheet.excel') }}";
        const runsUrl = "{{ route('admin.payroll-runs.index') }}";
        const reverseUrl = "{{ route('admin.payroll-runs.reverse', '__ID__') }}";
        let toProcess = null, toPay = [];

        function filterQuery() {
            const params = {
                month: $('#month').val(),
                department_id: $('#department_id').val(),
                include_bonus: $('#include_bonus').is(':checked') ? 1 : 0
            };
            if (!params.department_id) delete params.department_id;

            return $.param(params);
        }

        function validMonth() {
            $('#filterError').text($('#month').val() ? '' : 'Select the month.');
            return !!$('#month').val();
        }

        function monthLabel() {
            return new Date($('#month').val() + '-01T00:00:00').toLocaleString('en', { month: 'long', year: 'numeric' });
        }

        function loadRuns() {
            $.ajax({
                url: runsUrl,
                type: 'GET',
                dataType: 'json',
                data: { month: $('#month').val() },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    const runs = response.data || [];
                    $('#runsCard').toggleClass('d-none', runs.length === 0);
                    $('#runList').html(runs.map(function(r) {
                        const reversed = r.run_status !== 'Posted';
                        const canReverse = !reversed && parseFloat(r.total_paid) === 0;
                        return '<span class="run-chip ' + (reversed ? 'reversed' : '') + '" title="' + (reversed ? 'Reversed' : 'Posted ' + r.run_date) + '">' +
                            '<b>' + Voucher.esc(r.run_no) + '</b> ' + Voucher.esc(r.department || 'All departments') + ' · ' + r.employee_count +
                            ' emp · ' + Voucher.money(r.total_net) + (parseFloat(r.total_paid) > 0 ? ' · paid ' + Voucher.money(r.total_paid) : '') +
                            (canReverse ? ' <button type="button" class="btn btn-outline-danger run-reverse" data-id="' + r.id + '" data-no="' + Voucher.esc(r.run_no) + '">Reverse</button>' : '') +
                            '</span>';
                    }).join(''));
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }

        function loadReport() {
            if (!validMonth()) return;
            const $btn = $('#generateBtn');

            $.ajax({
                url: dataUrl + '?' + filterQuery(),
                type: 'GET',
                dataType: 'json',
                beforeSend: function() {
                    $('#reportBody').addClass('loading');
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Loading...');
                },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    if (!response.status) {
                        toastr.error(response.message || 'Unable to build the salary sheet.');
                        return;
                    }
                    $('#reportBody').html(response.html);
                    $('#sumEmployees').text(response.summary.employees);
                    $('#sumNet').text(response.summary.net);
                    $('#sumPaid').text(response.summary.paid);
                    $('#sumDue').text(response.summary.due);

                    toProcess = response.to_process;
                    toPay = response.to_pay || [];
                    $('#processBtn').prop('disabled', !toProcess || toProcess.count === 0)
                        .attr('title', toProcess && toProcess.count ? 'Process ' + toProcess.count + ' employee(s)' : 'Everyone is processed');
                    $('#payBtn').prop('disabled', toPay.length === 0)
                        .attr('title', toPay.length ? toPay.length + ' employee(s) have salary due' : 'Nothing due — process salary first');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    if (xhr.status === 422 && xhr.responseJSON) {
                        $('#filterError').text(xhr.responseJSON.message);
                    } else {
                        Voucher.ajaxError(xhr, 'Unable to build the salary sheet.');
                    }
                },
                complete: function() {
                    $('#reportBody').removeClass('loading');
                    $btn.prop('disabled', false).html('<i class="fas fa-search"></i> Generate');
                }
            });
            loadRuns();
        }

        function payTotal() {
            let sum = 0;
            $('#payRows .pay-amount').each(function() { sum += parseFloat($(this).val()) || 0; });
            $('#payTotal').text(Voucher.money(sum));
        }

        $(document).ready(function() {
            Voucher.initSelect2($('#department_id'));

            $('#filterForm').on('submit', function(e) { e.preventDefault(); loadReport(); });
            $('#pdfBtn').on('click', function() { if (validMonth()) window.open(pdfUrl + '?' + filterQuery(), '_blank'); });
            $('#excelBtn').on('click', function() { if (validMonth()) window.location.href = excelUrl + '?' + filterQuery(); });

            /* ----- Process salary ----- */
            const processModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('processModal'));
            Voucher.initSelect2($('#expense_account_id, #payable_account_id, #pf_account_id, #loan_account_id'), $('#processModal'));

            $('#processBtn').on('click', function() {
                if (!toProcess || !toProcess.count) return;
                $('#processIntro').text('Freezes the ' + monthLabel() + ' salary' +
                    ($('#department_id').val() ? ' of ' + $('#department_id option:selected').text() : '') +
                    ' for employees not processed yet and posts it (expense / salary payable). Loan installments are recovered. ' +
                    'It can be reversed until a payment is made.');
                $('#prCount').text(toProcess.count);
                $('#prGross').text(Voucher.money(toProcess.gross));
                $('#prBonus').text(Voucher.money(toProcess.bonus));
                $('#prAdd').text(Voucher.money(toProcess.additions));
                $('#prDed').text(Voucher.money(toProcess.deductions));
                $('#prPf').text(Voucher.money(toProcess.pf));
                $('#prLoan').text(Voucher.money(toProcess.loan));
                $('#prNet').text(Voucher.money(toProcess.net));
                $('.pr-pf').toggleClass('d-none', !(toProcess.pf > 0));
                $('.pr-loan').toggleClass('d-none', !(toProcess.loan > 0));
                Voucher.clearErrors($('#processForm'));
                $('#process_error').text('');
                processModal.show();
            });

            $('#processForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#processSubmit').prop('disabled', true).text('Processing...');
                $.ajax({
                    url: "{{ route('admin.payroll-runs.store') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize() + '&' + filterQuery(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        processModal.hide();
                        loadReport();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($('#processForm'), xhr.responseJSON.errors, $('#process_error'));
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            $('#process_error').text(xhr.responseJSON.message);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to process the salary.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false).text('Process'); }
                });
            });

            $(document).on('click', '.run-reverse', function() {
                const id = $(this).data('id'), no = $(this).data('no');
                Swal.fire({
                    title: 'Reverse ' + no + '?',
                    text: 'The posting and loan recoveries are undone; those employees can be processed again.',
                    input: 'text',
                    inputPlaceholder: 'Reason',
                    showCancelButton: true,
                    confirmButtonText: 'Reverse',
                    inputValidator: function(v) { return v ? null : 'Write the reason.'; }
                }).then(function(r) {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: reverseUrl.replace('__ID__', id),
                        type: 'POST',
                        dataType: 'json',
                        data: { reason: r.value },
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            loadReport();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to reverse.');
                        }
                    });
                });
            });

            /* ----- Pay salary ----- */
            const payModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('payModal'));
            Voucher.initSelect2($('#payment_account_id'), $('#payModal'));

            $('#payBtn').on('click', function() {
                if (!toPay.length) return;
                $('#payTitle').text('Pay Salary — ' + monthLabel());
                $('#payRows').html(toPay.map(function(r) {
                    return '<tr>' +
                        '<td><span class="fw-semibold">' + Voucher.esc(r.name) + '</span> <span class="text-muted">' + Voucher.esc(r.code || '') + '</span>' +
                        '<div class="text-muted small">' + Voucher.esc(r.bank || '') + '</div></td>' +
                        '<td class="text-end">' + Voucher.money(r.net) + '</td>' +
                        '<td class="text-end">' + Voucher.money(r.paid) + '</td>' +
                        '<td class="text-end fw-semibold">' + Voucher.money(r.due) + '</td>' +
                        '<td><input type="number" class="form-control pay-amount" name="items[' + r.item_id + ']" min="0" max="' + r.due + '" step="0.01" value="' + r.due + '" data-due="' + r.due + '" aria-label="Pay now"></td>' +
                        '</tr>';
                }).join(''));
                payTotal();
                Voucher.clearErrors($('#payForm'));
                $('#pay_error').text('');
                payModal.show();
            });

            $(document).on('input', '.pay-amount', function() {
                const due = parseFloat($(this).data('due')) || 0;
                if ((parseFloat($(this).val()) || 0) > due) $(this).val(due);
                payTotal();
            });
            $('#payAllDue').on('click', function() { $('.pay-amount').each(function() { $(this).val($(this).data('due')); }); payTotal(); });
            $('#payClear').on('click', function() { $('.pay-amount').val(''); payTotal(); });

            $('#payForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#paySubmit').prop('disabled', true).text('Posting...');
                $.ajax({
                    url: "{{ route('admin.salary-payments.store') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        payModal.hide();
                        loadReport();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($('#payForm'), xhr.responseJSON.errors, $('#pay_error'));
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            $('#pay_error').text(xhr.responseJSON.message);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to post the payment.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false).text('Post Payment'); }
                });
            });

            $('#month').on('change', loadReport);
            loadReport();
        });
    </script>
@endpush
