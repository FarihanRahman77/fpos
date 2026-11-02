@php
    $m = fn ($v) => number_format((float) $v, 2);
    $b = fn ($v) => (float) $v == 0 ? '' : number_format((float) $v, 2);
@endphp

@foreach ($methods as $sec)
    @php $label = $sec['method']; @endphp
    <section class="tl-section">
        <h6 class="tl-band">{{ $label }} Report</h6>
        <div class="table-responsive">
            <table class="table table-bordered ledger-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 48px;">SL</th>
                        <th>Particulars</th>
                        <th>Remarks</th>
                        <th>Voucher Type</th>
                        <th class="text-end">{{ $label }} In</th>
                        <th class="text-end">{{ $label }} Out</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sec['rows'] as $i => $r)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <div>{{ $r['particulars'] }}</div>
                                <div class="tl-sub">{{ $r['datetime']->format('d M Y h:i A') }}{{ $r['refs'] ? ' · ' . $r['refs'] : '' }}</div>
                            </td>
                            <td>
                                <div>{{ $r['account'] }}</div>
                                @if ($r['remarks'])<div class="tl-sub">{{ $r['remarks'] }}</div>@endif
                            </td>
                            <td class="text-nowrap">{{ $r['type'] }}</td>
                            <td class="text-end tl-num tl-in">{{ $b($r['in']) }}</td>
                            <td class="text-end tl-num tl-out">{{ $b($r['out']) }}</td>
                            <td class="text-end tl-num">{{ $m($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No {{ strtolower($label) }} transactions in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="tl-summary">
            <dl>
                <div><dt>Today {{ $label }} In</dt><dd class="tl-in">{{ $m($sec['in']) }}</dd></div>
                <div><dt>Today {{ $label }} Out</dt><dd class="tl-out">{{ $m($sec['out']) }}</dd></div>
                <div><dt>Today {{ $label }} Closing Balance</dt><dd>{{ $m($sec['period']) }}</dd></div>
                <div><dt>Net {{ $label }} Closing Balance</dt><dd>{{ $m($sec['closing']) }}</dd></div>
            </dl>
            <dl>
                <div><dt>{{ $label }} Opening Balance</dt><dd>{{ $m($sec['opening']) }}</dd></div>
                <div><dt>Today {{ $label }} Closing Balance</dt><dd>{{ $m($sec['period']) }}</dd></div>
                <div class="tl-strong"><dt>Net {{ $label }} Closing Balance</dt><dd>{{ $m($sec['closing']) }}</dd></div>
            </dl>
        </div>
    </section>
@endforeach

<section class="tl-section mb-0">
    <h6 class="tl-band">Closing Summary</h6>
    <div class="table-responsive">
        <table class="table table-bordered ledger-table mb-0">
            <thead>
                <tr>
                    <th style="width: 48px;">SL</th>
                    <th>Payment Method</th>
                    <th>Ledger Account</th>
                    <th class="text-end">Opening Balance</th>
                    <th class="text-end">In</th>
                    <th class="text-end">Out</th>
                    <th class="text-end">Closing Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary as $i => $a)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $a['method'] }}</td>
                        <td>{{ $a['account'] }}@if ($a['code']) <span class="tl-sub">({{ $a['code'] }})</span>@endif</td>
                        <td class="text-end tl-num">{{ $m($a['opening']) }}</td>
                        <td class="text-end tl-num">{{ $m($a['in']) }}</td>
                        <td class="text-end tl-num">{{ $m($a['out']) }}</td>
                        <td class="text-end tl-num fw-medium {{ $a['closing'] < 0 ? 'tl-out' : '' }}">{{ $m($a['closing']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">No payment accounts found. Add them under Chart of Accounts › Liquid Asset.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="ledger-total">
                    <td colspan="3" class="text-end">Total</td>
                    <td class="text-end tl-num">{{ $m($totals['opening']) }}</td>
                    <td class="text-end tl-num">{{ $m($totals['in']) }}</td>
                    <td class="text-end tl-num">{{ $m($totals['out']) }}</td>
                    <td class="text-end tl-num">{{ $m($totals['closing']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
