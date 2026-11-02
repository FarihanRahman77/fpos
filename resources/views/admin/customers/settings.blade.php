@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - VAT, Loyalty & Credit
@endsection

@push('styles')
    <style>
        .prog-card h6 { display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-xs); }
        .prog-card h6 i { color: var(--color-accent); }
        .prog-help { font-size: var(--text-caption); color: var(--color-text-muted); margin-bottom: var(--space-lg); }
        .prog-example { padding: var(--space-md); border-radius: var(--radius-md); background: var(--color-accent-soft); color: var(--color-accent-ink); font-size: var(--text-caption); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">VAT, Loyalty &amp; Credit</h4>
                <small class="text-muted">Rules the POS and sales screens apply to every sale.</small>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('admin.customers.loyalty') }}" class="btn btn-outline-secondary"><i class="bi bi-stars"></i> Customer points</a>
                <a href="{{ route('admin.customers.vat') }}" class="btn btn-outline-secondary"><i class="bi bi-percent"></i> VAT report</a>
            </div>
        </div>

        <form id="progForm" autocomplete="off" novalidate>
            <div class="row g-3">
                <div class="col-xl-4">
                    <div class="card acc-card prog-card h-100"><div class="card-body">
                        <h6><i class="bi bi-percent"></i> VAT</h6>
                        <p class="prog-help">Output VAT on sales (rate per product, else this default) and input VAT on purchases.</p>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="vat_enabled" id="vat_enabled" value="1" @checked($s['vat_enabled'] === '1')>
                            <label class="form-check-label" for="vat_enabled">Charge VAT on sales</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="vat_rate">Default VAT rate (%)</label>
                            <input type="number" name="vat_rate" id="vat_rate" class="form-control" min="0" max="100" step="0.01" value="{{ $s['vat_rate'] }}">
                            <div class="text-danger small field-error"></div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="vat_inclusive" id="vat_inclusive" value="1" @checked($s['vat_inclusive'] === '1')>
                            <label class="form-check-label" for="vat_inclusive">Prices already include VAT</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="vat_purchase_enabled" id="vat_purchase_enabled" value="1" @checked($s['vat_purchase_enabled'] === '1')>
                            <label class="form-check-label" for="vat_purchase_enabled">Record input VAT on purchases</label>
                        </div>
                        <div class="prog-example" id="vatExample"></div>
                    </div></div>
                </div>

                <div class="col-xl-4">
                    <div class="card acc-card prog-card h-100"><div class="card-body">
                        <h6><i class="bi bi-stars"></i> Loyalty points</h6>
                        <p class="prog-help">Customers earn points on what they buy and spend them as money off at the POS.</p>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="loyalty_enabled" id="loyalty_enabled" value="1" @checked($s['loyalty_enabled'] === '1')>
                            <label class="form-check-label" for="loyalty_enabled">Run the loyalty program</label>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="loyalty_earn_amount">For every (spend)</label>
                                <input type="number" name="loyalty_earn_amount" id="loyalty_earn_amount" class="form-control" min="1" step="0.01" value="{{ $s['loyalty_earn_amount'] }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="loyalty_earn_points">Earn points</label>
                                <input type="number" name="loyalty_earn_points" id="loyalty_earn_points" class="form-control" min="0.01" step="0.01" value="{{ $s['loyalty_earn_points'] }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="loyalty_point_value">1 point is worth</label>
                                <input type="number" name="loyalty_point_value" id="loyalty_point_value" class="form-control" min="0.01" step="0.01" value="{{ $s['loyalty_point_value'] }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="loyalty_min_redeem">Redeem at least</label>
                                <input type="number" name="loyalty_min_redeem" id="loyalty_min_redeem" class="form-control" min="0" step="1" value="{{ $s['loyalty_min_redeem'] }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="loyalty_max_percent">Points can pay at most (% of a sale)</label>
                                <input type="number" name="loyalty_max_percent" id="loyalty_max_percent" class="form-control" min="1" max="100" step="1" value="{{ $s['loyalty_max_percent'] }}">
                            </div>
                        </div>
                        <div class="prog-example" id="loyaltyExample"></div>
                    </div></div>
                </div>

                <div class="col-xl-4">
                    <div class="card acc-card prog-card h-100"><div class="card-body">
                        <h6><i class="bi bi-shield-check"></i> Credit limit</h6>
                        <p class="prog-help">Set each customer's limit in Customers (or on the Customer points page). A sale that would take their due over it is…</p>
                        <div class="mb-3">
                            @foreach (['block' => 'Blocked — collect more payment or raise the limit', 'warn' => 'Allowed with a warning', 'off' => 'Not checked'] as $k => $label)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="credit_mode" id="credit_{{ $k }}" value="{{ $k }}" @checked($s['credit_mode'] === $k)>
                                    <label class="form-check-label" for="credit_{{ $k }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <p class="prog-help mb-0">Customers with no limit (0) are never checked.</p>
                    </div></div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <span class="text-danger small me-3 align-self-center" id="prog_error"></span>
                <button type="submit" class="btn btn-primary" id="progSave">Save settings</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        const money = Voucher.money;

        function examples() {
            const rate = parseFloat($('#vat_rate').val()) || 0, inc = $('#vat_inclusive').is(':checked');
            $('#vatExample').text(!$('#vat_enabled').is(':checked') ? 'VAT is off.' : (inc
                ? 'A 1,150.00 price holds ' + money(1150 - 1150 / (1 + rate / 100)) + ' VAT; the customer pays 1,150.00.'
                : 'A 1,000.00 sale adds ' + money(1000 * rate / 100) + ' VAT; the customer pays ' + money(1000 * (1 + rate / 100)) + '.'));
            const per = parseFloat($('#loyalty_earn_amount').val()) || 0, pts = parseFloat($('#loyalty_earn_points').val()) || 0,
                val = parseFloat($('#loyalty_point_value').val()) || 0;
            $('#loyaltyExample').text(!$('#loyalty_enabled').is(':checked') ? 'Loyalty is off.' : (per
                ? 'A 5,000.00 sale earns ' + Math.floor(5000 / per) * pts + ' points, worth ' + money(Math.floor(5000 / per) * pts * val) + ' off a later sale.' : ''));
        }

        $(document).ready(function() {
            $('#progForm :input').on('input change', examples);
            examples();
            $('#progForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#progSave').prop('disabled', true);
                Voucher.clearErrors($(this));
                $('#prog_error').text('');
                $.ajax({
                    url: "{{ route('admin.customers.settings.save') }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        toastr.success(response.message);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) Voucher.showErrors($('#progForm'), xhr.responseJSON.errors, $('#prog_error'));
                        else Voucher.ajaxError(xhr, 'Unable to save.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });
        });
    </script>
@endpush
