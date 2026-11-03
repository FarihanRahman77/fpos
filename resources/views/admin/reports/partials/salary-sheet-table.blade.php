@php
    $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2);
    $stateClass = ['Not processed' => 'sheet-state-new', 'Processed' => 'sheet-state-run', 'Partly paid' => 'sheet-state-part', 'Paid' => 'sheet-state-paid'];
    // Breakdown shown on hover for the columns built from several lines.
    $detail = function ($r, $key) {
        $lines = match ($key) {
            'bonus_amount'   => array_map(fn ($b) => $b['name'] . ' (' . $b['rule'] . '): ' . number_format($b['amount'], 2), $r['bonus_detail'] ?? []),
            'additions'      => array_map(fn ($a) => $a['name'] . ': ' . number_format($a['amount'], 2) . (!empty($a['remarks']) ? ' — ' . $a['remarks'] : ''), $r['additions_detail'] ?? []),
            'deductions'     => array_map(fn ($a) => $a['name'] . ': ' . number_format($a['amount'], 2) . (!empty($a['remarks']) ? ' — ' . $a['remarks'] : ''), $r['deductions_detail'] ?? []),
            'loan_deduction' => array_map(fn ($l) => $l['loan_no'] . ' (' . $l['type'] . '): ' . number_format($l['amount'], 2), $r['loan_detail'] ?? []),
            'earned_gross'   => !empty($r['earned_note']) ? ['Full gross ' . number_format($r['gross_salary'], 2) . ' less ' . $r['earned_note'] . ': ' . number_format($r['prorate_off'], 2)] : [],
            default          => [],
        };

        return implode("\n", $lines);
    };
@endphp

@if (!$groups)
    <div class="text-center text-muted py-5">No active employees for {{ $month->format('F Y') }}.</div>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle sheet-table mb-0">
            <thead>
                <tr>
                    <th>SL</th>
                    @foreach ($columns as $key => [$label, $money])
                        <th class="{{ $money ? 'text-end' : '' }} {{ str_starts_with($key, 'paid') ? 'sheet-paid-col' : '' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $sl = 1; @endphp
                @foreach ($groups as $group)
                    <tr class="sheet-group"><td colspan="{{ count($columns) + 1 }}">{{ $group['name'] }} <span class="text-muted fw-normal">({{ count($group['rows']) }})</span></td></tr>
                    @foreach ($group['rows'] as $r)
                        <tr>
                            <td>{{ $sl++ }}</td>
                            @foreach ($columns as $key => [$label, $money])
                                @if ($money)
                                    @php $tip = $detail($r, $key); @endphp
                                    <td class="text-end {{ in_array($key, ['net_payable', 'due']) ? 'fw-bold' : '' }}
                                        {{ in_array($key, ['pf_amount', 'deductions', 'lwp_deduction', 'loan_deduction']) ? 'text-danger' : '' }}
                                        {{ $key === 'due' && $r['due'] > 0.004 && $r['item_id'] ? 'text-warning' : '' }}
                                        {{ str_starts_with($key, 'paid') ? 'sheet-paid-col' : '' }}"
                                        @if ($tip) title="{{ $tip }}" data-detail @endif>{{ $m($r[$key]) }}</td>
                                @elseif ($key === 'name')
                                    <td><span class="fw-semibold">{{ $r['name'] }}</span>
                                        <span class="sheet-state {{ $stateClass[$r['state']] ?? '' }}" @if ($r['run_no']) title="{{ $r['run_no'] }}" @endif>{{ $r['state'] }}</span>
                                        <div class="text-muted small">{{ $r['bank'] }}</div></td>
                                @else
                                    <td>{{ $r[$key] ?: '-' }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                    @if (count($groups) > 1)
                        <tr class="sheet-total">
                            <td colspan="{{ count($columns) - count($money_keys) + 1 }}">Subtotal — {{ $group['name'] }}</td>
                            @foreach ($money_keys as $k)<td class="text-end">{{ $m($group['totals'][$k]) }}</td>@endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr class="sheet-grand">
                    <td colspan="{{ count($columns) - count($money_keys) + 1 }}">Grand Total ({{ $count }} employees)</td>
                    @foreach ($money_keys as $k)<td class="text-end">{{ $m($totals[$k]) }}</td>@endforeach
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="text-muted small mt-2 mb-0">Hover Pro-rated Gross, Bonus, Additions, Deductions or Loan to see each line. “Not processed” rows are a live preview; processing freezes them.</p>
@endif
