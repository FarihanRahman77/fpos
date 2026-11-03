{{-- Amount in words: @include('pdf.partials.words', ['w' => ['amount' => 1250.5]]) or ['w' => ['text' => 'One Thousand ...']];
     optional 'currency' (default company currency, '' = none). --}}
@php
    $wordsText = $w['text'] ?? (function_exists('amount_in_words') ? amount_in_words($w['amount'] ?? 0) : null);
    $wordsCur = $w['currency'] ?? $pdfCo['currency'];
@endphp
@if ($wordsText)
    <div class="pdf-words">
        <span class="pdf-label">Amount in words</span><br>
        <span class="pdf-words-text">{{ $wordsCur ? $wordsCur . ' ' : '' }}{{ $wordsText }} Only</span>
    </div>
@endif
