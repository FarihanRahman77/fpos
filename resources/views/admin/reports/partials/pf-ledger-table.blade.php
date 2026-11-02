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
                    <th class="text-end">PF</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="pf-opening"><td colspan="4">Opening balance</td><td class="text-end">{{ number_format($opening, 2) }}</td></tr>
                @foreach ($periods as $n => $period)
                    <tr class="pf-period"><td colspan="5">{{ \App\Http\Controllers\Admin\ReportController::pfPeriodTitle($n, $period['entry']) }}</td></tr>
                    @foreach ($period['rows'] as $r)
                        <tr>
                            <td>{{ $r['month']->format('M Y') }}
                                @if ($r['source'])<span class="badge bg-success-subtle text-success-emphasis ms-1">Paid · {{ $r['source'] }}</span>@else<span class="badge bg-light ms-1">expected</span>@endif
                            </td>
                            <td class="text-end">{{ $m($r['basic']) }}</td>
                            <td class="text-end">{{ $m($r['gross']) }}</td>
                            <td class="text-end">{{ $m($r['pf']) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($r['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="pf-total">
                        <td colspan="3">Period total ({{ count($period['rows']) }} months)</td>
                        <td class="text-end">{{ number_format($period['total'], 2) }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="pf-grand">
                    <td colspan="3">Total PF / Closing balance</td>
                    <td class="text-end">{{ number_format($total, 2) }}</td>
                    <td class="text-end">{{ number_format($closing, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
