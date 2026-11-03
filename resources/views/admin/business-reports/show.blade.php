@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - {{ $title }}
@endsection

@push('styles')
    <style>
        .br-head-icon {
            display: inline-grid;
            flex: 0 0 44px;
            width: 44px;
            height: 44px;
            place-items: center;
            border-radius: var(--radius-md);
            background: var(--color-accent-soft);
            color: var(--color-accent-ink);
            font-size: var(--text-heading-sm);
        }
        .br-quick a { font-size: var(--text-caption); }
        .br-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: var(--space-md); margin-bottom: var(--space-lg); }
        .br-card {
            padding: var(--space-md) var(--space-lg);
            border: 1px solid var(--color-border);
            border-left: 3px solid var(--color-border-input);
            border-radius: var(--radius-lg);
            background: var(--color-surface);
            box-shadow: var(--shadow-1);
        }
        .br-card.tone-accent { border-left-color: var(--color-accent); }
        .br-card.tone-success { border-left-color: var(--color-success); }
        .br-card.tone-warning { border-left-color: var(--color-warning); }
        .br-card.tone-danger { border-left-color: var(--color-danger); }
        .br-card small { display: block; font-size: var(--text-caption); color: var(--color-text-muted); }
        .br-card b {
            display: block;
            margin-top: var(--space-xxs);
            font-size: var(--text-heading-md);
            font-weight: var(--font-weight-light);
            font-feature-settings: var(--font-feature-numeric);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .br-table { font-size: var(--text-caption); }
        /* beats the global ".table tbody td" (120px min-width) so wide reports fit */
        .br-table thead th, .br-table tbody td, .br-table tfoot td { min-width: 0; padding: .45rem 10px; vertical-align: middle; }
        .br-table tbody td:first-child { min-width: 160px; }
        .br-table td.heat { min-width: 44px; padding: .45rem 4px; }
        .br-table th { white-space: nowrap; }
        .br-table .num { text-align: right; white-space: nowrap; font-feature-settings: var(--font-feature-numeric); }
        .br-table .neg { color: var(--color-danger); }
        .br-table .grp td { background: var(--color-surface-soft); font-weight: var(--font-weight-medium); }
        .br-table .sub td, .br-table tfoot td { background: var(--color-surface-soft); font-weight: var(--font-weight-medium); }
        .br-table tfoot td { border-top: 2px solid var(--color-border); }
        .br-table td.heat { text-align: center; }
        .br-table td.heat-1 { background: color-mix(in srgb, var(--color-accent) 10%, var(--color-surface)); }
        .br-table td.heat-2 { background: color-mix(in srgb, var(--color-accent) 25%, var(--color-surface)); }
        .br-table td.heat-3 { background: color-mix(in srgb, var(--color-accent) 45%, var(--color-surface)); }
        .br-table td.heat-4 { background: color-mix(in srgb, var(--color-accent) 65%, var(--color-surface)); color: var(--color-button-text); }
        .br-table td.heat-5 { background: var(--color-accent); color: var(--color-button-text); }
        .br-notes { margin: var(--space-md) 0 0; padding-left: var(--space-lg); font-size: var(--text-caption); color: var(--color-text-muted); }
        #brBody { min-height: 140px; position: relative; }
        #brBody.loading { opacity: .45; pointer-events: none; }
        .br-extra { margin-top: var(--space-lg); padding: var(--space-lg); border: 1px dashed var(--color-border-input); border-radius: var(--radius-lg); background: var(--color-surface-soft); }
        .br-extra .form-control { max-width: 160px; }
        .cd-title { margin-bottom: var(--space-sm); font-weight: var(--font-weight-medium); }
        .cd-table td.num { width: 34%; text-align: right; }
        .table.cd-table tbody td, .table.cd-table tfoot td, .table.cd-table thead th { padding: var(--space-xs) var(--space-sm); min-width: 0; }
        .cd-table thead th:last-child { text-align: right; }
        .cd-table tr.cd-zero td { color: var(--color-text-muted); }
        .cd-table td.cd-in { color: var(--color-success); }
        .cd-table td.cd-out { color: var(--color-danger); }
        .cd-table tr.cd-method td { background: var(--color-surface-soft); font-weight: var(--font-weight-medium); text-align: center; }
        .cd-table tfoot td { font-weight: var(--font-weight-medium); background: var(--color-surface-soft); }
        .cd-summary { margin-top: var(--space-xl); padding: var(--space-lg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface-soft); }
        .cd-summary .form-control[readonly] { background: var(--color-surface); text-align: right; font-feature-settings: var(--font-feature-numeric); }
        .cd-summary .cd-closing { font-weight: var(--font-weight-medium); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
            <span class="br-head-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
            <div class="me-auto">
                <h4 class="mb-0">{{ $title }}</h4>
                <small class="text-muted">{{ $desc }} <span class="ms-1">· {{ $branch }}</span></small>
            </div>
            <a href="{{ route('admin.business-reports.index') }}" class="btn btn-outline-secondary"><i class="bi bi-grid"></i> All reports</a>
            <button type="button" class="btn btn-outline-danger" id="pdfBtn"><i class="fas fa-file-pdf"></i> PDF</button>
            <button type="button" class="btn btn-outline-success" id="excelBtn"><i class="fas fa-file-excel"></i> Excel</button>
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <form id="brFilters" class="row g-3 align-items-end" novalidate>
                    @if (in_array('range', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="from">From</label>
                            <input type="date" id="from" name="from" class="form-control" value="{{ $defaultRange[0] ?? now()->startOfMonth()->toDateString() }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="to">To</label>
                            <input type="date" id="to" name="to" class="form-control" value="{{ $defaultRange[1] ?? now()->toDateString() }}">
                        </div>
                    @endif
                    @if (in_array('date', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="date">Day</label>
                            <input type="date" id="date" name="date" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                    @endif
                    @if (in_array('as_at', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="as_at">As at</label>
                            <input type="date" id="as_at" name="as_at" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                    @endif
                    @if (in_array('month', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="month">Month</label>
                            <input type="month" id="month" name="month" class="form-control" value="{{ now()->format('Y-m') }}">
                        </div>
                    @endif
                    @if (in_array('group_by', $filters) && $groupBy)
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="group_by">Group by</label>
                            <select id="group_by" name="group_by" class="form-select">
                                @foreach ($groupBy as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if (in_array('return_type', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="return_type">Returns</label>
                            <select id="return_type" name="return_type" class="form-select">
                                <option value="sale">Sales returns</option>
                                <option value="purchase">Purchase returns</option>
                            </select>
                        </div>
                    @endif
                    @if (in_array('measure', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="measure">Show</label>
                            <select id="measure" name="measure" class="form-select">
                                <option value="amount">Sales amount</option>
                                <option value="count">Number of invoices</option>
                            </select>
                        </div>
                    @endif
                    @if (in_array('order_status', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="order_status">Status</label>
                            <select id="order_status" name="order_status" class="form-select">
                                <option value="">All</option>
                                @foreach (['Pending', 'Confirmed', 'Partial', 'Delivered', 'Cancelled'] as $st)<option value="{{ $st }}">{{ $st }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if (in_array('category', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="form-select">
                                <option value="">All categories</option>
                                @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if (in_array('brand', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="brand_id">Brand</label>
                            <select id="brand_id" name="brand_id" class="form-select">
                                <option value="">All brands</option>
                                @foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if (in_array('days', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="days">Not sold for (days)</label>
                            <input type="number" id="days" name="days" class="form-control" value="90" min="1" max="3650">
                        </div>
                    @endif
                    @if (in_array('cover_days', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="cover_days">Cover (days)</label>
                            <input type="number" id="cover_days" name="cover_days" class="form-control" value="30" min="1" max="365">
                        </div>
                    @endif
                    @if (in_array('limit', $filters))
                        <div class="col-6 col-md-2">
                            <label class="form-label" for="limit">Show top</label>
                            <input type="number" id="limit" name="limit" class="form-control" value="50" min="5" max="500">
                        </div>
                    @endif
                    @if (in_array('hide_zero', $filters))
                        <div class="col-6 col-md-2">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="hide_zero" checked>
                                <label class="form-check-label" for="hide_zero">Hide zero stock</label>
                            </div>
                        </div>
                    @endif
                    <div class="col-6 col-md-2">
                        <button type="submit" class="btn btn-primary w-100" id="goBtn"><i class="bi bi-search"></i> Show</button>
                    </div>
                    @if (in_array('range', $filters))
                        <div class="col-12 br-quick d-flex flex-wrap gap-2">
                            <span class="small text-muted">Quick:</span>
                            @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This week', 'month' => 'This month', 'last-month' => 'Last month', 'year' => 'This year'] as $k => $l)
                                <a href="#" data-range="{{ $k }}">{{ $l }}</a>
                            @endforeach
                        </div>
                    @endif
                    <div class="col-12"><div class="text-danger small" id="brError"></div></div>
                </form>
            </div>
        </div>

        <div id="brCards"></div>

        <div class="card acc-card">
            <div class="card-body" id="brBody">
                <div class="text-center text-muted py-5">Loading…</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const BR = {
            data: "{{ route('admin.business-reports.data', $key) }}",
            pdf: "{{ route('admin.business-reports.pdf', $key) }}",
            excel: "{{ route('admin.business-reports.excel', $key) }}",
            closing: "{{ route('admin.business-reports.closing.save') }}",
            closingClear: "{{ route('admin.business-reports.closing.clear') }}",
            budget: "{{ route('admin.business-reports.budget.save') }}"
        };

        function query() {
            const params = {};
            $('#brFilters').find('input[name], select[name]').each(function () {
                if ($(this).val() !== '' && $(this).val() !== null) params[this.name] = $(this).val();
            });
            if ($('#hide_zero').length) params.hide_zero = $('#hide_zero').is(':checked') ? 1 : 0;
            return $.param(params);
        }

        function load() {
            $('#brError').text('');
            $('#brBody').addClass('loading');
            $.ajax({
                url: BR.data + '?' + query(),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // alert(JSON.stringify(response));
                    $('#brCards').html(response.cards);
                    $('#brBody').html(response.html);
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Unable to build the report.';
                    $('#brError').text(msg);
                },
                complete: function () { $('#brBody').removeClass('loading'); }
            });
        }

        function setRange(r) {
            const pad = function (n) { return String(n).padStart(2, '0'); };
            const fmt = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
            const now = new Date();
            let from = new Date(now), to = new Date(now);
            if (r === 'yesterday') { from.setDate(now.getDate() - 1); to = new Date(from); }
            else if (r === 'week') { from.setDate(now.getDate() - ((now.getDay() + 6) % 7)); }
            else if (r === 'month') { from = new Date(now.getFullYear(), now.getMonth(), 1); }
            else if (r === 'last-month') { from = new Date(now.getFullYear(), now.getMonth() - 1, 1); to = new Date(now.getFullYear(), now.getMonth(), 0); }
            else if (r === 'year') { from = new Date(now.getFullYear(), 0, 1); }
            $('#from').val(fmt(from));
            $('#to').val(fmt(to));
            load();
        }

        $(function () {
            $('#brFilters').on('submit', function (e) { e.preventDefault(); load(); });
            $('#brFilters select, #hide_zero').on('change', load);
            $('[data-range]').on('click', function (e) { e.preventDefault(); setRange($(this).data('range')); });
            $('#pdfBtn').on('click', function () { window.open(BR.pdf + '?' + query(), '_blank'); });
            $('#excelBtn').on('click', function () { window.location.href = BR.excel + '?' + query(); });

            // Daily cash closing: save the day (its closing becomes the next day's opening) or clear a saved day.
            $(document).on('submit', '#cashDayForm', function (e) {
                e.preventDefault();
                const $b = $(this).find('button[type=submit]').prop('disabled', true);
                $.ajax({
                    url: BR.closing,
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        load();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to save.');
                    },
                    complete: function () { $b.prop('disabled', false); }
                });
            });
            $(document).on('click', '#cashDayClear', function () {
                const day = $('#cashDayForm [name=closing_date]').val();
                Swal.fire({
                    title: 'Clear the saved report?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Clear',
                    text: 'The days after it will then open from the closing before it.'
                }).then(function (r) {
                    if (!r.isConfirmed) return;
                    $.ajax({
                        url: BR.closingClear,
                        type: 'POST',
                        dataType: 'json',
                        data: { closing_date: day },
                        success: function(response) {
                            // alert(JSON.stringify(response));
                            toastr.success(response.message);
                            load();
                        },
                        error: function(xhr) {
                            // alert(JSON.stringify(xhr));
                            Voucher.ajaxError(xhr, 'Unable to clear.');
                        }
                    });
                });
            });

            // Budget: save the month's amounts.
            $(document).on('submit', '#budgetForm', function (e) {
                e.preventDefault();
                const $b = $(this).find('button[type=submit]').prop('disabled', true);
                $.ajax({
                    url: BR.budget,
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                        load();
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        Voucher.ajaxError(xhr, 'Unable to save.');
                    },
                    complete: function () { $b.prop('disabled', false); }
                });
            });

            load();
        });
    </script>
@endpush
