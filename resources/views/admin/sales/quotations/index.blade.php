@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - Quotations
@endsection

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Sale Quotations</h4>
                <small class="text-muted">Price offers for customers. Every re-price keeps the old version, so each quotation has its own price history.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('admin.quotations.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach ([['sumTotal', 'Quotations', 'fas fa-file-alt', 'i1'], ['sumOpen', 'Open (Draft / Sent)', 'fas fa-hourglass-half', 'i3'],
                       ['sumAccepted', 'Accepted', 'fas fa-handshake', 'i4'], ['sumValue', 'Open Value', 'fas fa-coins', 'i2']] as [$id, $label, $icon, $cls])
                <div class="col-6 col-lg-3">
                    <div class="card acc-stat"><div class="card-body d-flex align-items-center gap-3">
                        <div class="icon {{ $cls }}"><i class="{{ $icon }}"></i></div>
                        <div><div class="text-muted small">{{ $label }}</div><h5 class="mb-0" id="{{ $id }}">0</h5></div>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="card acc-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="filterCustomer">Customer</label>
                        <select id="filterCustomer" class="form-select">
                            @if ($selected)<option value="{{ $selected->id }}" selected>{{ $selected->name }}</option>@endif
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="filterStatus">Status</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">All</option>
                            @foreach ($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="filterFrom">From</label>
                        <input type="date" id="filterFrom" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="filterTo">To</label>
                        <input type="date" id="filterTo" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-fill" id="applyFilters"><i class="fas fa-filter"></i> Filter</button>
                        <button type="button" class="btn btn-outline-secondary" id="customerLogBtn" title="Open the customer's quotation log" disabled><i class="fas fa-stream"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card acc-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="quoteTable" class="table table-hover align-middle w-100 acc-table" data-page-length="25" data-order="[]" data-auto-width="false">
                        <thead>
                            <tr>
                                <th data-orderable="false" width="4%">SL.</th>
                                <th>Quotation</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th data-orderable="false" width="6%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('admin.sales.quotations.partials.send-modal')
@endsection

@push('scripts')
    <script>
        const listUrl = "{{ route('admin.quotations.list') }}";

        $(document).ready(function() {
            const params = function() {
                return $.param({
                    customer_id: $('#filterCustomer').val() || '', quotation_status: $('#filterStatus').val(),
                    from: $('#filterFrom').val(), to: $('#filterTo').val()
                });
            };

            $('#filterCustomer').select2({
                theme: 'bootstrap-5', width: '100%', placeholder: 'All customers', allowClear: true,
                ajax: { url: "{{ route('admin.quotations.customers') }}", dataType: 'json', delay: 250, data: function(p) { return { q: p.term || '' }; } }
            }).on('change', function() { $('#customerLogBtn').prop('disabled', !this.value); });
            $('#customerLogBtn').prop('disabled', !$('#filterCustomer').val());
            Voucher.initSelect2($('#filterStatus'));

            $('#quoteTable').on('xhr.dt', function(e, settings, json) {
                if (!json || !json.summary) return;
                $('#sumTotal').text(json.summary.total);
                $('#sumOpen').text(json.summary.open);
                $('#sumAccepted').text(json.summary.accepted);
                $('#sumValue').text(json.summary.value);
            });
            Voucher.keepSerial($('#quoteTable'));
            Quote.table = $('#quoteTable').DataTable({ ajax: listUrl + '?' + params(), processing: true });
            Quote.reload = function() { Quote.table.ajax.url(listUrl + '?' + params()).load(null, false); };

            $('#applyFilters').on('click', Quote.reload);
            $('#customerLogBtn').on('click', function() {
                window.location.href = "{{ url('admin/quotations/customer') }}/" + $('#filterCustomer').val();
            });
        });
    </script>
@endpush
