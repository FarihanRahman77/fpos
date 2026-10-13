<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use App\Services\Reports\StockLedgerReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use PDF;

class ReportController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Stock ledger                                                      */
    /* ------------------------------------------------------------------ */

    public function stockLedger()
    {
        $active = fn ($table) => DB::table($table)->where('deleted', 'No')->orderBy('name')->get(['id', 'name']);

        return view('admin.reports.stock-ledger', [
            'products'   => DB::table('products')->where('deleted', 'No')->orderBy('name')->get(['id', 'name', 'sku']),
            'variants'   => DB::table('product_variants as pv')
                ->leftJoin('attributes as a', 'a.id', '=', 'pv.attribute_id')
                ->where('pv.deleted', 'No')
                ->orderBy('a.name')
                ->get(['pv.id', 'pv.product_id', 'a.name']),
            'categories' => $active('categories'),
            'brands'     => $active('brands'),
            'types'      => StockLedgerReport::TYPES,
        ]);
    }

    /** JSON: rendered report body + summary, for the page. */
    public function stockLedgerData(Request $request)
    {
        $validator = $this->stockLedgerValidator($request);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $report = (new StockLedgerReport($validator->validated()))->build();

            return response()->json([
                'status'  => true,
                'html'    => view('admin.reports.partials.stock-ledger-table', $report)->render(),
                'summary' => [
                    'items'   => $report['totals']['items'],
                    'in'      => $this->qty($report['totals']['in']),
                    'out'     => $this->qty($report['totals']['out']),
                    'closing' => $this->qty($report['totals']['closing']),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Stock ledger error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['status' => false, 'message' => 'Unable to build the report.'], 500);
        }
    }

    public function stockLedgerPdf(Request $request)
    {
        $validator = $this->stockLedgerValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report = (new StockLedgerReport($validator->validated()))->build();

        $pdf = PDF::loadView('admin.reports.stock-ledger-pdf', $report + [
            'company'   => $this->company(),
            'printedAt' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true);

        // "Page X of Y" in the footer, drawn on the canvas (no inline PHP in the template).
        $pdf->render();   // wrapper's render() marks it rendered, so stream()/download() won't render again
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 95, $canvas->get_height() - 28,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.58, 0.64, 0.72]
        );

        $fileName = 'Stock-Ledger-' . now()->format('Y-m-d') . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function stockLedgerExcel(Request $request)
    {
        $validator = $this->stockLedgerValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report  = (new StockLedgerReport($validator->validated()))->build();
        $company = $this->company();

        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Stock Ledger');
        $book->getProperties()->setCreator($company['name'])->setTitle('Stock Ledger');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $cols = ['A' => 'Date', 'B' => 'Type', 'C' => 'Reference', 'D' => 'Party', 'E' => 'Remarks', 'F' => 'In', 'G' => 'Out', 'H' => 'Balance'];
        $widths = ['A' => 18, 'B' => 17, 'C' => 16, 'D' => 26, 'E' => 34, 'F' => 12, 'G' => 12, 'H' => 13];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Header block
        $sheet->setCellValue('A1', $company['name'])->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', 'Stock Ledger Report')->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1E3A8A');

        $row = 3;
        foreach ($report['filters_text'] as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ':')->setCellValue("B{$row}", $value);
            $sheet->mergeCells("B{$row}:H{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Generated:')->setCellValue("B{$row}", now()->format('d M Y, h:i A'))->mergeCells("B{$row}:H{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row += 2;

        $qtyFormat = '#,##0.###;[Red]-#,##0.###;"-"';
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]]];

        foreach ($report['items'] as $item) {
            // Product title
            $title = $item['product'] . ($item['sku'] ? '  [' . $item['sku'] . ']' : '') . ($item['unit'] ? '  (' . $item['unit'] . ')' : '');
            $sheet->setCellValue("A{$row}", $title)->mergeCells("A{$row}:H{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            ]);
            $row++;

            // Column headings
            foreach ($cols as $col => $label) {
                $sheet->setCellValue("{$col}{$row}", $label);
            }
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
            ]);
            $sheet->getStyle("F{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $first = $row;
            $row++;

            // Opening
            $sheet->setCellValue("A{$row}", 'Opening balance (before ' . $report['from']->format('d M Y') . ')')->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("H{$row}", $item['opening']);
            $sheet->getStyle("A{$row}:H{$row}")->getFont()->setItalic(true);
            $row++;

            foreach ($item['rows'] as $r) {
                $sheet->setCellValue("A{$row}", $r['date']->format('d M Y h:i A'));
                $sheet->setCellValue("B{$row}", $r['type']);
                $sheet->setCellValueExplicit("C{$row}", (string) ($r['reference'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("D{$row}", $r['party'] ?? '');
                $sheet->setCellValue("E{$row}", $r['remarks'] ?? '');
                $sheet->setCellValue("F{$row}", $r['in'] ?: null);
                $sheet->setCellValue("G{$row}", $r['out'] ?: null);
                $sheet->setCellValue("H{$row}", $r['balance']);
                $row++;
            }

            if (!$item['rows']) {
                $sheet->setCellValue("A{$row}", 'No movement in this period')->mergeCells("A{$row}:H{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
                $row++;
            }

            // Totals + closing
            $sheet->setCellValue("A{$row}", 'Total / Closing balance')->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("F{$row}", $item['in']);
            $sheet->setCellValue("G{$row}", $item['out']);
            $sheet->setCellValue("H{$row}", $item['closing']);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);

            $sheet->getStyle("A{$first}:H{$row}")->applyFromArray($thin);
            $sheet->getStyle("F{$first}:H{$row}")->getNumberFormat()->setFormatCode($qtyFormat);
            $row += 2;
        }

        // Grand summary
        $t = $report['totals'];
        $summary = [
            ['Products / variants', $t['items']],
            ['Opening balance', $t['opening']],
            ['Total in', $t['in']],
            ['Total out', $t['out']],
            ['Closing balance', $t['closing']],
        ];
        $sheet->setCellValue("A{$row}", 'Summary')->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $row++;
        $start = $row;
        foreach ($summary as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label)->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $value);
            $row++;
        }
        $sheet->getStyle("A{$start}:C" . ($row - 1))->applyFromArray($thin);
        $sheet->getStyle("C{$start}:C" . ($row - 1))->getNumberFormat()->setFormatCode($qtyFormat);
        $sheet->getStyle("A{$start}:A" . ($row - 1))->getFont()->setBold(true);

        // Print setup: landscape, fit to width, repeat nothing (product blocks have their own headers)
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)->setFitToHeight(0);

        $fileName = 'Stock-Ledger-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    private function stockLedgerValidator(Request $request)
    {
        return Validator::make($request->query(), [
            'from'          => ['required', 'date'],
            'to'            => ['required', 'date', 'after_or_equal:from'],
            'product_id'    => ['nullable', 'integer', 'exists:products,id'],
            'variant_id'    => ['nullable', 'integer', 'exists:product_variants,id'],
            'category_id'   => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id'      => ['nullable', 'integer', 'exists:brands,id'],
            'type'          => ['nullable', Rule::in(array_keys(StockLedgerReport::TYPES))],
            'only_movement' => ['nullable', 'boolean'],
        ], [
            'from.required'      => 'Select the start date.',
            'to.required'        => 'Select the end date.',
            'to.after_or_equal'  => 'End date cannot be before the start date.',
        ]);
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3), '0'), '.');
    }

    private function company(): array
    {
        $s = Setting::where('deleted', 'No')->where('status', 'Active')->first();

        $logo = null;
        if ($s && $s->logo && is_file($path = public_path($s->logo))) {
            $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $logo = in_array($ext, ['png', 'jpg', 'jpeg', 'gif'], true)
                ? 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($path))
                : null;
        }

        return [
            'name'    => $s->company_name ?? 'Company Name',
            'address' => trim(implode(', ', array_filter([$s->address ?? null, $s->city ?? null, $s->country ?? null]))),
            'phone'   => $s->phone ?? null,
            'email'   => $s->email ?? null,
            'logo'    => $logo,
        ];
    }
}
