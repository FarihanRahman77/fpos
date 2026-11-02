@php
    $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
    $bal = $balance_text;
    $badge = [
        'Receivable' => 'bg-warning ledger-badge-ink',
        'Payable'    => 'bg-warning ledger-badge-ink',
        'Advance'    => 'bg-info ledger-badge-ink',
        'Settled'    => 'bg-success',
    ];
@endphp

@forelse ($items as $item)
    @php $label = $balance_label($item['closing']); @endphp
    <div class="ledger-block mb-4">
        <div class="ledger-head d-flex flex-wrap align-items-center gap-2">
            <div>
                <div class="fw-semibold">
                    {{ $item['name'] }}
                    <span class="badge {{ $badge[$label] }} ms-1">{{ $label }}</span>
                </div>
                <div class="small text-muted">
                    @if ($item['code']) {{ $item['code'] }} · @endif
                    {{ $item['type'] }}
                    @if ($item['phone']) · {{ $item['phone'] }} @endif
                    @if ($item['address']) · {{ \Illuminate\Support\Str::limit($item['address'], 60) }} @endif
                </div>
            </div>
            <div class="ms-auto d-flex flex-wrap column-gap-3 row-gap-1 small" style="white-space: nowrap">
                <span>Opening <strong>{{ $bal($item['opening']) }}</strong></span>
                <span>Debit <strong>{{ number_format($item['dr'], 2) }}</strong></span>
                <span>Credit <strong>{{ number_format($item['cr'], 2) }}</strong></span>
                <span>Closing <strong>{{ $bal($item['closing']) }}</strong></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 acc-table ledger-table">
                <thead>
                    <tr>
                        <th width="10%">Date</th>
                        <th width="11%">Voucher No</th>
                        <th width="15%">Particulars</th>
                        <th width="12%">Reference</th>
                        <th>Remarks</th>
                        <th class="text-end" width="10%">Debit</th>
                        <th class="text-end" width="10%">Credit</th>
                        <th class="text-end" width="12%">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="ledger-opening">
                        <td colspan="7" class="fst-italic text-muted">Balance brought forward (before {{ $from->format('d M Y') }})</td>
                        <td class="text-end fw-semibold">{{ $bal($item['opening']) }}</td>
                    </tr>
                    @forelse ($item['rows'] as $r)
                        <tr>
                            <td class="text-nowrap">{{ $r['date']->format('d M Y') }}</td>
                            <td>{{ $r['voucher'] ?: '-' }}</td>
                            <td>{{ $r['particular'] }}</td>
                            <td>{{ $r['reference'] ?: '-' }}</td>
                            <td class="small text-muted">{{ $r['note'] }}</td>
                            <td class="text-end">{{ $m($r['dr']) }}</td>
                            <td class="text-end">{{ $m($r['cr']) }}</td>
                            <td class="text-end fw-semibold text-nowrap">{{ $bal($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted fst-italic">No transactions in this period</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="ledger-total">
                        <td colspan="5" class="text-end">Total / Closing balance</td>
                        <td class="text-end">{{ number_format($item['dr'], 2) }}</td>
                        <td class="text-end">{{ number_format($item['cr'], 2) }}</td>
                        <td class="text-end text-nowrap">{{ $bal($item['closing']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@empty
    <div class="text-center text-muted py-5">
        <i class="fas fa-user-slash fa-2x mb-2 d-block"></i>
        No {{ $ledger }} transactions match these filters.
    </div>
@endforelse
