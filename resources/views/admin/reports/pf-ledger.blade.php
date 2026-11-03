@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - PF Ledger
@endsection

@push('styles')
    <style>
        .pf-table { font-size: var(--text-caption); }
        .pf-table tbody td, .pf-table tfoot td, .pf-table thead th { min-width: 0; padding: .4rem 12px; }
        .pf-opening td { background: var(--admin-surface-soft); font-style: italic; }
        .pf-period td { background: var(--admin-surface-soft); font-weight: var(--font-weight-medium); color: var(--color-accent); }
        .pf-total td { font-weight: var(--font-weight-medium); }
        .pf-grand td { font-weight: var(--font-weight-medium); background: var(--admin-surface-soft); border-top: 2px solid var(--admin-border); }
        #reportBody { min-height: 120px; position: relative; }
        #reportBody.loading { opacity: .45; pointer-events: none; }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">PF Ledger</h4>
                <small class="text-muted">
                    Monthly provident fund of an employee, grouped by joining / increment / salary change. The balance counts PF deducted in processed salary only; months not processed are shown as projected.
                    @if ($payroll->pf_enabled !== 'Yes')
                        <span class="text-danger">PF deduction is currently off in Payroll Setup.</span>
                    @endif
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
                    <div class="col-md-4">
                        <label class="form-label" for="employee_id">Employee <span class="text-danger">*</span></label>
                        <select id="employee_id" name="employee_id" class="form-select">
                            @if ($selected)
                                <option value="{{ $selected->id }}" selected>{{ $selected->name }} ({{ $selected->employee_code }})</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="from">From</label>
                        <input type="month" id="from" name="from" class="form-control" title="Empty = from joining">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="to">To</label>
                        <input type="month" id="to" name="to" class="form-control" title="Empty = this month">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill" id="generateBtn">
                            <i class="fas fa-search"></i> Generate
                        </button>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Leave From / To empty for the whole service period.</small>
                        <div class="text-danger small" id="filterError"></div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Summary --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i1"><i class="fas fa-flag"></i></div>
                    <div><div class="text-muted small">Opening</div><h5 class="mb-0" id="sumOpening">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i4"><i class="fas fa-piggy-bank"></i></div>
                    <div><div class="text-muted small">Deducted in Period</div><h5 class="mb-0" id="sumTotal">0.00</h5>
                        <div class="text-muted small">Projected (not processed): <span id="sumProjected">0.00</span></div></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i3"><i class="fas fa-balance-scale"></i></div>
                    <div><div class="text-muted small">Closing Balance</div><h5 class="mb-0" id="sumClosing">0.00</h5></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                    <div class="icon i2"><i class="fas fa-level-up-alt"></i></div>
                    <div><div class="text-muted small">Annual Increments</div><h5 class="mb-0" id="sumIncrements">0</h5></div>
                </div></div>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body" id="reportBody">
                <div class="text-center text-muted py-5">Select an employee and press <strong>Generate</strong>.</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const dataUrl = "{{ route('admin.reports.pf-ledger.data') }}";
        const pdfUrl = "{{ route('admin.reports.pf-ledger.pdf') }}";
        const excelUrl = "{{ route('admin.reports.pf-ledger.excel') }}";

        function filterQuery() {
            const params = { employee_id: $('#employee_id').val(), from: $('#from').val(), to: $('#to').val() };
            Object.keys(params).forEach(function(k) { if (!params[k]) delete params[k]; });

            return $.param(params);
        }

        function validFilters() {
            $('#filterError').text($('#employee_id').val() ? '' : 'Select the employee.');
            return !!$('#employee_id').val();
        }

        function loadReport() {
            if (!validFilters()) return;

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
                        toastr.error(response.message || 'Unable to build the PF ledger.');
                        return;
                    }

                    $('#reportBody').html(response.html);
                    $('#sumOpening').text(response.summary.opening);
                    $('#sumTotal').text(response.summary.total);
                    $('#sumProjected').text(response.summary.projected || '0.00');
                    $('#sumClosing').text(response.summary.closing);
                    $('#sumIncrements').text(response.summary.increments);
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    if (xhr.status === 422 && xhr.responseJSON) {
                        $('#filterError').text(xhr.responseJSON.message);
                    } else {
                        Voucher.ajaxError(xhr, 'Unable to build the PF ledger.');
                    }
                },
                complete: function() {
                    $('#reportBody').removeClass('loading');
                    $btn.prop('disabled', false).html('<i class="fas fa-search"></i> Generate');
                }
            });
        }

        $(document).ready(function() {
            $('#employee_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Search by name or ID',
                ajax: {
                    url: "{{ route('admin.employee.search') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { q: params.term || '' }; }
                }
            }).on('change', loadReport);

            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                loadReport();
            });

            $('#pdfBtn').on('click', function() {
                if (validFilters()) window.open(pdfUrl + '?' + filterQuery(), '_blank');
            });

            $('#excelBtn').on('click', function() {
                if (validFilters()) window.location.href = excelUrl + '?' + filterQuery();
            });

            if ($('#employee_id').val()) loadReport();
        });
    </script>
@endpush
