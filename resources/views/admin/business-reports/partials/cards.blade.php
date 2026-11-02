@php $brFmt = fn ($v, $type) => \App\Http\Controllers\Admin\BusinessReportController::fmt($v, $type); @endphp
@if ($cards)
    <div class="br-cards">
        @foreach ($cards as $card)
            <div class="br-card tone-{{ $card[3] ?? 'none' }}">
                <small>{{ $card[0] }}</small>
                <b title="{{ is_numeric($card[1]) ? $brFmt($card[1], $card[2]) : $card[1] }}">{{ is_numeric($card[1]) || $card[1] === null ? $brFmt($card[1] ?? 0, $card[2]) : $card[1] }}</b>
            </div>
        @endforeach
    </div>
@endif
