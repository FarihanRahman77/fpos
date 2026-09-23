<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print Barcodes</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 10px; font-family: Arial, Helvetica, sans-serif; background: #fff; }
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
            border: 1px dashed #ccc;
            padding: 1.5mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .label .name {
            font-size: 8px;
            line-height: 1.1;
            max-height: 18px;
            overflow: hidden;
            margin-bottom: 1px;
            font-weight: 600;
        }
        .label .barcode-svg { max-width: 100%; }
        .label .barcode-svg svg { width: 100%; height: auto; }
        .label .barcode-error { font-size: 8px; color: #c00; line-height: 1.2; }
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
                        <div class="name">{{ \Illuminate\Support\Str::limit($line['name'], 40) }}</div>
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
