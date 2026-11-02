@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')
{{-- Trial Balance / Profit & Loss / Balance Sheet page. $filter: 'as_at' (one date) or 'range' (from / to + comparison). --}}

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - {{ $title }}
@endsection

@push('styles')
    <style>
        .st-table { font-size: var(--text-body-tabular); }
        .st-table tbody td, .st-table tfoot td, .st-table thead th { min-width: 0; padding: var(--space-xs) var(--space-md); }
        .st-table .st-num { text-align: right; white-space: nowrap; font-feature-settings: var(--font-feature-numeric); width: 1%; min-width: 140px; }
        .st-table .st-code { font-family: var(--font-mono); font-size: var(--text-micro); color: var(--color-text-muted); white-space: nowrap; width: 1%; }
        .st-table .st-section td { background: var(--color-surface-soft); font-weight: var(--font-weight-medium); color: var(--color-text); letter-spacing: var(--tracking-caption); }
        .st-table .st-head td { font-weight: var(--font-weight-medium); color: var(--color-accent-ink); }
        .st-table .st-group td { font-weight: var(--font-weight-medium); color: var(--color-text-secondary); }
        .st-table .st-line .st-label { color: var(--color-text); }
        .st-table .st-indent-1 { padding-left: calc(var(--space-md) + var(--space-lg)) !important; }
        .st-table .st-indent-2 { padding-left: calc(var(--space-md) + var(--space-xxl)) !important; }
        .st-table .st-subtotal td { font-weight: var(--font-weight-medium); border-top: 1px solid var(--color-text-muted); }
        .st-table .st-total td { font-weight: var(--font-weight-medium); background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .st-table .st-grand td { font-weight: var(--font-weight-medium); font-size: var(--text-body-md); background: var(--color-surface-soft); border-top: 3px double var(--color-text-muted); }
        .st-neg { color: var(--color-danger); }
        .st-check { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); margin-top: var(--space-md); font-size: var(--text-body-tabular); }
        .st-check.ok { background: var(--color-success-soft); color: var(--color-success); }
        .st-check.bad { background: var(--color-danger-soft); color: var(--color-danger); }
        .st-notes { margin: var(--space-lg) 0 0; padding: var(--space-md) var(--space-lg); border: 1px dashed var(--color-border); border-radius: var(--radius-md); font-size: var(--text-caption); color: var(--color-text-muted); }
        .st-notes li + li { margin-top: var(--space-xxs); }
        #reportBody { min-height: 120px; position: relative; }
        #reportBody.loading { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">{{ $title }}</h4>
                <small class="text-muted">{{ $intro }}</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-danger" id="pdfBtn" title="Open as PDF">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-outline-success" id="excelBtn" title="Download as Excel">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
            </div>
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <form id="filterForm" class="row g-3 align-items-end" novalidate>
                    @if ($filter === 'range')
                        <div class="col-md-3">
                            <label class="form-label" for="from">From <span class="text-danger">*</span></label>
                            <input type="date" id="from" name="from" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="to">To <span class="text-danger">*</span></label>
                            <input type="date" id="to" name="to" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="compare">
                                <label class="form-check-label" for="compare">Compare with the previous period</label>
                            </div>
                        </div>
                    @else
                        <div class="col-md-3">
                            <label class="form-label" for="as_at">As at</label>
                            <input type="date" id="as_at" name="as_at" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="hide_zero" checked>
                                <label class="form-check-label" for="hide_zero">Hide zero balances</label>
                            </div>
                        </div>
                        <div class="col-md-3"></div>
                    @endif
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill" id="generateBtn">
                            <i class="fas fa-search"></i> Generate
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="resetBtn" title="Reset">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                    @if ($filter === 'range')
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2 small">
                                <span class="text-muted me-1">Quick range:</span>
                                <a href="javascript:void(0)" class="quick-range" data-range="month">This month</a> ·
                                <a href="javascript:void(0)" class="quick-range" data-range="last-month">Last month</a> ·
                                <a href="javascript:void(0)" class="quick-range" data-range="quarter">This quarter</a> ·
                                <a href="javascript:void(0)" class="quick-range" data-range="year">This year</a> ·
                                <a href="javascript:void(0)" class="quick-range" data-range="last-year">Last year</a>
                            </div>
                        </div>
                    @endif
                    <div class="col-12"><div class="text-danger small" id="filterError"></div></div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3" id="summaryRow">
            @foreach ([['i1', 'fas fa-receipt'], ['i4', 'fas fa-layer-group'], ['i3', 'fas fa-balance-scale'], ['i2', 'fas fa-chart-line']] as $i => $c)
                <div class="col-6 col-lg-3">
                    <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                        <div class="icon {{ $c[0] }}"><i class="{{ $c[1] }}"></i></div>
                        <div><div class="text-muted small" data-sum-label="{{ $i }}">&nbsp;</div><h5 class="mb-0" data-sum-value="{{ $i }}">0.00</h5></div>
                    </div></div>
                </div>
            @endforeach
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
        const Statement = {
            filter: @json($filter),
            urls: @json($urls),

            query: function() {
                const p = {};
                if (this.filter === 'range') {
                    p.from = $('#from').val();
                    p.to = $('#to').val();
                    p.compare = $('#compare').is(':checked') ? 1 : 0;
                } else {
                    if ($('#as_at').val()) p.as_at = $('#as_at').val();
                    p.hide_zero = $('#hide_zero').is(':checked') ? 1 : 0;
                }
                return $.param(p);
            },

            valid: function() {
                $('#filterError').text('');
                if (this.filter !== 'range') return true;
                const from = $('#from').val(), to = $('#to').val();
                if (!from || !to) { $('#filterError').text('Select both From and To dates.'); return false; }
                if (to < from) { $('#filterError').text('To cannot be before From.'); return false; }
                return true;
            },

            load: function() {
                if (!this.valid()) return;
                const $btn = $('#generateBtn');

                $.ajax({
                    url: this.urls.data + '?' + this.query(),
                    type: 'GET',
                    dataType: 'json',
                    beforeSend: function() {
                        $('#reportBody').addClass('loading');
                        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Loading...');
                    },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (!response.status) { toastr.error(response.message || 'Unable to build the statement.'); return; }
                        $('#reportBody').html(response.html);
                        (response.summary || []).forEach(function(s, i) {
                            $('[data-sum-label="' + i + '"]').text(s.label);
                            $('[data-sum-value="' + i + '"]').text(s.value);
                        });
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON) {
                            $('#filterError').text(xhr.responseJSON.message);
                        } else {
                            Voucher.ajaxError(xhr, 'Unable to build the statement.');
                        }
                    },
                    complete: function() {
                        $('#reportBody').removeClass('loading');
                        $btn.prop('disabled', false).html('<i class="fas fa-search"></i> Generate');
                    }
                });
            },

            setRange: function(range) {
                const pad = function(n) { return String(n).padStart(2, '0'); };
                const fmt = function(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
                const now = new Date();
                let from = new Date(now.getFullYear(), now.getMonth(), 1), to = new Date(now);

                if (range === 'last-month') {
                    from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    to = new Date(now.getFullYear(), now.getMonth(), 0);
                } else if (range === 'quarter') {
                    from = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1);
                } else if (range === 'year') {
                    from = new Date(now.getFullYear(), 0, 1);
                } else if (range === 'last-year') {
                    from = new Date(now.getFullYear() - 1, 0, 1);
                    to = new Date(now.getFullYear() - 1, 11, 31);
                }
                $('#from').val(fmt(from));
                $('#to').val(fmt(to));
                this.load();
            }
        };

        $(document).ready(function() {
            $('#filterForm').on('submit', function(e) { e.preventDefault(); Statement.load(); });
            $('#compare, #hide_zero').on('change', function() { Statement.load(); });
            $('.quick-range').on('click', function() { Statement.setRange($(this).data('range')); });

            $('#resetBtn').on('click', function() {
                if (Statement.filter === 'range') {
                    $('#compare').prop('checked', false);
                    Statement.setRange('month');
                } else {
                    $('#as_at').val(Voucher.todayLocal());
                    $('#hide_zero').prop('checked', true);
                    Statement.load();
                }
            });

            $('#pdfBtn').on('click', function() {
                if (Statement.valid()) window.open(Statement.urls.pdf + '?' + Statement.query(), '_blank');
            });
            $('#excelBtn').on('click', function() {
                if (Statement.valid()) window.location.href = Statement.urls.excel + '?' + Statement.query();
            });

            Statement.load();
        });
    </script>
@endpush
