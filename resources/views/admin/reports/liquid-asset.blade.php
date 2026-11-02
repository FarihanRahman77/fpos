@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Cash & Bank Book
@endsection

@push('styles')
    <style>
        .day-block { border: 1px solid var(--admin-border); border-radius: var(--radius-lg); overflow: hidden; }
        .day-head { padding: 10px 14px; background: var(--admin-surface-soft); border-bottom: 1px solid var(--admin-border); }
        .book-table { font-size: var(--text-caption); }
        .book-table tbody td, .book-table tfoot td, .book-table thead th { min-width: 0; padding: .4rem 10px; }
        .book-table td:nth-child(-n+3) { white-space: nowrap; }
        .book-account td { background: var(--admin-surface-soft); font-weight: var(--font-weight-medium); color: var(--color-accent); }
        .book-close td { font-weight: var(--font-weight-medium); border-top: 2px solid var(--admin-border); }
        .book-method td { font-weight: var(--font-weight-medium); background: var(--admin-surface-soft); }
        .book-badge { background: var(--admin-surface-soft); color: var(--color-text) !important; font-weight: var(--font-weight-medium); }
        #reportBody { min-height: 120px; }
        #reportBody.loading { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Daily Cash &amp; Bank Book</h4>
                <small class="text-muted">Liquid asset accounts by payment method, day by day — yesterday's closing is today's opening.
                    Sales, purchases, payments, salary &amp; expenses, bill payments, journals and amount transfers.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-danger" id="pdfBtn"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" class="btn btn-outline-success" id="excelBtn"><i class="fas fa-file-excel"></i> Excel</button>
            </div>
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <form id="filterForm" class="row g-3 align-items-end" novalidate>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="from">From <span class="text-danger">*</span></label>
                        <input type="date" id="from" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="to">To <span class="text-danger">*</span></label>
                        <input type="date" id="to" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="method_id">Payment Method</label>
                        <select id="method_id" class="form-select">
                            <option value="">All methods</option>
                            @foreach ($methods as $mt)<option value="{{ $mt->id }}">{{ $mt->account_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="account_id">Account</label>
                        <select id="account_id" class="form-select">
                            <option value="">All accounts</option>
                            @foreach ($accounts as $a)<option value="{{ $a->id }}" data-method="{{ $a->parent_id }}">{{ $a->account_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" id="generateBtn"><i class="fas fa-search"></i> Generate</button>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 small">
                            <span class="text-muted me-1">Quick:</span>
                            <a href="javascript:void(0)" class="quick-range" data-range="today">Today</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="yesterday">Yesterday</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="week">Last 7 days</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="month">This month</a>
                        </div>
                        <div class="text-danger small mt-1" id="filterError"></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach ([['sumOpening', 'Opening', 'fas fa-flag', 'i1'], ['sumIn', 'Money In', 'fas fa-arrow-down', 'i4'],
                       ['sumOut', 'Money Out', 'fas fa-arrow-up', 'i2'], ['sumClosing', 'Closing', 'fas fa-wallet', 'i3']] as [$id, $label, $icon, $cls])
                <div class="col-6 col-lg-3">
                    <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                        <div class="icon {{ $cls }}"><i class="{{ $icon }}"></i></div>
                        <div><div class="text-muted small">{{ $label }}</div><h5 class="mb-0" id="{{ $id }}">0.00</h5></div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="card acc-card">
            <div class="card-body" id="reportBody">
                <div class="text-center text-muted py-5">Choose the dates and press <strong>Generate</strong>.</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const dataUrl = "{{ route('admin.reports.liquid-asset.data') }}";
        const pdfUrl = "{{ route('admin.reports.liquid-asset.pdf') }}";
        const excelUrl = "{{ route('admin.reports.liquid-asset.excel') }}";
        const allAccounts = $('#account_id option').clone();

        function filterQuery() {
            const params = { from: $('#from').val(), to: $('#to').val(), method_id: $('#method_id').val(), account_id: $('#account_id').val() };
            Object.keys(params).forEach(function(k) { if (!params[k]) delete params[k]; });

            return $.param(params);
        }

        function validDates() {
            const from = $('#from').val(), to = $('#to').val();
            $('#filterError').text('');
            if (!from || !to) { $('#filterError').text('Select both dates.'); return false; }
            if (to < from) { $('#filterError').text('End date cannot be before the start date.'); return false; }
            return true;
        }

        function loadReport() {
            if (!validDates()) return;
            const $btn = $('#generateBtn');

            $.ajax({
                url: dataUrl + '?' + filterQuery(),
                type: 'GET',
                dataType: 'json',
                beforeSend: function() {
                    $('#reportBody').addClass('loading');
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Loading...');
                },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    $('#reportBody').html(response.html);
                    $('#sumOpening').text(response.summary.opening);
                    $('#sumIn').text(response.summary.in);
                    $('#sumOut').text(response.summary.out);
                    $('#sumClosing').text(response.summary.closing);
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    if (xhr.status === 422 && xhr.responseJSON) {
                        $('#filterError').text(xhr.responseJSON.message);
                    } else {
                        Voucher.ajaxError(xhr, 'Unable to build the report.');
                    }
                },
                complete: function() {
                    $('#reportBody').removeClass('loading');
                    $btn.prop('disabled', false).html('<i class="fas fa-search"></i> Generate');
                }
            });
        }

        // Accounts of the chosen payment method only.
        function fillAccounts() {
            const method = $('#method_id').val();
            const $a = $('#account_id').empty();
            allAccounts.each(function() {
                if (!this.value || !method || String($(this).data('method')) === method) $a.append($(this).clone());
            });
            $a.val('').trigger('change.select2');
        }

        function setRange(range) {
            const pad = function(n) { return String(n).padStart(2, '0'); };
            const fmt = function(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
            const now = new Date();
            let from = new Date(now), to = new Date(now);
            if (range === 'yesterday') { from.setDate(now.getDate() - 1); to = new Date(from); }
            if (range === 'week') from.setDate(now.getDate() - 6);
            if (range === 'month') from = new Date(now.getFullYear(), now.getMonth(), 1);
            $('#from').val(fmt(from));
            $('#to').val(fmt(to));
            loadReport();
        }

        $(document).ready(function() {
            Voucher.initSelect2($('#method_id, #account_id'));
            $('#method_id').on('change', fillAccounts);
            $('#filterForm').on('submit', function(e) { e.preventDefault(); loadReport(); });
            $('.quick-range').on('click', function() { setRange($(this).data('range')); });
            $('#pdfBtn').on('click', function() { if (validDates()) window.open(pdfUrl + '?' + filterQuery(), '_blank'); });
            $('#excelBtn').on('click', function() { if (validDates()) window.location.href = excelUrl + '?' + filterQuery(); });
            loadReport();
        });
    </script>
@endpush
