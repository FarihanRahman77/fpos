@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Stock Ledger
@endsection

@push('styles')
    <style>
        .ledger-block { border: 1px solid var(--admin-border); border-radius: 12px; overflow: hidden; }
        .ledger-head { padding: 10px 14px; background: var(--admin-surface-soft); border-bottom: 1px solid var(--admin-border); }
        .ledger-table { font-size: .85rem; }
        /* beats the global ".table tbody td" (1rem padding, 120px min-width) for a compact ledger */
        .ledger-table tbody td, .ledger-table tfoot td, .ledger-table thead th { min-width: 0; padding: .4rem 12px; }
        .ledger-table td:nth-child(-n+4) { white-space: nowrap; }   /* date, type, reference, party on one line */
        .ledger-table .badge { font-size: .72rem; font-weight: 600; }
        /* dark text on light badges in both themes (dark mode turns .text-dark light) */
        .ledger-table .badge.ledger-badge-ink { color: #1f2937 !important; }
        .ledger-opening td { background: var(--admin-surface-soft); }
        .ledger-total td { font-weight: 700; background: var(--admin-surface-soft); border-top: 2px solid var(--admin-border); }
        #reportBody { min-height: 120px; position: relative; }
        #reportBody.loading { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Stock Ledger</h4>
                <small class="text-muted">Opening balance, every stock movement and running balance per product.</small>
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
                        <label class="form-label" for="product_id">Product</label>
                        <select id="product_id" name="product_id" class="form-select">
                            <option value="">All products</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="variant_id">Variant</label>
                        <select id="variant_id" name="variant_id" class="form-select" disabled>
                            <option value="">All variants</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="type">Movement Type</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">All movements</option>
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">All categories</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="brand_id">Brand</label>
                        <select id="brand_id" name="brand_id" class="form-select">
                            <option value="">All brands</option>
                            @foreach ($brands as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="only_movement" checked>
                            <label class="form-check-label" for="only_movement">Only products with movement</label>
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
                    <div class="icon i1"><i class="fas fa-boxes"></i></div>
                    <div><div class="text-muted small">Products / Variants</div><h5 class="mb-0" id="sumItems">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-arrow-down"></i></div>
                    <div><div class="text-muted small">Total In</div><h5 class="mb-0" id="sumIn">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-arrow-up"></i></div>
                    <div><div class="text-muted small">Total Out</div><h5 class="mb-0" id="sumOut">0</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-warehouse"></i></div>
                    <div><div class="text-muted small">Closing Balance</div><h5 class="mb-0" id="sumClosing">0</h5></div>
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
        const variants = @json($variants);
        const dataUrl = "{{ route('admin.reports.stock-ledger.data') }}";
        const pdfUrl = "{{ route('admin.reports.stock-ledger.pdf') }}";
        const excelUrl = "{{ route('admin.reports.stock-ledger.excel') }}";

        /* Current filters as a query string (used by Generate, PDF and Excel). */
        function filterQuery() {
            const params = {
                from: $('#from').val(),
                to: $('#to').val(),
                product_id: $('#product_id').val(),
                variant_id: $('#variant_id').val(),
                category_id: $('#category_id').val(),
                brand_id: $('#brand_id').val(),
                type: $('#type').val(),
                only_movement: $('#only_movement').is(':checked') ? 1 : 0
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
                    $('#sumItems').text(res.summary.items);
                    $('#sumIn').text(res.summary.in);
                    $('#sumOut').text(res.summary.out);
                    $('#sumClosing').text(res.summary.closing);
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

        function fillVariants(productId) {
            const $v = $('#variant_id').empty().append(new Option('All variants', ''));
            const list = variants.filter(function(v) { return String(v.product_id) === String(productId); });

            list.forEach(function(v) { $v.append(new Option(v.name || ('Variant #' + v.id), v.id)); });
            $v.prop('disabled', list.length === 0).trigger('change.select2');
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
            Voucher.initSelect2($('#product_id, #variant_id, #type, #category_id, #brand_id'));

            $('#product_id').on('change', function() { fillVariants(this.value); });

            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                loadReport();
            });

            $('#resetBtn').on('click', function() {
                $('#filterForm')[0].reset();
                $('#product_id, #type, #category_id, #brand_id').val('').trigger('change.select2');
                fillVariants('');
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
