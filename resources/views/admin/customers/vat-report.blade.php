@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - VAT Report
@endsection

@push('styles')
    <style>
        .vat-table td, .vat-table th { min-width: 0; padding: .45rem 10px; font-size: var(--text-caption); vertical-align: middle; }
        .vat-table .num { text-align: right; white-space: nowrap; font-feature-settings: var(--font-feature-numeric); }
        .vat-table tfoot td { font-weight: var(--font-weight-medium); background: var(--color-surface-soft); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">VAT Report</h4>
                <small class="text-muted">Output VAT on sales less sale returns, minus input VAT paid on purchases = VAT payable. <span id="branchNote"></span></small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <input type="date" id="from" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}" aria-label="From">
                <input type="date" id="to" class="form-control" value="{{ now()->toDateString() }}" aria-label="To">
                <button type="button" class="btn btn-primary" id="go"><i class="bi bi-search"></i> Show</button>
                <button type="button" class="btn btn-outline-success" id="excel"><i class="fas fa-file-excel"></i> Excel</button>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card acc-stat"><div class="card-body"><div class="text-muted small">Output VAT (sales less returns)</div><h5 class="mb-0" id="out">0.00</h5><small class="text-muted">on <span id="taxSales">0.00</span> · returns −<span id="retVat">0.00</span></small></div></div></div>
            <div class="col-md-4"><div class="card acc-stat"><div class="card-body"><div class="text-muted small">Input VAT (purchases)</div><h5 class="mb-0" id="in">0.00</h5><small class="text-muted">on <span id="taxPurch">0.00</span></small></div></div></div>
            <div class="col-md-4"><div class="card acc-stat"><div class="card-body"><div class="text-muted small">VAT payable</div><h5 class="mb-0" id="pay">0.00</h5></div></div></div>
        </div>

        <div class="row g-3">
            <div class="col-xl-6">
                <div class="card acc-card"><div class="card-body">
                    <h6>Sales (output VAT)</h6>
                    <div class="table-responsive"><table class="table table-bordered vat-table mb-0">
                        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="num">Taxable</th><th class="num">VAT</th><th class="num">Total</th></tr></thead>
                        <tbody id="salesRows"></tbody><tfoot id="salesFoot"></tfoot>
                    </table></div>
                </div></div>
            </div>
            <div class="col-xl-6">
                <div class="card acc-card"><div class="card-body">
                    <h6>Sale returns (VAT given back)</h6>
                    <div class="table-responsive"><table class="table table-bordered vat-table mb-0">
                        <thead><tr><th>Return (invoice)</th><th>Date</th><th>Customer</th><th class="num">Taxable</th><th class="num">VAT</th><th class="num">Total</th></tr></thead>
                        <tbody id="retRows"></tbody><tfoot id="retFoot"></tfoot>
                    </table></div>
                </div></div>
            </div>
            <div class="col-xl-6">
                <div class="card acc-card"><div class="card-body">
                    <h6>Purchases (input VAT)</h6>
                    <div class="table-responsive"><table class="table table-bordered vat-table mb-0">
                        <thead><tr><th>Invoice</th><th>Date</th><th>Supplier</th><th class="num">Taxable</th><th class="num">VAT</th><th class="num">Total</th></tr></thead>
                        <tbody id="purchRows"></tbody><tfoot id="purchFoot"></tfoot>
                    </table></div>
                </div></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const esc = Voucher.esc, money = Voucher.money;

        function rows(list) {
            return list.length ? list.map(function(r) {
                return '<tr><td class="fw-semibold">' + esc(r.ref) + '</td><td>' + esc(r.date) + '</td><td>' + esc(r.party) + '</td><td class="num">' + money(r.taxable) +
                    '</td><td class="num">' + money(r.vat) + '</td><td class="num">' + money(r.total) + '</td></tr>';
            }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">None in this period.</td></tr>';
        }
        function foot(taxable, vat) { return '<tr><td colspan="3">Total</td><td class="num">' + money(taxable) + '</td><td class="num">' + money(vat) + '</td><td></td></tr>'; }

        function load() {
            $.ajax({
                url: "{{ route('admin.customers.vat.data') }}",
                type: 'GET',
                dataType: 'json',
                data: { from: $('#from').val(), to: $('#to').val() },
                success: function(response) {
                    // alert(JSON.stringify(response));
                    $('#out').text(money(response.output));
                    $('#in').text(money(response.input));
                    $('#pay').text(money(response.payable)).toggleClass('text-success', response.payable < 0);
                    $('#taxSales').text(money(response.taxable_sales));
                    $('#taxPurch').text(money(response.taxable_purchases));
                    $('#branchNote').text('Branch: ' + response.branch + '.');
                    $('#salesRows').html(rows(response.sales));
                    $('#salesFoot').html(foot(response.taxable_sales + response.taxable_returns, response.sales_vat));
                    $('#retVat').text(money(response.returns_vat));
                    $('#retRows').html(rows(response.returns));
                    $('#retFoot').html(foot(-response.taxable_returns, -response.returns_vat));
                    $('#purchRows').html(rows(response.purchases));
                    $('#purchFoot').html(foot(response.taxable_purchases, response.input));
                },
                error: function(xhr) {
                    // alert(JSON.stringify(xhr));
                    Voucher.ajaxError(xhr, 'Unable to build the report.');
                }
            });
        }

        $(document).ready(function() {
            $('#go').on('click', load);
            $('#excel').on('click', function() {
                window.location.href = "{{ route('admin.customers.vat.excel') }}?" + $.param({ from: $('#from').val(), to: $('#to').val() });
            });
            load();
        });
    </script>
@endpush
