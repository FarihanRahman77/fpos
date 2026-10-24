@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Transactions Ledger
@endsection

@push('styles')
    <style>
        .ledger-table { font-size: .85rem; }
        /* beats the global ".table tbody td" (1rem padding, 120px min-width) for a compact ledger */
        .ledger-table tbody td, .ledger-table tfoot td, .ledger-table thead th { min-width: 0; padding: .4rem 10px; }
        .ledger-table td:nth-child(-n+4) { white-space: nowrap; }   /* sl, date, voucher, type on one line */
        .ledger-table .badge { font-size: .72rem; font-weight: 600; }
        /* dark text on light badges in both themes (dark mode turns .text-dark light) */
        .ledger-table .badge.ledger-badge-ink { color: #1f2937 !important; }
        .ledger-total td { font-weight: 700; background: var(--admin-surface-soft); border-top: 2px solid var(--admin-border); }
        #reportBody { min-height: 120px; position: relative; }
        #reportBody.loading { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Transactions Ledger</h4>
                <small class="text-muted">Every sale, order, purchase, return, receipt, payment, expense, bill, journal and amount transfer in the selected period.</small>
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
                    <div class="col-md-3">
                        <label class="form-label" for="from">From <span class="text-danger">*</span></label>
                        <input type="datetime-local" step="1" id="from" name="from" class="form-control"
                            value="{{ now()->startOfDay()->format('Y-m-d\TH:i:s') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="to">To <span class="text-danger">*</span></label>
                        <input type="datetime-local" step="1" id="to" name="to" class="form-control"
                            value="{{ now()->endOfDay()->format('Y-m-d\TH:i:s') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="type">Transaction Type</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">All types</option>
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="party_id">Party</label>
                        <select id="party_id" name="party_id" class="form-select">
                            <option value="">All parties</option>
                            @foreach ($parties as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}{{ $p->code ? ' (' . $p->code . ')' : '' }} - {{ $p->type }}</option>
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
                    <div><div class="text-muted small">Entries</div><h5 class="mb-0" id="sumEntries">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-layer-group"></i></div>
                    <div><div class="text-muted small">Transaction Types</div><h5 class="mb-0" id="sumTypes">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-plus"></i></div>
                    <div><div class="text-muted small">Total Debit</div><h5 class="mb-0" id="sumDr">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-minus"></i></div>
                    <div><div class="text-muted small">Total Credit</div><h5 class="mb-0" id="sumCr">0.00</h5></div>
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
                type: $('#type').val(),
                party_id: $('#party_id').val()
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
                beforeSend: function() {
                    $('#reportBody').addClass('loading');
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Loading...');
                },
                success: function(res) {
                    if (!res.status) {
                        toastr.error(res.message || 'Unable to build the report.');
                        return;
                    }

                    $('#reportBody').html(res.html);
                    $('#sumEntries').text(res.summary.entries);
                    $('#sumTypes').text(res.summary.types);
                    $('#sumDr').text(res.summary.dr);
                    $('#sumCr').text(res.summary.cr);
                },
                error: function(xhr) {
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
            Voucher.initSelect2($('#type, #party_id'));

            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                loadReport();
            });

            $('#resetBtn').on('click', function() {
                $('#type, #party_id').val('').trigger('change.select2');
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
