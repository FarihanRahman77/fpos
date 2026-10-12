@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Amount Transfers
@endsection

@section('content')
    <div class="container-fluid">

        <div class="d-flex align-items-center mb-3">
            <div>
                <h4 class="mb-0">Amount Transfers</h4>
                <small class="text-muted">Move money from one cash / bank account to another.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button type="button" class="btn btn-primary" onclick="addTransfer()">
                    <i class="fas fa-plus"></i> Add Transfer
                </button>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-exchange-alt"></i></div>
                    <div><div class="text-muted small">Total (filtered)</div><h5 class="mb-0" id="sumFilteredTotal">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-list-ol"></i></div>
                    <div><div class="text-muted small">Transfers (filtered)</div><h5 class="mb-0" id="sumFilteredCount">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-calendar-alt"></i></div>
                    <div><div class="text-muted small">This Month</div><h5 class="mb-0" id="sumMonthTotal">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-calendar-day"></i></div>
                    <div><div class="text-muted small">Today</div><h5 class="mb-0" id="sumTodayTotal">0.00</h5></div>
                </div></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="filterFrom">From Date</label>
                        <input type="date" id="filterFrom" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterTo">To Date</label>
                        <input type="date" id="filterTo" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="filterAccount">Account (from or to)</label>
                        <select id="filterAccount" class="form-select">
                            <option value="">All accounts</option>
                            @foreach ($ledgers->groupBy('group_name') as $groupName => $items)
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
        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="transferTable" class="table table-hover align-middle w-100 acc-table"
                        data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" data-searchable="false" width="5%">SL.</th>
                                <th>Transfer No</th>
                                <th>Date</th>
                                <th>From</th>
                                <th>To</th>
                                <th class="text-end">Amount</th>
                                <th data-orderable="false" data-searchable="false" width="12%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="transferForm" novalidate>
                    @csrf
                    <input type="hidden" name="id" id="transfer_id">

                    <div class="modal-header">
                        <h5 class="modal-title" id="transferModalTitle">Add Transfer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        @if ($paymentGroups->isEmpty())
                            <div class="alert alert-warning py-2 small">
                                No active 2nd layer group under the Liquid Asset root. Add one (e.g. Cash, Bank) first.
                            </div>
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="transfer_date">Date <span class="text-danger">*</span></label>
                                <input type="date" name="transfer_date" id="transfer_date" class="form-control">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="amount">Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control text-end acc-grand" placeholder="0.00">
                                <div class="text-danger small field-error"></div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="acc-section h-100">
                                    <h6 class="text-danger"><i class="fas fa-arrow-up"></i> From (money goes out)</h6>
                                    <div class="mb-2">
                                        <label class="form-label" for="from_group_id">Group <span class="text-danger">*</span></label>
                                        <select name="from_group_id" id="from_group_id" class="form-select">
                                            <option value="">Select group</option>
                                            @foreach ($paymentGroups as $group)
                                                <option value="{{ $group->id }}">{{ $group->account_code }} - {{ $group->account_name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div>
                                        <label class="form-label" for="from_account_id">Account <span class="text-danger">*</span></label>
                                        <select name="from_account_id" id="from_account_id" class="form-select" disabled>
                                            <option value="">Select group first</option>
                                        </select>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="acc-section h-100">
                                    <h6 class="text-success"><i class="fas fa-arrow-down"></i> To (money comes in)</h6>
                                    <div class="mb-2">
                                        <label class="form-label" for="to_group_id">Group <span class="text-danger">*</span></label>
                                        <select name="to_group_id" id="to_group_id" class="form-select">
                                            <option value="">Select group</option>
                                            @foreach ($paymentGroups as $group)
                                                <option value="{{ $group->id }}">{{ $group->account_code }} - {{ $group->account_name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div>
                                        <label class="form-label" for="to_account_id">Account <span class="text-danger">*</span></label>
                                        <select name="to_account_id" id="to_account_id" class="form-select" disabled>
                                            <option value="">Select group first</option>
                                        </select>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="reference_no">Reference</label>
                                <input type="text" name="reference_no" id="reference_no" class="form-control" maxlength="100" placeholder="Cheque / deposit slip / Txn no">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="remarks">Note</label>
                                <input type="text" name="remarks" id="remarks" class="form-control" maxlength="1000" placeholder="Optional">
                                <div class="text-danger small field-error"></div>
                            </div>
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

    {{-- View modal --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalTitle">Transfer</h5>
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
        let transferTable, transferModal, viewModal;

        const listUrl = "{{ route('admin.transfer.list') }}";
        const showUrl = "{{ route('admin.transfer.show') }}";

        function resetForm() {
            $('#transferForm')[0].reset();
            Voucher.clearErrors($('#transferForm'));
            $('#transfer_id').val('');

            Voucher.refresh($('#from_group_id, #to_group_id'));
            Voucher.loadAccounts($('#from_account_id'), '');
            Voucher.loadAccounts($('#to_account_id'), '');
        }

        function addTransfer() {
            resetForm();

            $('#transfer_date').val(Voucher.todayLocal());
            $('#transferModalTitle').text('Add Transfer');
            $('#saveBtn').prop('disabled', false).text('Save');
            transferModal.show();
        }

        function loadTransfer(id, done) {
            $.ajax({
                url: showUrl,
                type: 'GET',
                data: { id: id },
                success: function(res) {
                    if (!res.status) {
                        toastr.error(res.message || 'Unable to load transfer.');
                        return;
                    }
                    done(res.data);
                },
                error: function(xhr) {
                    Voucher.ajaxError(xhr, 'Unable to load transfer.');
                }
            });
        }

        function editTransfer(id) {
            loadTransfer(id, function(d) {
                resetForm();

                $('#transfer_id').val(d.id);
                $('#transfer_date').val(d.transfer_date);
                $('#amount').val(d.amount);
                $('#reference_no').val(d.reference_no || '');
                $('#remarks').val(d.remarks || '');

                Voucher.setValue($('#from_group_id'), d.from_group_id, d.from_group_label);
                Voucher.setValue($('#to_group_id'), d.to_group_id, d.to_group_label);

                $.when(
                    Voucher.loadAccounts($('#from_account_id'), d.from_group_id, d.from_account_id, d.from_account_label),
                    Voucher.loadAccounts($('#to_account_id'), d.to_group_id, d.to_account_id, d.to_account_label)
                ).always(function() {
                    $('#transferModalTitle').text('Edit Transfer ' + d.transfer_no);
                    $('#saveBtn').prop('disabled', false).text('Update');
                    transferModal.show();
                });
            });
        }

        function viewTransfer(id) {
            loadTransfer(id, function(d) {
                $('#viewModalTitle').text('Transfer ' + d.transfer_no);
                $('#viewBody').html(
                    '<dl class="row acc-view mb-0">' +
                        '<dt class="col-sm-4">Date</dt><dd class="col-sm-8">' + Voucher.esc(d.transfer_date_label) + '</dd>' +
                        '<dt class="col-sm-4">From</dt><dd class="col-sm-8">' + Voucher.esc(d.from_account_label) + '<div class="text-muted small">' + Voucher.esc(d.from_group_label) + '</div></dd>' +
                        '<dt class="col-sm-4">To</dt><dd class="col-sm-8">' + Voucher.esc(d.to_account_label) + '<div class="text-muted small">' + Voucher.esc(d.to_group_label) + '</div></dd>' +
                        '<dt class="col-sm-4">Amount</dt><dd class="col-sm-8 fw-semibold">' + Voucher.money(d.amount) + '</dd>' +
                        '<dt class="col-sm-4">Reference</dt><dd class="col-sm-8">' + Voucher.esc(d.reference_no || '-') + '</dd>' +
                        '<dt class="col-sm-4">Note</dt><dd class="col-sm-8">' + Voucher.esc(d.remarks || '-') + '</dd>' +
                    '</dl>'
                );
                viewModal.show();
            });
        }

        function deleteTransfer(id) {
            Voucher.confirmDelete(
                'Delete this transfer?',
                'The amount will be moved back to the original account.',
                "{{ route('admin.transfer.delete') }}",
                id,
                function() { transferTable.ajax.reload(null, false); }
            );
        }

        $(document).ready(function() {
            transferModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('transferModal'));
            viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal'));

            Voucher.initSelect2($('#filterAccount'));
            Voucher.initSelect2($('#from_group_id, #from_account_id, #to_group_id, #to_account_id'), $('#transferModal'));

            $('#transferTable').on('xhr.dt', function(e, settings, json) {
                if (!json || !json.summary) return;

                $('#sumFilteredTotal').text(json.summary.filtered_total);
                $('#sumFilteredCount').text(json.summary.filtered_count);
                $('#sumMonthTotal').text(json.summary.month_total);
                $('#sumTodayTotal').text(json.summary.today_total);
            });

            Voucher.keepSerial($('#transferTable'));

            transferTable = $('#transferTable').DataTable({
                ajax: listUrl,
                processing: true
            });

            /* ----- filters ----- */
            $('#applyFilters').on('click', function() {
                const params = $.param({
                    from: $('#filterFrom').val(),
                    to: $('#filterTo').val(),
                    account_id: $('#filterAccount').val()
                });

                transferTable.ajax.url(listUrl + '?' + params).load();
            });

            $('#resetFilters').on('click', function() {
                $('#filterFrom, #filterTo').val('');
                $('#filterAccount').val('').trigger('change.select2');
                transferTable.ajax.url(listUrl).load();
            });

            $('#refreshBtn').on('click', function() {
                transferTable.ajax.reload(null, false);
            });

            /* ----- group => accounts ----- */
            $('#from_group_id').on('change', function() {
                Voucher.loadAccounts($('#from_account_id'), this.value);
            });

            $('#to_group_id').on('change', function() {
                Voucher.loadAccounts($('#to_account_id'), this.value);
            });

            /* ----- save (create or update) ----- */
            $('#transferForm').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $button = $('#saveBtn');
                const label = $('#transfer_id').val() ? 'Update' : 'Save';

                Voucher.clearErrors($form);

                $.ajax({
                    url: "{{ route('admin.transfer.store') }}",
                    type: 'POST',
                    data: $form.serialize(),
                    beforeSend: function() {
                        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
                    },
                    success: function(res) {
                        if (res.status) {
                            transferModal.hide();
                            toastr.success(res.message);
                            transferTable.ajax.reload(null, false);
                        } else {
                            toastr.error(res.message || 'Unable to save transfer.');
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($form, xhr.responseJSON.errors);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save transfer.');
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(label);
                    }
                });
            });
        });
    </script>
@endpush
