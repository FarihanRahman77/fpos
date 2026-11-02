@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Daily Attendance
@endsection

@push('styles')
    <style>
        .att-filter, .att-filter + .select2-container { width: 200px !important; max-width: 100%; }
        .att-grid td, .att-grid th { padding: .4rem 8px; font-size: var(--text-caption); vertical-align: middle; }
        .att-grid input[type="time"] { min-width: 110px; }
        .att-grid select { min-width: 120px; }
        .att-grid tr.dirty td:first-child { box-shadow: inset 3px 0 0 var(--color-accent); }
        .att-grid tr.off td { background: var(--color-surface-soft); }
        .att-pill { display: inline-block; padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-size: var(--text-micro); font-weight: var(--font-weight-medium); white-space: nowrap; }
        .att-pill.s-Present { background: var(--color-success-soft); color: var(--color-success); }
        .att-pill.s-Late, .att-pill.s-HalfDay { background: var(--color-warning-soft); color: var(--color-warning); }
        .att-pill.s-Absent { background: var(--color-danger-soft); color: var(--color-danger); }
        .att-pill.s-Leave { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .att-pill.s-Holiday, .att-pill.s-WeeklyOff, .att-pill.s-none { background: var(--color-surface-soft); color: var(--color-text-muted); }
        .att-meta { font-size: var(--text-micro); color: var(--color-text-muted); }
        .att-summary { display: flex; flex-wrap: wrap; gap: var(--space-md); font-size: var(--text-caption); color: var(--color-text-muted); }
        .att-summary b { color: var(--color-text); }
        .att-note { padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); background: var(--color-warning-soft); color: var(--color-warning); font-size: var(--text-caption); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Daily Attendance</h4>
                <small class="text-muted">Enter check-in / check-out times; the status is worked out from the employee's shift unless you pick one.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                <input type="date" id="date" class="form-control att-filter" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" aria-label="Date">
                <select id="department_id" class="form-select att-filter" aria-label="Department">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
                <a href="{{ route('admin.attendance.monthly') }}" class="btn btn-outline-secondary"><i class="fas fa-calendar-alt"></i> Monthly Report</a>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <h6 class="mb-0" id="dayLabel"></h6>
                    <span class="att-summary ms-2" id="summary"></span>
                    <div class="ms-auto d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="markPresent"><i class="fas fa-user-check"></i> Mark all present</button>
                        <button type="button" class="btn btn-outline-secondary" id="clearAll"><i class="fas fa-eraser"></i> Clear times</button>
                        <button type="button" class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> Save</button>
                    </div>
                </div>
                <div class="att-note mb-3 d-none" id="note"></div>
                <div class="table-responsive">
                    <table class="table table-bordered att-grid mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th><th>Shift</th><th>Check In</th><th>Check Out</th>
                                <th>Status</th><th>Result</th><th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2 mb-0">“Mark all present” fills the shift times for everyone without times on a working day. Rows you change are marked on the left; Save stores them (and everyone not saved yet for the day).</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const R = { data: "{{ route('admin.attendance.daily.data') }}", save: "{{ route('admin.attendance.daily.save') }}" };
        const STATUSES = @json($statuses);
        const esc = Voucher.esc;
        let rows = [], isFuture = false;

        function pill(status) {
            return '<span class="att-pill s-' + (status ? status.replace(/\s/g, '') : 'none') + '">' + esc(status || 'Not saved') + '</span>';
        }

        function minutes(m) {
            m = parseInt(m, 10) || 0;
            return m >= 60 ? Math.floor(m / 60) + 'h ' + (m % 60) + 'm' : m + 'm';
        }

        function render() {
            $('#rows').html(rows.length ? rows.map(function(r, i) {
                const off = r.day_type && r.day_type !== 'n/a';
                const meta = [];
                if (r.late_minutes) meta.push('late ' + minutes(r.late_minutes));
                if (r.ot_minutes) meta.push('OT ' + minutes(r.ot_minutes));
                if (r.source) meta.push(r.source.toLowerCase());
                return '<tr data-i="' + i + '" class="' + (off ? 'off' : '') + '">' +
                    '<td><span class="fw-semibold">' + esc(r.name) + '</span><div class="att-meta">' + esc(r.code || '') + (r.designation ? ' · ' + esc(r.designation) : '') + '</div></td>' +
                    '<td>' + esc(r.shift) + '<div class="att-meta">' + r.shift_start + ' – ' + r.shift_end + '</div></td>' +
                    '<td><input type="time" class="form-control form-control-sm f-in" value="' + esc(r.check_in) + '" aria-label="Check in"></td>' +
                    '<td><input type="time" class="form-control form-control-sm f-out" value="' + esc(r.check_out) + '" aria-label="Check out"></td>' +
                    '<td><select class="form-select form-select-sm f-status" aria-label="Status"><option value="">Auto</option>' +
                        STATUSES.map(function(s) { return '<option value="' + s + '"' + (r.override === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') +
                    '</select>' + (off ? '<div class="att-meta">' + esc(r.day_type) + '</div>' : '') + '</td>' +
                    '<td>' + pill(r.status) + (meta.length ? '<div class="att-meta">' + esc(meta.join(' · ')) + '</div>' : '') + '</td>' +
                    '<td><input type="text" class="form-control form-control-sm f-remarks" maxlength="255" value="' + esc(r.remarks) + '" aria-label="Remarks"></td>' +
                    '</tr>';
            }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No active employees for this date.</td></tr>');
            summary();
        }

        function summary() {
            const count = {};
            rows.forEach(function(r) { if (r.status) count[r.status] = (count[r.status] || 0) + 1; });
            const saved = rows.filter(function(r) { return r.saved; }).length;
            $('#summary').html(rows.length + ' employee(s) · <b>' + saved + '</b> saved' +
                Object.keys(count).map(function(k) { return ' · ' + esc(k) + ' <b>' + count[k] + '</b>'; }).join(''));
        }

        function load() {
            $('#rows').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>');
            const f = { date: $('#date').val() };
            if ($('#department_id').val()) f.department_id = $('#department_id').val();
            $.ajax({
                url: R.data,
                type: 'GET',
                dataType: 'json',
                data: f,
                success: function(response) {
                    // alert(JSON.stringify(response));
                    rows = response.data;
                    isFuture = response.future;
                    $('#dayLabel').text(response.label);
                    const notes = [];
                    if (response.holiday) notes.push('Holiday: ' + response.holiday + '. Anyone who works today gets all their hours as overtime.');
                    if (response.future) notes.push('This date is in the future: attendance cannot be saved yet.');
                    $('#note').toggleClass('d-none', !notes.length).text(notes.join(' '));
                    $('#saveBtn, #markPresent').prop('disabled', response.future);
                    render();
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    Voucher.ajaxError(xhr, 'Unable to load the attendance.');
                }
            });
        }

        function dirty($tr) { $tr.addClass('dirty'); }

        $(document).ready(function() {
            Voucher.initSelect2($('#department_id'));
            $('#date, #department_id').on('change', load);

            $(document).on('input change', '#rows input, #rows select', function() { dirty($(this).closest('tr')); });

            $('#markPresent').on('click', function() {
                $('#rows tr[data-i]').each(function() {
                    const r = rows[$(this).data('i')];
                    const $in = $(this).find('.f-in'), $out = $(this).find('.f-out');
                    if (r.day_type || $in.val() || $(this).find('.f-status').val()) return;
                    $in.val(r.shift_start);
                    $out.val(r.shift_end);
                    dirty($(this));
                });
            });

            $('#clearAll').on('click', function() {
                $('#rows tr[data-i]').each(function() {
                    if ($(this).find('.f-in').val() || $(this).find('.f-out').val()) {
                        $(this).find('.f-in, .f-out').val('');
                        dirty($(this));
                    }
                });
            });

            $('#saveBtn').on('click', function() {
                if (isFuture) return;
                const payload = [];
                $('#rows tr[data-i]').each(function() {
                    const r = rows[$(this).data('i')];
                    if (!$(this).hasClass('dirty') && r.saved) return;
                    payload.push({
                        employee_id: r.employee_id,
                        check_in: $(this).find('.f-in').val(),
                        check_out: $(this).find('.f-out').val(),
                        status: $(this).find('.f-status').val(),
                        remarks: $(this).find('.f-remarks').val()
                    });
                });
                if (!payload.length) { toastr.info('Nothing changed.'); return; }

                const $b = $(this).prop('disabled', true);
                $.ajax({
                    url: R.save,
                    type: 'POST',
                    dataType: 'json',
                    data: { date: $('#date').val(), rows: payload },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        load();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to save the attendance.');
                    },
                    complete: function() { $b.prop('disabled', false); }
                });
            });

            load();
        });
    </script>
@endpush
