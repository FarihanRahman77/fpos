{{-- Totals box (right) with amount in words / notes (left). Everything goes in one 'box' array so the
     including view's own variables ($rows, $totals, ...) never leak in:
     @include('pdf.partials.totals', ['box' => [
         'rows'   => [['Subtotal', 1000.00], ['Discount', 50.00], ['VAT (5%)', 47.50]],   // [label, amount or preformatted string, optional row class]
         'grand'  => ['Grand Total', 997.50],
         'after'  => [['Paid', 500], ['Due', 497.50, 'due']],                             // below the grand total; class 'due' / 'clear'
         'words'  => 997.50,              // optional: amount in words (amount_in_words helper)
         'notes'  => ['Notes', 'text'],   // optional: [label, text] under the words
         'left'   => '<div>html</div>',   // optional: extra trusted HTML on the left
         'currency' => 'BDT',             // optional: prefix of the grand total and words (default: company currency; '' = none)
     ]]) --}}
@php
    $fmt = fn ($v) => is_numeric($v) ? number_format((float) $v, 2) : $v;
    $cur = $box['currency'] ?? $pdfCo['currency'];
@endphp
<table class="pdf-bottom">
    <tr>
        <td class="pdf-bottom-left">
            @if (isset($box['words']))
                @include('pdf.partials.words', ['w' => ['amount' => $box['words'], 'currency' => $cur]])
            @endif
            @if (!empty($box['notes']))
                @include('pdf.partials.notes', ['note' => $box['notes']])
            @endif
            {!! $box['left'] ?? '' !!}
        </td>
        <td class="pdf-bottom-right">
            <table class="pdf-totals">
                @foreach ($box['rows'] ?? [] as $row)
                    <tr class="{{ $row[2] ?? '' }}"><td class="k">{{ $row[0] }}</td><td class="num">{{ $fmt($row[1]) }}</td></tr>
                @endforeach
                @if (isset($box['grand']))
                    <tr class="grand"><td class="k">{{ $box['grand'][0] }}</td><td class="num">{{ $cur && is_numeric($box['grand'][1]) ? $cur . ' ' : '' }}{{ $fmt($box['grand'][1]) }}</td></tr>
                @endif
                @foreach ($box['after'] ?? [] as $row)
                    <tr class="after {{ $row[2] ?? '' }}"><td class="k">{{ $row[0] }}</td><td class="num">{{ $fmt($row[1]) }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
