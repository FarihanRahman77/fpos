{{-- Statement body for the page (see AccountingReportController: sections / lines / grand / checks / notes). --}}
@php
    $m = fn ($v) => $v === null ? '' : number_format((float) $v, 2);
    $span = count($columns) + 2;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
    <h5 class="mb-0">{{ $title }}</h5>
    <span class="text-muted small">{{ $period }}@isset($filters_text['Branch']) · {{ $filters_text['Branch'] }}@endisset</span>
</div>

<div class="table-responsive">
    <table class="table st-table mb-0">
        <thead>
            <tr>
                <th>Code</th>
                <th>Particulars</th>
                @foreach ($columns as $c)
                    <th class="st-num">{{ $c }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($sections as $sec)
                <tr class="st-section"><td colspan="{{ $span }}">{{ $sec['title'] }}</td></tr>
                @foreach ($sec['lines'] as $l)
                    <tr class="st-{{ $l['kind'] }}">
                        <td class="st-code">{{ $l['code'] ?? '' }}</td>
                        <td class="st-label st-indent-{{ (int) ($l['indent'] ?? 0) }}">{{ $l['label'] }}</td>
                        @foreach ($columns as $i => $c)
                            @php $val = $l['values'][$i] ?? null; @endphp
                            <td class="st-num {{ $val !== null && $val < 0 ? 'st-neg' : '' }}">{{ $m($val) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        @if ($grand)
            <tfoot>
                <tr class="st-grand">
                    <td></td>
                    <td>{{ $grand['label'] }}</td>
                    @foreach ($grand['values'] as $val)
                        <td class="st-num {{ $val < 0 ? 'st-neg' : '' }}">{{ $m($val) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</div>

@foreach ($checks as $c)
    <div class="st-check {{ $c['ok'] ? 'ok' : 'bad' }}">
        <i class="fas {{ $c['ok'] ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
        <span><strong>{{ $c['label'] }}</strong>{{ $c['ok'] ? '' : ' — not balanced' }}: {{ $c['detail'] }}</span>
    </div>
@endforeach

@if ($notes)
    <ul class="st-notes">
        @foreach ($notes as $n)
            <li>{{ $n }}</li>
        @endforeach
    </ul>
@endif
