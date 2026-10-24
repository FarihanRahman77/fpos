<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Setting;
use App\Services\Reports\PartyLedgerReport;
use App\Services\Reports\StockLedgerReport;
use App\Services\Reports\TransactionLedgerReport;
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

        $this->addPageNumbers($pdf);

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

    /* ------------------------------------------------------------------ */
    /*  Customer / supplier ledger ({ledger} = customer | supplier)       */
    /* ------------------------------------------------------------------ */

    public function partyLedger(string $ledger)
    {
        $config = PartyLedgerReport::LEDGERS[$ledger];

        return view('admin.reports.party-ledger', [
            'ledger'  => $ledger,
            'config'  => $config,
            'parties' => DB::table('parties')->where('deleted', 'No')->whereIn('type', $config['types'])
                ->orderBy('name')->get(['id', 'code', 'name', 'phone', 'type']),
        ]);
    }

    /** JSON: rendered report body + summary, for the page. */
    public function partyLedgerData(Request $request, string $ledger)
    {
        $validator = $this->partyLedgerValidator($request, $ledger);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $service = new PartyLedgerReport($ledger, $validator->validated());
            $report  = $service->build();
            $t       = $report['totals'];

            return response()->json([
                'status'  => true,
                'html'    => view('admin.reports.partials.party-ledger-table', $report + ['service' => $service])->render(),
                'summary' => [
                    'parties' => $t['parties'],
                    'dr'      => number_format($t['dr'], 2),
                    'cr'      => number_format($t['cr'], 2),
                    'closing' => PartyLedgerReport::balanceText($t['closing']),
                    'label'   => $service->balanceLabel($t['closing']),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Party ledger error', ['ledger' => $ledger, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['status' => false, 'message' => 'Unable to build the report.'], 500);
        }
    }

    public function partyLedgerPdf(Request $request, string $ledger)
    {
        $validator = $this->partyLedgerValidator($request, $ledger);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $service = new PartyLedgerReport($ledger, $validator->validated());
        $report  = $service->build();

        $pdf = PDF::loadView('admin.reports.party-ledger-pdf', $report + [
            'service'   => $service,
            'company'   => $this->company(),
            'printedAt' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true);

        $this->addPageNumbers($pdf);

        $fileName = str_replace(' ', '-', $report['config']['title']) . '-' . now()->format('Y-m-d') . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function partyLedgerExcel(Request $request, string $ledger)
    {
        $validator = $this->partyLedgerValidator($request, $ledger);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $service = new PartyLedgerReport($ledger, $validator->validated());
        $report  = $service->build();
        $company = $this->company();
        $title   = $report['config']['title'];

        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle($title);
        $book->getProperties()->setCreator($company['name'])->setTitle($title);
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $cols   = ['A' => 'Date', 'B' => 'Voucher No', 'C' => 'Particulars', 'D' => 'Reference', 'E' => 'Remarks', 'F' => 'Debit', 'G' => 'Credit', 'H' => 'Balance'];
        $widths = ['A' => 13, 'B' => 14, 'C' => 22, 'D' => 16, 'E' => 32, 'F' => 14, 'G' => 14, 'H' => 17];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Header block
        $sheet->setCellValue('A1', $company['name'])->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', $title)->mergeCells('A2:H2');
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

        $moneyFormat   = '#,##0.00;-#,##0.00;"-"';
        // Signed balance (Dr - Cr) shown as "1,250.00 Dr" / "1,250.00 Cr" while staying numeric.
        $balanceFormat = '#,##0.00" Dr";#,##0.00" Cr";"0.00"';
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]]];

        foreach ($report['items'] as $item) {
            // Party title
            $title = $item['name'] . ($item['code'] ? '  [' . $item['code'] . ']' : '') . '  (' . $item['type'] . ')'
                . ($item['phone'] ? '  ·  ' . $item['phone'] : '');
            $sheet->setCellValue("A{$row}", $title)->mergeCells("A{$row}:H{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            ]);
            $row++;

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

            $sheet->setCellValue("A{$row}", 'Balance brought forward (before ' . $report['from']->format('d M Y') . ')')->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("H{$row}", $item['opening']);
            $sheet->getStyle("A{$row}:H{$row}")->getFont()->setItalic(true);
            $row++;

            foreach ($item['rows'] as $r) {
                $sheet->setCellValue("A{$row}", $r['date']->format('d M Y'));
                $sheet->setCellValueExplicit("B{$row}", (string) ($r['voucher'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("C{$row}", $r['particular']);
                $sheet->setCellValueExplicit("D{$row}", (string) ($r['reference'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("E{$row}", $r['note'] ?? '');
                $sheet->setCellValue("F{$row}", $r['dr'] ?: null);
                $sheet->setCellValue("G{$row}", $r['cr'] ?: null);
                $sheet->setCellValue("H{$row}", $r['balance']);
                $row++;
            }

            if (!$item['rows']) {
                $sheet->setCellValue("A{$row}", 'No transactions in this period')->mergeCells("A{$row}:H{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
                $row++;
            }

            $sheet->setCellValue("A{$row}", 'Total / Closing balance (' . $service->balanceLabel($item['closing']) . ')')->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("F{$row}", $item['dr']);
            $sheet->setCellValue("G{$row}", $item['cr']);
            $sheet->setCellValue("H{$row}", $item['closing']);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);

            $sheet->getStyle("A{$first}:H{$row}")->applyFromArray($thin);
            $sheet->getStyle("F{$first}:G{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
            $sheet->getStyle("H{$first}:H{$row}")->getNumberFormat()->setFormatCode($balanceFormat);
            $row += 2;
        }

        // Grand summary
        $t      = $report['totals'];
        $labels = $report['config']['labels'];
        $summary = [
            ['Parties', $t['parties'], null],
            ['Balance brought forward', $t['opening'], $balanceFormat],
            ['Total debit', $t['dr'], $moneyFormat],
            ['Total credit', $t['cr'], $moneyFormat],
            ['Total ' . strtolower($labels['Dr']) . ' (Dr balances)', $t['balance_dr'], $moneyFormat],
            ['Total ' . strtolower($labels['Cr']) . ' (Cr balances)', $t['balance_cr'], $moneyFormat],
            ['Net closing balance', $t['closing'], $balanceFormat],
        ];
        $sheet->setCellValue("A{$row}", 'Summary')->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $row++;
        $start = $row;
        foreach ($summary as [$label, $value, $format]) {
            $sheet->setCellValue("A{$row}", $label)->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $value);
            if ($format) {
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode($format);
            }
            $row++;
        }
        $sheet->getStyle("A{$start}:C" . ($row - 1))->applyFromArray($thin);
        $sheet->getStyle("A{$start}:A" . ($row - 1))->getFont()->setBold(true);

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)->setFitToHeight(0);

        $fileName = str_replace(' ', '-', $report['config']['title']) . '-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /* ------------------------------------------------------------------ */
    /*  Transactions ledger                                               */
    /* ------------------------------------------------------------------ */

    public function transactionLedger()
    {
        return view('admin.reports.transaction-ledger', [
            'types'   => TransactionLedgerReport::TYPES,
            'parties' => DB::table('parties')->where('deleted', 'No')->orderBy('name')->get(['id', 'code', 'name', 'type']),
        ]);
    }

    /** JSON: rendered report body + summary, for the page. */
    public function transactionLedgerData(Request $request)
    {
        $validator = $this->transactionLedgerValidator($request);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $report = (new TransactionLedgerReport($validator->validated()))->build();

            return response()->json([
                'status'  => true,
                'html'    => view('admin.reports.partials.transaction-ledger-table', $report)->render(),
                'summary' => [
                    'entries' => $report['totals']['entries'],
                    'dr'      => number_format($report['totals']['dr'], 2),
                    'cr'      => number_format($report['totals']['cr'], 2),
                    'types'   => $report['by_type']->count(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Transaction ledger error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['status' => false, 'message' => 'Unable to build the report.'], 500);
        }
    }

    public function transactionLedgerPdf(Request $request)
    {
        $validator = $this->transactionLedgerValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report = (new TransactionLedgerReport($validator->validated()))->build();

        $pdf = PDF::loadView('admin.reports.transaction-ledger-pdf', $report + [
            'company'   => $this->company(),
            'printedAt' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true);

        $this->addPageNumbers($pdf);

        $fileName = 'Transactions-Ledger-' . $report['from']->format('Y-m-d') . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function transactionLedgerExcel(Request $request)
    {
        $validator = $this->transactionLedgerValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report  = (new TransactionLedgerReport($validator->validated()))->build();
        $company = $this->company();

        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Transactions Ledger');
        $book->getProperties()->setCreator($company['name'])->setTitle('Transactions Ledger');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $cols   = ['A' => 'SL', 'B' => 'Date & Time', 'C' => 'Voucher No', 'D' => 'Type', 'E' => 'Party', 'F' => 'Reference', 'G' => 'Account / Method', 'H' => 'Remarks', 'I' => 'Debit', 'J' => 'Credit'];
        $widths = ['A' => 6, 'B' => 21, 'C' => 14, 'D' => 24, 'E' => 24, 'F' => 15, 'G' => 22, 'H' => 34, 'I' => 14, 'J' => 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Header block
        $sheet->setCellValue('A1', $company['name'])->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', 'Transactions Ledger')->mergeCells('A2:J2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1E3A8A');

        $row = 3;
        foreach ($report['filters_text'] as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ':')->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $value)->mergeCells("C{$row}:J{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Generated:')->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", now()->format('d M Y, h:i A'))->mergeCells("C{$row}:J{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row += 2;

        $moneyFormat = '#,##0.00;-#,##0.00;"-"';
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]]];
        $head = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
        ];

        foreach ($cols as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray($head);
        $sheet->getStyle("I{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $first = $headRow = $row;
        $row++;

        foreach ($report['rows'] as $i => $r) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $r->datetime->format('d M Y h:i:s A'));
            $sheet->setCellValueExplicit("C{$row}", (string) ($r->voucher ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("D{$row}", $r->type);
            $sheet->setCellValue("E{$row}", $r->party ? $r->party . ($r->party_type ? ' (' . $r->party_type . ')' : '') : '');
            $sheet->setCellValueExplicit("F{$row}", (string) ($r->reference ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $r->account ?? '');
            $sheet->setCellValue("H{$row}", $r->remarks ?? '');
            $sheet->setCellValue("I{$row}", $r->dr ?: null);
            $sheet->setCellValue("J{$row}", $r->cr ?: null);
            $row++;
        }

        if ($report['rows']->isEmpty()) {
            $sheet->setCellValue("A{$row}", 'No transactions in this period')->mergeCells("A{$row}:J{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Total (' . $report['totals']['entries'] . ' entries)')->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("I{$row}", $report['totals']['dr']);
        $sheet->setCellValue("J{$row}", $report['totals']['cr']);
        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$first}:J{$row}")->applyFromArray($thin);
        $sheet->getStyle("I{$first}:J{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
        $row += 2;

        // Summary by type
        $sheet->setCellValue("A{$row}", 'Summary by type')->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $row++;
        foreach (['A' => 'Type', 'E' => 'Entries', 'F' => 'Debit', 'G' => 'Credit'] as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($head);
        $start = $row;
        $row++;
        foreach ($report['by_type'] as $t) {
            $sheet->setCellValue("A{$row}", $t['type'])->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("E{$row}", $t['count']);
            $sheet->setCellValue("F{$row}", $t['dr']);
            $sheet->setCellValue("G{$row}", $t['cr']);
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Total')->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("E{$row}", $report['totals']['entries']);
        $sheet->setCellValue("F{$row}", $report['totals']['dr']);
        $sheet->setCellValue("G{$row}", $report['totals']['cr']);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$start}:G{$row}")->applyFromArray($thin);
        $sheet->getStyle("F{$start}:G{$row}")->getNumberFormat()->setFormatCode($moneyFormat);

        $sheet->freezePane('A' . ($headRow + 1));
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headRow, $headRow);

        $fileName = 'Transactions-Ledger-' . $report['from']->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    private function transactionLedgerValidator(Request $request)
    {
        return Validator::make($request->query(), [
            'from'     => ['required', 'date'],
            'to'       => ['required', 'date', 'after_or_equal:from'],
            'type'     => ['nullable', Rule::in(array_keys(TransactionLedgerReport::TYPES))],
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('deleted', 'No')],
        ], [
            'from.required'     => 'Select the start date and time.',
            'to.required'       => 'Select the end date and time.',
            'to.after_or_equal' => 'End date and time cannot be before the start.',
        ]);
    }

    private function partyLedgerValidator(Request $request, string $ledger)
    {
        $types = PartyLedgerReport::LEDGERS[$ledger]['types'];

        return Validator::make($request->query(), [
            'from'       => ['required', 'date'],
            'to'         => ['required', 'date', 'after_or_equal:from'],
            'party_id'   => ['nullable', 'integer', Rule::exists('parties', 'id')->whereIn('type', $types)->where('deleted', 'No')],
            'party_type' => ['nullable', Rule::in($types)],
            'hide_zero'  => ['nullable', 'boolean'],
        ], [
            'from.required'     => 'Select the start date.',
            'to.required'       => 'Select the end date.',
            'to.after_or_equal' => 'End date cannot be before the start date.',
            'party_id.exists'   => 'Select a valid ' . ($ledger === 'customer' ? 'customer' : 'supplier') . '.',
        ]);
    }

    /** "Page X of Y" in the footer, drawn on the canvas (no inline PHP in the template). */
    private function addPageNumbers($pdf): void
    {
        $pdf->render();   // wrapper's render() marks it rendered, so stream()/download() won't render again
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 95, $canvas->get_height() - 28,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.58, 0.64, 0.72]
        );
    }

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
