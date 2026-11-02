@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Transactions Ledger
@endsection

@push('styles')
    <style>
        .ledger-table { font-size: var(--text-caption); }
        /* beats the global ".table tbody td" (1rem padding, 120px min-width) for a compact ledger */
        .ledger-table tbody td, .ledger-table tfoot td, .ledger-table thead th { min-width: 0; padding: .4rem 10px; }
        .tl-section { margin-bottom: var(--space-xl); }
        .tl-band {
            margin: 0;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--color-border);
            border-bottom: 0;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            background: var(--color-surface-soft);
            text-align: center;
            font-size: var(--text-body-tabular);
            font-weight: var(--font-weight-medium);
        }
        .tl-sub { font-size: var(--text-micro); color: var(--color-text-muted); }
        .tl-num { white-space: nowrap; font-feature-settings: var(--font-feature-numeric); }
        .tl-in { color: var(--color-success); }
        .tl-out { color: var(--color-danger); }
        .tl-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--space-xs) var(--space-xxl);
            margin-top: var(--space-md);
            font-size: var(--text-caption);
        }
        .tl-summary dl { margin: 0; }
        .tl-summary dl > div { display: flex; justify-content: space-between; gap: var(--space-md); padding: var(--space-xxs) 0; border-bottom: 1px dashed var(--color-border); }
        .tl-summary dt { font-weight: var(--font-weight-regular); color: var(--color-text-secondary); }
        .tl-summary dd { margin: 0; font-weight: var(--font-weight-medium); font-feature-settings: var(--font-feature-numeric); white-space: nowrap; }
        .tl-summary .tl-strong dt { color: var(--color-text); font-weight: var(--font-weight-medium); }
        @media (max-width: 767.98px) { .tl-summary { grid-template-columns: 1fr; } }
        .ledger-total td { font-weight: var(--font-weight-medium); background: var(--admin-surface-soft); border-top: 2px solid var(--admin-border); }
        #reportBody { min-height: 120px; position: relative; }
        #reportBody.loading { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Transactions Ledger</h4>
                <small class="text-muted">Money in and out of every payment method (cash, bank, mobile banking): sales receipts, purchases, payments, expenses, salary, bills, journals and transfers.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-danger" id="pdfBtn" title="Download as PDF">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-outline-success" id="excelBtn" title="Download as Excel">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card acc-card mb-3">
            <div class="card-body">
                <form id="filterForm" class="row g-3 align-items-end" novalidate>
                    <div class="col-md-3 col-xl-3">
                        <label class="form-label" for="from">From <span class="text-danger">*</span></label>
                        <input type="datetime-local" step="1" id="from" name="from" class="form-control"
                            value="{{ now()->startOfDay()->format('Y-m-d\TH:i:s') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="to">To <span class="text-danger">*</span></label>
                        <input type="datetime-local" step="1" id="to" name="to" class="form-control"
                            value="{{ now()->endOfDay()->format('Y-m-d\TH:i:s') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="method_id">Payment Method</label>
                        <select id="method_id" name="method_id" class="form-select">
                            <option value="">All methods</option>
                            @foreach ($methods as $mt)
                                <option value="{{ $mt->id }}">{{ $mt->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="account_id">Account</label>
                        <select id="account_id" name="account_id" class="form-select">
                            <option value="">All accounts</option>
                            @foreach ($accounts as $ac)
                                <option value="{{ $ac->id }}" data-method="{{ $ac->parent_id }}">{{ $ac->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="type">Voucher Type</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">All types</option>
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-9">
                        <div class="d-flex flex-wrap gap-2 small">
                            <span class="text-muted me-1">Quick range:</span>
                            <a href="javascript:void(0)" class="quick-range" data-range="today">Today</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="yesterday">Yesterday</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="week">This week</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="month">This month</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="last-month">Last month</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="year">This year</a>
                        </div>
                        <div class="text-danger small mt-1" id="filterError"></div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill" id="generateBtn">
                            <i class="fas fa-search"></i> Generate
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="resetBtn" title="Reset to today">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-receipt"></i></div>
                    <div><div class="text-muted small">Opening Balance</div><h5 class="mb-0" id="sumOpening">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-layer-group"></i></div>
                    <div><div class="text-muted small">Total In</div><h5 class="mb-0" id="sumIn">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-plus"></i></div>
                    <div><div class="text-muted small">Total Out</div><h5 class="mb-0" id="sumOut">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-minus"></i></div>
                    <div><div class="text-muted small">Net Closing Balance</div><h5 class="mb-0" id="sumClosing">0.00</h5></div>
                </div></div>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body" id="reportBody">
                <div class="text-center text-muted py-5">Choose filters and press <strong>Generate</strong>.</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const dataUrl = "{{ route('admin.reports.transaction-ledger.data') }}";
        const pdfUrl = "{{ route('admin.reports.transaction-ledger.pdf') }}";
        const excelUrl = "{{ route('admin.reports.transaction-ledger.excel') }}";

        /* Current filters as a query string (used by Generate, PDF and Excel). */
        function filterQuery() {
            const params = {
                from: $('#from').val(),
                to: $('#to').val(),
                method_id: $('#method_id').val(),
                account_id: $('#account_id').val(),
                type: $('#type').val()
            };

            Object.keys(params).forEach(function(k) {
                if (params[k] === '' || params[k] === null) delete params[k];
            });

            return $.param(params);
        }

        function validDates() {
            const from = $('#from').val(), to = $('#to').val();
            $('#filterError').text('');

            if (!from || !to) {
                $('#filterError').text('Select both From and To date and time.');
                return false;
            }
            if (to < from) {
                $('#filterError').text('To cannot be before From.');
                return false;
            }
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
                    if (!response.status) {
                        toastr.error(response.message || 'Unable to build the report.');
                        return;
                    }

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

        /* Whole days: start at 12:00:00 AM, end at 11:59:59 PM. */
        function setRange(range) {
            const pad = function(n) { return String(n).padStart(2, '0'); };
            const fmt = function(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
            const now = new Date();
            let from = new Date(now), to = new Date(now);

            if (range === 'yesterday') {
                from.setDate(now.getDate() - 1);
                to = new Date(from);
            } else if (range === 'week') {
                from.setDate(now.getDate() - ((now.getDay() + 6) % 7));   // Monday
            } else if (range === 'month') {
                from = new Date(now.getFullYear(), now.getMonth(), 1);
            } else if (range === 'last-month') {
                from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                to = new Date(now.getFullYear(), now.getMonth(), 0);
            } else if (range === 'year') {
                from = new Date(now.getFullYear(), 0, 1);
            }

            $('#from').val(fmt(from) + 'T00:00:00');
            $('#to').val(fmt(to) + 'T23:59:59');
            loadReport();
        }

        $(document).ready(function() {
            Voucher.initSelect2($('#method_id, #account_id, #type'));

            // An account belongs to one method: picking an account selects its method,
            // picking another method clears an account that is not under it.
            $('#method_id').on('change', function() {
                const method = $(this).val();
                const owner = $('#account_id').find(':selected').data('method');
                if (method && owner && String(owner) !== method) {
                    $('#account_id').val('').trigger('change.select2');
                }
            });
            $('#account_id').on('change', function() {
                const owner = $(this).find(':selected').data('method');
                if (owner) $('#method_id').val(String(owner)).trigger('change.select2');
            });

            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                loadReport();
            });

            $('#resetBtn').on('click', function() {
                $('#method_id, #account_id, #type').val('').trigger('change.select2');
                setRange('today');
            });

            $('.quick-range').on('click', function() { setRange($(this).data('range')); });

            $('#pdfBtn').on('click', function() {
                if (validDates()) window.open(pdfUrl + '?' + filterQuery(), '_blank');
            });

            $('#excelBtn').on('click', function() {
                if (validDates()) window.location.href = excelUrl + '?' + filterQuery();
            });

            loadReport();
        });
    </script>
@endpush
