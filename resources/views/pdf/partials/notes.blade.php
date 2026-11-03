{{-- Note / terms block: @include('pdf.partials.notes', ['note' => ['Terms & conditions', $text]]) --}}
@if (trim((string) ($note[1] ?? '')) !== '')
    <div class="pdf-notes">
        <div class="pdf-label">{{ $note[0] ?? 'Notes' }}</div>
        <div class="pdf-notes-text">{!! nl2br(e($note[1])) !!}</div>
    </div>
@endif
