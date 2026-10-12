@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Bills
@endsection

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Bills</h4>
                <small class="text-muted">Supplier bills for services and expenses, paid now or later from a Liquid Asset account.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn btn-primary" onclick="addBill()">
                    <i class="fas fa-plus"></i> Add Bill
                </button>
            </div>
        </div>

        @if (!$hasPayable)
            <div class="alert alert-warning py-2">
                The <strong>Accounts Payable</strong> account is missing. Run <code>php artisan migrate</code> before saving bills.
            </div>
        @endif

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-file-invoice"></i></div>
                    <div><div class="text-muted small">Billed (filtered)</div><h5 class="mb-0" id="sumFilteredTotal">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-list-ol"></i></div>
                    <div><div class="text-muted small">Bills (filtered)</div><h5 class="mb-0" id="sumFilteredCount">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-hourglass-half"></i></div>
                    <div><div class="text-muted small">Total Due (all bills)</div><h5 class="mb-0" id="sumTotalDue">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-calendar-alt"></i></div>
                    <div><div class="text-muted small">Billed This Month</div><h5 class="mb-0" id="sumMonthTotal">0.00</h5></div>
                </div></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label" for="filterFrom">From</label>
                        <input type="date" id="filterFrom" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filterTo">To</label>
                        <input type="date" id="filterTo" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterParty">Supplier</label>
                        <select id="filterParty" class="form-select">
                            <option value="">All suppliers</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }} ({{ $supplier->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterStatus">Status</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">All</option>
                            <option value="Unpaid">Unpaid</option>
                            <option value="Partial">Partial</option>
                            <option value="Paid">Paid</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-fill" id="applyFilters" title="Apply filters">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary flex-fill" id="resetFilters" title="Reset filters">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="billTable" class="table table-hover align-middle w-100 acc-table"
                        data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" data-searchable="false" width="5%">SL.</th>
                                <th>Bill No</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Due Date</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th>Status</th>
                                <th data-orderable="false" data-searchable="false" width="13%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit bill modal --}}
    <div class="modal fade" id="billModal" tabindex="-1" aria-labelledby="billModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <form id="billForm" novalidate>
                    @csrf
                    <input type="hidden" name="id" id="bill_id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="billModalTitle">Add Bill</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        @if ($suppliers->isEmpty() || $expenseGroups->isEmpty())
                            <div class="alert alert-warning py-2 small">
                                @if ($suppliers->isEmpty()) No active supplier found. @endif
                                @if ($expenseGroups->isEmpty()) No active 2nd layer group under the Expense root. @endif
                                Add them first, otherwise a bill cannot be saved.
                            </div>
                        @endif

                        <div class="acc-section mb-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="party_id">Supplier <span class="text-danger">*</span></label>
                                    <select name="party_id" id="party_id" class="form-select">
                                        <option value="">Select supplier</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }} ({{ $supplier->code }})</option>
                                        @endforeach
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="bill_date">Bill Date <span class="text-danger">*</span></label>
                                    <input type="date" name="bill_date" id="bill_date" class="form-control">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="due_date">Due Date</label>
                                    <input type="date" name="due_date" id="due_date" class="form-control">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="reference_no">Supplier Bill / Reference No</label>
                                    <input type="text" name="reference_no" id="reference_no" class="form-control" maxlength="100" placeholder="The supplier's bill number">
                                    <div class="text-danger small field-error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="acc-section mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0">Bill Lines</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="addLineBtn">
                                    <i class="fas fa-plus"></i> Add Line
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 acc-lines" style="min-width: 980px">
                                    <thead>
                                        <tr>
                                            <th width="4%">#</th>
                                            <th width="20%">Expense Group</th>
                                            <th width="20%">Expense Account</th>
                                            <th>Description</th>
                                            <th width="9%" class="text-end">Qty</th>
                                            <th width="11%" class="text-end">Rate</th>
                                            <th width="12%" class="text-end">Amount</th>
                                            <th width="4%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="linesBody"></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="6" class="text-end fw-semibold pt-3">Subtotal</td>
                                            <td class="pt-2"><input type="text" id="subtotal" class="form-control form-control-sm text-end" readonly value="0.00"></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="text-end fw-semibold pt-3">Discount</td>
                                            <td class="pt-2">
                                                <input type="number" step="0.01" min="0" name="discount_amount" id="discount_amount" class="form-control form-control-sm text-end" placeholder="0.00">
                                                <div class="text-danger small field-error"></div>
                                            </td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="text-end fw-semibold pt-3">Grand Total (owed to supplier)</td>
                                            <td class="pt-2"><input type="text" id="grand_total" class="form-control text-end acc-grand" readonly value="0.00"></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="text-danger small mt-1" id="lines_error"></div>
                        </div>

                        <div>
                            <label class="form-label" for="remarks">Note</label>
                            <textarea name="remarks" id="remarks" class="form-control" rows="2" maxlength="1000" placeholder="Any note about this bill (optional)"></textarea>
                            <div class="text-danger small field-error"></div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- View bill modal --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalTitle">Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bill payments modal --}}
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalTitle">Bill Payments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3 mb-3" id="paySummary"></div>

                    <div class="acc-section mb-3">
                        <h6 class="mb-2" id="payFormTitle">Add Payment</h6>
                        <form id="paymentForm" novalidate>
                            @csrf
                            <input type="hidden" name="id" id="payment_id">
                            <input type="hidden" name="bill_id" id="payment_bill_id">

                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label" for="payment_date">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_date" id="payment_date" class="form-control">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="payment_group_id">Paid From - Group <span class="text-danger">*</span></label>
                                    <select name="payment_group_id" id="payment_group_id" class="form-select">
                                        <option value="">Select group (e.g. Bank, Cash)</option>
                                        @foreach ($paymentGroups as $group)
                                            <option value="{{ $group->id }}">{{ $group->account_code }} - {{ $group->account_name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="payment_account_id">Paid From - Account <span class="text-danger">*</span></label>
                                    <select name="payment_account_id" id="payment_account_id" class="form-select" disabled>
                                        <option value="">Select group first</option>
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="payment_amount">Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" name="amount" id="payment_amount" class="form-control text-end" placeholder="0.00">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="payment_reference">Reference</label>
                                    <input type="text" name="reference_no" id="payment_reference" class="form-control" maxlength="100" placeholder="Cheque / Txn no">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="payment_remarks">Note</label>
                                    <input type="text" name="remarks" id="payment_remarks" class="form-control" maxlength="1000" placeholder="Optional">
                                    <div class="text-danger small field-error"></div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end gap-2">
                                    <button type="button" class="btn btn-outline-secondary flex-fill" id="payCancelEdit">New</button>
                                    <button type="submit" class="btn btn-success flex-fill" id="paySaveBtn">Save Payment</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle acc-table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Payment No</th>
                                    <th>Date</th>
                                    <th>Paid From</th>
                                    <th>Reference</th>
                                    <th class="text-end">Amount</th>
                                    <th width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="paymentsBody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let billTable, billModal, viewModal, paymentModal;
        let lineIndex = 0;
        let currentBill = null;   // bill shown in the payments modal

        const expenseGroups = @json($expenseGroups);
        const listUrl = "{{ route('admin.bill.list') }}";
        const showUrl = "{{ route('admin.bill.show') }}";

        /* ================= bill lines ================= */
        function groupOptions() {
            return expenseGroups.map(function(g) {
                return '<option value="' + g.id + '">' + Voucher.esc(g.account_code + ' - ' + g.account_name) + '</option>';
            }).join('');
        }

        function lineHtml(i) {
            return '<tr>' +
                '<td class="text-muted line-no pt-2"></td>' +
                '<td><select name="details[' + i + '][account_group_id]" class="form-select form-select-sm line-group">' +
                    '<option value="">Select expense group</option>' + groupOptions() + '</select>' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><select name="details[' + i + '][account_id]" class="form-select form-select-sm line-account" disabled>' +
                    '<option value="">Select group first</option></select>' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm line-description" maxlength="255" placeholder="Description (optional)">' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="number" step="0.01" min="0" name="details[' + i + '][qty]" class="form-control form-control-sm text-end line-qty" value="1">' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="number" step="0.01" min="0" name="details[' + i + '][rate]" class="form-control form-control-sm text-end line-rate" placeholder="0.00">' +
                    '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="text" class="form-control form-control-sm text-end line-amount" readonly value="0.00"></td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove line"><i class="fas fa-times"></i></button></td>' +
                '</tr>';
        }

        function renumberLines() {
            $('#linesBody tr').each(function(n) {
                $(this).find('.line-no').text(n + 1);
            });
        }

        function calcTotals() {
            let subtotal = 0;

            $('#linesBody tr').each(function() {
                const qty = parseFloat($(this).find('.line-qty').val()) || 0;
                const rate = parseFloat($(this).find('.line-rate').val()) || 0;
                const amount = Math.round(qty * rate * 100) / 100;

                $(this).find('.line-amount').val(amount.toFixed(2));
                subtotal += amount;
            });

            const discount = parseFloat($('#discount_amount').val()) || 0;

            $('#subtotal').val(subtotal.toFixed(2));
            $('#grand_total').val(Math.max(subtotal - discount, 0).toFixed(2));
        }

        function addLine(data) {
            const i = lineIndex++;
            const $tr = $(lineHtml(i));

            $('#linesBody').append($tr);
            Voucher.initSelect2($tr.find('select'), $('#billModal'));
            renumberLines();

            if (!data) {
                return $.Deferred().resolve().promise();
            }

            Voucher.setValue($tr.find('.line-group'), data.account_group_id, data.group_label);
            $tr.find('.line-description').val(data.description || '');
            $tr.find('.line-qty').val(data.qty);
            $tr.find('.line-rate').val(data.rate);

            return Voucher.loadAccounts($tr.find('.line-account'), data.account_group_id, data.account_id, data.account_label);
        }

        /* ================= add / edit bill ================= */
        function resetForm() {
            $('#billForm')[0].reset();
            Voucher.clearErrors($('#billForm'));
            $('#lines_error').text('');
            $('#bill_id').val('');

            Voucher.destroySelect2($('#linesBody').find('select'));
            $('#linesBody').empty();
            lineIndex = 0;

            Voucher.refresh($('#party_id'));
        }

        function addBill() {
            resetForm();

            $('#bill_date').val(Voucher.todayLocal());
            $('#billModalTitle').text('Add Bill');
            $('#saveBtn').prop('disabled', false).text('Save');

            addLine();
            calcTotals();
            billModal.show();
        }

        function editBill(id) {
            $.ajax({
                url: showUrl,
                type: 'GET',
                data: { id: id },
                success: function(res) {
                    if (!res.status) {
                        toastr.error(res.message || 'Unable to load bill.');
                        return;
                    }

                    const d = res.data;
                    resetForm();

                    $('#bill_id').val(d.id);
                    $('#bill_date').val(d.bill_date);
                    $('#due_date').val(d.due_date || '');
                    $('#reference_no').val(d.reference_no || '');
                    $('#remarks').val(d.remarks || '');
                    $('#discount_amount').val(parseFloat(d.discount_amount) > 0 ? d.discount_amount : '');
                    Voucher.setValue($('#party_id'), d.party_id, d.party_label);

                    const jobs = (d.details || []).map(function(line) { return addLine(line); });

                    $.when.apply($, jobs).always(function() {
                        calcTotals();
                        $('#billModalTitle').text('Edit Bill ' + d.bill_no + (parseFloat(d.paid_amount) > 0 ? '  (paid ' + Voucher.money(d.paid_amount) + ')' : ''));
                        $('#saveBtn').prop('disabled', false).text('Update');
                        billModal.show();
                    });
                },
                error: function(xhr) {
                    Voucher.ajaxError(xhr, 'Unable to load bill.');
                }
            });
        }

        /* ================= view bill ================= */
        function statusBadge(status) {
            const cls = { Paid: 'bg-success', Partial: 'bg-warning text-dark', Unpaid: 'bg-danger' }[status] || 'bg-secondary';
            return '<span class="badge ' + cls + '">' + Voucher.esc(status) + '</span>';
        }

        function viewBill(id) {
            $.ajax({
                url: showUrl,
                type: 'GET',
                data: { id: id },
                success: function(res) {
                    if (!res.status) {
                        toastr.error(res.message || 'Unable to load bill.');
                        return;
                    }

                    const d = res.data;

                    const lines = (d.details || []).map(function(l, n) {
                        return '<tr><td>' + (n + 1) + '</td>' +
                            '<td>' + Voucher.esc(l.account_label) + '<div class="text-muted small">' + Voucher.esc(l.group_label) + '</div></td>' +
                            '<td>' + Voucher.esc(l.description || '-') + '</td>' +
                            '<td class="text-end">' + l.qty + '</td>' +
                            '<td class="text-end">' + Voucher.money(l.rate) + '</td>' +
                            '<td class="text-end">' + Voucher.money(l.amount) + '</td></tr>';
                    }).join('');

                    const payments = (d.payments || []).map(function(p) {
                        return '<tr><td>' + Voucher.esc(p.payment_no) + '</td><td>' + Voucher.esc(p.payment_date_label) + '</td>' +
                            '<td>' + Voucher.esc(p.payment_account_label) + '</td>' +
                            '<td class="text-end">' + Voucher.money(p.amount) + '</td></tr>';
                    }).join('') || '<tr><td colspan="4" class="text-muted text-center">No payments yet.</td></tr>';

                    $('#viewModalTitle').html('Bill ' + Voucher.esc(d.bill_no) + ' ' + statusBadge(d.payment_status));
                    $('#viewBody').html(
                        '<dl class="row acc-view mb-3">' +
                            '<dt class="col-sm-3">Supplier</dt><dd class="col-sm-9">' + Voucher.esc(d.party_label) + (d.party_phone ? ' · ' + Voucher.esc(d.party_phone) : '') + '</dd>' +
                            '<dt class="col-sm-3">Bill Date</dt><dd class="col-sm-9">' + Voucher.esc(d.bill_date) + '</dd>' +
                            '<dt class="col-sm-3">Due Date</dt><dd class="col-sm-9">' + Voucher.esc(d.due_date || '-') + '</dd>' +
                            '<dt class="col-sm-3">Reference</dt><dd class="col-sm-9">' + Voucher.esc(d.reference_no || '-') + '</dd>' +
                            '<dt class="col-sm-3">Note</dt><dd class="col-sm-9">' + Voucher.esc(d.remarks || '-') + '</dd>' +
                        '</dl>' +
                        '<div class="table-responsive"><table class="table table-sm acc-table">' +
                            '<thead><tr><th>#</th><th>Account</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>' +
                            '<tbody>' + lines + '</tbody>' +
                            '<tfoot>' +
                                '<tr><td colspan="5" class="text-end">Subtotal</td><td class="text-end">' + Voucher.money(d.subtotal) + '</td></tr>' +
                                '<tr><td colspan="5" class="text-end">Discount</td><td class="text-end">' + Voucher.money(d.discount_amount) + '</td></tr>' +
                                '<tr class="fw-semibold"><td colspan="5" class="text-end">Grand Total</td><td class="text-end">' + Voucher.money(d.grand_total) + '</td></tr>' +
                                '<tr><td colspan="5" class="text-end">Paid</td><td class="text-end">' + Voucher.money(d.paid_amount) + '</td></tr>' +
                                '<tr class="fw-semibold"><td colspan="5" class="text-end">Due</td><td class="text-end">' + Voucher.money(d.due_amount) + '</td></tr>' +
                            '</tfoot>' +
                        '</table></div>' +
                        '<h6 class="mt-3">Payments</h6>' +
                        '<div class="table-responsive"><table class="table table-sm acc-table">' +
                            '<thead><tr><th>Payment No</th><th>Date</th><th>Paid From</th><th class="text-end">Amount</th></tr></thead>' +
                            '<tbody>' + payments + '</tbody>' +
                        '</table></div>'
                    );
                    viewModal.show();
                },
                error: function(xhr) {
                    Voucher.ajaxError(xhr, 'Unable to load bill.');
                }
            });
        }

        /* ================= delete bill ================= */
        function deleteBill(id) {
            Voucher.confirmDelete(
                'Delete this bill?',
                'Its amounts will be taken back out of the supplier due and the account balances.',
                "{{ route('admin.bill.delete') }}",
                id,
                function() { billTable.ajax.reload(null, false); }
            );
        }

        /* ================= bill payments ================= */
        function openBillPayments(id) {
            loadBillPayments(id, function() {
                resetPaymentForm();
                paymentModal.show();
            });
        }

        function loadBillPayments(id, done) {
            $.ajax({
                url: showUrl,
                type: 'GET',
                data: { id: id },
                success: function(res) {
                    if (!res.status) {
                        toastr.error(res.message || 'Unable to load bill.');
                        return;
                    }

                    currentBill = res.data;
                    renderPayments();
                    if (done) done();
                },
                error: function(xhr) {
                    Voucher.ajaxError(xhr, 'Unable to load bill.');
                }
            });
        }

        function renderPayments() {
            const d = currentBill;

            $('#paymentModalTitle').html('Payments · Bill ' + Voucher.esc(d.bill_no) + ' ' + statusBadge(d.payment_status));

            const card = function(label, value, tone) {
                return '<div class="col-6 col-md-3"><div class="acc-section py-2">' +
                    '<div class="text-muted small">' + label + '</div>' +
                    '<div class="fw-semibold ' + (tone || '') + '">' + value + '</div></div></div>';
            };

            $('#paySummary').html(
                card('Supplier', Voucher.esc(d.party_label)) +
                card('Grand Total', Voucher.money(d.grand_total)) +
                card('Paid', Voucher.money(d.paid_amount), 'text-success') +
                card('Due', Voucher.money(d.due_amount), parseFloat(d.due_amount) > 0 ? 'text-danger' : '')
            );

            const rows = (d.payments || []).map(function(p, n) {
                return '<tr>' +
                    '<td>' + (n + 1) + '</td>' +
                    '<td class="fw-semibold">' + Voucher.esc(p.payment_no) + '</td>' +
                    '<td>' + Voucher.esc(p.payment_date_label) + '</td>' +
                    '<td>' + Voucher.esc(p.payment_account_label) + '<div class="text-muted small">' + Voucher.esc(p.payment_group_label) + '</div></td>' +
                    '<td>' + Voucher.esc(p.reference_no || '-') + '</td>' +
                    '<td class="text-end">' + Voucher.money(p.amount) + '</td>' +
                    '<td><div class="btn-group btn-group-sm">' +
                        '<button type="button" class="btn btn-outline-primary" title="Edit" onclick="editBillPayment(' + p.id + ')"><i class="fas fa-edit"></i></button>' +
                        '<button type="button" class="btn btn-outline-danger" title="Delete" onclick="deleteBillPayment(' + p.id + ')"><i class="fas fa-trash"></i></button>' +
                    '</div></td>' +
                    '</tr>';
            }).join('');

            $('#paymentsBody').html(rows || '<tr><td colspan="7" class="text-center text-muted">No payments yet.</td></tr>');
        }

        function resetPaymentForm() {
            $('#paymentForm')[0].reset();
            Voucher.clearErrors($('#paymentForm'));

            $('#payment_id').val('');
            $('#payment_bill_id').val(currentBill.id);
            $('#payment_date').val(Voucher.todayLocal());

            const due = parseFloat(currentBill.due_amount) || 0;
            $('#payment_amount').val(due > 0 ? due.toFixed(2) : '');

            Voucher.refresh($('#payment_group_id'));
            Voucher.loadAccounts($('#payment_account_id'), '', '', '', 'Select group first');

            $('#payFormTitle').text('Add Payment');
            $('#paySaveBtn').prop('disabled', due <= 0).text('Save Payment');
        }

        function editBillPayment(paymentId) {
            const p = (currentBill.payments || []).find(function(x) { return x.id === paymentId; });
            if (!p) return;

            Voucher.clearErrors($('#paymentForm'));

            $('#payment_id').val(p.id);
            $('#payment_date').val(p.payment_date);
            $('#payment_amount').val(p.amount);
            $('#payment_reference').val(p.reference_no || '');
            $('#payment_remarks').val(p.remarks || '');

            Voucher.setValue($('#payment_group_id'), p.payment_group_id, p.payment_group_label);
            Voucher.loadAccounts($('#payment_account_id'), p.payment_group_id, p.payment_account_id, p.payment_account_label);

            $('#payFormTitle').text('Edit Payment ' + p.payment_no);
            $('#paySaveBtn').prop('disabled', false).text('Update Payment');
        }

        function deleteBillPayment(paymentId) {
            Voucher.confirmDelete(
                'Delete this payment?',
                'The bill due and the account balance will go back up.',
                "{{ route('admin.bill.payment.delete') }}",
                paymentId,
                function() {
                    loadBillPayments(currentBill.id, resetPaymentForm);
                    billTable.ajax.reload(null, false);
                }
            );
        }

        /* ================= ready ================= */
        $(document).ready(function() {
            billModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('billModal'));
            viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal'));
            paymentModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal'));

            Voucher.initSelect2($('#filterParty, #filterStatus'));
            Voucher.initSelect2($('#party_id'), $('#billModal'));
            Voucher.initSelect2($('#payment_group_id, #payment_account_id'), $('#paymentModal'));

            $('#billTable').on('xhr.dt', function(e, settings, json) {
                if (!json || !json.summary) return;

                $('#sumFilteredTotal').text(json.summary.filtered_total);
                $('#sumFilteredCount').text(json.summary.filtered_count);
                $('#sumTotalDue').text(json.summary.total_due);
                $('#sumMonthTotal').text(json.summary.month_total);
            });

            Voucher.keepSerial($('#billTable'));

            billTable = $('#billTable').DataTable({
                ajax: listUrl,
                processing: true
            });

            /* ----- filters ----- */
            $('#applyFilters').on('click', function() {
                const params = $.param({
                    from: $('#filterFrom').val(),
                    to: $('#filterTo').val(),
                    party_id: $('#filterParty').val(),
                    payment_status: $('#filterStatus').val()
                });

                billTable.ajax.url(listUrl + '?' + params).load();
            });

            $('#resetFilters').on('click', function() {
                $('#filterFrom, #filterTo').val('');
                $('#filterParty, #filterStatus').val('').trigger('change.select2');
                billTable.ajax.url(listUrl).load();
            });

            $('#refreshBtn').on('click', function() {
                billTable.ajax.reload(null, false);
            });

            /* ----- bill lines ----- */
            $('#addLineBtn').on('click', function() { addLine(); });

            $('#linesBody').on('change', '.line-group', function() {
                Voucher.loadAccounts($(this).closest('tr').find('.line-account'), this.value);
            });

            $('#linesBody').on('input', '.line-qty, .line-rate', calcTotals);
            $('#discount_amount').on('input', calcTotals);

            $('#linesBody').on('click', '.remove-line', function() {
                const $tr = $(this).closest('tr');

                if ($('#linesBody tr').length === 1) {
                    $tr.find('.line-group').val('').trigger('change');
                    $tr.find('.line-description, .line-rate').val('');
                    $tr.find('.line-qty').val(1);
                } else {
                    Voucher.destroySelect2($tr.find('select'));
                    $tr.remove();
                }

                renumberLines();
                calcTotals();
            });

            /* ----- save bill ----- */
            $('#billForm').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $button = $('#saveBtn');
                const label = $('#bill_id').val() ? 'Update' : 'Save';

                Voucher.clearErrors($form);
                $('#lines_error').text('');

                $.ajax({
                    url: "{{ route('admin.bill.store') }}",
                    type: 'POST',
                    data: $form.serialize(),
                    beforeSend: function() {
                        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
                    },
                    success: function(res) {
                        if (res.status) {
                            billModal.hide();
                            toastr.success(res.message);
                            billTable.ajax.reload(null, false);
                        } else {
                            toastr.error(res.message || 'Unable to save bill.');
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors, $('#lines_error'));
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save bill.');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(label);
                    }
                });
            });

            /* ----- payments ----- */
            $('#payment_group_id').on('change', function() {
                Voucher.loadAccounts($('#payment_account_id'), this.value, '', '', 'Select group first');
            });

            $('#payCancelEdit').on('click', resetPaymentForm);

            $('#paymentForm').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $button = $('#paySaveBtn');
                const label = $('#payment_id').val() ? 'Update Payment' : 'Save Payment';

                Voucher.clearErrors($form);

                $.ajax({
                    url: "{{ route('admin.bill.payment.store') }}",
                    type: 'POST',
                    data: $form.serialize(),
                    beforeSend: function() {
                        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
                    },
                    success: function(res) {
                        if (res.status) {
                            toastr.success(res.message);
                            loadBillPayments(currentBill.id, resetPaymentForm);
                            billTable.ajax.reload(null, false);
                        } else {
                            toastr.error(res.message || 'Unable to save payment.');
                            $button.prop('disabled', false).text(label);
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save payment.');
                        }
                        $button.prop('disabled', false).text(label);
                    }
                });
            });
        });
    </script>
@endpush
