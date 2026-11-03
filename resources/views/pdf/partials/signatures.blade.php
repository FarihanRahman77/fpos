{{-- Signature lines: ['Prepared by' => 'Name', 'Checked by' => '', 'Approved by' => ''] (or a plain list of labels).
     The settings signature image is printed above the last line (the authorising one) when uploaded. --}}
@php
    $sigs = [];
    foreach ($signatures ?? [] as $k => $v) {
        $sigs[] = is_int($k) ? [$v, ''] : [$k, (string) $v];
    }
    $n = count($sigs);
@endphp
@if ($n)
    <table class="pdf-sign">
        <tr>
            @foreach ($sigs as $i => [$label, $name])
                <td style="width: {{ floor(100 / $n) }}%;" class="{{ $i > 0 ? 'gap' : '' }}">
                    <div class="pdf-sign-space">
                        @if ($i === $n - 1 && $pdfCo['signature'] && ($withImage ?? true))
                            <img src="{{ $pdfCo['signature'] }}" class="pdf-sign-img" alt="">
                        @elseif ($name !== '')
                            <span class="pdf-sign-name">{{ $name }}</span>
                        @endif
                    </div>
                    <div class="pdf-sign-line">{{ $label }}</div>
                </td>
            @endforeach
        </tr>
    </table>
@endif
