@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Monthly Attendance
@endsection

@push('styles')
    <style>
        .att-filter { max-width: 200px; }
        .att-month td, .att-month th { padding: .3rem 4px; font-size: var(--text-micro); text-align: center; vertical-align: middle; min-width: 0; }
        .att-month th.emp, .att-month td.emp { text-align: left; min-width: 170px; padding-left: 8px; position: sticky; left: 0; background: var(--color-surface); z-index: 1; }
        .att-month thead th { background: var(--color-surface-soft); color: var(--color-text-muted); font-weight: var(--font-weight-medium); }
        .att-month th.dow-off { color: var(--color-text-muted); background: var(--color-canvas-soft); }
        .att-month td.c { width: 30px; font-weight: var(--font-weight-medium); }
        .att-month td.c-P { color: var(--color-success); }
        .att-month td.c-L, .att-month td.c-HD { color: var(--color-warning); background: var(--color-warning-soft); }
        .att-month td.c-A { color: var(--color-danger); background: var(--color-danger-soft); }
        .att-month td.c-LV { color: var(--color-accent-ink); background: var(--color-accent-soft); }
        .att-month td.c-H, .att-month td.c-W { color: var(--color-text-muted); background: var(--color-surface-soft); }
        .att-month td.n { font-feature-settings: var(--font-feature-numeric); min-width: 38px; }
        .att-month th.n { min-width: 38px; }
        .att-legend { display: flex; flex-wrap: wrap; gap: var(--space-md); font-size: var(--text-caption); color: var(--color-text-muted); }
        .att-legend b { color: var(--color-text); }
        .sal-table td, .sal-table th { padding: .35rem 8px; font-size: var(--text-caption); vertical-align: middle; }
        .sal-table .num { text-align: right; font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }
        .att-pill { display: inline-block; padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-size: var(--text-micro); font-weight: var(--font-weight-medium); }
        .att-pill.lock { background: var(--color-surface-soft); color: var(--color-text-muted); }
        .att-pill.warn { background: var(--color-warning-soft); color: var(--color-warning); }
        .att-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Monthly Attendance</h4>
                <small class="text-muted">Each employee's month at a glance, with present, late, absent, leave and overtime counts.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                <input type="month" id="month" class="form-control att-filter" value="{{ now()->format('Y-m') }}" aria-label="Month">
                <select id="department_id" class="form-select att-filter" aria-label="Department">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
                <select id="employee_id" class="form-select att-filter" aria-label="Employee">
                    <option value="">All employees</option>
                    @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_code }})</option>@endforeach
                </select>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h6 class="mb-0" id="monthLabel"></h6>
                    <div class="ms-auto d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="pdfBtn"><i class="fas fa-file-pdf"></i> PDF</button>
                        <button type="button" class="btn btn-outline-secondary" id="excelBtn"><i class="fas fa-file-excel"></i> Excel</button>
                        <button type="button" class="btn btn-primary" id="salaryBtn"><i class="fas fa-money-check-alt"></i> Send to salary</button>
                    </div>
                </div>
                <div class="att-legend mb-3">
                    @foreach ($codes as $status => $code)<span><b>{{ $code }}</b> {{ $status }}</span>@endforeach
                    <span><b>·</b> No attendance taken</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered att-month mb-0">
                        <thead id="head"></thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Send to salary --}}
    <div class="modal fade" id="salaryModal" tabindex="-1" aria-labelledby="salaryTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="salaryTitle">Send attendance to salary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="att-hint" id="salaryRules"></p>
                    <div class="table-responsive">
                        <table class="table table-bordered sal-table mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th class="num">Absent</th><th class="num">Half day</th><th class="num">Late</th><th class="num">OT hours</th>
                                    <th class="num">Daily rate</th>
                                    <th class="num">Absence</th><th class="num">Late fine</th><th class="num">Overtime</th><th></th>
                                </tr>
                            </thead>
                            <tbody id="salaryRows"></tbody>
                            <tfoot id="salaryFoot"></tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary me-auto" data-bs-dismiss="modal">Close</button>
                    <a href="{{ route('admin.payroll-adjustments.index') }}" class="btn btn-outline-secondary">Bonus &amp; Adjustments</a>
                    <button type="button" class="btn btn-primary" id="salaryConfirm">Create salary adjustments</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const R = {
            data: "{{ route('admin.attendance.monthly.data') }}",
            pdf: "{{ route('admin.attendance.monthly.pdf') }}",
            excel: "{{ route('admin.attendance.monthly.excel') }}",
            preview: "{{ route('admin.attendance.salary.preview') }}",
            send: "{{ route('admin.attendance.salary.send') }}"
        };
        const esc = Voucher.esc, money = Voucher.money;
        const salaryModal = new bootstrap.Modal(document.getElementById('salaryModal'));

        function filters(withEmployee) {
            const f = { month: $('#month').val() };
            if ($('#department_id').val()) f.department_id = $('#department_id').val();
            if (withEmployee !== false && $('#employee_id').val()) f.employee_id = $('#employee_id').val();
            return f;
        }
        function hours(v) { v = parseFloat(v) || 0; return v ? String(Math.round(v * 100) / 100) : '-'; }

        function load() {
            $('#rows').html('<tr><td class="emp text-muted">Loading…</td></tr>');
            $.ajax({
                url: R.data,
                type: 'GET',
                dataType: 'json',
                data: filters(),
                success: function(response) {
                    // alert(JSON.stringify(response));
                    $('#monthLabel').text(response.month_label);
                    $('#head').html('<tr><th class="emp">Employee</th>' + response.dates.map(function(d) {
                        return '<th class="' + (d.holiday || d.weekend ? 'dow-off' : '') + '" title="' + esc(d.holiday || d.dow) + '">' + d.day + '<div>' + d.dow.charAt(0) + '</div></th>';
                    }).join('') + '<th class="n">P</th><th class="n">L</th><th class="n">HD</th><th class="n">A</th><th class="n">LV</th><th class="n">Off</th><th class="n">OT h</th></tr>');

                    $('#rows').html(response.employees.length ? response.employees.map(function(e) {
                        const c = e.counts;
                        return '<tr><td class="emp"><span class="fw-semibold">' + esc(e.name) + '</span><div class="text-muted">' + esc(e.code || '') + (e.department ? ' · ' + esc(e.department) : '') + '</div></td>' +
                            e.days.map(function(d) {
                                const tip = [d.status || ''];
                                if (d.in) tip.push(d.in + (d.out ? ' – ' + d.out : ''));
                                if (d.late) tip.push('late ' + d.late + 'm');
                                if (d.ot) tip.push('OT ' + d.ot + 'm');
                                return '<td class="c c-' + esc(d.code) + '" title="' + esc(tip.join(' · ')) + '">' + esc(d.code) + '</td>';
                            }).join('') +
                            '<td class="n">' + (c.present + c.late) + '</td><td class="n">' + c.late + '</td><td class="n">' + c.half_day + '</td>' +
                            '<td class="n">' + c.absent + '</td><td class="n">' + c.leave + '</td><td class="n">' + (c.holiday + c.weekly_off) + '</td>' +
                            '<td class="n">' + hours(c.ot_hours) + '</td></tr>';
                    }).join('') : '<tr><td class="emp text-muted">No employees.</td></tr>');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    Voucher.ajaxError(xhr, 'Unable to load the month.');
                }
            });
        }

        function preview() {
            $('#salaryRows').html('<tr><td colspan="10" class="text-center text-muted py-4">Working it out…</td></tr>');
            $('#salaryFoot').empty();
            $('#salaryConfirm').prop('disabled', true);
            salaryModal.show();
            $.ajax({
                url: R.preview,
                type: 'GET',
                dataType: 'json',
                data: filters(false),
                success: function(response) {
                    // alert(JSON.stringify(response));
                    const s = response.settings;
                    $('#salaryTitle').text('Send attendance of ' + response.month + ' to salary');
                    $('#salaryRules').text(
                        'Absence ' + (s.absence_deduction === 'Yes' ? 'deducted (a half day counts ½)' : 'not deducted') + ' · ' +
                        (parseInt(s.lates_per_day, 10) ? 'every ' + s.lates_per_day + ' lates = 1 day fine' : 'no late fine') + ' · ' +
                        (s.overtime_pay === 'Yes' ? 'overtime paid at ' + parseFloat(s.overtime_multiplier) + '× hourly rate (gross / days / ' + s.hours_per_day + 'h)' : 'overtime not paid') +
                        '. Entries created earlier from attendance (remarks “Attendance:”) are replaced. Employees already processed are skipped.'
                    );
                    let active = 0;
                    $('#salaryRows').html(response.data.length ? response.data.map(function(l) {
                        const has = l.absence_amount > 0 || l.late_amount > 0 || l.ot_amount > 0;
                        if (!l.processed && (has || l.replaces)) active++;
                        const tag = l.processed ? '<span class="att-pill lock">Processed</span>'
                            : (l.replaces ? '<span class="att-pill warn">Replaces ' + l.replaces + '</span>' : '');
                        return '<tr class="' + (l.processed ? 'text-muted' : '') + '">' +
                            '<td><span class="fw-semibold">' + esc(l.name) + '</span><div class="text-muted small">' + esc(l.code || '') + '</div></td>' +
                            '<td class="num">' + l.absent + '</td><td class="num">' + l.half_day + '</td><td class="num">' + l.late + '</td><td class="num">' + hours(l.ot_hours) + '</td>' +
                            '<td class="num">' + money(l.daily_rate) + '</td>' +
                            '<td class="num text-danger">' + (l.absence_amount ? '−' + money(l.absence_amount) : '-') + '</td>' +
                            '<td class="num text-danger">' + (l.late_amount ? '−' + money(l.late_amount) + ' <span class="text-muted">(' + l.late_days + 'd)</span>' : '-') + '</td>' +
                            '<td class="num text-success">' + (l.ot_amount ? '+' + money(l.ot_amount) : '-') + '</td>' +
                            '<td>' + tag + '</td></tr>';
                    }).join('') : '<tr><td colspan="10" class="text-center text-muted py-4">No employees.</td></tr>');
                    $('#salaryFoot').html('<tr class="fw-semibold"><td colspan="6">Total (not processed)</td>' +
                        '<td class="num text-danger">' + money(response.totals.absence) + '</td><td class="num text-danger">' + money(response.totals.late) + '</td>' +
                        '<td class="num text-success">' + money(response.totals.overtime) + '</td><td></td></tr>');
                    $('#salaryConfirm').prop('disabled', !active);
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    Voucher.ajaxError(xhr, 'Unable to work out the salary entries.');
                }
            });
        }

        $(document).ready(function() {
            Voucher.initSelect2($('#department_id, #employee_id'));
            $('#month, #department_id, #employee_id').on('change', load);
            $('#pdfBtn').on('click', function() { window.open(R.pdf + '?' + $.param(filters()), '_blank'); });
            $('#excelBtn').on('click', function() { window.location = R.excel + '?' + $.param(filters()); });
            $('#salaryBtn').on('click', preview);
            $('#salaryConfirm').on('click', function() {
                const $b = $(this).prop('disabled', true);
                $.ajax({
                    url: R.send,
                    type: 'POST',
                    dataType: 'json',
                    data: filters(false),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        salaryModal.hide();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to create the salary entries.');
                        $b.prop('disabled', false);
                    }
                });
            });
            load();
        });
    </script>
@endpush
