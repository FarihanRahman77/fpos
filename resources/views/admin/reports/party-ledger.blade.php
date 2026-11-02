@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@php $partyWord = $ledger === 'customer' ? 'Customer' : 'Supplier'; @endphp

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - {{ $config['title'] }}
@endsection

@push('styles')
    <style>
        .ledger-block { border: 1px solid var(--admin-border); border-radius: var(--radius-lg); overflow: hidden; }
        .ledger-head { padding: 10px 14px; background: var(--admin-surface-soft); border-bottom: 1px solid var(--admin-border); }
        .ledger-table { font-size: var(--text-caption); }
        /* beats the global ".table tbody td" (1rem padding, 120px min-width) for a compact ledger */
        .ledger-table tbody td, .ledger-table tfoot td, .ledger-table thead th { min-width: 0; padding: .4rem 12px; }
        .ledger-table td:nth-child(-n+4) { white-space: nowrap; }   /* date, voucher, particulars, reference on one line */
        .ledger-block .badge { font-size: var(--text-micro); font-weight: var(--font-weight-medium); }
        /* dark text on light badges in both themes (dark mode turns .text-dark light) */
        .ledger-block .badge.ledger-badge-ink { color: var(--color-text) !important; }
        .ledger-opening td { background: var(--admin-surface-soft); }
        .ledger-total td { font-weight: var(--font-weight-medium); background: var(--admin-surface-soft); border-top: 2px solid var(--admin-border); }
        #reportBody { min-height: 120px; position: relative; }
        #reportBody.loading { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">{{ $config['title'] }}</h4>
                <small class="text-muted">
                    {{ $ledger === 'customer' ? 'Accounts receivable' : 'Accounts payable' }}:
                    balance brought forward, every transaction with debit, credit and running balance per {{ strtolower($partyWord) }}.
                </small>
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
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="from">From <span class="text-danger">*</span></label>
                        <input type="date" id="from" name="from" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="to">To <span class="text-danger">*</span></label>
                        <input type="date" id="to" name="to" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="party_id">{{ $partyWord }}</label>
                        <select id="party_id" name="party_id" class="form-select">
                            <option value="">All {{ strtolower($partyWord) }}s</option>
                            @foreach ($parties as $p)
                                <option value="{{ $p->id }}" data-type="{{ $p->type }}">
                                    {{ $p->name }}{{ $p->code ? ' (' . $p->code . ')' : '' }}{{ $p->phone ? ' - ' . $p->phone : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if (count($config['types']) > 1)
                        <div class="col-md-2">
                            <label class="form-label" for="party_type">{{ $partyWord }} Type</label>
                            <select id="party_type" name="party_type" class="form-select">
                                <option value="">All types</option>
                                @foreach ($config['types'] as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="hide_zero" checked>
                            <label class="form-check-label" for="hide_zero">Hide {{ strtolower($partyWord) }}s with no balance or activity</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill" id="generateBtn">
                            <i class="fas fa-search"></i> Generate
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="resetBtn" title="Reset filters">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 small">
                            <span class="text-muted me-1">Quick range:</span>
                            <a href="javascript:void(0)" class="quick-range" data-range="today">Today</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="week">This week</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="month">This month</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="last-month">Last month</a> ·
                            <a href="javascript:void(0)" class="quick-range" data-range="year">This year</a>
                        </div>
                        <div class="text-danger small mt-1" id="filterError"></div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-users"></i></div>
                    <div><div class="text-muted small">{{ $partyWord }}s</div><h5 class="mb-0" id="sumParties">0</h5></div>
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
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-balance-scale"></i></div>
                    <div>
                        <div class="text-muted small">Net Closing <span id="sumLabel"></span></div>
                        <h5 class="mb-0" id="sumClosing">0.00</h5>
                    </div>
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
        const dataUrl = "{{ route('admin.reports.party-ledger.data', $ledger) }}";
        const pdfUrl = "{{ route('admin.reports.party-ledger.pdf', $ledger) }}";
        const excelUrl = "{{ route('admin.reports.party-ledger.excel', $ledger) }}";

        /* Current filters as a query string (used by Generate, PDF and Excel). */
        function filterQuery() {
            const params = {
                from: $('#from').val(),
                to: $('#to').val(),
                party_id: $('#party_id').val(),
                party_type: $('#party_type').val(),
                hide_zero: $('#hide_zero').is(':checked') ? 1 : 0
            };

            Object.keys(params).forEach(function(k) {
                if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k];
            });

            return $.param(params);
        }

        function validDates() {
            const from = $('#from').val(), to = $('#to').val();
            $('#filterError').text('');

            if (!from || !to) {
                $('#filterError').text('Select both From and To dates.');
                return false;
            }
            if (to < from) {
                $('#filterError').text('To date cannot be before From date.');
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
                    $('#sumParties').text(response.summary.parties);
                    $('#sumDr').text(response.summary.dr);
                    $('#sumCr').text(response.summary.cr);
                    $('#sumClosing').text(response.summary.closing);
                    $('#sumLabel').text('(' + response.summary.label + ')');
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

        function setRange(range) {
            const pad = function(n) { return String(n).padStart(2, '0'); };
            const fmt = function(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
            const now = new Date();
            let from = new Date(now), to = new Date(now);

            if (range === 'week') {
                from.setDate(now.getDate() - ((now.getDay() + 6) % 7));   // Monday
            } else if (range === 'month') {
                from = new Date(now.getFullYear(), now.getMonth(), 1);
            } else if (range === 'last-month') {
                from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                to = new Date(now.getFullYear(), now.getMonth(), 0);
            } else if (range === 'year') {
                from = new Date(now.getFullYear(), 0, 1);
            }

            $('#from').val(fmt(from));
            $('#to').val(fmt(to));
            loadReport();
        }

        $(document).ready(function() {
            Voucher.initSelect2($('#party_id, #party_type'));

            // A single party already fixes the type
            $('#party_id').on('change', function() {
                if (this.value) $('#party_type').val('').trigger('change.select2');
            });
            $('#party_type').on('change', function() {
                if (this.value) $('#party_id').val('').trigger('change.select2');
            });

            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                loadReport();
            });

            $('#resetBtn').on('click', function() {
                $('#filterForm')[0].reset();
                $('#party_id, #party_type').val('').trigger('change.select2');
                loadReport();
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
