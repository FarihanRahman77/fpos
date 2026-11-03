<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Barcodes</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 10px; font-family: Arial, Helvetica, sans-serif; background: {{ $pc['paper'] }}; }
        .toolbar { margin-bottom: 12px; }
        .toolbar button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
        .sheet {
            display: flex;
            flex-wrap: wrap;
            gap: 2mm;
        }
        .label {
            width: {{ $settings['label_width'] }}mm;
            height: {{ $settings['label_height'] }}mm;
            border: 1px dashed {{ $pc['border'] }};
            padding: 1.5mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            page-break-inside: avoid;
        }
        /* The whole name is printed: up to 2 lines, long names in a smaller font over up to 3 lines. */
        .label .name {
            font-size: 8px;
            line-height: 1.15;
            margin-bottom: 1px;
            font-weight: 600;
            width: 100%;
            overflow-wrap: anywhere;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            flex-shrink: 0;
        }
        .label .name.long { font-size: 6.5px; -webkit-line-clamp: 3; }
        .label .barcode-svg { max-width: 100%; }
        .label .barcode-svg svg { width: 100%; height: auto; }
        .label .barcode-error { font-size: 8px; color: {{ $pc['danger'] }}; line-height: 1.2; }
        .label .price { font-size: 9px; font-weight: bold; margin-top: 1px; }
        @media print {
            .toolbar { display: none; }
            .label { border: none; }
            @page { margin: 5mm; }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <button onclick="window.print()">🖨️ Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="sheet">
        @foreach ($lines as $line)
            @for ($i = 0; $i < $line['qty']; $i++)
                <div class="label">
                    @if ($settings['show_name'])
                        <div class="name{{ mb_strlen($line['name']) > 34 ? ' long' : '' }}" title="{{ $line['name'] }}">{{ $line['name'] }}</div>
                    @endif

                    <div class="barcode-svg">
                        @if ($line['barcode_svg'])
                            {!! $line['barcode_svg'] !!}
                            {{ $line['barcode'] }}
                        @else
                            <div class="barcode-error">
                                {{ $line['barcode'] }}<br>
                                {{ $line['barcode_error'] ?? 'Could not render this barcode' }}
                            </div>
                        @endif
                    </div>

                    @if ($settings['show_price'] && $line['price'] !== null && $line['price'] !== '')
                        <div class="price">Price: {{ number_format((float) $line['price'], 2) }}</div>
                    @endif
                </div>
            @endfor
        @endforeach
    </div>

    <script>
        // Barcodes are already rendered server-side, so we can print as soon
        // as the page has laid out — no external library to wait on.
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 200);
        });
    </script>
</body>
</html>
