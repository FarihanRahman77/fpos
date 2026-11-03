@extends('pdf.layout')
@php
    $m = fn ($v) => number_format((float) $v, 2);
    $b = fn ($v) => (float) $v == 0 ? '' : number_format((float) $v, 2);
    $doc = [
        'title'     => 'Transactions Ledger',
        'info'      => $filters_text,
        'printedBy' => $printedBy ?: null,
        'printedAt' => $printedAt,
        'signatures' => ['Prepared by' => $printedBy ?: '', 'Checked by' => '', 'Approved by' => ''],
    ];
@endphp

@section('content')
    @foreach ($methods as $sec)
        @php $label = $sec['method']; @endphp
        <div class="pdf-h">{{ $label }} Report</div>
        <table class="pdf-table compact">
            <thead>
                <tr>
                    <th style="width: 4%;">SL</th>
                    <th style="width: 31%;">Particulars</th>
                    <th style="width: 23%;">Remarks</th>
                    <th style="width: 11%;">Voucher Type</th>
                    <th class="num" style="width: 10%;">{{ $label }} In</th>
                    <th class="num" style="width: 10%;">{{ $label }} Out</th>
                    <th class="num" style="width: 11%;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sec['rows'] as $i => $r)
                    <tr>
                        <td class="muted">{{ $i + 1 }}</td>
                        <td>
                            {{ $r['particulars'] }}
                            <div class="sub">{{ $r['datetime']->format('d M Y h:i A') }}{{ $r['refs'] ? ' · ' . $r['refs'] : '' }}</div>
                        </td>
                        <td>
                            {{ $r['account'] }}
                            @if ($r['remarks'])<div class="sub">{{ $r['remarks'] }}</div>@endif
                        </td>
                        <td>{{ $r['type'] }}</td>
                        <td class="num">{{ $b($r['in']) }}</td>
                        <td class="num">{{ $b($r['out']) }}</td>
                        <td class="num">{{ $m($r['balance']) }}</td>
                    </tr>
                @empty
                    <tr class="empty"><td colspan="7">No transactions in this period</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="pdf-kv avoid">
            <tr>
                <td class="k">Today {{ $label }} In</td><td class="v num b">{{ $m($sec['in']) }}</td>
                <td class="k" style="padding-left: 24px;">{{ $label }} Opening Balance</td><td class="v num b">{{ $m($sec['opening']) }}</td>
            </tr>
            <tr>
                <td class="k">Today {{ $label }} Out</td><td class="v num b">{{ $m($sec['out']) }}</td>
                <td class="k" style="padding-left: 24px;">Today {{ $label }} Closing Balance</td><td class="v num b">{{ $m($sec['period']) }}</td>
            </tr>
            <tr>
                <td class="k">Today {{ $label }} Closing Balance</td><td class="v num b">{{ $m($sec['period']) }}</td>
                <td class="k" style="padding-left: 24px;">Net {{ $label }} Closing Balance</td><td class="v num b">{{ $m($sec['closing']) }}</td>
            </tr>
            <tr>
                <td class="k">Net {{ $label }} Closing Balance</td><td class="v num b">{{ $m($sec['closing']) }}</td>
                <td></td><td></td>
            </tr>
        </table>
    @endforeach

    <div class="pdf-h">Closing Summary</div>
    <table class="pdf-table compact">
        <thead>
            <tr>
                <th style="width: 4%;">SL</th>
                <th style="width: 18%;">Payment Method</th>
                <th style="width: 26%;">Ledger Account</th>
                <th class="num" style="width: 13%;">Opening Balance</th>
                <th class="num" style="width: 13%;">In</th>
                <th class="num" style="width: 13%;">Out</th>
                <th class="num" style="width: 13%;">Closing Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary as $i => $a)
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td>{{ $a['method'] }}</td>
                    <td>{{ $a['account'] }}{{ $a['code'] ? ' (' . $a['code'] . ')' : '' }}</td>
                    <td class="num">{{ $m($a['opening']) }}</td>
                    <td class="num">{{ $m($a['in']) }}</td>
                    <td class="num">{{ $m($a['out']) }}</td>
                    <td class="num b">{{ $m($a['closing']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3" class="r">Total</td>
                <td class="num">{{ $m($totals['opening']) }}</td>
                <td class="num">{{ $m($totals['in']) }}</td>
                <td class="num">{{ $m($totals['out']) }}</td>
                <td class="num">{{ $m($totals['closing']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
