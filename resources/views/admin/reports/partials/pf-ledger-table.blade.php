@php $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2); @endphp

@if (!$periods)
    <div class="text-center text-muted py-5">No PF months in this period.</div>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle pf-table mb-0">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-end">Basic</th>
                    <th class="text-end">Gross</th>
                    <th class="text-end">Deducted PF</th>
                    <th class="text-end">Projected PF</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="pf-opening"><td colspan="5">Opening balance (deducted PF)</td><td class="text-end">{{ number_format($opening, 2) }}</td></tr>
                @foreach ($periods as $n => $period)
                    <tr class="pf-period"><td colspan="6">{{ \App\Http\Controllers\Admin\ReportController::pfPeriodTitle($n, $period['entry']) }}</td></tr>
                    @foreach ($period['rows'] as $r)
                        <tr>
                            <td>{{ $r['month']->format('M Y') }}
                                @if ($r['source'])<span class="badge bg-success-subtle text-success-emphasis ms-1">Deducted · {{ $r['source'] }}</span>@else<span class="badge bg-light text-muted ms-1" title="Salary of this month is not processed yet">Projected</span>@endif
                            </td>
                            <td class="text-end">{{ $m($r['basic']) }}</td>
                            <td class="text-end">{{ $m($r['gross']) }}</td>
                            <td class="text-end">{{ $m($r['deducted']) }}</td>
                            <td class="text-end text-muted">{{ $m($r['projected']) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($r['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="pf-total">
                        <td colspan="3">Period total ({{ count($period['rows']) }} months)</td>
                        <td class="text-end">{{ number_format($period['deducted'], 2) }}</td>
                        <td class="text-end text-muted">{{ number_format($period['projected'], 2) }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="pf-grand">
                    <td colspan="3">Total / Closing balance (deducted only)</td>
                    <td class="text-end">{{ number_format($deducted_total, 2) }}</td>
                    <td class="text-end text-muted">{{ number_format($projected_total, 2) }}</td>
                    <td class="text-end">{{ number_format($closing, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="text-muted small mt-2 mb-0">Deducted = PF taken in a processed salary run (in the fund). Projected = months whose salary is not processed yet, at the current salary (joining / leaving month pro-rated); it is not in the balance.</p>
@endif
