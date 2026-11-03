@extends('admin.master')
@include('admin.accounts.partials.voucher-assets')

@php
    $m = fn ($v) => number_format((float) $v, 2);
    $maxTotal = max(array_map(fn ($h) => (float) $h['rev']->grand_total, $history) ?: [1]) ?: 1;
    $icons = ['created' => 'fa-plus', 'repriced' => 'fa-tags', 'updated' => 'fa-pen', 'emailed' => 'fa-paper-plane', 'shared' => 'fa-share-alt',
              'downloaded' => 'fa-download', 'status' => 'fa-flag', 'duplicated' => 'fa-copy', 'deleted' => 'fa-trash'];
@endphp

@section('title')
    {{ $generalSetting['company_name'] ?? 'POS' }} - {{ $quotation->quotation_no }}
@endsection

@push('styles')
    <style>
        .q-thumb { width: 48px; height: 48px; border-radius: var(--radius-md); object-fit: cover; background: var(--color-surface-soft); border: 1px solid var(--color-border); }
        .q-thumb-empty { display: inline-flex; align-items: center; justify-content: center; color: var(--color-text-muted); }
        .q-hero { display: flex; flex-wrap: wrap; gap: var(--space-xl); align-items: flex-end; }
        .q-hero .q-amount { font-size: var(--text-display-md); font-weight: var(--font-weight-light); letter-spacing: var(--tracking-display-md); font-feature-settings: var(--font-feature-numeric); line-height: 1; }
        .q-meta dt { color: var(--color-text-muted); font-size: var(--text-caption); font-weight: var(--font-weight-regular); }
        .q-meta dd { margin-bottom: var(--space-sm); }

        /* price history */
        .q-bars { display: flex; align-items: flex-end; gap: var(--space-sm); height: 90px; padding: 0 var(--space-xs); border-bottom: 1px solid var(--color-border); }
        .q-bar { flex: 1; max-width: 46px; border-radius: var(--radius-xs) var(--radius-xs) 0 0; background: var(--color-primary-subdued); position: relative; min-height: 4px; }
        .q-bar.current { background: var(--color-button); }
        .q-bar span { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); font-size: var(--text-micro); color: var(--color-text-muted); white-space: nowrap; }
        .q-bar-labels { display: flex; gap: var(--space-sm); padding: var(--space-xs) var(--space-xs) 0; }
        .q-bar-labels div { flex: 1; max-width: 46px; text-align: center; font-size: var(--text-micro); color: var(--color-text-muted); }

        .q-timeline { position: relative; padding-left: var(--space-xl); }
        .q-timeline::before { content: ""; position: absolute; left: 9px; top: 6px; bottom: 6px; width: 2px; background: var(--color-border); }
        .q-tl-item { position: relative; margin-bottom: var(--space-lg); }
        .q-tl-dot { position: absolute; left: calc(-1 * var(--space-xl) + 2px); top: 2px; width: 16px; height: 16px; border-radius: 50%;
            background: var(--color-surface); border: 3px solid var(--color-primary-subdued); }
        .q-tl-item.current .q-tl-dot { border-color: var(--color-button); }
        .q-rev-card { border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-md); background: var(--color-surface); }
        .q-chip { display: inline-flex; align-items: center; gap: var(--space-xs); border-radius: var(--radius-pill); padding: 2px var(--space-sm);
            font-size: var(--text-caption); margin: 0 var(--space-xs) var(--space-xs) 0; background: var(--color-surface-soft); color: var(--color-text-secondary); }
        .q-chip.up { background: var(--color-danger-soft); color: var(--color-danger); }
        .q-chip.down { background: var(--color-success-soft); color: var(--color-success); }
        .q-chip.added { background: var(--color-accent-soft); color: var(--color-accent-ink); }
        .q-chip.removed { background: var(--color-warning-soft); color: var(--color-warning); text-decoration: line-through; }
        .q-delta.up { color: var(--color-danger); }
        .q-delta.down { color: var(--color-success); }

        .q-log-icon { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
            background: var(--color-accent-soft); color: var(--color-accent-ink); flex: 0 0 auto; font-size: var(--text-caption); }
    </style>
@endpush

@section('content')
    <div class="container-fluid">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">{{ $quotation->quotation_no }} {!! \App\Http\Controllers\Admin\SaleQuotationController::statusBadge($quotation->quotation_status) !!}</h4>
                {!! $convertedLink !!}
                <small class="text-muted">
                    Revision {{ $quotation->revision_no }} · for
                    <a href="{{ route('admin.quotations.customer', $quotation->customer_id) }}">{{ $quotation->customer_name }}</a>
                    @if ($quotation->title) · {{ $quotation->title }} @endif
                </small>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                @if ($quotation->quotation_status === 'Accepted')
                    <button type="button" class="btn btn-success" onclick="QuoteConvert.open('sale')"><i class="fas fa-receipt"></i> Convert to Sale</button>
                    <button type="button" class="btn btn-outline-success" onclick="QuoteConvert.open('order')"><i class="fas fa-truck"></i> Convert to Sale Order</button>
                @endif
                <a href="{{ route('admin.quotations.edit', $quotation->id) }}" class="btn btn-outline-primary"><i class="fas fa-tags"></i> Re-price</a>
                <a href="{{ route('admin.quotations.pdf', $quotation->id) }}" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-file-pdf"></i> PDF</a>
                <button type="button" class="btn btn-outline-success" onclick="Quote.share({{ $quotation->id }})"><i class="fab fa-whatsapp"></i> WhatsApp</button>
                <button type="button" class="btn btn-primary" onclick="Quote.send({{ $quotation->id }})"><i class="fas fa-paper-plane"></i> Send</button>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach (['Accepted' => 'fa-check text-success', 'Rejected' => 'fa-times text-danger', 'Sent' => 'fa-paper-plane', 'Draft' => 'fa-pen', 'Expired' => 'fa-hourglass-end'] as $s => $icon)
                            @if ($s !== $quotation->quotation_status && $quotation->quotation_status !== 'Converted')
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="Quote.setStatus({{ $quotation->id }}, '{{ $s }}')"><i class="fas {{ $icon }} me-2"></i>Mark {{ $s }}</a></li>
                            @endif
                        @endforeach
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('admin.quotations.pdf', [$quotation->id, 'download' => 1]) }}"><i class="fas fa-download me-2"></i>Download PDF</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="Quote.duplicate({{ $quotation->id }})"><i class="fas fa-copy me-2"></i>Duplicate</a></li>
                        <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="Quote.remove({{ $quotation->id }})"><i class="fas fa-trash me-2"></i>Delete</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row g-3">
            {{-- ===== The quotation ===== --}}
            <div class="col-xl-7">
                <div class="card acc-card mb-3">
                    <div class="card-body">
                        <div class="q-hero mb-4">
                            <div>
                                <div class="text-muted small">Total (revision {{ $quotation->revision_no }})</div>
                                <div class="q-amount">{{ $m($quotation->grand_total) }}</div>
                            </div>
                            <dl class="q-meta row mb-0 flex-fill">
                                <div class="col-6 col-md-3"><dt>Date</dt><dd>{{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d M Y') }}</dd></div>
                                <div class="col-6 col-md-3"><dt>Valid until</dt><dd>{{ $quotation->valid_until ? \Carbon\Carbon::parse($quotation->valid_until)->format('d M Y') : '-' }}</dd></div>
                                <div class="col-6 col-md-3"><dt>Sent</dt><dd>{{ $quotation->sent_count ? $quotation->sent_count . '× · ' . \Carbon\Carbon::parse($quotation->sent_at)->format('d M') : 'Not yet' }}</dd></div>
                                <div class="col-6 col-md-3"><dt>Prepared by</dt><dd>{{ $quotation->created_by_name ?: '-' }}</dd></div>
                            </dl>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Discount</th><th class="text-end">Amount</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $l)
                                        <tr>
                                            <td>
                                                <div class="d-flex gap-2 align-items-center">
                                                    @if ($l->image)
                                                        <img src="{{ asset('uploads/products/' . $l->image) }}" class="q-thumb" alt="">
                                                    @else
                                                        <span class="q-thumb q-thumb-empty"><i class="fas fa-box"></i></span>
                                                    @endif
                                                    <div>
                                                        <div class="fw-semibold">{{ $l->item_name }}</div>
                                                        @if ($l->description)<div class="text-muted small">{{ $l->description }}</div>@endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end">{{ rtrim(rtrim(number_format((float) $l->qty, 3), '0'), '.') }}</td>
                                            <td class="text-end">{{ $m($l->unit_price) }}</td>
                                            <td class="text-end">{{ (float) $l->discount ? $m($l->discount) : '-' }}</td>
                                            <td class="text-end fw-semibold">{{ $m($l->line_total) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr><td colspan="4" class="text-end text-muted">Subtotal</td><td class="text-end">{{ $m($quotation->subtotal) }}</td></tr>
                                    @if ((float) $quotation->discount_amount)
                                        <tr><td colspan="4" class="text-end text-muted">Discount</td><td class="text-end">− {{ $m($quotation->discount_amount) }}</td></tr>
                                    @endif
                                    @if ((float) $quotation->tax_amount)
                                        <tr><td colspan="4" class="text-end text-muted">VAT {{ (float) $quotation->tax_percent }}%</td><td class="text-end">{{ $m($quotation->tax_amount) }}</td></tr>
                                    @endif
                                    <tr class="fw-semibold"><td colspan="4" class="text-end">Total</td><td class="text-end">{{ $m($quotation->grand_total) }}</td></tr>
                                </tfoot>
                            </table>
                        </div>

                        @if ($quotation->notes || $quotation->terms)
                            <div class="row g-3 mt-2">
                                @if ($quotation->notes)<div class="col-md-6"><div class="text-muted small mb-1">Note</div><div style="white-space: pre-line">{{ $quotation->notes }}</div></div>@endif
                                @if ($quotation->terms)<div class="col-md-6"><div class="text-muted small mb-1">Terms</div><div class="small" style="white-space: pre-line">{{ $quotation->terms }}</div></div>@endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===== History ===== --}}
            <div class="col-xl-5">
                <div class="card acc-card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="mb-0">Price History</h6>
                            <span class="ms-auto text-muted small">{{ count($history) }} revision(s)</span>
                        </div>

                        @if (count($history) > 1)
                            {{-- totals per revision, oldest to newest --}}
                            <div class="q-bars" aria-hidden="true">
                                @foreach (array_reverse($history) as $h)
                                    <div class="q-bar {{ $h['rev']->revision_no == $quotation->revision_no ? 'current' : '' }}"
                                        style="height: {{ max(6, round((float) $h['rev']->grand_total / $maxTotal * 100)) }}%"
                                        title="Rev {{ $h['rev']->revision_no }}: {{ $m($h['rev']->grand_total) }}"></div>
                                @endforeach
                            </div>
                            <div class="q-bar-labels mb-3" aria-hidden="true">
                                @foreach (array_reverse($history) as $h)<div>R{{ $h['rev']->revision_no }}</div>@endforeach
                            </div>
                        @endif

                        <div class="q-timeline">
                            @foreach ($history as $h)
                                @php $r = $h['rev']; @endphp
                                <div class="q-tl-item {{ $r->revision_no == $quotation->revision_no ? 'current' : '' }}">
                                    <span class="q-tl-dot"></span>
                                    <div class="q-rev-card">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="badge {{ $r->revision_no == $quotation->revision_no ? 'bg-primary' : 'bg-light' }}">Rev {{ $r->revision_no }}</span>
                                            <span class="fw-semibold">{{ $m($r->grand_total) }}</span>
                                            @if ($h['delta'] !== null && abs($h['delta']) > 0.004)
                                                <span class="q-delta small {{ $h['delta'] > 0 ? 'up' : 'down' }}">
                                                    {{ $h['delta'] > 0 ? '▲ +' : '▼ −' }}{{ $m(abs($h['delta'])) }}{{ $h['pct'] !== null ? ' (' . ($h['pct'] > 0 ? '+' : '') . number_format($h['pct'], 1) . '%)' : '' }}
                                                </span>
                                            @endif
                                            <a href="{{ route('admin.quotations.pdf', [$quotation->id, 'revision' => $r->revision_no]) }}" target="_blank"
                                                class="ms-auto small" title="PDF of this revision"><i class="fas fa-file-pdf"></i> PDF</a>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            {{ \Carbon\Carbon::parse($r->created_at)->format('d M Y, h:i A') }}{{ $r->user_name ? ' · ' . $r->user_name : '' }}
                                            @if ($r->reason) · <span class="text-body">{{ $r->reason }}</span>@endif
                                        </div>
                                        @if ($h['changes'])
                                            <div class="mt-2">
                                                @foreach ($h['changes'] as $c)
                                                    <span class="q-chip {{ $c['type'] }}">
                                                        @switch($c['type'])
                                                            @case('up') <i class="fas fa-arrow-up"></i> @break
                                                            @case('down') <i class="fas fa-arrow-down"></i> @break
                                                            @case('added') <i class="fas fa-plus"></i> @break
                                                            @case('removed') <i class="fas fa-minus"></i> @break
                                                        @endswitch
                                                        <span>{{ \Illuminate\Support\Str::limit($c['name'], 32) }}:
                                                            @if (isset($c['old']))
                                                                {{ $c['text'] }} {{ $c['type'] === 'qty' || $c['type'] === 'tax' ? rtrim(rtrim(number_format($c['old'], 3), '0'), '.') : $m($c['old']) }}
                                                                → {{ $c['type'] === 'qty' || $c['type'] === 'tax' ? rtrim(rtrim(number_format($c['new'], 3), '0'), '.') : $m($c['new']) }}
                                                                @if (!empty($c['pct'])) ({{ $c['pct'] > 0 ? '+' : '' }}{{ number_format($c['pct'], 1) }}%)@endif
                                                            @else
                                                                {{ $c['text'] }}
                                                            @endif
                                                        </span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @elseif ($r->revision_no > 1)
                                            <div class="text-muted small mt-2">No line changes.</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card acc-card">
                    <div class="card-body">
                        <h6 class="mb-3">Activity</h6>
                        @forelse ($logs as $log)
                            <div class="d-flex gap-2 mb-3">
                                <span class="q-log-icon"><i class="fas {{ $icons[$log->action] ?? 'fa-circle' }}"></i></span>
                                <div>
                                    <div>{{ $log->message }}</div>
                                    <div class="text-muted small">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') }}{{ $log->user_name ? ' · ' . $log->user_name : '' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted small">No activity yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.sales.quotations.partials.send-modal')

    {{-- Convert an accepted quotation to a sale (now) or a sale order (delivered later). --}}
    <div class="modal fade" id="convertModal" tabindex="-1" aria-labelledby="convertTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="convertForm" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="convertTitle">Convert {{ $quotation->quotation_no }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="kind" id="convert_kind" value="sale">
                        <p class="text-muted small mb-3" id="convert_help"></p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="convert_date"><span class="convert-date-label">Sale date</span> <span class="text-danger">*</span></label>
                                <input type="date" name="date" id="convert_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-md-6 convert-order-only">
                                <label class="form-label" for="convert_delivery">Delivery date</label>
                                <input type="date" name="delivery_date" id="convert_delivery" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="convert_total">Total (as quoted)</label>
                                <input type="text" id="convert_total" class="form-control" value="{{ number_format((float) $quotation->grand_total, 2, '.', '') }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="convert_paid"><span class="convert-paid-label">Paid now</span></label>
                                <input type="number" step="0.01" min="0" max="{{ (float) $quotation->grand_total }}" name="paid_amount" id="convert_paid" class="form-control" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="convert_method">Payment method</label>
                                <select name="payment_method" id="convert_method" class="form-select">
                                    <option value="">Select method</option>
                                    @foreach ($payMethods as $pm)
                                        <option value="{{ $pm->id }}">{{ $pm->account_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="convert_account">Payment account</label>
                                <select name="payment_method_account" id="convert_account" class="form-select">
                                    <option value="">Select method first</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="convert_remarks">Remarks</label>
                                <input type="text" name="remarks" id="convert_remarks" class="form-control" maxlength="500">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success" id="convertSubmit">Convert</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const QuoteConvert = {
            open: function(kind) {
                const isOrder = kind === 'order';
                $('#convert_kind').val(kind);
                $('#convertTitle').text(isOrder ? 'Convert {{ $quotation->quotation_no }} to a Sale Order' : 'Convert {{ $quotation->quotation_no }} to a Sale');
                $('#convert_help').text(isOrder
                    ? 'Creates a sale order with the quoted lines, prices, discounts and VAT. Each delivery later becomes a sale invoice; an advance taken now is applied to those invoices.'
                    : 'Creates a wholesale sale now with the quoted lines, prices, discounts and VAT, and takes the goods out of stock. Anything not paid now is the customer due.');
                $('.convert-date-label').text(isOrder ? 'Order date' : 'Sale date');
                $('.convert-paid-label').text(isOrder ? 'Advance received' : 'Paid now');
                $('.convert-order-only').toggle(isOrder);
                $('#convertSubmit').text(isOrder ? 'Create Sale Order' : 'Create Sale');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('convertModal')).show();
            }
        };

        $(document).ready(function() {
            $('#convert_method').on('change', function() {
                const $account = $('#convert_account');
                if (!$(this).val()) {
                    $account.html('<option value="">Select method first</option>');
                    return;
                }
                $.ajax({
                    url: "{{ route('admin.getCoaChilds') }}",
                    type: 'GET',
                    dataType: 'html',
                    data: { parent_id: $(this).val() },
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        $account.html(response);
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error('Could not load payment accounts.');
                    }
                });
            });

            $('#convertForm').on('submit', function(e) {
                e.preventDefault();
                const $btn = $('#convertSubmit').prop('disabled', true);
                $.ajax({
                    url: "{{ route('admin.quotations.convert', $quotation->id) }}",
                    type: 'POST',
                    dataType: 'json',
                    data: $(this).serialize(),
                    success: function(response) {
                        // alert(JSON.stringify(response));
                        if (response.status) {
                            toastr.success(response.message);
                            if (response.pdf_url) window.open(response.pdf_url, '_blank');
                            window.location.href = response.redirect;
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        // alert(JSON.stringify(xhr));
                        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Unable to convert the quotation.');
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            @if ($quotation->quotation_status === 'Accepted' && in_array(request('convert'), ['sale', 'order'], true))
                QuoteConvert.open(@json(request('convert')));
            @endif
        });
    </script>
@endpush

