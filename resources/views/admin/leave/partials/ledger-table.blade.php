@php $d = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.'); @endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div>
        <div class="fw-bold">{{ $employee->name }} <span class="text-muted fw-normal">({{ $employee->employee_code }})</span></div>
        <div class="small text-muted">
            {{ $employee->designation ?: '-' }} · Leave group:
            @if ($group)
                <b>{{ $group }}</b>
            @else
                <span class="text-danger">none — set it on the Employees page to get the group's yearly leave</span>
            @endif
        </div>
    </div>
</div>

{{-- Balance summary --}}
<div class="table-responsive mb-4">
    <table class="table table-sm table-bordered ledger-table mb-0">
        <thead>
            <tr>
                <th>Leave Type ({{ $year }})</th>
                <th class="text-end">Group Entitlement</th>
                <th class="text-end">Adjustment</th>
                <th class="text-end">Total</th>
                <th class="text-end">Taken</th>
                <th class="text-end">Pending</th>
                <th class="text-end">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($balances as $b)
                <tr>
                    <td class="fw-semibold">{{ $b['type']->name }} @unless ($b['paid'])<span class="badge bg-danger-subtle text-danger-emphasis">Unpaid</span>@endunless</td>
                    <td class="text-end">{{ $b['paid'] ? $d($b['entitled']) : '-' }}</td>
                    <td class="text-end">{{ $b['adjusted'] ? ($b['adjusted'] > 0 ? '+' : '') . $d($b['adjusted']) : '-' }}</td>
                    <td class="text-end">{{ $b['paid'] ? $d($b['entitled'] + $b['adjusted']) : '-' }}</td>
                    <td class="text-end">{{ $d($b['taken']) }}</td>
                    <td class="text-end">{{ $b['pending'] ? $d($b['pending']) : '-' }}</td>
                    <td class="text-end fw-bold {{ $b['paid'] && $b['balance'] < 0 ? 'text-danger' : '' }}">{{ $b['paid'] ? $d($b['balance']) : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Ledger per type --}}
@foreach ($ledger as $block)
    @continue(!$block['rows'])
    <div class="ledger-block mb-3">
        <div class="ledger-head fw-semibold">{{ $block['type']->name }}</div>
        <table class="table table-sm mb-0 ledger-table">
            <thead>
                <tr>
                    <th style="width: 110px;">Date</th>
                    <th style="width: 110px;">Entry</th>
                    <th>Details</th>
                    <th class="text-end" style="width: 90px;">Days</th>
                    <th class="text-end" style="width: 90px;">Balance</th>
                    <th style="width: 40px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($block['rows'] as $r)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($r['date'])->format('d M Y') }}</td>
                        <td>
                            <span class="badge {{ ['Entitlement' => 'bg-primary-subtle text-primary-emphasis', 'Adjustment' => 'bg-warning-subtle text-warning-emphasis', 'Leave' => 'bg-secondary-subtle text-secondary-emphasis'][$r['kind']] }}">{{ $r['kind'] }}</span>
                        </td>
                        <td>{{ $r['text'] }}</td>
                        <td class="text-end {{ $r['days'] < 0 ? 'text-danger' : 'text-success' }}">{{ ($r['days'] > 0 ? '+' : '') . $d($r['days']) }}</td>
                        <td class="text-end fw-semibold">{{ $block['type']->is_paid === 'Yes' ? $d($r['balance']) : '-' }}</td>
                        <td class="text-end">
                            @isset($r['adjustment_id'])
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" title="Remove adjustment" onclick="Ledger.removeAdjustment({{ $r['adjustment_id'] }})"><i class="fas fa-times"></i></button>
                            @endisset
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
