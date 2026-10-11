@extends('admin.master')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Expenses
@endsection

@push('styles')
    <style>
        .exp-stat, .exp-card { border: 0; border-radius: 14px; box-shadow: 0 2px 12px rgba(30, 41, 59, .07); }
        .exp-stat .icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; }
        .exp-stat .i1 { background: #fee2e2; color: #b91c1c; }
        .exp-stat .i2 { background: #e0e7ff; color: #4338ca; }
        .exp-stat .i3 { background: #fef3c7; color: #b45309; }
        .exp-stat .i4 { background: #dcfce7; color: #15803d; }
        #expenseTable thead th { background: #f8fafc; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; white-space: nowrap; }
        #expenseTable tbody td { vertical-align: middle; }
        .coa-code { font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: .8rem; background: #f1f5f9; padding: 1px 6px; border-radius: 6px; }
        .exp-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; }
        #detailsTable thead th { font-size: .75rem; text-transform: uppercase; color: #64748b; }
        #detailsTable td { vertical-align: top; }
        .exp-grand { font-size: 1.15rem; font-weight: 700; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Expenses</h4>
                <small class="text-muted">One expense, many expense heads. Paid from a Liquid Asset account.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="refreshExpenseBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn btn-primary" onclick="addExpense()">
                    <i class="fas fa-plus"></i> Add Expense
                </button>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card exp-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-coins"></i></div>
                    <div><div class="text-muted small">Total (filtered)</div><h5 class="mb-0" id="sumFilteredTotal">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card exp-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-receipt"></i></div>
                    <div><div class="text-muted small">Expenses (filtered)</div><h5 class="mb-0" id="sumFilteredCount">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card exp-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-calendar-alt"></i></div>
                    <div><div class="text-muted small">This Month</div><h5 class="mb-0" id="sumMonthTotal">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card exp-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-calendar-day"></i></div>
                    <div><div class="text-muted small">Today</div><h5 class="mb-0" id="sumTodayTotal">0.00</h5></div>
                </div></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card exp-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label" for="filterFrom">From</label>
                        <input type="date" id="filterFrom" class="form-control" placeholder="From date">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filterTo">To</label>
                        <input type="date" id="filterTo" class="form-control" placeholder="To date">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterEmployee">Employee</label>
                        <select id="filterEmployee" class="form-select">
                            <option value="">All employees</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->employee_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterAccount">Expense Account</label>
                        <select id="filterAccount" class="form-select">
                            <option value="">All expense accounts</option>
                            @foreach ($expenseLedgers->groupBy('group_name') as $groupName => $items)
                                <optgroup label="{{ $groupName }}">
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->account_code }} - {{ $item->account_name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
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
        <div class="card exp-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="expenseTable" class="table table-hover align-middle w-100"
                        data-page-length="25"
                        data-order="[]"
                        data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" data-searchable="false" width="5%">SL.</th>
                                <th>Expense No</th>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Paid From</th>
                                <th>Expense Heads</th>
                                <th class="text-end">Total</th>
                                <th data-orderable="false" data-searchable="false" width="9%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="expenseModal" tabindex="-1" aria-labelledby="expenseModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <form id="expenseForm" novalidate>
                    @csrf
                    <input type="hidden" name="id" id="expense_id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="expenseModalTitle">Add Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                        @if ($employees->isEmpty() || $expenseGroups->isEmpty() || $paymentGroups->isEmpty())
                            <div class="alert alert-warning py-2 small">
                                @if ($employees->isEmpty()) No active employee found. @endif
                                @if ($expenseGroups->isEmpty()) No active 2nd layer group under the Expense root. @endif
                                @if ($paymentGroups->isEmpty()) No active 2nd layer group under the Liquid Asset root. @endif
                                Add them first, otherwise an expense cannot be saved.
                            </div>
                        @endif

                        <div class="exp-section mb-3">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="expense_date">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="expense_date" id="expense_date" class="form-control" placeholder="Expense date">
                                    <div class="text-danger small field-error"></div>
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label" for="employee_id">Employee (expense made by)</label>
                                    <select name="employee_id" id="employee_id" class="form-select">
                                        <option value="">Select employee</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->employee_code }})</option>
                                        @endforeach
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="reference_no">Reference / Voucher No</label>
                                    <input type="text" name="reference_no" id="reference_no" class="form-control" maxlength="100" placeholder="Reference or voucher number">
                                    <div class="text-danger small field-error"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="payment_group_id">Paid From - Group (Liquid Asset) <span class="text-danger">*</span></label>
                                    <select name="payment_group_id" id="payment_group_id" class="form-select">
                                        <option value="">Select payment group (e.g. Bank, Cash)</option>
                                        @foreach ($paymentGroups as $group)
                                            <option value="{{ $group->id }}">{{ $group->account_code }} - {{ $group->account_name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="payment_account_id">Paid From - Account <span class="text-danger">*</span></label>
                                    <select name="payment_account_id" id="payment_account_id" class="form-select" disabled>
                                        <option value="">Select payment group first</option>
                                    </select>
                                    <div class="text-danger small field-error"></div>
                                </div>
                            </div>
                        </div>

                        <div class="exp-section mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0">Expense Details</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="addLineBtn">
                                    <i class="fas fa-plus"></i> Add Line
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" id="detailsTable" style="min-width: 860px">
                                    <thead>
                                        <tr>
                                            <th width="4%">#</th>
                                            <th width="26%">Expense Group</th>
                                            <th width="26%">Expense Account</th>
                                            <th>Description</th>
                                            <th width="15%" class="text-end">Amount</th>
                                            <th width="4%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="detailsBody"></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end fw-semibold pt-3">Total (credited to payment account)</td>
                                            <td class="pt-2">
                                                <input type="text" id="grand_total" class="form-control text-end exp-grand" readonly placeholder="0.00" value="0.00">
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="text-danger small mt-1" id="details_error"></div>
                        </div>

                        <div>
                            <label class="form-label" for="note">Note</label>
                            <textarea name="note" id="note" class="form-control" rows="2" maxlength="1000" placeholder="Any note about this expense (optional)"></textarea>
                            <div class="text-danger small field-error"></div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="expenseSaveBtn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let expenseTable;
        let expenseModal;
        let lineIndex = 0;
        const childrenCache = {};

        const expenseGroups = @json($expenseGroups);

        const expenseListUrl = "{{ route('admin.expense.list') }}";
        const childrenUrl = "{{ route('admin.expense.children') }}";

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                'Accept': 'application/json'
            }
        });

        /* ---------- errors ---------- */
        function clearExpenseErrors() {
            $('#expenseForm .field-error').text('');
            $('#details_error').text('');
            $('#expenseForm .is-invalid').removeClass('is-invalid');
        }

        // key "details.0.amount" -> input name "details[0][amount]"
        function showExpenseErrors(errors) {
            clearExpenseErrors();

            Object.keys(errors || {}).forEach(function(key) {
                const parts = key.split('.');
                const name = parts[0] + parts.slice(1).map(function(p) { return '[' + p + ']'; }).join('');
                const $input = $('#expenseForm [name="' + name + '"]');
                const message = errors[key][0];

                if ($input.length) {
                    $input.addClass('is-invalid');
                    $input.siblings('.field-error').first().text(message);
                } else {
                    $('#details_error').text(message);
                }
            });
        }

        /* ---------- select helpers ---------- */
        function ensureOption($select, id, label) {
            if (id && label && $select.find('option[value="' + id + '"]').length === 0) {
                $select.append(new Option(label + ' (inactive)', id));
            }
        }

        function fillSelect($select, items, placeholder, selectedId, fallbackLabel) {
            $select.empty().append(new Option(placeholder, ''));

            items.forEach(function(a) {
                $select.append(new Option(a.account_code + ' - ' + a.account_name, a.id));
            });

            ensureOption($select, selectedId, fallbackLabel);

            $select.val(selectedId ? String(selectedId) : '').prop('disabled', false);
        }

        function loadChildren(parentId) {
            if (childrenCache[parentId]) {
                return $.Deferred().resolve(childrenCache[parentId]).promise();
            }

            return $.ajax({
                url: childrenUrl,
                type: 'GET',
                data: { parent_id: parentId }
            }).then(function(response) {
                childrenCache[parentId] = response.data || [];
                return childrenCache[parentId];
            }, function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Unable to load accounts.');
            });
        }

        // Loads the 3rd layer accounts of a 2nd layer group into $select.
        function loadAccounts($select, groupId, selectedId, fallbackLabel, placeholder) {
            if (!groupId) {
                $select.empty()
                    .append(new Option(placeholder || 'Select group first', ''))
                    .prop('disabled', true);

                return $.Deferred().resolve().promise();
            }

            $select.prop('disabled', true).empty().append(new Option('Loading...', ''));

            return loadChildren(groupId).then(function(items) {
                fillSelect($select, items || [], 'Select account', selectedId, fallbackLabel);
            });
        }

        /* ---------- detail lines ---------- */
        function groupOptions() {
            return expenseGroups.map(function(g) {
                return '<option value="' + g.id + '">' + g.account_code + ' - ' + $('<div>').text(g.account_name).html() + '</option>';
            }).join('');
        }

        function lineHtml(i) {
            return '<tr data-index="' + i + '">' +
                '<td class="text-muted line-no pt-2"></td>' +
                '<td><select name="details[' + i + '][expense_group_id]" class="form-select form-select-sm line-group">' +
                '<option value="">Select expense group</option>' + groupOptions() + '</select>' +
                '<div class="text-danger small field-error"></div></td>' +
                '<td><select name="details[' + i + '][expense_account_id]" class="form-select form-select-sm line-account" disabled>' +
                '<option value="">Select group first</option></select>' +
                '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="text" name="details[' + i + '][description]" class="form-control form-control-sm line-description" maxlength="255" placeholder="Description (optional)">' +
                '<div class="text-danger small field-error"></div></td>' +
                '<td><input type="number" step="0.01" min="0" name="details[' + i + '][amount]" class="form-control form-control-sm text-end line-amount" placeholder="0.00">' +
                '<div class="text-danger small field-error"></div></td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove line"><i class="fas fa-times"></i></button></td>' +
                '</tr>';
        }

        function renumberLines() {
            $('#detailsBody tr').each(function(n) {
                $(this).find('.line-no').text(n + 1);
            });
        }

        function calcTotal() {
            let total = 0;

            $('#detailsBody .line-amount').each(function() {
                total += parseFloat(this.value) || 0;
            });

            $('#grand_total').val(total.toFixed(2));
        }

        // data (optional) = a saved line, used by the edit modal.
        function addLine(data) {
            const i = lineIndex++;
            const $tr = $(lineHtml(i));

            $('#detailsBody').append($tr);
            renumberLines();

            if (!data) {
                return $.Deferred().resolve().promise();
            }

            const $group = $tr.find('.line-group');

            ensureOption($group, data.expense_group_id, data.group_label);
            $group.val(String(data.expense_group_id));

            $tr.find('.line-description').val(data.description || '');
            $tr.find('.line-amount').val(data.amount);

            return loadAccounts($tr.find('.line-account'), data.expense_group_id, data.expense_account_id, data.account_label);
        }

        /* ---------- add / edit ---------- */
        function todayLocal() {
            const d = new Date();
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 10);
        }

        function resetExpenseForm() {
            $('#expenseForm')[0].reset();
            clearExpenseErrors();

            $('#expense_id').val('');
            $('#detailsBody').empty();
            lineIndex = 0;

            loadAccounts($('#payment_account_id'), '', '', '', 'Select payment group first');
        }

        function addExpense() {
            resetExpenseForm();

            $('#expense_date').val(todayLocal());
            $('#expenseModalTitle').text('Add Expense');
            $('#expenseSaveBtn').prop('disabled', false).text('Save');

            addLine();
            calcTotal();
            expenseModal.show();
        }

        function editExpense(id) {
            $.ajax({
                url: "{{ route('admin.expense.show') }}",
                type: "GET",
                data: { id: id },

                success: function(response) {
                    if (!response.status) {
                        toastr.error(response.message || 'Unable to load expense.');
                        return;
                    }

                    const d = response.data;

                    resetExpenseForm();

                    $('#expense_id').val(d.id);
                    $('#expense_date').val(d.expense_date);
                    $('#reference_no').val(d.reference_no || '');
                    $('#note').val(d.note || '');

                    ensureOption($('#employee_id'), d.employee_id, d.employee_label);
                    $('#employee_id').val(d.employee_id ? String(d.employee_id) : '');

                    ensureOption($('#payment_group_id'), d.payment_group_id, d.payment_group_label);
                    $('#payment_group_id').val(String(d.payment_group_id));

                    const jobs = [
                        loadAccounts($('#payment_account_id'), d.payment_group_id, d.payment_account_id, d.payment_account_label)
                    ];

                    (d.details || []).forEach(function(line) {
                        jobs.push(addLine(line));
                    });

                    $.when.apply($, jobs).always(function() {
                        calcTotal();
                        $('#expenseModalTitle').text('Edit Expense ' + (d.expense_no || ''));
                        $('#expenseSaveBtn').prop('disabled', false).text('Update');
                        expenseModal.show();
                    });
                },

                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Unable to load expense.');
                }
            });
        }

        /* ---------- delete ---------- */
        function deleteExpense(id) {
            const run = function() {
                $.ajax({
                    url: "{{ route('admin.expense.delete') }}",
                    type: "GET",
                    data: { id: id },

                    success: function(response) {
                        if (response.status) {
                            toastr.success(response.message);
                            expenseTable.ajax.reload(null, false);
                        } else {
                            toastr.error(response.message || 'Unable to delete expense.');
                        }
                    },

                    error: function(xhr) {
                        alert(JSON.stringify(xhr));
                        toastr.error(xhr.responseJSON?.message || 'Unable to delete expense.');
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete this expense?',
                    text: 'Its amounts will be taken back out of the account balances.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Yes, delete',
                    reverseButtons: true
                }).then(function(result) {
                    if (result.isConfirmed) run();
                });
            } else if (confirm('Delete this expense? Its amounts will be taken back out of the account balances.')) {
                run();
            }
        }

        /* ---------- ready ---------- */
        $(document).ready(function() {
            expenseModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('expenseModal'));

            // Summary cards come back in the same response as the rows.
            $('#expenseTable').on('xhr.dt', function(e, settings, json) {
                if (!json || !json.summary) return;

                $('#sumFilteredTotal').text(json.summary.filtered_total);
                $('#sumFilteredCount').text(json.summary.filtered_count);
                $('#sumMonthTotal').text(json.summary.month_total);
                $('#sumTodayTotal').text(json.summary.today_total);
            });

            // Keep SL. sequential after sorting / paging.
            $('#expenseTable').on('draw.dt', function(e, settings) {
                const api = new $.fn.dataTable.Api(settings);
                const start = api.page.info().start;

                api.column(0, { page: 'current' }).nodes().each(function(cell, i) {
                    cell.innerHTML = start + i + 1;
                });
            });

            /* ===== DataTable ===== */
            expenseTable = $('#expenseTable').DataTable({

                ajax: "{{ route('admin.expense.list') }}",

                processing: true,

            });

            /* ----- filters ----- */
            $('#applyFilters').on('click', function() {
                const params = $.param({
                    from: $('#filterFrom').val(),
                    to: $('#filterTo').val(),
                    employee_id: $('#filterEmployee').val(),
                    expense_account_id: $('#filterAccount').val()
                });

                expenseTable.ajax.url(expenseListUrl + '?' + params).load();
            });

            $('#resetFilters').on('click', function() {
                $('#filterFrom, #filterTo, #filterEmployee, #filterAccount').val('');
                expenseTable.ajax.url(expenseListUrl).load();
            });

            $('#refreshExpenseBtn').on('click', function() {
                expenseTable.ajax.reload(null, false);
            });

            /* ----- payment group => load its accounts ----- */
            $('#payment_group_id').on('change', function() {
                loadAccounts($('#payment_account_id'), this.value, '', '', 'Select payment group first');
            });

            /* ----- detail lines ----- */
            $('#addLineBtn').on('click', function() { addLine(); });

            $('#detailsBody').on('change', '.line-group', function() {
                loadAccounts($(this).closest('tr').find('.line-account'), this.value);
            });

            $('#detailsBody').on('input', '.line-amount', calcTotal);

            $('#detailsBody').on('click', '.remove-line', function() {
                if ($('#detailsBody tr').length === 1) {
                    // keep one empty line instead of removing the last one
                    const $tr = $(this).closest('tr');

                    $tr.find('.line-group').val('').trigger('change');
                    $tr.find('input').val('');
                } else {
                    $(this).closest('tr').remove();
                }

                renumberLines();
                calcTotal();
            });

            /* ----- save (create or update) ----- */
            $('#expenseForm').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const button = $('#expenseSaveBtn');
                const label = $('#expense_id').val() ? 'Update' : 'Save';

                clearExpenseErrors();

                $.ajax({
                    url: "{{ route('admin.expense.store') }}",
                    type: "POST",
                    data: form.serialize(),

                    beforeSend: function() {
                        button.prop('disabled', true).html(
                            '<span class="spinner-border spinner-border-sm me-1"></span> Saving...'
                        );
                    },

                    success: function(response) {
                      //  alert(JSON.stringify(response));
                        if (response.status) {
                            expenseModal.hide();
                            toastr.success(response.message);
                            expenseTable.ajax.reload(null, false);
                        } else {
                            toastr.error(response.message || 'Unable to save expense.');
                        }
                    },

                    error: function(xhr) {
                       // alert(JSON.stringify(xhr.responseJSON));
                        if (xhr.status === 422 && xhr.responseJSON?.errors) {
                            showExpenseErrors(xhr.responseJSON.errors);
                        } else {
                            toastr.error(xhr.responseJSON?.message || 'Something went wrong.');
                        }
                    },

                    complete: function() {
                        button.prop('disabled', false).text(label);
                    }
                });
            });
        });


         function pdf(id) {
            window.open("{{ route('admin.expense.pdf') }}?id=" + encodeURIComponent(id), '_blank');
        }
    </script>
@endpush
