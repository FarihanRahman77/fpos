{{-- Company (logo, name, address, contacts, BIN/TIN/trade license) left; document block right.
     Uses $doc (title, number, date, status, meta); can be included again with ['doc' => [...]] (e.g. per payslip). --}}
@php
    $tones = ['success' => $pdfT['success'], 'warning' => $pdfT['warning'], 'danger' => $pdfT['danger'], 'neutral' => $pdfT['muted']];
    $status = $doc['status'] ?? null;
    $statusColor = $status ? ($tones[$status[1] ?? 'neutral'] ?? $pdfT['muted']) : null;
    $ids = array_filter(['BIN' => $pdfCo['bin'], 'TIN' => $pdfCo['tin'], 'Trade Lic.' => $pdfCo['trade_license']]);
    $contacts = array_filter([$pdfCo['phone'] ? 'Phone: ' . $pdfCo['phone'] : null, $pdfCo['email'], $pdfCo['website']]);
@endphp
<table class="pdf-head">
    <tr>
        <td class="pdf-head-co">
            <table>
                <tr>
                    @if ($pdfCo['logo'])
                        <td class="pdf-logo-cell"><span class="pdf-logo-chip"><img src="{{ $pdfCo['logo'] }}" class="pdf-logo" alt=""></span></td>
                    @endif
                    <td>
                        <div class="pdf-co-name">{{ $pdfCo['name'] }}</div>
                        @if ($pdfCo['tagline'])<div class="pdf-co-tag">{{ $pdfCo['tagline'] }}</div>@endif
                        @if ($pdfCo['address'])<div class="pdf-co-line">{{ $pdfCo['address'] }}</div>@endif
                        @if ($pdfCo['city_line'])<div class="pdf-co-line">{{ $pdfCo['city_line'] }}</div>@endif
                        @if ($contacts)<div class="pdf-co-line">{{ implode('  ·  ', $contacts) }}</div>@endif
                        @if ($ids)
                            <div class="pdf-co-line">
                                @foreach ($ids as $k => $v){{ $k }}: {{ $v }}@if (!$loop->last)&nbsp;&nbsp;·&nbsp;&nbsp;@endif @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td class="pdf-head-doc">
            <div class="pdf-title">{{ $doc['title'] ?? '' }}</div>
            @if (!empty($doc['number']))<div class="pdf-docno">{{ $doc['number'] }}</div>@endif
            @if (!empty($doc['date']) || !empty($doc['meta']))
                <table class="pdf-docmeta">
                    @if (!empty($doc['date']))<tr><td class="k">Date</td><td class="v">{{ $doc['date'] }}</td></tr>@endif
                    @foreach ($doc['meta'] ?? [] as $k => $v)
                        <tr><td class="k">{{ $k }}</td><td class="v">{{ $v }}</td></tr>
                    @endforeach
                </table>
            @endif
            @if ($status)
                <div class="pdf-status"><span class="pdf-badge" style="color: {{ $statusColor }}; border-color: {{ $statusColor }};">{{ $status[0] }}</span></div>
            @endif
        </td>
    </tr>
</table>
