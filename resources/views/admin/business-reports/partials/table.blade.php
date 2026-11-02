@php
    $brFmt = fn ($v, $type) => \App\Http\Controllers\Admin\BusinessReportController::fmt($v, $type);
    $keys = array_keys($columns);
    $isNum = fn ($k) => in_array($columns[$k][1], ['money', 'qty', 'int', 'pct', 'heat'], true);
    $heatMax = (float) ($extra['heat_max'] ?? 0);
    $cell = function ($row, $k) use ($columns, $isNum, $brFmt, $heatMax) {
        $v = $row[$k] ?? null;
        $type = $columns[$k][1];
        $class = $isNum($k) ? 'num' : '';
        if ($type === 'heat') {
            $level = $heatMax > 0 && $v ? min(5, max(1, (int) ceil((float) $v / $heatMax * 5))) : 0;
            $class = 'heat heat-' . $level;
        } elseif (in_array($type, ['money', 'qty'], true) && is_numeric($v) && (float) $v < 0) {
            $class .= ' neg';
        }
        return '<td class="' . $class . '">' . e($brFmt($v, $type)) . '</td>';
    };
    $empty = empty($rows) && empty($groups);
@endphp

@if ($empty)
    <div class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2" aria-hidden="true"></i>Nothing to show for these filters.</div>
@else
    <div class="table-responsive">
        <table class="table table-bordered br-table mb-0">
            <thead>
                <tr>
                    @foreach ($columns as $k => [$label, $type])
                        <th class="{{ $isNum($k) ? 'text-end' : '' }}">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if ($groups)
                    @foreach ($groups as $g)
                        <tr class="grp"><td colspan="{{ count($keys) }}">{{ $g['name'] }} <span class="text-muted fw-normal">({{ count($g['rows']) }})</span></td></tr>
                        @foreach ($g['rows'] as $row)
                            <tr>@foreach ($keys as $k){!! $cell($row, $k) !!}@endforeach</tr>
                        @endforeach
                        @if (!empty($g['totals']) && count($groups) > 1)
                            <tr class="sub">@foreach ($keys as $i => $k)
                                @if ($i === 0)<td>Subtotal</td>@else{!! $cell($g['totals'], $k) !!}@endif
                            @endforeach</tr>
                        @endif
                    @endforeach
                @else
                    @foreach ($rows as $row)
                        <tr>@foreach ($keys as $k){!! $cell($row, $k) !!}@endforeach</tr>
                    @endforeach
                @endif
            </tbody>
            @if ($totals)
                <tfoot>
                    <tr>@foreach ($keys as $i => $k)
                        @if ($i === 0)<td>Total</td>@elseif ($isNum($k) && $columns[$k][1] !== 'heat' || $columns[$k][1] === 'heat'){!! $cell($totals, $k) !!}@else<td></td>@endif
                    @endforeach</tr>
                </tfoot>
            @endif
        </table>
    </div>
@endif

@if ($notes)
    <ul class="br-notes">@foreach ($notes as $n)<li>{{ $n }}</li>@endforeach</ul>
@endif

{{-- Budget entry --}}
@if (!empty($extra['budget']['rows']))
    <form id="budgetForm" class="br-extra">
        <input type="hidden" name="month" value="{{ $extra['budget']['month'] }}">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h6 class="mb-0">Set the budget for {{ \Carbon\Carbon::createFromFormat('!Y-m', $extra['budget']['month'])->format('F Y') }}</h6>
            <button type="submit" class="btn btn-primary ms-auto">Save budget</button>
        </div>
        <div class="row g-2">
            @foreach ($extra['budget']['rows'] as $r)
                <div class="col-md-4 col-xl-3">
                    <label class="form-label small mb-1" for="bud{{ $r['account_id'] }}">{{ $r['label'] }}</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="bud{{ $r['account_id'] }}" name="budgets[{{ $r['account_id'] }}]" value="{{ $r['budget'] ?: '' }}" placeholder="0.00" style="max-width: none;">
                </div>
            @endforeach
        </div>
    </form>
@endif
