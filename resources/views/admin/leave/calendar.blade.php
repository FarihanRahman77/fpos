@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Holiday Calendar
@endsection

@push('styles')
    <style>
        .cal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; }
        .cal-month { border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 10px; background: var(--color-surface); }
        .cal-month h6 { text-align: center; margin-bottom: 6px; font-weight: var(--font-weight-medium); }
        .cal-month table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: var(--text-caption); }
        .cal-month th { text-align: center; color: var(--color-text-muted); font-weight: var(--font-weight-medium); padding: 2px 0; }
        .cal-month td { text-align: center; padding: 0; height: 28px; }
        .cal-day { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: var(--radius-sm); position: relative; cursor: default; }
        .cal-day.weekend { background: var(--color-surface-soft); color: var(--color-text-muted); }
        .cal-day.holiday-Public { background: var(--color-danger-soft); color: var(--color-danger); font-weight: var(--font-weight-medium); }
        .cal-day.holiday-Festival { background: var(--color-warning-soft); color: var(--color-warning); font-weight: var(--font-weight-medium); }
        .cal-day.holiday-Company { background: var(--color-info-soft); color: var(--color-accent-ink); font-weight: var(--font-weight-medium); }
        .cal-day.holiday-Optional { background: var(--color-primary-subdued); color: var(--color-primary-press); font-weight: var(--font-weight-medium); }
        .cal-day.today { outline: 2px solid var(--color-accent); }
        .cal-day .leave-dot { position: absolute; bottom: 1px; left: 50%; transform: translateX(-50%); width: 5px; height: 5px; border-radius: 50%; background: var(--color-success); }
        .cal-legend span { display: inline-flex; align-items: center; gap: 6px; margin-right: 14px; font-size: var(--text-caption); }
        .cal-legend i { width: 14px; height: 14px; border-radius: var(--radius-xs); display: inline-block; }
        .weekday-chip .btn { min-width: 52px; }
        .holiday-list { max-height: 520px; overflow-y: auto; }
        .holiday-list .list-group-item { padding: .55rem .75rem; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Holiday Calendar</h4>
                <small class="text-muted">Weekends and holidays of each year. Leave days are counted on working days only.</small>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary" id="prevYear" title="Previous year"><i class="fas fa-chevron-left"></i></button>
                <input type="number" id="year" class="form-control text-center fw-bold" style="width: 100px;" value="{{ $year }}" min="2000" max="2100">
                <button type="button" class="btn btn-outline-secondary" id="nextYear" title="Next year"><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="btn btn-outline-primary" id="copyBtn"><i class="fas fa-copy"></i> Copy from last year</button>
                <button type="button" class="btn btn-primary" id="addHolidayBtn"><i class="fas fa-plus"></i> Add Holiday</button>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach ([['sumDays', 'Days in Year', 'fas fa-calendar', 'i1'], ['sumWorking', 'Working Days', 'fas fa-briefcase', 'i4'],
                       ['sumHolidays', 'Holiday Days', 'fas fa-umbrella-beach', 'i2'], ['sumWeekend', 'Weekend', 'fas fa-bed', 'i3']] as [$id, $label, $icon, $cls])
                <div class="col-6 col-lg-3">
                    <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                        <div class="icon {{ $cls }}"><i class="{{ $icon }}"></i></div>
                        <div><div class="text-muted small">{{ $label }}</div><h5 class="mb-0" id="{{ $id }}">-</h5></div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="row g-3">
            <div class="col-xl-4">
                <div class="card acc-card mb-3">
                    <div class="card-body">
                        <h6 class="mb-2">Weekend of <span class="year-label">{{ $year }}</span></h6>
                        <div class="d-flex flex-wrap gap-1 weekday-chip mb-2" id="weekendDays">
                            @foreach ($dayNames as $num => $name)
                                <input type="checkbox" class="btn-check" id="wd{{ $num }}" value="{{ $num }}" autocomplete="off">
                                <label class="btn btn-sm btn-outline-secondary" for="wd{{ $num }}">{{ substr($name, 0, 3) }}</label>
                            @endforeach
                        </div>
                        <input type="text" id="calendarNote" class="form-control form-control-sm mb-2" maxlength="255" placeholder="Note (optional)" value="{{ $note }}">
                        <button type="button" class="btn btn-sm btn-primary" id="saveWeekendBtn"><i class="fas fa-save"></i> Save Weekend</button>
                    </div>
                </div>

                <div class="card acc-card">
                    <div class="card-body">
                        <h6 class="mb-2">Holidays of <span class="year-label">{{ $year }}</span></h6>
                        <div class="list-group list-group-flush holiday-list" id="holidayList"></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card acc-card">
                    <div class="card-body">
                        <div class="cal-legend mb-3">
                            <span><i style="background:var(--color-surface-soft)"></i> Weekend</span>
                            <span><i style="background:var(--color-danger-soft)"></i> Public</span>
                            <span><i style="background:var(--color-warning-soft)"></i> Festival</span>
                            <span><i style="background:var(--color-info-soft)"></i> Company</span>
                            <span><i style="background:var(--color-primary-subdued)"></i> Optional</span>
                            <span><i style="background:var(--color-success); width:8px; height:8px; border-radius:50%"></i> Employees on leave</span>
                        </div>
                        <div class="cal-grid" id="calendarGrid"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Holiday modal --}}
    <div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="holidayForm" autocomplete="off">
                    <input type="hidden" name="id" id="holiday_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="holidayTitle">Add Holiday</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="title">Holiday <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control" maxlength="150" placeholder="e.g. Victory Day">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="from_date">From <span class="text-danger">*</span></label>
                                <input type="date" name="from_date" id="from_date" class="form-control">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="to_date">To <span class="text-danger">*</span></label>
                                <input type="date" name="to_date" id="to_date" class="form-control">
                                <div class="text-danger small field-error"></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="type">Type</label>
                                <select name="type" id="type" class="form-select">
                                    @foreach ($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="holiday_status">Status</label>
                                <select name="status" id="holiday_status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="description">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                        <div class="text-danger small mt-2" id="holiday_error"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto d-none" id="deleteHolidayBtn"><i class="fas fa-trash"></i> Delete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="holidaySaveBtn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const Cal = {
            data: null,
            holidays: {},
            modal: null,
            months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],

            year: function() { return parseInt($('#year').val()) || new Date().getFullYear(); },

            load: function() {
                const year = Cal.year();
                $('.year-label').text(year);
                history.replaceState(null, '', '?year=' + year);

                $.ajax({
                    url: "{{ route('admin.leave.calendar.data') }}",
                    type: 'GET',
                    dataType: 'json',
                    data: { year: year },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        Cal.data = response;
                        $('#sumDays').text(response.summary.days);
                        $('#sumWorking').text(response.summary.working);
                        $('#sumHolidays').text(response.summary.holidays);
                        $('#sumWeekend').text(response.summary.weekend);
                        $('#weekendDays input').each(function() { this.checked = response.weekend.indexOf(parseInt(this.value)) !== -1; });
                        Cal.renderList(response.holidays);
                        Cal.renderGrid(response);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to load the calendar.');
                    }
                });
            },

            renderList: function(rows) {
                Cal.holidays = {};
                if (!rows.length) {
                    $('#holidayList').html('<div class="text-muted small py-3 text-center">No holidays yet. Add them or copy from last year.</div>');
                    return;
                }
                $('#holidayList').html(rows.map(function(h) {
                    Cal.holidays[h.id] = h;
                    return '<a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center gap-2" onclick="Cal.edit(' + h.id + ')">' +
                        '<span class="cal-day holiday-' + h.type + '" style="width:auto; padding:0 6px; white-space:nowrap">' + Voucher.esc(h.label) + '</span>' +
                        '<span class="flex-fill"><span class="fw-semibold' + (h.status === 'Active' ? '' : ' text-decoration-line-through text-muted') + '">' + Voucher.esc(h.title) + '</span>' +
                        '<div class="text-muted small">' + h.type + ' · ' + h.days + ' day(s)</div></span></a>';
                }).join(''));
            },

            renderGrid: function(res) {
                const pad = function(n) { return String(n).padStart(2, '0'); };
                const today = new Date();
                const todayKey = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());
                let html = '';

                for (let m = 0; m < 12; m++) {
                    const first = new Date(res.year, m, 1);
                    const daysIn = new Date(res.year, m + 1, 0).getDate();
                    const lead = (first.getDay() + 6) % 7;   // Monday first
                    let cells = '';
                    for (let i = 0; i < lead; i++) cells += '<td></td>';

                    for (let d = 1; d <= daysIn; d++) {
                        const key = res.year + '-' + pad(m + 1) + '-' + pad(d);
                        const iso = ((lead + d - 1) % 7) + 1;
                        const hol = res.map[key];
                        const leave = res.on_leave[key] || [];
                        const cls = ['cal-day'];
                        const tips = [];
                        if (res.weekend.indexOf(iso) !== -1) { cls.push('weekend'); tips.push('Weekend'); }
                        if (hol) { cls.push('holiday-' + hol.type); tips.push(hol.title + ' (' + hol.type + ')'); }
                        if (key === todayKey) cls.push('today');
                        if (leave.length) tips.push('On leave: ' + leave.join(', '));

                        cells += '<td><span class="' + cls.join(' ') + '" title="' + Voucher.esc(tips.join('\n')) + '">' + d +
                            (leave.length ? '<span class="leave-dot"></span>' : '') + '</span></td>';
                        if ((lead + d) % 7 === 0) cells += '</tr><tr>';
                    }

                    html += '<div class="cal-month"><h6>' + Cal.months[m] + '</h6><table><thead><tr>' +
                        ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map(function(n) { return '<th>' + n + '</th>'; }).join('') +
                        '</tr></thead><tbody><tr>' + cells + '</tr></tbody></table></div>';
                }
                $('#calendarGrid').html(html);
            },

            add: function() {
                Cal.prepare('Add Holiday');
                $('#from_date, #to_date').val(Cal.year() + '-01-01');
                Cal.modal.show();
            },

            edit: function(id) {
                const h = Cal.holidays[id];
                Cal.prepare('Edit Holiday');
                $('#holiday_id').val(h.id);
                $('#title').val(h.title);
                $('#from_date').val(h.from_date);
                $('#to_date').val(h.to_date);
                $('#type').val(h.type);
                $('#holiday_status').val(h.status);
                $('#description').val(h.description || '');
                $('#deleteHolidayBtn').removeClass('d-none');
                Cal.modal.show();
            },

            prepare: function(title) {
                $('#holidayForm')[0].reset();
                Voucher.clearErrors($('#holidayForm'));
                $('#holiday_error').text('');
                $('#holiday_id').val('');
                $('#holidayTitle').text(title);
                $('#deleteHolidayBtn').addClass('d-none');
            },

            post: function(url, data, done) {
                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    data: data,
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        response.status ? toastr.success(response.message) : toastr.error(response.message);
                        if (response.status) { Cal.load(); if (done) done(); }
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Something went wrong.');
                    }
                });
            }
        };

        $(document).ready(function() {
            Cal.modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('holidayModal'));

            $('#year').on('change', Cal.load);
            $('#prevYear').on('click', function() { $('#year').val(Cal.year() - 1); Cal.load(); });
            $('#nextYear').on('click', function() { $('#year').val(Cal.year() + 1); Cal.load(); });
            $('#addHolidayBtn').on('click', Cal.add);

            // A one-day holiday by default: keep "To" in step with "From".
            $('#from_date').on('change', function() {
                if (!$('#to_date').val() || $('#to_date').val() < this.value) $('#to_date').val(this.value);
            });

            $('#saveWeekendBtn').on('click', function() {
                Cal.post("{{ route('admin.leave.calendar.weekend') }}", {
                    year: Cal.year(),
                    weekend_days: $('#weekendDays input:checked').map(function() { return this.value; }).get(),
                    note: $('#calendarNote').val()
                });
            });

            $('#copyBtn').on('click', function() {
                const year = Cal.year();
                Swal.fire({
                    title: 'Copy ' + (year - 1) + ' into ' + year + '?',
                    text: 'Weekend and holidays are copied to the same day and month. Already existing holidays are skipped.',
                    icon: 'question', showCancelButton: true, confirmButtonText: 'Copy', reverseButtons: true
                }).then(function(r) {
                    if (r.isConfirmed) Cal.post("{{ route('admin.leave.calendar.copy') }}", { from_year: year - 1, year: year });
                });
            });

            $('#holidayForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#holidaySaveBtn').prop('disabled', true);
                $.ajax({
                    url: "{{ route('admin.leave.calendar.holiday') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        Cal.modal.hide();
                        Cal.load();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            Voucher.showErrors($('#holidayForm'), xhr.responseJSON.errors, $('#holiday_error'));
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to save.');
                        }
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            $('#deleteHolidayBtn').on('click', function() {
                const id = $('#holiday_id').val();
                Swal.fire({ title: 'Delete this holiday?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'var(--color-danger)', confirmButtonText: 'Delete', reverseButtons: true })
                    .then(function(r) {
                        if (r.isConfirmed) Cal.post("{{ url('admin/leave/calendar/holiday/delete') }}/" + id, {}, function() { Cal.modal.hide(); });
                    });
            });

            Cal.load();
        });
    </script>
@endpush
