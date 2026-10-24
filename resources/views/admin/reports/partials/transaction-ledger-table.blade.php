@php
    $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
    $badge = [
        'Walk-In'                    => 'bg-primary',
        'Wholesale'                  => 'bg-primary',
        'Sale Order'                 => 'bg-primary',
        'Walk-In Sale Return'        => 'bg-info ledger-badge-ink',
        'Wholesale Return'           => 'bg-info ledger-badge-ink',
        'Walk-In Payment Receive'    => 'bg-success',
        'Wholesale Payment Receive'  => 'bg-success',
        'Sale Order Payment Receive' => 'bg-success',
        'Payment Receive'            => 'bg-success',
        'Purchase'                   => 'bg-warning ledger-badge-ink',
        'Purchase Return'            => 'bg-info ledger-badge-ink',
        'Purchase Return Refund'     => 'bg-success',
        'Purchase Payment'           => 'bg-danger',
        'Payment'                    => 'bg-danger',
        'Bill Payment'               => 'bg-danger',
        'Expense'                    => 'bg-danger',
        'Bill'                       => 'bg-warning ledger-badge-ink',
        'Discount'                   => 'bg-secondary',
        'Journal'                    => 'bg-dark',
        'Amount Transfer'            => 'bg-dark',
    ];
@endphp

<div class="table-responsive">
    <table class="table table-sm align-middle mb-0 acc-table ledger-table">
        <thead>
            <tr>
                <th width="4%">SL</th>
                <th width="12%">Date &amp; Time</th>
                <th width="9%">Voucher No</th>
                <th width="13%">Type</th>
                <th>Party</th>
                <th width="9%">Reference</th>
                <th>Account / Method</th>
                <th>Remarks</th>
                <th class="text-end" width="9%">Debit</th>
                <th class="text-end" width="9%">Credit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                <tr>
                    <td class="text-muted">{{ $i + 1 }}</td>
                    <td class="text-nowrap">{{ $r->datetime->format('d M Y') }} <span class="text-muted small">{{ $r->datetime->format('h:i:s A') }}</span></td>
                    <td>{{ $r->voucher ?: '-' }}</td>
                    <td><span class="badge {{ $badge[$r->type_key] ?? 'bg-secondary' }}">{{ $r->type }}</span></td>
                    <td>
                        {{ $r->party ?: '-' }}
                        @if ($r->party_type) <div class="small text-muted">{{ $r->party_type }}</div> @endif
                    </td>
                    <td>{{ $r->reference ?: '-' }}</td>
                    <td>{{ $r->account ?: '-' }}</td>
                    <td class="small text-muted">{{ $r->remarks }}</td>
                    <td class="text-end">{{ $m($r->dr) }}</td>
                    <td class="text-end">{{ $m($r->cr) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                        <i class="fas fa-receipt fa-2x mb-2 d-block"></i>
                        No transactions in this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="ledger-total">
                    <td colspan="8" class="text-end">Total ({{ $totals['entries'] }} entries)</td>
                    <td class="text-end">{{ number_format($totals['dr'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['cr'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

@if ($by_type->isNotEmpty())
    <h6 class="mt-4 mb-2">Summary by type</h6>
    <div class="table-responsive" style="max-width: 640px;">
        <table class="table table-sm align-middle mb-0 acc-table ledger-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th class="text-end">Entries</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($by_type as $t)
                    <tr>
                        <td>{{ $t['type'] }}</td>
                        <td class="text-end">{{ $t['count'] }}</td>
                        <td class="text-end">{{ $m($t['dr']) }}</td>
                        <td class="text-end">{{ $m($t['cr']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="ledger-total">
                    <td>Total</td>
                    <td class="text-end">{{ $totals['entries'] }}</td>
                    <td class="text-end">{{ number_format($totals['dr'], 2) }}</td>
                    <td class="text-end">{{ number_format($totals['cr'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
