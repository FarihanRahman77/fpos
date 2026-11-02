@php $m = fn ($v) => $v == 0 ? '-' : number_format($v, 2); @endphp

@foreach ($days as $day)
    <div class="day-block mb-4">
        <div class="day-head d-flex flex-wrap align-items-center gap-3">
            <span class="fw-bold">{{ $day['date']->format('l, d M Y') }}</span>
            <span class="ms-auto small">Opening <b>{{ number_format($day['totals']['opening'], 2) }}</b></span>
            <span class="small text-success">In <b>{{ number_format($day['totals']['in'], 2) }}</b></span>
            <span class="small text-danger">Out <b>{{ number_format($day['totals']['out'], 2) }}</b></span>
            <span class="small">Closing <b>{{ number_format($day['totals']['closing'], 2) }}</b></span>
        </div>

        @if (!$day['accounts'])
            <div class="text-center text-muted small py-3">No liquid asset balance or movement.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm mb-0 book-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Voucher</th>
                            <th>Party</th>
                            <th>Reference</th>
                            <th>Remarks</th>
                            <th class="text-end">In</th>
                            <th class="text-end">Out</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($day['accounts'] as $a)
                            <tr class="book-account">
                                <td colspan="8">{{ $a['method'] }} › {{ $a['account'] }}</td>
                                <td class="text-end" title="Opening">{{ number_format($a['opening'], 2) }}</td>
                            </tr>
                            @foreach ($a['rows'] as $r)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($r['datetime'])->format('h:i A') }}</td>
                                    <td><span class="badge book-badge">{{ $r['type'] }}</span></td>
                                    <td>{{ $r['voucher'] }}</td>
                                    <td>{{ $r['party'] ?: '-' }}</td>
                                    <td>{{ $r['reference'] ?: '-' }}</td>
                                    <td class="text-muted small">{{ $r['remarks'] ?: '' }}</td>
                                    <td class="text-end text-success">{{ $m($r['in']) }}</td>
                                    <td class="text-end text-danger">{{ $m($r['out']) }}</td>
                                    <td class="text-end fw-semibold {{ $r['balance'] < 0 ? 'text-danger' : '' }}">{{ number_format($r['balance'], 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="book-close">
                                <td colspan="6">Closing — {{ $a['account'] }}</td>
                                <td class="text-end">{{ $m($a['in']) }}</td>
                                <td class="text-end">{{ $m($a['out']) }}</td>
                                <td class="text-end {{ $a['closing'] < 0 ? 'text-danger' : '' }}">{{ number_format($a['closing'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @foreach ($day['methods'] as $mt)
                            <tr class="book-method">
                                <td colspan="6">{{ $mt['method'] }} total <span class="text-muted fw-normal">(opening {{ number_format($mt['opening'], 2) }})</span></td>
                                <td class="text-end">{{ $m($mt['in']) }}</td>
                                <td class="text-end">{{ $m($mt['out']) }}</td>
                                <td class="text-end">{{ number_format($mt['closing'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
@endforeach

@if ($by_type)
    <h6 class="mt-2">Summary by type</h6>
    <div class="table-responsive" style="max-width: 640px;">
        <table class="table table-sm table-bordered book-table">
            <thead><tr><th>Type</th><th class="text-end">Entries</th><th class="text-end">In</th><th class="text-end">Out</th></tr></thead>
            <tbody>
                @foreach ($by_type as $t)
                    <tr><td>{{ $t['type'] }}</td><td class="text-end">{{ $t['count'] }}</td><td class="text-end">{{ $m($t['in']) }}</td><td class="text-end">{{ $m($t['out']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
