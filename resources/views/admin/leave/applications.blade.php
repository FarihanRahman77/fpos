@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Leave Applications
@endsection

@push('styles')
    <style>
        .balance-table td, .balance-table th { min-width: 0 !important; padding: .3rem .5rem !important; font-size: var(--text-caption); }
        .days-box { font-size: var(--text-heading-lg); font-weight: var(--font-weight-medium); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Leave Applications</h4>
                <small class="text-muted">Apply, approve and cancel leave. Weekends and holidays of the
                    <a href="{{ route('admin.leave.calendar.index') }}">calendar</a> are not counted.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('admin.leave.ledger') }}" class="btn btn-outline-secondary"><i class="fas fa-book"></i> Leave Ledger</a>
                <button type="button" class="btn btn-primary" onclick="Leave.add()"><i class="fas fa-plus"></i> Apply Leave</button>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach ([['sumPending', 'Pending', 'fas fa-hourglass-half', 'i2'], ['sumApproved', 'Approved', 'fas fa-check-circle', 'i4'],
                       ['sumDays', 'Approved Days', 'fas fa-calendar-check', 'i1'], ['sumToday', 'On Leave Today', 'fas fa-user-clock', 'i3']] as [$id, $label, $icon, $cls])
                <div class="col-6 col-lg-3">
                    <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                        <div class="icon {{ $cls }}"><i class="{{ $icon }}"></i></div>
                        <div><div class="text-muted small">{{ $label }}</div><h5 class="mb-0" id="{{ $id }}">0</h5></div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="filterYear">Year</label>
                        <input type="number" id="filterYear" class="form-control" value="{{ now()->year }}" min="2000" max="2100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="filterEmployee">Employee</label>
                        <select id="filterEmployee" class="form-select"></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filterType">Leave Type</label>
                        <select id="filterType" class="form-select">
                            <option value="">All types</option>
                            @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filterStatus">Status</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">All</option>
                            @foreach ($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-fill" id="applyFilters"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="leaveTable" class="table table-hover align-middle w-100 acc-table" data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" width="4%">SL.</th>
                                <th>No.</th>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Period</th>
                                <th class="text-end">Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th data-orderable="false" width="6%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Apply / edit --}}
    <div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="leaveForm" autocomplete="off">
                    <input type="hidden" name="id" id="leave_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="leaveTitle">Apply Leave</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="employee_id">Employee <span class="text-danger">*</span></label>
                                <select name="employee_id" id="employee_id" class="form-select"></select>
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="leave_type_id">Leave Type <span class="text-danger">*</span></label>
                                <select name="leave_type_id" id="leave_type_id" class="form-select">
                                    <option value="">Select leave type</option>
                                    @foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}{{ $t->is_paid === 'No' ? ' (unpaid)' : '' }}</option>@endforeach
                                </select>
                                <div class="text-danger small field-error"></div>
                                <small class="text-muted" id="typeHint"></small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="from_date">From <span class="text-danger">*</span></label>
                                <input type="date" name="from_date" id="from_date" class="form-control">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="to_date">To <span class="text-danger">*</span></label>
                                <input type="date" name="to_date" id="to_date" class="form-control">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="text-muted small">Leave days</div>
                                <div class="days-box" id="daysBox">0</div>
                                <div class="form-check d-inline-block">
                                    <input class="form-check-input" type="checkbox" name="half_day" value="1" id="half_day">
                                    <label class="form-check-label small" for="half_day">Half day</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="reason">Reason</label>
                                <textarea name="reason" id="reason" class="form-control" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="col-12" id="balanceBox"></div>
                        </div>
                        <div class="text-danger small mt-2" id="leave_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="leaveSaveBtn">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- View --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewBody"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const listUrl = "{{ route('admin.leave.list') }}";
        const employeeSearch = {
            theme: 'bootstrap-5', width: '100%', placeholder: 'Search by name or ID', allowClear: true,
            ajax: { url: "{{ route('admin.employee.search') }}", dataType: 'json', delay: 250, data: function(p) { return { q: p.term || '' }; } }
        };

        const Leave = {
            table: null,
            modal: null,
            viewModal: null,

            filters: function() {
                return $.param({
                    year: $('#filterYear').val(), employee_id: $('#filterEmployee').val() || '',
                    leave_type_id: $('#filterType').val(), leave_status: $('#filterStatus').val()
                });
            },

            reload: function() { Leave.table.ajax.url(listUrl + '?' + Leave.filters()).load(null, false); },

            reset: function(title) {
                $('#leaveForm')[0].reset();
                Voucher.clearErrors($('#leaveForm'));
                $('#leave_error').text('');
                $('#leave_id').val('');
                $('#employee_id').empty().trigger('change.select2');
                Voucher.refresh($('#leave_type_id').val(''));
                $('#daysBox').text('0');
                $('#balanceBox').empty();
                $('#leave_type_id option').prop('disabled', false);
                $('#typeHint').text('');
                $('#leaveTitle').text(title);
            },

            add: function() {
                Leave.reset('Apply Leave');
                $('#leaveSaveBtn').text('Submit');
                $('#from_date, #to_date').val(Voucher.todayLocal());
                Leave.modal.show();
            },

            edit: function(id) {
                $.ajax({
                    url: "{{ url('admin/leave/applications') }}/" + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const d = response.data;
                        Leave.reset('Edit Leave ' + d.application_no);
                        $('#leaveSaveBtn').text('Update');
                        $('#leave_id').val(d.id);
                        $('#employee_id').append(new Option(d.employee_name + ' (' + d.employee_code + ')', d.employee_id, true, true)).trigger('change.select2');
                        Voucher.refresh($('#leave_type_id').val(d.leave_type_id));
                        $('#from_date').val(d.from_date);
                        $('#to_date').val(d.to_date);
                        $('#half_day').prop('checked', d.half_day === 'Yes');
                        $('#reason').val(d.reason || '');
                        Leave.preview();
                        Leave.modal.show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load.');
                    }
                });
            },

            view: function(id) {
                $.ajax({
                    url: "{{ url('admin/leave/applications') }}/" + id,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        const d = response.data;
                        const row = function(k, v) { return '<tr><th class="text-muted fw-normal" style="width:38%">' + k + '</th><td>' + Voucher.esc(v || '-') + '</td></tr>'; };
                        $('#viewBody').html('<table class="table table-sm mb-0">' +
                            row('Application', d.application_no) + row('Employee', d.employee_name + ' (' + d.employee_code + ')') +
                            row('Leave Type', d.type_name) + row('Period', d.from_date + (d.to_date !== d.from_date ? ' to ' + d.to_date : '') + (d.half_day === 'Yes' ? ' (half day)' : '')) +
                            row('Days', String(parseFloat(d.days))) + row('Reason', d.reason) + row('Status', d.leave_status) +
                            row('Decided by', d.decided_by_name ? d.decided_by_name + ', ' + d.decided_at : '') + row('Note', d.decision_note) + '</table>');
                        Leave.viewModal.show();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                    }
                });
            },

            // One preview request per edit: changes within 300 ms are combined, an older request is aborted.
            previewTimer: null, previewXhr: null, previewSeq: 0,
            schedulePreview: function() {
                clearTimeout(Leave.previewTimer);
                Leave.previewTimer = setTimeout(Leave.preview, 300);
            },

            // Leave types the employee may take (group rules, unpaid, gender); others are disabled in the list.
            applyAllowed: function(allowed) {
                const ok = (allowed || []).map(String);
                const $sel = $('#leave_type_id');
                $sel.find('option').each(function() {
                    if (!this.value) return;
                    $(this).prop('disabled', ok.indexOf(this.value) === -1);
                });
                if ($sel.val() && ok.indexOf(String($sel.val())) === -1) $sel.val('');
                Voucher.refresh($sel);
                $('#typeHint').text(ok.length ? '' : 'This employee has no leave type available — check the leave group on the Employees page.');
            },

            // Days of the chosen period + the employee's balance for every leave type.
            preview: function() {
                const employeeId = $('#employee_id').val();
                const from = $('#from_date').val();
                $('#half_day').prop('disabled', !from || from !== $('#to_date').val());
                if (!employeeId) return;

                if (Leave.previewXhr) Leave.previewXhr.abort();
                const seq = ++Leave.previewSeq;
                Leave.previewXhr = $.ajax({
                    url: "{{ route('admin.leave.preview') }}",
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        employee_id: employeeId, from_date: from, to_date: $('#to_date').val(),
                        half_day: $('#half_day').is(':checked') && !$('#half_day').prop('disabled') ? 1 : 0, id: $('#leave_id').val()
                    },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (seq !== Leave.previewSeq) return;
                        if (response.allowed) Leave.applyAllowed(response.allowed);
                        $('#daysBox').text(response.days);
                        const typeId = String($('#leave_type_id').val());
                        const rows = response.balances.map(function(b) {
                            const available = b.balance === null ? null : b.balance - b.pending;
                            const short = String(b.id) === typeId && available !== null && response.days > available;
                            return '<tr class="' + (String(b.id) === typeId ? 'table-active fw-semibold' : '') + '"><td>' + Voucher.esc(b.name) + '</td>' +
                                '<td class="text-end">' + (b.paid ? b.entitled : '-') + '</td><td class="text-end">' + b.taken + '</td>' +
                                '<td class="text-end">' + b.pending + '</td>' +
                                '<td class="text-end ' + (short ? 'text-danger' : '') + '">' + (available === null ? 'Unpaid' : available) + '</td></tr>';
                        }).join('');
                        $('#balanceBox').html('<div class="small text-muted mb-1">Balance ' + response.year + (response.group ? ' — group <b>' + Voucher.esc(response.group) + '</b>' :
                            ' — <span class="text-danger">no leave group assigned (set it on the Employees page)</span>') + '</div>' +
                            '<table class="table table-sm table-bordered balance-table mb-0"><thead><tr><th>Type</th><th class="text-end">Entitled</th>' +
                            '<th class="text-end">Taken</th><th class="text-end">Pending</th><th class="text-end">Available</th></tr></thead><tbody>' + rows + '</tbody></table>');
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (seq !== Leave.previewSeq || xhr.statusText === 'abort') return;
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                    }
                });
            },

            decide: function(id, action) {
                const titles = { approve: 'Approve this leave?', reject: 'Reject this leave?', cancel: 'Cancel this leave?' };
                Swal.fire({
                    title: titles[action], input: 'text',
                    inputPlaceholder: action === 'reject' ? 'Reason for rejecting (required)' : 'Note (optional)', showCancelButton: true,
                    confirmButtonText: action.charAt(0).toUpperCase() + action.slice(1), reverseButtons: true,
                    confirmButtonColor: action === 'approve' ? 'var(--color-success)' : 'var(--color-danger)',
                    inputValidator: function(v) { return action === 'reject' && !$.trim(v || '') ? 'Write why the leave is rejected.' : null; }
                }).then(function(r) {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: "{{ url('admin/leave/applications') }}/" + id + '/decide',
                        type: 'POST',
                        dataType: 'json',
                        data: { action: action, note: r.value || '' },
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            Leave.reload();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to update the leave.');
                        }
                    });
                });
            },

            remove: function(id) {
                Swal.fire({ title: 'Delete this application?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'var(--color-danger)', confirmButtonText: 'Delete', reverseButtons: true })
                    .then(function(r) {
                        if (!r.isConfirmed) return;
                        $.ajax({
                            url: "{{ url('admin/leave/applications') }}/" + id + '/delete',
                            type: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                // alert(JSON.stringify(response));
                                toastr.success(response.message);
                                Leave.reload();
                            },
                            error: function(xhr) {
                                // alert(JSON.stringify(xhr));
                                Voucher.ajaxError(xhr, 'Unable to delete.');
                            }
                        });
                    });
            }
        };

        $(document).ready(function() {
            Leave.modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('leaveModal'));
            Leave.viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal'));

            $('#filterEmployee').select2(employeeSearch);
            $('#employee_id').select2($.extend({}, employeeSearch, { dropdownParent: $('#leaveModal'), allowClear: false }));
            Voucher.initSelect2($('#filterType, #filterStatus'));
            Voucher.initSelect2($('#leave_type_id'), $('#leaveModal'));

            $('#leaveTable').on('xhr.dt', function(e, settings, json) {
                if (!json || !json.summary) return;
                $('#sumPending').text(json.summary.pending);
                $('#sumApproved').text(json.summary.approved);
                $('#sumDays').text(json.summary.days);
                $('#sumToday').text(json.summary.today);
            });
            Voucher.keepSerial($('#leaveTable'));
            Leave.table = $('#leaveTable').DataTable({ ajax: listUrl + '?' + Leave.filters(), processing: true });

            $('#applyFilters').on('click', Leave.reload);

            $('#from_date').on('change', function() {
                if (!$('#to_date').val() || $('#to_date').val() < this.value) $('#to_date').val(this.value);
            });
            $('#employee_id, #leave_type_id, #from_date, #to_date, #half_day').on('change', Leave.schedulePreview);

            $('#leaveForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#leaveSaveBtn');
                const label = $btn.text();
                $btn.prop('disabled', true).text('Saving...');
                $.ajax({
                    url: "{{ route('admin.leave.store') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        Leave.modal.hide();
                        Leave.reload();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($('#leaveForm'), xhr.responseJSON.errors, $('#leave_error'));
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            $('#leave_error').text(xhr.responseJSON.message);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false).text(label); }
                });
            });
        });
    </script>
@endpush
