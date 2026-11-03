{{--
    The one frame of every PDF in the app (dompdf). Build PDFs with SettingController::pdf(), which passes
    $pdfT (template palette + fonts: Settings > Theme Settings > PDF Template) and $pdfCo (company) and draws
    "Page X of Y" in the footer.

    A document view:
        @extends('pdf.layout')
        @php $doc = [
            'title'  => 'Sales Invoice',              // required
            'number' => 'INV-0001',                   // optional, shown under the title
            'date'   => '03 Nov 2026',                // optional
            'status' => ['Paid', 'success'],          // optional [label, success|warning|danger|neutral]
            'meta'   => ['Sale type' => 'Cash'],      // optional extra rows in the document block
            'info'   => ['Period' => '...'],          // optional filter strip under the header (reports)
            'signatures' => ['Prepared by' => 'Name', 'Checked by' => '', 'Approved by' => ''],   // optional, after the content
            'printedBy' => 'Name', 'printedAt' => '...',   // optional footer overrides
            'header' => false,                        // optional: the view prints pdf.partials.header itself (e.g. one per payslip)
        ]; @endphp
        @section('content') ... @endsection
    Building blocks (pdf.partials.*): header, party, totals, words, notes, signatures; CSS classes in pdf.partials.styles.
--}}
@php
    $doc = $doc ?? [];
    $pdfT = $pdfT ?? \App\Http\Controllers\Admin\SettingController::pdfTheme();
    $pdfCo = $pdfCo ?? \App\Http\Controllers\Admin\SettingController::pdfCompany();
    $pdfPreview = $pdfPreview ?? false;
    $printedBy = $doc['printedBy'] ?? (auth()->user()->name ?? null);
    $printedAt = $doc['printedAt'] ?? now()->format('d M Y, h:i A');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['title'] ?? 'Document' }}{{ !empty($doc['number']) ? ' ' . $doc['number'] : '' }}</title>
    @include('pdf.partials.styles')
    @yield('styles')
</head>
<body class="tpl-{{ $pdfT['template'] }} {{ $pdfPreview ? 'is-preview' : '' }}">

    @if ($pdfCo['watermark'])
        <div class="pdf-watermark"><img src="{{ $pdfCo['watermark'] }}" alt=""></div>
    @endif

    <div class="pdf-footer">
        <table>
            <tr>
                <td style="width: 42%;">
                    <span class="b">{{ $pdfCo['name'] }}</span>
                    @php $footLine = $pdfCo['footer_text'] ?: ($pdfCo['tagline'] !== $pdfCo['name'] ? $pdfCo['tagline'] : null); @endphp
                    @if ($footLine)<br>{{ \Illuminate\Support\Str::limit($footLine, 110) }}@endif
                </td>
                <td style="width: 38%;" class="c">
                    @if ($printedBy)Printed by {{ $printedBy }}<br>@endif
                    {{ $printedAt }}
                </td>
                <td style="width: 20%;" class="r">@if ($pdfPreview)Page 1 of 1 @endif</td>
            </tr>
        </table>
    </div>

    @if (($doc['header'] ?? true) !== false)
        @include('pdf.partials.header')
    @endif

    @if (!empty($doc['info']))
        <table class="pdf-info">
            @foreach (array_chunk($doc['info'], 4, true) as $infoRow)
                <tr>
                    @foreach ($infoRow as $label => $value)
                        <td style="width: 25%;"><span class="pdf-label">{{ $label }}</span><br><span class="v">{{ $value }}</span></td>
                    @endforeach
                    @for ($k = count($infoRow); $k < 4; $k++)<td style="width: 25%;"></td>@endfor
                </tr>
            @endforeach
        </table>
    @endif

    @yield('content')

    @if (!empty($doc['signatures']))
        @include('pdf.partials.signatures', ['signatures' => $doc['signatures']])
    @endif
</body>
</html>
