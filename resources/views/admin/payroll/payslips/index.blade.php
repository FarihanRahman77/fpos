@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Payslips
@endsection

@push('styles')
    <style>
        .ps-filter { max-width: 200px; }
        .ps-table td, .ps-table th { padding: .45rem 10px; font-size: var(--text-caption); vertical-align: middle; }
        .ps-table .num { text-align: right; font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }
        .ps-totals { display: flex; flex-wrap: wrap; gap: var(--space-lg); font-size: var(--text-caption); color: var(--color-text-muted); }
        .ps-totals b { color: var(--color-text); font-feature-settings: var(--font-feature-numeric); }
        .ps-pill { display: inline-block; padding: 0 var(--space-sm); border-radius: var(--radius-pill); font-size: var(--text-micro); font-weight: var(--font-weight-medium); }
        .ps-pill.ok { background: var(--color-success-soft); color: var(--color-success); }
        .ps-pill.part { background: var(--color-warning-soft); color: var(--color-warning); }
        .ps-pill.due { background: var(--color-danger-soft); color: var(--color-danger); }
        .ps-pill.muted { background: var(--color-surface-soft); color: var(--color-text-muted); }
        .ps-results li { font-size: var(--text-caption); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Payslips</h4>
                <small class="text-muted">Payslips of processed salary. Print, download or email them to each employee.</small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                <input type="month" id="month" class="form-control ps-filter" value="{{ now()->subMonthNoOverflow()->format('Y-m') }}" aria-label="Month">
                <select id="department_id" class="form-select ps-filter" aria-label="Department">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <h6 class="mb-0">Processed salary of <span id="monthLabel"></span></h6>
                    <span class="ps-totals ms-2" id="totals"></span>
                    <div class="ms-auto d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary bulk-btn" id="viewAll" disabled><i class="fas fa-print"></i> Print all</button>
                        <button type="button" class="btn btn-outline-secondary bulk-btn" id="downloadAll" disabled><i class="fas fa-download"></i> Download all</button>
                        <button type="button" class="btn btn-primary bulk-btn" id="emailAll" disabled><i class="fas fa-envelope"></i> Email payslips</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered ps-table mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th><th>Department</th><th>Run</th>
                                <th class="num">Net Payable</th><th class="num">Paid</th><th class="num">Due</th>
                                <th>Email</th><th style="width: 150px;"></th>
                            </tr>
                        </thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
                <div class="d-none mt-3" id="resultBox">
                    <h6 class="mb-2">Email results</h6>
                    <ul class="ps-results mb-0" id="results"></ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const R = {
            list: "{{ route('admin.payslips.list') }}",
            pdf: "{{ route('admin.payslips.pdf', '__ID__') }}",
            bulkPdf: "{{ route('admin.payslips.bulk-pdf') }}",
            email: "{{ route('admin.payslips.email', '__ID__') }}",
            emailBulk: "{{ route('admin.payslips.email-bulk') }}"
        };
        const esc = Voucher.esc, money = Voucher.money;
        let cache = [];

        function filters() {
            const f = { month: $('#month').val() };
            if ($('#department_id').val()) f.department_id = $('#department_id').val();
            return f;
        }

        function load() {
            $('#rows').html('<tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>');
            $.ajax({
                url: R.list,
                type: 'GET',
                dataType: 'json',
                data: filters(),
                success: function(response) {
                    // alert(JSON.stringify(response));
                    cache = response.data;
                    $('#monthLabel').text(response.month);
                    $('.bulk-btn').prop('disabled', !response.data.length);
                    $('#emailAll').prop('disabled', !response.totals.with_email);
                    $('#totals').html(response.data.length
                        ? response.data.length + ' employee(s) · net <b>' + money(response.totals.net) + '</b> · paid <b>' + money(response.totals.paid) + '</b>'
                        : '');
                    $('#rows').html(response.data.length ? response.data.map(function(r) {
                        const pill = r.due <= 0 ? '<span class="ps-pill ok">Paid</span>' : (r.paid > 0 ? '<span class="ps-pill part">Partly paid</span>' : '<span class="ps-pill due">Unpaid</span>');
                        return '<tr data-id="' + r.id + '">' +
                            '<td><span class="fw-semibold">' + esc(r.name) + '</span><div class="text-muted small">' + esc(r.code || '') + (r.designation ? ' · ' + esc(r.designation) : '') + '</div></td>' +
                            '<td>' + esc(r.department || '-') + '</td><td>' + esc(r.run_no) + '</td>' +
                            '<td class="num fw-semibold">' + money(r.net) + '</td><td class="num">' + money(r.paid) + '</td>' +
                            '<td class="num">' + money(r.due) + ' ' + pill + '</td>' +
                            '<td>' + (r.email ? esc(r.email) : '<span class="ps-pill muted">No email</span>') + '</td>' +
                            '<td class="text-nowrap">' +
                                '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="' + R.pdf.replace('__ID__', r.id) + '" title="View / print" aria-label="View"><i class="fas fa-eye"></i></a> ' +
                                '<a class="btn btn-sm btn-outline-secondary" href="' + R.pdf.replace('__ID__', r.id) + '?download=1" title="Download" aria-label="Download"><i class="fas fa-download"></i></a> ' +
                                '<button type="button" class="btn btn-sm btn-outline-primary ps-email" data-id="' + r.id + '" title="Email" aria-label="Email"' + (r.email ? '' : ' disabled') + '><i class="fas fa-envelope"></i></button>' +
                            '</td></tr>';
                    }).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">No processed salary in ' + esc(response.month) + '. Process it from the salary sheet first.</td></tr>');
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    Voucher.ajaxError(xhr, 'Unable to load payslips.');
                }
            });
        }

        function showResults(results) {
            $('#resultBox').removeClass('d-none');
            $('#results').html(results.map(function(r) {
                return '<li class="' + (r.status ? 'text-success' : 'text-danger') + '">' + esc(r.name) + ' — ' + esc(r.message) + '</li>';
            }).join(''));
        }

        $(document).ready(function() {
            Voucher.initSelect2($('#department_id'));
            $('#month, #department_id').on('change', load);

            $('#viewAll').on('click', function() { window.open(R.bulkPdf + '?' + $.param(filters()), '_blank'); });
            $('#downloadAll').on('click', function() { window.location = R.bulkPdf + '?' + $.param($.extend(filters(), { download: 1 })); });

            $(document).on('click', '.ps-email', function() {
                const $b = $(this).prop('disabled', true);
                $.ajax({
                    url: R.email.replace('__ID__', $b.data('id')),
                    type: 'POST',
                    dataType: 'json',
                    data: {},
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to email the payslip.');
                    },
                    complete: function() { $b.prop('disabled', false); }
                });
            });

            $('#emailAll').on('click', function() {
                const withEmail = cache.filter(function(r) { return r.email; }).length;
                Swal.fire({
                    title: 'Email ' + withEmail + ' payslip(s)?',
                    text: 'Each employee with an email address gets their own payslip as a PDF attachment.',
                    icon: 'question', showCancelButton: true, confirmButtonText: 'Send', reverseButtons: true
                }).then(function(r) {
                    if (!r.isConfirmed) return;
                    const $b = $('#emailAll').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending…');
                    $.ajax({
                        url: R.emailBulk,
                        type: 'POST',
                        dataType: 'json',
                        data: filters(),
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            showResults(response.results);
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to email the payslips.');
                            if (xhr.responseJSON && xhr.responseJSON.results) showResults(xhr.responseJSON.results);
                        },
                        complete: function() { $b.prop('disabled', false).html('<i class="fas fa-envelope"></i> Email payslips'); }
                    });
                });
            });

            load();
        });
    </script>
@endpush
