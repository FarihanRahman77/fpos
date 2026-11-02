@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Attendance Setup
@endsection

@push('styles')
    <style>
        .att-table td, .att-table th { padding: .45rem 10px; font-size: var(--text-caption); vertical-align: middle; }
        .att-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
        .att-pill { display: inline-block; padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-size: var(--text-micro); font-weight: var(--font-weight-medium); }
        .att-pill.ok { background: var(--color-success-soft); color: var(--color-success); }
        .att-pill.off { background: var(--color-surface-soft); color: var(--color-text-muted); }
        .att-pill.info { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .att-pill.warn { background: var(--color-warning-soft); color: var(--color-warning); }
        .att-days { display: flex; flex-wrap: wrap; gap: var(--space-xs); }
        .att-days .btn { min-width: 52px; }
        .att-msg { font-size: var(--text-caption); color: var(--color-text-muted); max-width: 360px; }
        .att-msg.err { color: var(--color-danger); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Attendance Setup</h4>
                <small class="text-muted">Work shifts, attendance devices and the rules used when attendance is sent to the salary sheet.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <a href="{{ route('admin.attendance.daily') }}" class="btn btn-outline-secondary"><i class="fas fa-calendar-check"></i> Daily Attendance</a>
                <a href="{{ route('admin.attendance.import') }}" class="btn btn-outline-secondary"><i class="fas fa-file-import"></i> Import</a>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabShifts" type="button" role="tab">Work Shifts</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDevices" type="button" role="tab">Devices</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRules" type="button" role="tab">Salary Rules</button></li>
        </ul>

        <div class="tab-content">
            {{-- Shifts --}}
            <div class="tab-pane fade show active" id="tabShifts" role="tabpanel">
                <div class="row g-3">
                    <div class="col-xl-4">
                        <div class="card acc-card">
                            <div class="card-body">
                                <h6 class="mb-3" id="shiftTitle">New shift</h6>
                                <form id="shiftForm" autocomplete="off" novalidate>
                                    <input type="hidden" name="id" id="shift_id">
                                    <div class="mb-3">
                                        <label class="form-label" for="shift_name">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="shift_name" class="form-control" maxlength="100" placeholder="e.g. Day (9 AM - 6 PM)">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label" for="start_time">Starts <span class="text-danger">*</span></label>
                                            <input type="time" name="start_time" id="start_time" class="form-control" value="09:00">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label" for="end_time">Ends <span class="text-danger">*</span></label>
                                            <input type="time" name="end_time" id="end_time" class="form-control" value="18:00">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-1">
                                        <div class="col-4">
                                            <label class="form-label" for="grace_minutes">Grace (min)</label>
                                            <input type="number" name="grace_minutes" id="grace_minutes" class="form-control" min="0" max="240" value="10">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label" for="half_day_after_minutes">Half day after</label>
                                            <input type="number" name="half_day_after_minutes" id="half_day_after_minutes" class="form-control" min="0" max="720" value="120">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label" for="overtime_after_minutes">OT after (min)</label>
                                            <input type="number" name="overtime_after_minutes" id="overtime_after_minutes" class="form-control" min="0" max="720" value="30">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                    </div>
                                    <p class="att-hint mb-3">Late = in after start + grace. Half day = in this many minutes after start (0 = off), or worked less than half the shift. Overtime counts from shift end once the employee stays this long.</p>
                                    <div class="mb-3">
                                        <label class="form-label d-block">Weekly Off</label>
                                        <div class="att-days">
                                            @foreach ($days as $d)
                                                <input type="checkbox" class="btn-check" name="weekly_offs[]" value="{{ $d }}" id="off_{{ $d }}">
                                                <label class="btn btn-sm btn-outline-secondary" for="off_{{ $d }}">{{ substr($d, 0, 3) }}</label>
                                            @endforeach
                                        </div>
                                        <div class="att-hint mt-1">None picked = the weekend of the holiday calendar.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="shift_status">Status</label>
                                        <select name="status" id="shift_status" class="form-select">
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                    <div class="text-danger small mb-2" id="shift_error"></div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill" id="shiftSubmit">Save Shift</button>
                                        <button type="button" class="btn btn-outline-secondary d-none" id="shiftCancel">Cancel edit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="card acc-card">
                            <div class="card-body">
                                <h6 class="mb-2">Shifts</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered att-table mb-0">
                                        <thead><tr><th>Shift</th><th>Time</th><th>Grace / Half day / OT after</th><th>Weekly Off</th><th class="text-center">Employees</th><th>Status</th><th style="width: 90px;"></th></tr></thead>
                                        <tbody id="shiftRows"></tbody>
                                    </table>
                                </div>
                                <p class="att-hint mt-2 mb-0">Employees without a shift follow the default shift (Salary Rules tab). Set an employee's shift on the employee form.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Devices --}}
            <div class="tab-pane fade" id="tabDevices" role="tabpanel">
                <div class="row g-3">
                    <div class="col-xl-4">
                        <div class="card acc-card">
                            <div class="card-body">
                                <h6 class="mb-3" id="deviceTitle">Add a device</h6>
                                <form id="deviceForm" autocomplete="off" novalidate>
                                    <input type="hidden" name="id" id="device_id">
                                    <div class="mb-3">
                                        <label class="form-label" for="device_name">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="device_name" class="form-control" maxlength="100" placeholder="e.g. Main gate">
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-8">
                                            <label class="form-label" for="device_ip">IP Address <span class="text-danger">*</span></label>
                                            <input type="text" name="ip" id="device_ip" class="form-control" maxlength="64" placeholder="192.168.1.201">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label" for="device_port">Port</label>
                                            <input type="number" name="port" id="device_port" class="form-control" min="1" max="65535" value="4370">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label" for="comm_key">Comm Key</label>
                                            <input type="number" name="comm_key" id="comm_key" class="form-control" min="0" value="0">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label" for="device_status">Status</label>
                                            <select name="status" id="device_status" class="form-select">
                                                <option value="Active">Active</option>
                                                <option value="Inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="device_branch">Branch</label>
                                        <select name="branch_id" id="device_branch" class="form-select">
                                            <option value="">—</option>
                                            @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="text-danger small mb-2" id="device_error"></div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-fill" id="deviceSubmit">Save Device</button>
                                        <button type="button" class="btn btn-outline-secondary d-none" id="deviceCancel">Cancel edit</button>
                                    </div>
                                </form>
                                <p class="att-hint mt-3 mb-0">ZKTeco devices are read over UDP port 4370 on the local network (the server running this app must reach the device). Link people by entering their device user id in the employee form (“Device User ID”).</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-8">
                        <div class="card acc-card">
                            <div class="card-body">
                                <h6 class="mb-2">Devices</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered att-table mb-0">
                                        <thead><tr><th>Device</th><th>Address</th><th>Last sync</th><th class="text-center">Punches</th><th>Status</th><th style="width: 210px;"></th></tr></thead>
                                        <tbody id="deviceRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rules --}}
            <div class="tab-pane fade" id="tabRules" role="tabpanel">
                <div class="row g-3">
                    <div class="col-xl-6">
                        <div class="card acc-card">
                            <div class="card-body">
                                <h6 class="mb-3">Attendance rules</h6>
                                <form id="rulesForm" autocomplete="off" novalidate>
                                    <div class="mb-3">
                                        <label class="form-label" for="default_shift_id">Default Shift <span class="text-danger">*</span></label>
                                        <select name="default_shift_id" id="default_shift_id" class="form-select"></select>
                                        <div class="att-hint mt-1">Used for employees without a shift of their own.</div>
                                        <div class="text-danger small field-error"></div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="absence_deduction">Deduct absence</label>
                                            <select name="absence_deduction" id="absence_deduction" class="form-select">
                                                <option value="Yes">Yes — absent days × daily rate</option>
                                                <option value="No">No</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="lates_per_day">Late fine: lates per 1 day</label>
                                            <input type="number" name="lates_per_day" id="lates_per_day" class="form-control" min="0" max="31">
                                            <div class="att-hint mt-1">e.g. 3 = every 3 lates cost 1 day. 0 = no late fine.</div>
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label" for="overtime_pay">Pay overtime</label>
                                            <select name="overtime_pay" id="overtime_pay" class="form-select">
                                                <option value="Yes">Yes</option>
                                                <option value="No">No</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="overtime_multiplier">OT rate ×</label>
                                            <input type="number" name="overtime_multiplier" id="overtime_multiplier" class="form-control" min="0" max="10" step="0.25">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="hours_per_day">Hours / day</label>
                                            <input type="number" name="hours_per_day" id="hours_per_day" class="form-control" min="1" max="24">
                                            <div class="text-danger small field-error"></div>
                                        </div>
                                    </div>
                                    <p class="att-hint">Daily rate follows the Payroll setting (basic or gross / days in the month). Overtime per hour = OT rate × gross / days in the month / hours per day.</p>
                                    <div class="text-danger small mb-2" id="rules_error"></div>
                                    <button type="submit" class="btn btn-primary" id="rulesSubmit">Save Rules</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const R = {
            shifts: "{{ route('admin.attendance.shifts') }}",
            shiftSave: "{{ route('admin.attendance.shifts.save') }}",
            shiftDelete: "{{ route('admin.attendance.shifts.delete', '__ID__') }}",
            rulesSave: "{{ route('admin.attendance.settings.save') }}",
            devices: "{{ route('admin.attendance.devices') }}",
            deviceSave: "{{ route('admin.attendance.devices.save') }}",
            deviceDelete: "{{ route('admin.attendance.devices.delete', '__ID__') }}",
            deviceTest: "{{ route('admin.attendance.devices.test', '__ID__') }}",
            deviceSync: "{{ route('admin.attendance.devices.sync', '__ID__') }}"
        };
        const SETTINGS = @json($settings);
        const esc = Voucher.esc;
        let shiftCache = [], deviceCache = [];

        function submitForm($form, url, $btn, $err, label, done) {
            $btn.prop('disabled', true).text('Saving...');
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
                    done();
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        Voucher.showErrors($form, xhr.responseJSON.errors, $err);
                    } else {
                        $err.text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to save.');
                    }
                },
                complete: function() { $btn.prop('disabled', false).text(label); }
            });
        }
        function confirmDelete(text, url, done) {
            Swal.fire({ title: text, icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove', reverseButtons: true })
                .then(function(r) {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: url,
                        type: 'POST',
                        dataType: 'json',
                        data: {},
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            done();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to remove.');
                        }
                    });
                });
        }

        /* ---------------- Shifts ---------------- */
        function loadShifts() {
            $.ajax({
                url: R.shifts,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    shiftCache = response.data;
                    $('#shiftRows').html(response.data.length ? response.data.map(function(s) {
                        return '<tr><td class="fw-semibold">' + esc(s.name) + (s.is_default ? ' <span class="att-pill info">Default</span>' : '') + '</td>' +
                            '<td>' + s.start + ' – ' + s.end + '</td>' +
                            '<td>' + s.grace_minutes + ' / ' + (s.half_day_after_minutes || '—') + ' / ' + s.overtime_after_minutes + ' min</td>' +
                            '<td>' + (s.weekly_offs.length ? esc(s.weekly_offs.join(', ')) : '<span class="text-muted">Calendar weekend</span>') + '</td>' +
                            '<td class="text-center">' + s.employees + '</td>' +
                            '<td><span class="att-pill ' + (s.status === 'Active' ? 'ok' : 'off') + '">' + s.status + '</span></td>' +
                            '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-secondary shift-edit" data-id="' + s.id + '" aria-label="Edit"><i class="fas fa-pen"></i></button> ' +
                            '<button type="button" class="btn btn-sm btn-outline-danger shift-del" data-id="' + s.id + '" aria-label="Remove"><i class="fas fa-trash"></i></button></td></tr>';
                    }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No shifts yet.</td></tr>');

                    const $def = $('#default_shift_id').empty();
                    response.data.filter(function(s) { return s.status === 'Active' || s.is_default; }).forEach(function(s) {
                        $def.append(new Option(s.name + ' (' + s.start + ' – ' + s.end + ')', s.id, false, s.is_default));
                    });
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }
        function resetShift() {
            $('#shiftForm')[0].reset();
            $('#shift_id').val('');
            $('#shiftTitle').text('New shift');
            $('#shiftCancel').addClass('d-none');
            Voucher.clearErrors($('#shiftForm'));
            $('#shift_error').text('');
        }

        /* ---------------- Devices ---------------- */
        function loadDevices() {
            $.ajax({
                url: R.devices,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    deviceCache = response.data;
                    $('#deviceRows').html(response.data.length ? response.data.map(function(d) {
                        const failed = d.last_message && /fail|no reply|not reachable|refused|cannot|rejected/i.test(d.last_message);
                        return '<tr><td><span class="fw-semibold">' + esc(d.name) + '</span>' + (d.branch ? '<div class="text-muted small">' + esc(d.branch) + '</div>' : '') + '</td>' +
                            '<td>' + esc(d.ip) + ':' + d.port + '</td>' +
                            '<td>' + (d.last_synced_at ? esc(d.last_synced_at) : '<span class="text-muted">Never</span>') +
                                (d.last_message ? '<div class="att-msg ' + (failed ? 'err' : '') + '" id="msg' + d.id + '">' + esc(d.last_message) + '</div>' : '<div class="att-msg" id="msg' + d.id + '"></div>') +
                                (d.unmapped_users ? '<div><span class="att-pill warn">' + d.unmapped_users + ' unlinked user(s)</span></div>' : '') + '</td>' +
                            '<td class="text-center">' + d.punches + '</td>' +
                            '<td><span class="att-pill ' + (d.status === 'Active' ? 'ok' : 'off') + '">' + d.status + '</span></td>' +
                            '<td class="text-nowrap">' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary dev-test" data-id="' + d.id + '"><i class="fas fa-plug"></i> Test</button> ' +
                                '<button type="button" class="btn btn-sm btn-outline-primary dev-sync" data-id="' + d.id + '"><i class="fas fa-sync"></i> Sync now</button> ' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary dev-edit" data-id="' + d.id + '" aria-label="Edit"><i class="fas fa-pen"></i></button> ' +
                                '<button type="button" class="btn btn-sm btn-outline-danger dev-del" data-id="' + d.id + '" aria-label="Remove"><i class="fas fa-trash"></i></button>' +
                            '</td></tr>';
                    }).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No devices yet.</td></tr>');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong. Please try again.');
                }
            });
        }
        function resetDevice() {
            $('#deviceForm')[0].reset();
            $('#device_id').val('');
            $('#deviceTitle').text('Add a device');
            $('#deviceCancel').addClass('d-none');
            Voucher.clearErrors($('#deviceForm'));
            $('#device_error').text('');
        }
        function deviceAction(url, $btn, busy) {
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + busy);
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: {},
                success: function(response) {
                    // alert(JSON.stringify(response));
                    toastr.success(response.message);
                    loadDevices();
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    Voucher.ajaxError(xhr, 'The device did not answer.');
                    loadDevices();
                },
                complete: function() { $btn.prop('disabled', false).html(html); }
            });
        }

        $(document).ready(function() {
            /* shifts */
            $('#shiftForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), R.shiftSave, $('#shiftSubmit'), $('#shift_error'), 'Save Shift', function() { resetShift(); loadShifts(); });
            });
            $('#shiftCancel').on('click', resetShift);
            $(document).on('click', '.shift-edit', function() {
                const s = shiftCache.find(function(x) { return x.id == $(this).data('id'); }, this);
                if (!s) return;
                resetShift();
                $('#shift_id').val(s.id);
                $('#shift_name').val(s.name);
                $('#start_time').val(s.start);
                $('#end_time').val(s.end);
                $('#grace_minutes').val(s.grace_minutes);
                $('#half_day_after_minutes').val(s.half_day_after_minutes);
                $('#overtime_after_minutes').val(s.overtime_after_minutes);
                $('#shiftForm input[name="weekly_offs[]"]').each(function() { $(this).prop('checked', s.weekly_offs.indexOf($(this).val()) !== -1); });
                $('#shift_status').val(s.status);
                $('#shiftTitle').text('Edit ' + s.name);
                $('#shiftCancel').removeClass('d-none');
            });
            $(document).on('click', '.shift-del', function() {
                confirmDelete('Remove this shift?', R.shiftDelete.replace('__ID__', $(this).data('id')), loadShifts);
            });

            /* devices */
            $('#deviceForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), R.deviceSave, $('#deviceSubmit'), $('#device_error'), 'Save Device', function() { resetDevice(); loadDevices(); });
            });
            $('#deviceCancel').on('click', resetDevice);
            $(document).on('click', '.dev-edit', function() {
                const d = deviceCache.find(function(x) { return x.id == $(this).data('id'); }, this);
                if (!d) return;
                resetDevice();
                $('#device_id').val(d.id);
                $('#device_name').val(d.name);
                $('#device_ip').val(d.ip);
                $('#device_port').val(d.port);
                $('#comm_key').val(d.comm_key);
                $('#device_branch').val(d.branch_id ? String(d.branch_id) : '');
                $('#device_status').val(d.status);
                $('#deviceTitle').text('Edit ' + d.name);
                $('#deviceCancel').removeClass('d-none');
            });
            $(document).on('click', '.dev-del', function() {
                confirmDelete('Remove this device?', R.deviceDelete.replace('__ID__', $(this).data('id')), loadDevices);
            });
            $(document).on('click', '.dev-test', function() { deviceAction(R.deviceTest.replace('__ID__', $(this).data('id')), $(this), 'Testing…'); });
            $(document).on('click', '.dev-sync', function() { deviceAction(R.deviceSync.replace('__ID__', $(this).data('id')), $(this), 'Syncing…'); });

            /* rules */
            $('#absence_deduction').val(SETTINGS.absence_deduction);
            $('#lates_per_day').val(SETTINGS.lates_per_day);
            $('#overtime_pay').val(SETTINGS.overtime_pay);
            $('#overtime_multiplier').val(parseFloat(SETTINGS.overtime_multiplier));
            $('#hours_per_day').val(SETTINGS.hours_per_day);
            $('#rulesForm').on('submit', function(e) {
                e.preventDefault();
                submitForm($(this), R.rulesSave, $('#rulesSubmit'), $('#rules_error'), 'Save Rules', loadShifts);
            });

            loadShifts();
            loadDevices();
        });
    </script>
@endpush
