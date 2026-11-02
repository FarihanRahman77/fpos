@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Import Attendance
@endsection

@push('styles')
    <style>
        .imp-table td, .imp-table th { padding: .35rem 8px; font-size: var(--text-caption); vertical-align: middle; white-space: nowrap; }
        .imp-table th.picked { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .imp-step { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: var(--radius-pill); background: var(--color-button); color: var(--color-button-text); font-size: var(--text-micro); font-weight: var(--font-weight-medium); margin-right: var(--space-sm); }
        .imp-hint { font-size: var(--text-caption); color: var(--color-text-muted); }
        .imp-stats { display: flex; flex-wrap: wrap; gap: var(--space-lg); font-size: var(--text-caption); color: var(--color-text-muted); }
        .imp-stats b { display: block; font-size: var(--text-body-lg); color: var(--color-text); font-feature-settings: var(--font-feature-numeric); }
        .imp-warn { color: var(--color-warning); }
        .imp-bad { color: var(--color-danger); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Import Attendance</h4>
                <small class="text-muted">Upload punches exported from an attendance device (CSV or Excel), map the columns, check, then import.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('admin.attendance.monthly') }}" class="btn btn-outline-secondary"><i class="fas fa-calendar-alt"></i> Monthly Report</a>
            </div>
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <h6 class="mb-3"><span class="imp-step">1</span>Upload the file</h6>
                <form id="uploadForm" class="d-flex flex-wrap gap-2 align-items-start" enctype="multipart/form-data" novalidate>
                    <input type="file" name="file" id="file" class="form-control" accept=".csv,.txt,.xlsx,.xls" style="max-width: 420px;">
                    <button type="submit" class="btn btn-primary" id="uploadBtn"><i class="fas fa-upload"></i> Read file</button>
                </form>
                <p class="imp-hint mt-2 mb-0">One row per punch. Typical ZKTeco exports work as they are: <b>No., Name, Date/Time</b> or <b>AC-No., Date, Time</b>.
                    The first punch of a day is the check-in, the last is the check-out. Days entered by hand on the daily sheet are not overwritten.</p>
            </div>
        </div>

        <div class="card acc-card mb-3 d-none" id="mapCard">
            <div class="card-body">
                <h6 class="mb-3"><span class="imp-step">2</span>Map the columns <span class="text-muted small" id="fileInfo"></span></h6>
                <form id="mapForm" autocomplete="off" novalidate>
                    <input type="hidden" name="token" id="token">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label" for="id_col">Employee column</label>
                            <select name="id_col" id="id_col" class="form-select col-select"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="id_type">It holds</label>
                            <select name="id_type" id="id_type" class="form-select">
                                <option value="device">Device user id (AC-No.)</option>
                                <option value="code">Employee code</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="mode">Date &amp; time are</label>
                            <select name="mode" id="mode" class="form-select">
                                <option value="datetime">In one column</option>
                                <option value="split">In two columns</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="date_order">Date format</label>
                            <select name="date_order" id="date_order" class="form-select">
                                <option value="auto">Detect (day first if unclear)</option>
                                <option value="DMY">Day / Month / Year</option>
                                <option value="MDY">Month / Day / Year</option>
                                <option value="YMD">Year - Month - Day</option>
                            </select>
                        </div>
                        <div class="col-md-3 mode-datetime">
                            <label class="form-label" for="dt_col">Date/time column</label>
                            <select name="dt_col" id="dt_col" class="form-select col-select"></select>
                        </div>
                        <div class="col-md-3 mode-split d-none">
                            <label class="form-label" for="date_col">Date column</label>
                            <select name="date_col" id="date_col" class="form-select col-select"></select>
                        </div>
                        <div class="col-md-3 mode-split d-none">
                            <label class="form-label" for="time_col">Time column</label>
                            <select name="time_col" id="time_col" class="form-select col-select"></select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="header" value="1" id="header">
                                <label class="form-check-label" for="header">First row is a header</label>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered imp-table mb-0">
                            <thead id="sampleHead"></thead>
                            <tbody id="sampleRows"></tbody>
                        </table>
                    </div>
                    <div class="text-danger small mb-2" id="map_error"></div>
                    <button type="submit" class="btn btn-outline-primary" id="checkBtn"><i class="fas fa-search"></i> Check</button>
                </form>
            </div>
        </div>

        <div class="card acc-card d-none" id="previewCard">
            <div class="card-body">
                <h6 class="mb-3"><span class="imp-step">3</span>Check and import</h6>
                <div class="imp-stats mb-3" id="stats"></div>
                <div id="problems"></div>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered imp-table mb-0">
                        <thead><tr><th>Employee</th><th>Punch time</th></tr></thead>
                        <tbody id="punchRows"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-primary" id="importBtn"><i class="fas fa-file-import"></i> Import</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const R = {
            upload: "{{ route('admin.attendance.import.upload') }}",
            preview: "{{ route('admin.attendance.import.preview') }}",
            commit: "{{ route('admin.attendance.import.commit') }}"
        };
        const esc = Voucher.esc;
        let sample = [];

        function colLabel(i) {
            const head = $('#header').is(':checked') && sample[0] ? String(sample[0][i] == null ? '' : sample[0][i]).trim() : '';
            return 'Column ' + (i + 1) + (head ? ' — ' + head : '');
        }

        function fillColumns(count, mapping) {
            $('.col-select').each(function() {
                const $s = $(this).empty();
                for (let i = 0; i < count; i++) $s.append(new Option(colLabel(i), i));
            });
            if (mapping) {
                $('#id_col').val(mapping.id_col);
                $('#dt_col').val(mapping.dt_col);
                if (mapping.date_col !== null) $('#date_col').val(mapping.date_col);
                if (mapping.time_col !== null) $('#time_col').val(mapping.time_col);
            }
        }

        function renderSample() {
            const header = $('#header').is(':checked');
            const cols = sample.reduce(function(n, r) { return Math.max(n, r.length); }, 0);
            const picked = $('#mode').val() === 'datetime' ? [$('#id_col').val(), $('#dt_col').val()] : [$('#id_col').val(), $('#date_col').val(), $('#time_col').val()];
            let head = '<tr>';
            for (let i = 0; i < cols; i++) head += '<th class="' + (picked.indexOf(String(i)) !== -1 ? 'picked' : '') + '">' + esc(colLabel(i)) + '</th>';
            $('#sampleHead').html(head + '</tr>');
            $('#sampleRows').html(sample.slice(header ? 1 : 0).map(function(r) {
                let tr = '<tr>';
                for (let i = 0; i < cols; i++) tr += '<td>' + esc(r[i] == null ? '' : r[i]) + '</td>';
                return tr + '</tr>';
            }).join(''));
        }

        function toggleMode() {
            const split = $('#mode').val() === 'split';
            $('.mode-split').toggleClass('d-none', !split);
            $('.mode-datetime').toggleClass('d-none', split);
            renderSample();
        }

        function mapData() {
            return $('#mapForm').serialize() + ($('#header').is(':checked') ? '' : '&header=0');
        }

        $(document).ready(function() {
            $('#uploadForm').on('submit', function(e) {
                e.preventDefault();
                if (!$('#file')[0].files.length) { toastr.error('Choose the CSV or Excel file.'); return; }
                const fd = new FormData(this);
                const $b = $('#uploadBtn').prop('disabled', true);
                $('#previewCard').addClass('d-none');
                $.ajax({
                    url: R.upload,
                    type: 'POST',
                    dataType: 'json',
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        sample = response.sample;
                        $('#token').val(response.token);
                        $('#fileInfo').text('· ' + response.name + ' · ' + response.total + ' row(s)');
                        $('#header').prop('checked', !!response.mapping.header);
                        $('#id_type').val(response.mapping.id_type);
                        $('#mode').val(response.mapping.mode);
                        $('#date_order').val(response.mapping.date_order);
                        fillColumns(response.columns, response.mapping);
                        toggleMode();
                        $('#mapCard').removeClass('d-none');
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'The file could not be read.');
                    },
                    complete: function() { $b.prop('disabled', false); }
                });
            });

            $('#header').on('change', function() {
                const keep = {}; $('.col-select').each(function() { keep[this.id] = $(this).val(); });
                fillColumns(sample.reduce(function(n, r) { return Math.max(n, r.length); }, 0));
                $('.col-select').each(function() { $(this).val(keep[this.id]); });
                renderSample();
            });
            $('#mode').on('change', toggleMode);
            $(document).on('change', '.col-select', renderSample);

            $('#mapForm').on('submit', function(e) {
                e.preventDefault();
                const $b = $('#checkBtn').prop('disabled', true);
                $('#map_error').text('');
                $.ajax({
                    url: R.preview,
                    type: 'POST',
                    dataType: 'json',
                    data: mapData(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        $('#stats').html(
                            '<div><b>' + response.rows + '</b>rows read</div>' +
                            '<div><b>' + response.count + '</b>punches matched</div>' +
                            '<div><b>' + response.employees + '</b>employees</div>' +
                            '<div><b>' + (response.from ? response.from + (response.to !== response.from ? ' → ' + response.to : '') : '-') + '</b>dates</div>' +
                            (response.unmatched_rows ? '<div class="imp-warn"><b>' + response.unmatched_rows + '</b>rows of unknown ids</div>' : '') +
                            (response.invalid.length ? '<div class="imp-bad"><b>' + response.invalid.length + (response.invalid.length >= 10 ? '+' : '') + '</b>rows not understood</div>' : '')
                        );
                        let html = '';
                        if (response.unmatched.length) {
                            html += '<p class="imp-hint imp-warn">Not linked to any employee (set their ' + ($('#id_type').val() === 'code' ? 'employee code' : '“Device User ID” on the employee form') + '): ' +
                                response.unmatched.map(function(u) { return esc(u.id) + ' (' + u.rows + ')'; }).join(', ') + '</p>';
                        }
                        if (response.invalid.length) {
                            html += '<p class="imp-hint imp-bad mb-1">Rows skipped:</p><ul class="imp-hint">' + response.invalid.map(function(r) {
                                return '<li>Line ' + r.line + ': ' + esc(r.reason) + ' — <span class="text-muted">' + esc(r.text) + '</span></li>';
                            }).join('') + '</ul>';
                        }
                        $('#problems').html(html);
                        $('#punchRows').html(response.punches.length ? response.punches.map(function(p) {
                            return '<tr><td>' + esc(p.name) + ' <span class="text-muted">(' + esc(p.code || '') + ')</span></td><td>' + esc(p.time) + '</td></tr>';
                        }).join('') + (response.count > response.punches.length ? '<tr><td colspan="2" class="text-muted">… and ' + (response.count - response.punches.length) + ' more</td></tr>' : '')
                            : '<tr><td colspan="2" class="text-center text-muted py-3">No punch matches an employee. Check the column mapping.</td></tr>');
                        $('#importBtn').prop('disabled', !response.count);
                        $('#previewCard').removeClass('d-none');
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        $('#map_error').text((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to read the file with this mapping.');
                    },
                    complete: function() { $b.prop('disabled', false); }
                });
            });

            $('#importBtn').on('click', function() {
                const $b = $(this).prop('disabled', true);
                $.ajax({
                    url: R.commit,
                    type: 'POST',
                    dataType: 'json',
                    data: mapData(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        Swal.fire({ title: 'Imported', text: response.message, icon: 'success' });
                        $('#mapCard, #previewCard').addClass('d-none');
                        $('#uploadForm')[0].reset();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to import.');
                        $b.prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endpush
