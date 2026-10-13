@php
    $q = fn ($v) => $v == 0 ? '-' : rtrim(rtrim(number_format($v, 3), '0'), '.');
    $badge = [
        'Opening Stock'   => 'bg-secondary',
        'Purchase'        => 'bg-success',
        'Purchase Return' => 'bg-warning ledger-badge-ink',
        'Sale'            => 'bg-primary',
        'Sale Return'     => 'bg-info ledger-badge-ink',
        'Order'           => 'bg-dark',
    ];
@endphp

@forelse ($items as $item)
    <div class="ledger-block mb-4">
        <div class="ledger-head d-flex flex-wrap align-items-center gap-2">
            <div>
                <div class="fw-semibold">{{ $item['product'] }}</div>
                <div class="small text-muted">
                    @if ($item['sku']) SKU: {{ $item['sku'] }} @endif
                    @if ($item['category']) · {{ $item['category'] }} @endif
                    @if ($item['brand']) · {{ $item['brand'] }} @endif
                    @if ($item['unit']) · Unit: {{ $item['unit'] }} @endif
                </div>
            </div>
            <div class="ms-auto d-flex gap-3 small text-nowrap">
                <span>Opening <strong>{{ $q($item['opening']) }}</strong></span>
                <span class="text-success">In <strong>{{ $q($item['in']) }}</strong></span>
                <span class="text-danger">Out <strong>{{ $q($item['out']) }}</strong></span>
                <span>Closing <strong>{{ $q($item['closing']) }}</strong></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 acc-table ledger-table">
                <thead>
                    <tr>
                        <th width="14%">Date</th>
                        <th width="13%">Type</th>
                        <th width="12%">Reference</th>
                        <th>Party</th>
                        <th>Remarks</th>
                        <th class="text-end" width="9%">In</th>
                        <th class="text-end" width="9%">Out</th>
                        <th class="text-end" width="10%">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="ledger-opening">
                        <td colspan="7" class="fst-italic text-muted">Opening balance (before {{ $from->format('d M Y') }})</td>
                        <td class="text-end fw-semibold">{{ $q($item['opening']) }}</td>
                    </tr>
                    @forelse ($item['rows'] as $r)
                        <tr>
                            <td class="text-nowrap">{{ $r['date']->format('d M Y') }} <span class="text-muted small">{{ $r['date']->format('h:i A') }}</span></td>
                            <td><span class="badge {{ $badge[$r['type_key']] ?? 'bg-secondary' }}">{{ $r['type'] }}</span></td>
                            <td>{{ $r['reference'] ?: '-' }}</td>
                            <td>{{ $r['party'] ?: '-' }}</td>
                            <td class="small text-muted">{{ $r['remarks'] ?: '' }}</td>
                            <td class="text-end text-success">{{ $q($r['in']) }}</td>
                            <td class="text-end text-danger">{{ $q($r['out']) }}</td>
                            <td class="text-end fw-semibold {{ $r['balance'] < 0 ? 'text-danger' : '' }}">{{ $q($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted fst-italic">No movement in this period</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="ledger-total">
                        <td colspan="5" class="text-end">Total / Closing balance</td>
                        <td class="text-end text-success">{{ $q($item['in']) }}</td>
                        <td class="text-end text-danger">{{ $q($item['out']) }}</td>
                        <td class="text-end {{ $item['closing'] < 0 ? 'text-danger' : '' }}">{{ $q($item['closing']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@empty
    <div class="text-center text-muted py-5">
        <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
        No stock movement matches these filters.
    </div>
@endforelse
