<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use App\Models\Admin\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

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
            'types'      => self::STOCK_TYPES,
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
            $report = $this->buildStockLedger($validator->validated());

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

        $report = $this->buildStockLedger($validator->validated());

        $pdf = SettingController::pdf('admin.reports.stock-ledger-pdf', $report + [
            'printedAt' => now()->format('d M Y, h:i A'),
        ], 'landscape');

        $fileName = 'Stock-Ledger-' . now()->format('Y-m-d') . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function stockLedgerExcel(Request $request)
    {
        $validator = $this->stockLedgerValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report  = $this->buildStockLedger($validator->validated());
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
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));

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
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::xl('border_strong')]]]];

        foreach ($report['items'] as $item) {
            // Product title
            $title = $item['product'] . ($item['sku'] ? '  [' . $item['sku'] . ']' : '') . ($item['unit'] ? '  (' . $item['unit'] . ')' : '');
            $sheet->setCellValue("A{$row}", $title)->mergeCells("A{$row}:H{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => self::xl('on_brand')], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('brand')]],
            ]);
            $row++;

            // Column headings
            foreach ($cols as $col => $label) {
                $sheet->setCellValue("{$col}{$row}", $label);
            }
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('border')]],
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
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::xl('muted'));
                $row++;
            }

            // Totals + closing
            $sheet->setCellValue("A{$row}", 'Total / Closing balance')->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("F{$row}", $item['in']);
            $sheet->setCellValue("G{$row}", $item['out']);
            $sheet->setCellValue("H{$row}", $item['closing']);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('soft')]],
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
        $config = self::PARTY_LEDGERS[$ledger];

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
            $report  = $this->buildPartyLedger($ledger, $validator->validated());
            $t       = $report['totals'];

            return response()->json([
                'status'  => true,
                'html'    => view('admin.reports.partials.party-ledger-table', $report)->render(),
                'summary' => [
                    'parties' => $t['parties'],
                    'dr'      => number_format($t['dr'], 2),
                    'cr'      => number_format($t['cr'], 2),
                    'closing' => self::partyBalanceText($t['closing']),
                    'label'   => $report['balance_label']($t['closing']),
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

        $report  = $this->buildPartyLedger($ledger, $validator->validated());

        $pdf = SettingController::pdf('admin.reports.party-ledger-pdf', $report + [
            'printedAt' => now()->format('d M Y, h:i A'),
        ], 'portrait');

        $fileName = str_replace(' ', '-', $report['config']['title']) . '-' . now()->format('Y-m-d') . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function partyLedgerExcel(Request $request, string $ledger)
    {
        $validator = $this->partyLedgerValidator($request, $ledger);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report  = $this->buildPartyLedger($ledger, $validator->validated());
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
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));

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
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::xl('border_strong')]]]];

        foreach ($report['items'] as $item) {
            // Party title
            $title = $item['name'] . ($item['code'] ? '  [' . $item['code'] . ']' : '') . '  (' . $item['type'] . ')'
                . ($item['phone'] ? '  ·  ' . $item['phone'] : '');
            $sheet->setCellValue("A{$row}", $title)->mergeCells("A{$row}:H{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => self::xl('on_brand')], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('brand')]],
            ]);
            $row++;

            foreach ($cols as $col => $label) {
                $sheet->setCellValue("{$col}{$row}", $label);
            }
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('border')]],
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
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::xl('muted'));
                $row++;
            }

            $sheet->setCellValue("A{$row}", 'Total / Closing balance (' . $report['balance_label']($item['closing']) . ')')->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("F{$row}", $item['dr']);
            $sheet->setCellValue("G{$row}", $item['cr']);
            $sheet->setCellValue("H{$row}", $item['closing']);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('soft')]],
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
        $liquid = fn ($layer) => DB::table('chart_of_accounts')->where('account_type', 'Liquid Asset')->where('layer_level', $layer)
            ->where('deleted', 'No')->orderBy('account_code')->get(['id', 'account_name', 'parent_id']);

        return view('admin.reports.transaction-ledger', [
            'types'    => self::TRANSACTION_TYPES,
            'methods'  => $liquid(2),
            'accounts' => $liquid(3),
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
            $report = $this->buildTransactionLedger($validator->validated());

            return response()->json([
                'status'  => true,
                'html'    => view('admin.reports.partials.transaction-ledger-table', $report)->render(),
                'summary' => [
                    'opening' => number_format($report['totals']['opening'], 2),
                    'in'      => number_format($report['totals']['in'], 2),
                    'out'     => number_format($report['totals']['out'], 2),
                    'closing' => number_format($report['totals']['closing'], 2),
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

        $report = $this->buildTransactionLedger($validator->validated());

        $pdf = SettingController::pdf('admin.reports.transaction-ledger-pdf', $report + [
            'printedAt' => now()->format('d M Y, h:i A'),
        ], 'portrait');

        $fileName = 'Transactions-Ledger-' . $report['from']->format('Y-m-d') . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function transactionLedgerExcel(Request $request)
    {
        $validator = $this->transactionLedgerValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report  = $this->buildTransactionLedger($validator->validated());
        $company = $this->company();

        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Transactions Ledger');
        $book->getProperties()->setCreator($company['name'])->setTitle('Transactions Ledger');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        foreach (['A' => 6, 'B' => 42, 'C' => 30, 'D' => 18, 'E' => 15, 'F' => 15, 'G' => 16] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $moneyFormat = '#,##0.00;-#,##0.00;"0.00"';
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::xl('border_strong')]]]];
        $head = [
            'font' => ['bold' => true, 'color' => ['rgb' => self::xl('on_brand')]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('brand')]],
        ];
        $band = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('soft')]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        // Header block
        $sheet->setCellValue('A1', $company['name'])->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', 'Transactions Ledger')->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));

        $row = 3;
        $meta = $report['filters_text'] + ['Printed Date' => now()->format('d-m-Y h:i A'), 'Printed By' => $report['printedBy']];
        foreach (array_filter($meta) as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ':')->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $value)->mergeCells("C{$row}:G{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $row++;

        foreach ($report['methods'] as $sec) {
            $label = $sec['method'];

            $sheet->setCellValue("A{$row}", $label . ' Report')->mergeCells("A{$row}:G{$row}");
            $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($band);
            $first = $row;
            $row++;

            foreach (['A' => 'SL', 'B' => 'Particulars', 'C' => 'Remarks', 'D' => 'Voucher Type', 'E' => $label . ' In', 'F' => $label . ' Out', 'G' => 'Balance'] as $col => $text) {
                $sheet->setCellValue("{$col}{$row}", $text);
            }
            $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($head);
            $sheet->getStyle("E{$row}:G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;

            foreach ($sec['rows'] as $i => $r) {
                $sheet->setCellValue("A{$row}", $i + 1);
                $sheet->setCellValue("B{$row}", $r['particulars'] . "\n" . $r['datetime']->format('d M Y h:i A') . ($r['refs'] ? ' · ' . $r['refs'] : ''));
                $sheet->setCellValue("C{$row}", trim($r['account'] . ($r['remarks'] ? "\n" . $r['remarks'] : '')));
                $sheet->setCellValue("D{$row}", $r['type']);
                $sheet->setCellValue("E{$row}", $r['in'] ?: null);
                $sheet->setCellValue("F{$row}", $r['out'] ?: null);
                $sheet->setCellValue("G{$row}", $r['balance']);
                $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $row++;
            }

            if (!$sec['rows']) {
                $sheet->setCellValue("A{$row}", 'No transactions in this period')->mergeCells("A{$row}:G{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::xl('muted'));
                $row++;
            }

            $sheet->getStyle("A{$first}:G" . ($row - 1))->applyFromArray($thin);
            $sheet->getStyle("E{$first}:G" . ($row - 1))->getNumberFormat()->setFormatCode($moneyFormat);
            $row++;

            // Two-column summary, as on the printed cash report.
            $left = [
                "Today {$label} In"              => $sec['in'],
                "Today {$label} Out"             => $sec['out'],
                "Today {$label} Closing Balance" => $sec['period'],
                "Net {$label} Closing Balance"   => $sec['closing'],
            ];
            $right = [
                "{$label} Opening Balance"       => $sec['opening'],
                "Today {$label} Closing Balance" => $sec['period'],
                "Net {$label} Closing Balance"   => $sec['closing'],
            ];
            $start = $row;
            $l = array_keys($left);
            $r = array_keys($right);
            for ($k = 0; $k < 4; $k++) {
                if (isset($l[$k])) {
                    $sheet->setCellValue("A{$row}", $l[$k])->mergeCells("A{$row}:B{$row}");
                    $sheet->setCellValue("C{$row}", $left[$l[$k]]);
                }
                if (isset($r[$k])) {
                    $sheet->setCellValue("D{$row}", $r[$k])->mergeCells("D{$row}:F{$row}");
                    $sheet->setCellValue("G{$row}", $right[$r[$k]]);
                }
                $row++;
            }
            $sheet->getStyle("A{$start}:G" . ($row - 1))->getFont()->setBold(true);
            $sheet->getStyle("C{$start}:C" . ($row - 1))->getNumberFormat()->setFormatCode($moneyFormat);
            $sheet->getStyle("G{$start}:G" . ($row - 1))->getNumberFormat()->setFormatCode($moneyFormat);
            $sheet->getStyle("C{$start}:C" . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $row += 2;
        }

        // Closing summary
        $sheet->setCellValue("A{$row}", 'Closing Summary')->mergeCells("A{$row}:G{$row}");
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($band);
        $first = $row;
        $row++;
        foreach (['A' => 'SL', 'B' => 'Payment Method', 'C' => 'Ledger Account', 'D' => 'Opening Balance', 'E' => 'In', 'F' => 'Out', 'G' => 'Closing Balance'] as $col => $text) {
            $sheet->setCellValue("{$col}{$row}", $text);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($head);
        $row++;
        foreach ($report['summary'] as $i => $a) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $a['method']);
            $sheet->setCellValue("C{$row}", $a['account']);
            $sheet->setCellValue("D{$row}", $a['opening']);
            $sheet->setCellValue("E{$row}", $a['in']);
            $sheet->setCellValue("F{$row}", $a['out']);
            $sheet->setCellValue("G{$row}", $a['closing']);
            $row++;
        }
        $t = $report['totals'];
        $sheet->setCellValue("A{$row}", 'Total')->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", $t['opening']);
        $sheet->setCellValue("E{$row}", $t['in']);
        $sheet->setCellValue("F{$row}", $t['out']);
        $sheet->setCellValue("G{$row}", $t['closing']);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$first}:G{$row}")->applyFromArray($thin);
        $sheet->getStyle("D{$first}:G{$row}")->getNumberFormat()->setFormatCode($moneyFormat);

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)->setFitToHeight(0);

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
            'type'       => ['nullable', Rule::in(array_keys(self::TRANSACTION_TYPES))],
            'method_id'  => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('account_type', 'Liquid Asset')->where('layer_level', 2)],
            'account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('account_type', 'Liquid Asset')->where('layer_level', 3)],
        ], [
            'from.required'     => 'Select the start date and time.',
            'to.required'       => 'Select the end date and time.',
            'to.after_or_equal' => 'End date and time cannot be before the start.',
        ]);
    }

    private function partyLedgerValidator(Request $request, string $ledger)
    {
        $types = self::PARTY_LEDGERS[$ledger]['types'];

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

    /* ------------------------------------------------------------------ */
    /*  Salary sheet (columns follow the Scale / Contractual setting)     */
    /* ------------------------------------------------------------------ */

    public function salarySheet(Request $request)
    {
        // ?month=Y-m opens that month (links from the dashboard's unpaid-salary list); ?pay=1 opens Pay Salary.
        $m = (string) $request->query('month', '');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now()->format('Y-m');

        // Ledgers offered by Process / Pay Salary: those usable in the logged-in branch, defaults from Payroll Settings or by name.
        return view('admin.reports.salary-sheet', [
            'departments'       => DB::table('departments')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']),
            'payroll'           => PayrollSetupController::salarySettings(),
            'paymentAccounts'   => PayrollSetupController::payrollLedgers('Liquid Asset'),
            'expenseAccounts'   => PayrollSetupController::payrollLedgers('Expense'),
            'liabilityAccounts' => PayrollSetupController::payrollLedgers('Liability'),
            'assetAccounts'     => PayrollSetupController::loanLedgers(),
            'ledgerDefaults'    => PayrollSetupController::payrollLedgerDefaults(),
            'initialMonth'      => $month,
            'openPay'           => $request->boolean('pay'),
        ]);
    }

    public function salarySheetData(Request $request)
    {
        $validator = $this->salarySheetValidator($request);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $report = self::buildSalarySheet($validator->validated());
            $m = fn ($v) => number_format($v, 2);

            return response()->json([
                'status'  => true,
                'html'    => view('admin.reports.partials.salary-sheet-table', $report)->render(),
                'summary' => [
                    'employees' => $report['count'],
                    'net'       => $m($report['totals']['net_payable']),
                    'paid'      => $m($report['totals']['paid_amount']),
                    'due'       => $m($report['totals']['due']),
                ],
                'to_process' => $report['to_process'],
                'to_pay'     => $report['to_pay'],
                'paid_count' => $report['paid_count'],
            ]);
        } catch (Throwable $e) {
            Log::error('Salary sheet error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['status' => false, 'message' => 'Unable to build the salary sheet.'], 500);
        }
    }

    public function salarySheetPdf(Request $request)
    {
        $validator = $this->salarySheetValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report = self::buildSalarySheet($validator->validated());

        $pdf = SettingController::pdf('admin.reports.salary-sheet-pdf', $report + [
            'printedAt' => now()->format('d M Y, h:i A'),
        ], 'landscape');

        $fileName = 'Salary-Sheet-' . $report['month']->format('Y-m') . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function salarySheetExcel(Request $request)
    {
        $validator = $this->salarySheetValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report  = self::buildSalarySheet($validator->validated());
        $company = $this->company();

        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Salary ' . $report['month']->format('M Y'));
        $book->getProperties()->setCreator($company['name'])->setTitle('Salary Sheet');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // Column A = SL, then the report columns, then a signature column.
        $keys  = array_keys($report['columns']);
        $colOf = [];
        foreach ($keys as $n => $key) {
            $colOf[$key] = Coordinate::stringFromColumnIndex($n + 2);
        }
        $last = Coordinate::stringFromColumnIndex(count($keys) + 2);

        $widths = ['employee_code' => 10, 'name' => 24, 'designation' => 20, 'grade_step' => 14];
        $sheet->getColumnDimension('A')->setWidth(5);
        foreach ($keys as $key) {
            $sheet->getColumnDimension($colOf[$key])->setWidth($widths[$key] ?? 14);
        }
        $sheet->getColumnDimension($last)->setWidth(16);

        // Header block
        $sheet->setCellValue('A1', $company['name'])->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', 'Salary Sheet — ' . $report['month']->format('F Y'))->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));

        $row = 3;
        foreach ($report['filters_text'] as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ':')->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $value)->mergeCells("C{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $row++;

        $moneyFormat = '#,##0.00;[Red]-#,##0.00;"-"';
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::xl('border_strong')]]]];
        $firstMoney = $colOf[$report['money_keys'][0]];
        $lastMoney  = $colOf[end($report['money_keys'])];

        // Column headings (repeated on every printed page)
        $sheet->setCellValue("A{$row}", 'SL');
        foreach ($report['columns'] as $key => [$label]) {
            $sheet->setCellValue($colOf[$key] . $row, $label);
        }
        $sheet->setCellValue("{$last}{$row}", 'Signature');
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::xl('on_brand')]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('brand')]],
        ]);
        $sheet->getStyle("{$firstMoney}{$row}:{$lastMoney}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($row, $row);
        $first = $row;
        $row++;

        $sl = 1;
        $totalRow = function (string $label, array $totals, string $fill) use ($sheet, &$row, $colOf, $report, $firstMoney, $last) {
            $labelEnd = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($firstMoney) - 1);
            $sheet->setCellValue("A{$row}", $label)->mergeCells("A{$row}:{$labelEnd}{$row}");
            foreach ($report['money_keys'] as $k) {
                $sheet->setCellValue($colOf[$k] . $row, $totals[$k]);
            }
            $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
            ]);
            $row++;
        };

        foreach ($report['groups'] as $group) {
            $sheet->setCellValue("A{$row}", $group['name'] . ' (' . count($group['rows']) . ')')->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => self::xl('accent')]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('border')]],
            ]);
            $row++;

            foreach ($group['rows'] as $r) {
                $sheet->setCellValue("A{$row}", $sl++);
                foreach ($keys as $key) {
                    if ($key === 'employee_code') {
                        $sheet->setCellValueExplicit($colOf[$key] . $row, (string) $r[$key], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    } else {
                        $sheet->setCellValue($colOf[$key] . $row, $r[$key]);
                    }
                }
                $sheet->getRowDimension($row)->setRowHeight(22);
                $row++;
            }

            if (count($report['groups']) > 1) {
                $totalRow('Subtotal — ' . $group['name'], $group['totals'], self::xl('soft'));
            }
        }

        if (!$report['groups']) {
            $sheet->setCellValue("A{$row}", 'No active employees for this month.')->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::xl('muted'));
            $row++;
        } else {
            $totalRow('Grand Total (' . $report['count'] . ' employees)', $report['totals'], self::xl('border_strong'));
        }

        $sheet->getStyle("A{$first}:{$last}" . ($row - 1))->applyFromArray($thin);
        $sheet->getStyle("{$firstMoney}{$first}:{$lastMoney}" . ($row - 1))->getNumberFormat()->setFormatCode($moneyFormat);
        $sheet->freezePane('A' . ($first + 1));

        // Signature lines
        $row += 3;
        // Three equal blocks with a gap column between them.
        $span = max(2, intdiv(count($keys) + 2, 3));
        foreach (['Prepared by', 'Checked by', 'Approved by'] as $n => $label) {
            $start = Coordinate::stringFromColumnIndex(1 + $n * $span);
            $end   = Coordinate::stringFromColumnIndex($n * $span + $span - 1);
            $sheet->setCellValue("{$start}{$row}", $label)->mergeCells("{$start}{$row}:{$end}{$row}");
            $sheet->getStyle("{$start}{$row}:{$end}{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        }

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)->setFitToHeight(0);

        $fileName = 'Salary-Sheet-' . $report['month']->format('Y-m') . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /* ------------------------------------------------------------------ */
    /*  Daily cash & bank book (Liquid Asset accounts, method wise)       */
    /* ------------------------------------------------------------------ */

    public function liquidAsset()
    {
        $liquid = fn ($layer) => DB::table('chart_of_accounts')->where('account_type', 'Liquid Asset')->where('layer_level', $layer)
            ->where('deleted', 'No')->orderBy('account_code')->get(['id', 'account_name', 'parent_id']);

        return view('admin.reports.liquid-asset', ['methods' => $liquid(2), 'accounts' => $liquid(3)]);
    }

    public function liquidAssetData(Request $request)
    {
        $validator = $this->liquidAssetValidator($request);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $report = $this->buildLiquidAsset($validator->validated());
            $m = fn ($v) => number_format($v, 2);

            return response()->json([
                'status'  => true,
                'html'    => view('admin.reports.partials.liquid-asset-table', $report)->render(),
                'summary' => [
                    'opening' => $m($report['totals']['opening']),
                    'in'      => $m($report['totals']['in']),
                    'out'     => $m($report['totals']['out']),
                    'closing' => $m($report['totals']['closing']),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Liquid asset report error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['status' => false, 'message' => 'Unable to build the report.'], 500);
        }
    }

    public function liquidAssetPdf(Request $request)
    {
        $validator = $this->liquidAssetValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report = $this->buildLiquidAsset($validator->validated());

        $pdf = SettingController::pdf('admin.reports.liquid-asset-pdf', $report + [
            'printedAt' => now()->format('d M Y, h:i A'),
        ], 'landscape');

        $fileName = 'Cash-Bank-Book-' . $report['from']->format('Y-m-d') . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function liquidAssetExcel(Request $request)
    {
        $validator = $this->liquidAssetValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report  = $this->buildLiquidAsset($validator->validated());
        $company = $this->company();

        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Cash & Bank Book');
        $book->getProperties()->setCreator($company['name'])->setTitle('Daily Cash & Bank Book');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        foreach (['A' => 11, 'B' => 22, 'C' => 15, 'D' => 22, 'E' => 16, 'F' => 34, 'G' => 14, 'H' => 14, 'I' => 15] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $sheet->setCellValue('A1', $company['name'])->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', 'Daily Cash & Bank Book (Liquid Assets)')->mergeCells('A2:I2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));

        $row = 3;
        foreach ($report['filters_text'] as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ':')->setCellValue("B{$row}", $value)->mergeCells("B{$row}:I{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $row++;

        $moneyFormat = '#,##0.00;[Red]-#,##0.00;"-"';
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::xl('border_strong')]]]];
        $band = fn ($range, $rgb, $font = null) => $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $font ?? self::xl('ink')]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
        ]);

        foreach ($report['days'] as $day) {
            $t = $day['totals'];
            $sheet->setCellValue("A{$row}", $day['date']->format('l, d M Y'))->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("G{$row}", 'Opening ' . number_format($t['opening'], 2))->mergeCells("G{$row}:I{$row}");
            $band("A{$row}:I{$row}", self::xl('brand'), self::xl('on_brand'));
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
            $first = $row;

            foreach (['Time', 'Type', 'Voucher', 'Party', 'Reference', 'Remarks', 'In', 'Out', 'Balance'] as $n => $label) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($n + 1) . $row, $label);
            }
            $band("A{$row}:I{$row}", self::xl('border'));
            $sheet->getStyle("G{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;

            foreach ($day['accounts'] as $a) {
                $sheet->setCellValue("A{$row}", $a['method'] . ' › ' . $a['account'])->mergeCells("A{$row}:H{$row}");
                $sheet->setCellValue("I{$row}", $a['opening']);
                $band("A{$row}:I{$row}", self::xl('soft'), self::xl('accent'));
                $row++;

                foreach ($a['rows'] as $m) {
                    $sheet->setCellValue("A{$row}", Carbon::parse($m['datetime'])->format('h:i A'));
                    $sheet->setCellValue("B{$row}", $m['type']);
                    $sheet->setCellValueExplicit("C{$row}", (string) $m['voucher'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue("D{$row}", $m['party'] ?? '');
                    $sheet->setCellValueExplicit("E{$row}", (string) ($m['reference'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue("F{$row}", $m['remarks'] ?? '');
                    $sheet->setCellValue("G{$row}", $m['in'] ?: null);
                    $sheet->setCellValue("H{$row}", $m['out'] ?: null);
                    $sheet->setCellValue("I{$row}", $m['balance']);
                    $row++;
                }

                $sheet->setCellValue("A{$row}", 'Closing — ' . $a['account'])->mergeCells("A{$row}:F{$row}");
                $sheet->setCellValue("G{$row}", $a['in']);
                $sheet->setCellValue("H{$row}", $a['out']);
                $sheet->setCellValue("I{$row}", $a['closing']);
                $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
                $row++;
            }

            if (!$day['accounts']) {
                $sheet->setCellValue("A{$row}", 'No liquid asset balance or movement.')->mergeCells("A{$row}:I{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB(self::xl('muted'));
                $row++;
            }

            foreach ($day['methods'] as $mt) {
                $sheet->setCellValue("A{$row}", $mt['method'] . ' total (opening ' . number_format($mt['opening'], 2) . ')')->mergeCells("A{$row}:F{$row}");
                $sheet->setCellValue("G{$row}", $mt['in']);
                $sheet->setCellValue("H{$row}", $mt['out']);
                $sheet->setCellValue("I{$row}", $mt['closing']);
                $band("A{$row}:I{$row}", self::xl('soft'));
                $row++;
            }

            $sheet->setCellValue("A{$row}", 'Day total — closing becomes next day\'s opening')->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("G{$row}", $t['in']);
            $sheet->setCellValue("H{$row}", $t['out']);
            $sheet->setCellValue("I{$row}", $t['closing']);
            $band("A{$row}:I{$row}", self::xl('border_strong'));

            $sheet->getStyle("A{$first}:I{$row}")->applyFromArray($thin);
            $sheet->getStyle("G{$first}:I{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
            $row += 2;
        }

        // Period summary by transaction type
        $sheet->setCellValue("A{$row}", 'Summary by type')->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $row++;
        $start = $row;
        foreach (['Type', 'Entries', 'In', 'Out'] as $n => $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($n + 1) . $row, $label);
        }
        $band("A{$row}:D{$row}", self::xl('border'));
        $row++;
        foreach ($report['by_type'] as $tp) {
            $sheet->fromArray([$tp['type'], $tp['count'], $tp['in'], $tp['out']], null, "A{$row}");
            $row++;
        }
        $sheet->fromArray(['Opening ' . number_format($report['totals']['opening'], 2) . ' / Closing ' . number_format($report['totals']['closing'], 2),
            $report['totals']['entries'], $report['totals']['in'], $report['totals']['out']], null, "A{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$start}:D{$row}")->applyFromArray($thin);
        $sheet->getStyle("C{$start}:D{$row}")->getNumberFormat()->setFormatCode($moneyFormat);

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)->setFitToHeight(0);

        $fileName = 'Cash-Bank-Book-' . $report['from']->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function liquidAssetValidator(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from'       => ['required', 'date'],
            'to'         => ['required', 'date', 'after_or_equal:from'],
            'method_id'  => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('account_type', 'Liquid Asset')->where('layer_level', 2)],
            'account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('account_type', 'Liquid Asset')->where('layer_level', 3)],
        ], [
            'from.required'     => 'Select the start date.',
            'to.after_or_equal' => 'End date cannot be before the start date.',
        ]);

        $validator->after(function ($v) use ($request) {
            if (!$v->errors()->any() && Carbon::parse($request->query('from'))->diffInDays(Carbon::parse($request->query('to'))) >= self::LIQUID_MAX_DAYS) {
                $v->errors()->add('to', 'Choose at most ' . self::LIQUID_MAX_DAYS . ' days.');
            }
        });

        return $validator;
    }

    /* ------------------------------------------------------------------ */
    /*  PF ledger of one employee, grouped by increment / salary period   */
    /* ------------------------------------------------------------------ */

    public function pfLedger(Request $request)
    {
        // ?employee_id= preselects (link from the employee list).
        $selected = $request->filled('employee_id')
            ? BranchController::scope(DB::table('employees'), 'branch_id')->where('id', (int) $request->query('employee_id'))->where('deleted', 'No')->first(['id', 'name', 'employee_code'])
            : null;

        return view('admin.reports.pf-ledger', ['selected' => $selected, 'payroll' => PayrollSetupController::salarySettings()]);
    }

    public function pfLedgerData(Request $request)
    {
        $validator = $this->pfLedgerValidator($request);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $report = $this->buildPfLedger($validator->validated());
            $m = fn ($v) => number_format($v, 2);

            return response()->json([
                'status'  => true,
                'html'    => view('admin.reports.partials.pf-ledger-table', $report)->render(),
                'summary' => [
                    'opening'    => $m($report['opening']),
                    'total'      => $m($report['total']),
                    'projected'  => $m($report['projected_total']),
                    'closing'    => $m($report['closing']),
                    'increments' => $report['increments'],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('PF ledger error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['status' => false, 'message' => 'Unable to build the PF ledger.'], 500);
        }
    }

    public function pfLedgerPdf(Request $request)
    {
        $validator = $this->pfLedgerValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report = $this->buildPfLedger($validator->validated());

        $pdf = SettingController::pdf('admin.reports.pf-ledger-pdf', $report + [
            'printedAt' => now()->format('d M Y, h:i A'),
        ], 'portrait');

        $fileName = 'PF-Ledger-' . $report['employee']->employee_code . '.pdf';

        return $request->boolean('download') ? $pdf->download($fileName) : $pdf->stream($fileName);
    }

    public function pfLedgerExcel(Request $request)
    {
        $validator = $this->pfLedgerValidator($request);
        abort_if($validator->fails(), 422, $validator->errors()->first());

        $report  = $this->buildPfLedger($validator->validated());
        $company = $this->company();

        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('PF Ledger');
        $book->getProperties()->setCreator($company['name'])->setTitle('PF Ledger');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        foreach (['A' => 26, 'B' => 14, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 16] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $sheet->setCellValue('A1', $company['name'])->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $sheet->setCellValue('A2', 'Provident Fund Ledger')->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::xl('accent'));

        $row = 3;
        foreach ($report['filters_text'] as $label => $value) {
            $sheet->setCellValue("A{$row}", $label . ':')->setCellValue("B{$row}", $value)->mergeCells("B{$row}:F{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $row++;

        $moneyFormat = '#,##0.00;[Red]-#,##0.00;"-"';
        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::xl('border_strong')]]]];
        $first = $row;

        foreach (['Month', 'Basic', 'Gross', 'Deducted PF', 'Projected PF', 'Balance'] as $n => $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($n + 1) . $row, $label);
        }
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::xl('on_brand')]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('brand')]],
        ]);
        $sheet->getStyle("B{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($row, $row);
        $row++;

        $sheet->setCellValue("A{$row}", 'Opening balance (deducted PF)')->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("F{$row}", $report['opening']);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setItalic(true);
        $row++;

        foreach ($report['periods'] as $n => $period) {
            $e = $period['entry'];
            $sheet->setCellValue("A{$row}", $this->pfPeriodTitle($n, $e))->mergeCells("A{$row}:F{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => self::xl('accent')]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('border')]],
            ]);
            $row++;

            foreach ($period['rows'] as $r) {
                $sheet->setCellValue("A{$row}", $r['month']->format('M Y') . ($r['source'] ? ' · ' . $r['source'] : ' · projected'));
                $sheet->setCellValue("B{$row}", $r['basic']);
                $sheet->setCellValue("C{$row}", $r['gross']);
                $sheet->setCellValue("D{$row}", $r['deducted']);
                $sheet->setCellValue("E{$row}", $r['projected']);
                $sheet->setCellValue("F{$row}", $r['balance']);
                $row++;
            }

            $sheet->setCellValue("A{$row}", 'Period total (' . count($period['rows']) . ' months)')->mergeCells("A{$row}:C{$row}");
            $sheet->setCellValue("D{$row}", $period['deducted']);
            $sheet->setCellValue("E{$row}", $period['projected']);
            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('soft')]],
            ]);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Total / Closing balance (deducted only)')->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", $report['deducted_total']);
        $sheet->setCellValue("E{$row}", $report['projected_total']);
        $sheet->setCellValue("F{$row}", $report['closing']);
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::xl('border_strong')]],
        ]);

        $sheet->getStyle("A{$first}:F{$row}")->applyFromArray($thin);
        $sheet->getStyle("B{$first}:F{$row}")->getNumberFormat()->setFormatCode($moneyFormat);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);

        $fileName = 'PF-Ledger-' . $report['employee']->employee_code . '.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** "Period 2 — Annual increment, from 01 Jan 2026 · Grade-10 / Step 3 · Basic 21,000.00 · PF 2,100.00 / month" */
    public static function pfPeriodTitle(int $index, object $entry): string
    {
        return 'Period ' . ($index + 1) . ' — ' . $entry->reason . ', from ' . Carbon::parse($entry->effective_date)->format('d M Y')
            . ($entry->grade_step ? ' · ' . $entry->grade_step : '')
            . ' · Basic ' . number_format((float) $entry->basic_salary, 2)
            . ' · PF ' . number_format((float) $entry->pf_amount, 2) . ' / month';
    }

    private function pfLedgerValidator(Request $request)
    {
        return Validator::make($request->query(), [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('deleted', 'No')->whereIn('branch_id', BranchController::visibleIds())],
            'from'        => ['nullable', 'date_format:Y-m'],
            'to'          => ['nullable', 'date_format:Y-m'],
        ], [
            'employee_id.required' => 'Select the employee.',
        ]);
    }

    private function salarySheetValidator(Request $request)
    {
        return Validator::make($request->query(), [
            'month'         => ['required', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('deleted', 'No')],
            'include_bonus' => ['nullable', 'boolean'],
        ], [
            'month.required'    => 'Select the month.',
            'month.date_format' => 'Select a valid month.',
        ]);
    }

    /** A Settings > Theme Colors palette color as Excel RRGGBB (see SettingController::pdfColors()). */
    private static function xl(string $key): string
    {
        return strtoupper(ltrim(SettingController::pdfColors()[$key], '#'));
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
            'type'          => ['nullable', Rule::in(array_keys(self::STOCK_TYPES))],
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

    /* ================================================================== */
    /*  Stock ledger builder                                                */
    /* ================================================================== */

    /**
 * Stock ledger: per product (and variant) the opening balance, every stock
 * movement in the period with a running balance, and the closing balance.
 * Shared by the screen, the PDF and the Excel export so all three match.
     */

    /** Movement types as shown in the report, keyed by stocks.reference_type. */
    public const STOCK_TYPES = [
        'Opening Stock'   => 'Opening Stock',
        'Purchase'        => 'Purchase',
        'Purchase Return' => 'Purchase Return',
        'Sale'            => 'Sale',
        'Sale Return'     => 'Sale Return',
        'Order'           => 'Order Delivery',
        'Transfer Out'    => 'Transfer Out',
        'Transfer In'     => 'Transfer In',
        'Adjustment'      => 'Adjustment',
    ];
    private function buildStockLedger(array $filters): array
    {
        $ctx = new \stdClass();
        $ctx->from = Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $ctx->to   = Carbon::parse($filters['to'] ?? now())->endOfDay();

        $ctx->filters = [
            'product_id'    => $filters['product_id'] ?? null,
            'variant_id'    => $filters['variant_id'] ?? null,
            'category_id'   => $filters['category_id'] ?? null,
            'brand_id'      => $filters['brand_id'] ?? null,
            'type'          => $filters['type'] ?? null,
            'only_movement' => (bool) ($filters['only_movement'] ?? true),
        ];


        $type = $ctx->filters['type'];

        // Every product / variant that has stock rows and matches the product filters.
        $items = $this->stockLedgerStockQuery($ctx)
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 's.product_variant_id')
            ->leftJoin('attributes as a', 'a.id', '=', 'pv.attribute_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->groupBy('s.product_id', 's.product_variant_id', 'p.name', 'p.sku', 'a.name', 'pv.barcode', 'p.barcode', 'u.name', 'c.name', 'b.name')
            ->orderBy('p.name')->orderBy('a.name')
            ->get([
                's.product_id', 's.product_variant_id',
                'p.name as product_name', 'p.sku', 'a.name as variant_name',
                DB::raw('COALESCE(pv.barcode, p.barcode) as barcode'),
                'u.name as unit', 'c.name as category', 'b.name as brand',
                DB::raw("SUM(CASE WHEN s.date < '{$ctx->from->toDateTimeString()}' THEN s.in_qty - s.out_qty ELSE 0 END) as opening"),
            ]);

        // Movements inside the period (all types, so the running balance stays true).
        $movements = $this->stockLedgerStockQuery($ctx)
            ->whereBetween('s.date', [$ctx->from, $ctx->to])
            ->leftJoin('purchases as pu', 'pu.id', '=', 's.purchase_id')
            ->leftJoin('purchase_returns as pr', 'pr.id', '=', 's.purchase_return_id')
            ->leftJoin('sales as sa', 'sa.id', '=', 's.sale_id')
            ->leftJoin('sale_returns as sr', 'sr.id', '=', 's.sale_return_id')
            ->leftJoin('sale_orders as so', 'so.id', '=', 's.order_id')
            ->leftJoin('parties as party', 'party.id', '=', DB::raw('COALESCE(pr.supplier_id, pu.supplier_id, sr.customer_id, sa.customer_id, so.customer_id)'))
            ->orderBy('s.date')->orderBy('s.id')
            ->get([
                's.id', 's.product_id', 's.product_variant_id', 's.date', 's.in_qty', 's.out_qty', 's.remarks',
                DB::raw($this->stockLedgerTypeSql($ctx) . ' as type_key'),
                DB::raw('COALESCE(pr.return_no, sr.return_no, so.order_no, pu.invoice_no, sa.invoice_no) as reference_no'),
                'party.name as party_name',
            ])
            ->groupBy(fn ($m) => $m->product_id . '-' . ($m->product_variant_id ?? 0));

        $grand = ['opening' => 0.0, 'in' => 0.0, 'out' => 0.0, 'closing' => 0.0, 'items' => 0, 'rows' => 0];

        $report = $items->map(function ($item) use ($ctx, $movements, $type, &$grand) {
            $balance = round((float) $item->opening, 3);
            $rows    = [];
            $in = $out = 0.0;

            foreach ($movements->get($item->product_id . '-' . ($item->product_variant_id ?? 0), collect()) as $m) {
                $balance = round($balance + (float) $m->in_qty - (float) $m->out_qty, 3);

                if ($type && $m->type_key !== $type) {
                    continue;   // hidden by the type filter, but still moves the balance
                }

                $in  += (float) $m->in_qty;
                $out += (float) $m->out_qty;

                $rows[] = [
                    'date'      => Carbon::parse($m->date),
                    'type'      => self::STOCK_TYPES[$m->type_key] ?? ($m->type_key ?: 'Adjustment'),
                    'type_key'  => $m->type_key,
                    'reference' => $m->reference_no,
                    'party'     => $m->party_name,
                    'remarks'   => $m->remarks,
                    'in'        => (float) $m->in_qty,
                    'out'       => (float) $m->out_qty,
                    'balance'   => $balance,
                ];
            }

            if ($ctx->filters['only_movement'] && !$rows) {
                return null;
            }

            $grand['opening'] += (float) $item->opening;
            $grand['in']      += $in;
            $grand['out']     += $out;
            $grand['closing'] += $balance;
            $grand['items']++;
            $grand['rows']    += count($rows);

            return [
                'product'  => $item->product_name . ($item->variant_name ? ' - ' . $item->variant_name : ''),
                'sku'      => $item->sku,
                'barcode'  => $item->barcode,
                'unit'     => $item->unit,
                'category' => $item->category,
                'brand'    => $item->brand,
                'opening'  => round((float) $item->opening, 3),
                'rows'     => $rows,
                'in'       => round($in, 3),
                'out'      => round($out, 3),
                'closing'  => $balance,
            ];
        })->filter()->values();

        return [
            'from'         => $ctx->from,
            'to'           => $ctx->to,
            'items'        => $report,
            'totals'       => array_map(fn ($v) => is_float($v) ? round($v, 3) : $v, $grand),
            'filters_text' => $this->stockLedgerFiltersText($ctx),
        ];

    }

    /** Active stock rows, narrowed by the product filters. */
    private function stockLedgerStockQuery(object $ctx)
    {
        $f = $ctx->filters;

        return BranchController::scope(DB::table('stocks as s'), 's.branch_id')
            ->where('s.deleted', 'No')
            ->where('s.date', '<=', $ctx->to)
            ->when($f['product_id'], fn ($q) => $q->where('s.product_id', (int) $f['product_id']))
            ->when($f['variant_id'], fn ($q) => $q->where('s.product_variant_id', (int) $f['variant_id']))
            ->when($f['category_id'] || $f['brand_id'], function ($q) use ($ctx, $f) {
                $q->whereExists(function ($w) use ($ctx, $f) {
                    $w->select(DB::raw(1))->from('products as fp')->whereColumn('fp.id', 's.product_id')
                        ->when($f['category_id'], fn ($x) => $x->where('fp.category_id', (int) $f['category_id']))
                        ->when($f['brand_id'], fn ($x) => $x->where('fp.brand_id', (int) $f['brand_id']));
                });
            });
    }

    /** Movement type: the stored reference type, or derived from the linked document for old rows. */
    private function stockLedgerTypeSql(object $ctx): string
    {
        return "COALESCE(s.reference_type,
            CASE
                WHEN s.purchase_return_id IS NOT NULL THEN 'Purchase Return'
                WHEN s.sale_return_id IS NOT NULL THEN 'Sale Return'
                WHEN s.order_id IS NOT NULL THEN 'Order'
                WHEN s.purchase_id IS NOT NULL THEN 'Purchase'
                WHEN s.sale_id IS NOT NULL THEN 'Sale'
            END)";
    }

    /** Human-readable list of the active filters, for the report header. */
    private function stockLedgerFiltersText(object $ctx): array
    {
        $f = $ctx->filters;
        $text = ['Period' => $ctx->from->format('d M Y') . ' to ' . $ctx->to->format('d M Y')];

        if ($f['product_id']) {
            $text['Product'] = DB::table('products')->where('id', $f['product_id'])->value('name');
        }
        if ($f['variant_id']) {
            $text['Variant'] = DB::table('product_variants as pv')->leftJoin('attributes as a', 'a.id', '=', 'pv.attribute_id')
                ->where('pv.id', $f['variant_id'])->value('a.name');
        }
        if ($f['category_id']) {
            $text['Category'] = DB::table('categories')->where('id', $f['category_id'])->value('name');
        }
        if ($f['brand_id']) {
            $text['Brand'] = DB::table('brands')->where('id', $f['brand_id'])->value('name');
        }
        if ($f['type']) {
            $text['Movement'] = self::STOCK_TYPES[$f['type']] ?? $f['type'];
        }

        return array_filter($text);
    }

    /* ================================================================== */
    /*  Transactions ledger builder                                         */
    /* ================================================================== */

    /**
 * Transactions ledger in the cash report format: one section per payment method (Liquid Asset
 * layer 2: Cash, Bank, Mobile Banking ...). Each section lists the period's money movements with
 * In / Out and a running balance from zero, then the period in / out, the method's opening balance
 * and its net closing (opening + in - out). A closing summary lists every account (layer 3).
 * Built on liquidAssetMovements(), so it always agrees with the Cash & Bank Book.
 */
    /** Movement types offered by the Type filter (a filter value matches the start of the type). */
    public const TRANSACTION_TYPES = [
        'Sale Receive'           => 'Sale Receive',
        'Payment Receive'        => 'Payment Receive (customer)',
        'Purchase Payment'       => 'Purchase Payment',
        'Payment to Supplier'    => 'Payment to Supplier',
        'Purchase Return Refund' => 'Purchase Return Refund',
        'Bill Payment'           => 'Bill Payment',
        'Expense'                => 'Expense',
        'Salary'                 => 'Salary',
        'Loan / Advance Paid'    => 'Loan / Advance Paid',
        'Loan Repayment'         => 'Loan Repayment',
        'Journal'                => 'Journal',
        'Amount Transfer'        => 'Amount Transfer',
    ];

    private function buildTransactionLedger(array $filters): array
    {
        $ctx = new \stdClass();
        $ctx->from = Carbon::parse($filters['from'] ?? now()->startOfDay());
        $ctx->to   = Carbon::parse($filters['to'] ?? now()->endOfDay());

        // An input without seconds ("...T23:59") still covers the whole last minute.
        if ($ctx->to->second === 0 && !preg_match('/:\d{2}:\d{2}/', (string) ($filters['to'] ?? ''))) {
            $ctx->to->second(59);
        }

        $ctx->filters = [
            'method_id'  => $filters['method_id'] ?? null,
            'account_id' => $filters['account_id'] ?? null,
            'type'       => $filters['type'] ?? null,
        ];

        $accounts = $this->liquidAssetAccounts($ctx);

        $opening = [];
        foreach ($accounts as $key => $a) {
            $opening[$key] = (float) $a['opening_balance'];
        }
        foreach ($this->liquidAssetMovements($ctx, null, $ctx->from->copy()->subSecond()) as $m) {
            $opening[$m->key] = ($opening[$m->key] ?? 0) + $m->in - $m->out;
        }

        $type = $ctx->filters['type'];
        $movements = $this->liquidAssetMovements($ctx, $ctx->from, $ctx->to)
            ->filter(fn ($m) => !$type || str_starts_with($m->type, $type));

        foreach (array_unique(array_merge(array_keys($opening), $movements->pluck('key')->all())) as $key) {
            if (!isset($accounts[$key])) {
                $accounts[$key] = $this->liquidAssetUnassigned($ctx, $key);
            }
        }
        $accounts  = $this->liquidAssetApplyFilters($ctx, $accounts);
        $movements = $movements->filter(fn ($m) => isset($accounts[$m->key]))->values();

        // One section per payment method, in chart of accounts order.
        $methods = [];
        foreach ($accounts as $key => $a) {
            $methods[$a['method']] ??= ['method' => $a['method'], 'rows' => [], 'in' => 0.0, 'out' => 0.0, 'opening' => 0.0];
            $methods[$a['method']]['opening'] += $opening[$key] ?? 0;
        }

        foreach ($movements as $m) {
            $a = $accounts[$m->key];
            $name = $a['method'];
            $methods[$name]['in']  += $m->in;
            $methods[$name]['out'] += $m->out;

            $methods[$name]['rows'][] = [
                'datetime'    => Carbon::parse($m->datetime),
                'particulars' => $m->type . ($m->party ? ' — ' . $m->party : ''),
                'refs'        => implode(' · ', array_filter([
                    $m->voucher ? 'Voucher: ' . $m->voucher : null,
                    $m->reference ? 'Ref: ' . $m->reference : null,
                ])),
                'remarks'     => $m->remarks,
                'account'     => $a['account'],
                'type'        => preg_replace('/ \(.*\)$/', '', $m->type),
                'in'          => $m->in,
                'out'         => $m->out,
                'balance'     => round($methods[$name]['in'] - $methods[$name]['out'], 2),
            ];
        }

        $methods = array_values(array_map(function ($s) {
            $s['in']      = round($s['in'], 2);
            $s['out']     = round($s['out'], 2);
            $s['opening'] = round($s['opening'], 2);
            $s['period']  = round($s['in'] - $s['out'], 2);
            $s['closing'] = round($s['opening'] + $s['period'], 2);

            return $s;
        }, $methods));

        // Closing summary per account.
        $summary = [];
        foreach ($accounts as $key => $a) {
            $in   = round($movements->where('key', $key)->sum('in'), 2);
            $out  = round($movements->where('key', $key)->sum('out'), 2);
            $open = round($opening[$key] ?? 0, 2);
            $summary[] = [
                'method'  => $a['method'], 'account' => $a['account'], 'code' => $a['code'],
                'opening' => $open, 'in' => $in, 'out' => $out, 'closing' => round($open + $in - $out, 2),
            ];
        }

        $sum = fn ($k) => round(array_sum(array_column($methods, $k)), 2);

        return [
            'from'         => $ctx->from,
            'to'           => $ctx->to,
            'methods'      => $methods,
            'summary'      => $summary,
            'totals'       => [
                'entries' => $movements->count(),
                'opening' => $sum('opening'),
                'in'      => $sum('in'),
                'out'     => $sum('out'),
                'closing' => $sum('closing'),
            ],
            'filters_text' => $this->transactionLedgerFiltersText($ctx),
            'printedBy'    => auth()->user()->name ?? '',
        ];
    }

    private function transactionLedgerFiltersText(object $ctx): array
    {
        $f = $ctx->filters;
        $text = ['Period' => $ctx->from->format('d M Y, h:i:s A') . '  to  ' . $ctx->to->format('d M Y, h:i:s A')];
        $name = fn ($id) => DB::table('chart_of_accounts')->where('id', $id)->value('account_name');

        if ($f['method_id']) {
            $text['Payment Method'] = $name($f['method_id']);
        }
        if ($f['account_id']) {
            $text['Account'] = $name($f['account_id']);
        }
        if ($f['type']) {
            $text['Type'] = self::TRANSACTION_TYPES[$f['type']] ?? $f['type'];
        }

        return array_filter($text);
    }

    /* ================================================================== */
    /*  Customer / supplier ledger builder                                  */
    /* ================================================================== */

    /**
 * Customer / supplier ledger built from the party sub-ledger (payments.party_id).
 * Shared by the screen, the PDF and the Excel export so all three match.
 *
 * Amounts are shown from the party account's side, per double-entry rules:
 *  - Customer = Accounts Receivable (asset, normal Dr balance)
 *      Dr: sales, order deliveries, refunds paid to the customer
 *      Cr: payments received, sales returns, discounts allowed
 *  - Supplier = Accounts Payable (liability, normal Cr balance)
 *      Cr: purchases, bills, refunds received
 *      Dr: payments made, purchase returns, discounts received
 *
 * The payments table stores the opposite side (the company's view), so
 * Dr = payments.credit and Cr = payments.debit. Purchase-return rows are
 * the exception: they are classified by their type, not their stored column.
 *
 * Balances are signed Dr - Cr: positive = Dr balance, negative = Cr balance.
     */

    public const PARTY_LEDGERS = [
        'customer' => [
            'title'   => 'Customer Ledger',
            'types'   => ['Customer', 'Walk-In Customer'],
            'normal'  => 'Dr',
            'labels'  => ['Dr' => 'Receivable', 'Cr' => 'Advance'],
        ],
        'supplier' => [
            'title'   => 'Supplier Ledger',
            'types'   => ['Supplier'],
            'normal'  => 'Cr',
            'labels'  => ['Dr' => 'Advance', 'Cr' => 'Payable'],
        ],
    ];
    private function buildPartyLedger(string $ledger, array $filters): array
    {
        $ctx = new \stdClass();
        $ctx->ledger = $ledger;
        $ctx->config = self::PARTY_LEDGERS[$ledger];
        $ctx->from   = Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $ctx->to     = Carbon::parse($filters['to'] ?? now())->endOfDay();

        $ctx->filters = [
            'party_id'   => $filters['party_id'] ?? null,
            'party_type' => $filters['party_type'] ?? null,
            'hide_zero'  => (bool) ($filters['hide_zero'] ?? true),
        ];


        $f = $ctx->filters;

        $parties = DB::table('parties')
            ->where('deleted', 'No')
            ->whereIn('type', $f['party_type'] ? [$f['party_type']] : $ctx->config['types'])
            ->when($f['party_id'], fn ($q) => $q->where('id', (int) $f['party_id']))
            ->orderBy('name')
            ->get(['id', 'code', 'type', 'name', 'phone', 'address', 'opening_balance', 'opening_balance_type', 'opening_balance_date']);

        $ids = $parties->pluck('id')->all();

        // Net (Dr - Cr) of every transaction before the period, per party.
        $before = $this->partyLedgerEntries($ctx, $ids)
            ->where('p.datetime', '<', $ctx->from)
            ->groupBy('p.party_id')
            ->selectRaw('p.party_id, SUM((' . $this->partyLedgerDrSql($ctx) . ') - (' . $this->partyLedgerCrSql($ctx) . ')) as net')
            ->pluck('net', 'party_id');

        $rows = $this->partyLedgerEntries($ctx, $ids)
            ->whereBetween('p.datetime', [$ctx->from, $ctx->to])
            ->leftJoin('sales as sa', 'sa.id', '=', 'p.sale_id')
            ->leftJoin('sale_returns as sr', 'sr.id', '=', 'p.sale_return_id')
            ->leftJoin('sale_orders as so', 'so.id', '=', 'p.order_id')
            ->leftJoin('purchases as pu', 'pu.id', '=', 'p.purchase_id')
            ->leftJoin('purchase_returns as pr', 'pr.id', '=', 'p.purchase_return_id')
            ->leftJoin('bills as b', 'b.id', '=', 'p.bill_id')
            // Day by day, and within a day by the time of the entry (rows saved with a bare date use the time
            // they were entered), so a receipt never shows before the sale it paid.
            ->orderByRaw('DATE(p.datetime)')
            ->orderByRaw("CASE WHEN TIME(p.datetime) = '00:00:00' THEN TIME(COALESCE(p.created_datetime, p.datetime)) ELSE TIME(p.datetime) END")
            ->orderBy('p.id')
            ->get([
                'p.id', 'p.party_id', 'p.code', 'p.payment_type', 'p.datetime', 'p.payment_method', 'p.remarks',
                'p.purchase_return_id',
                DB::raw($this->partyLedgerDrSql($ctx) . ' as dr'),
                DB::raw($this->partyLedgerCrSql($ctx) . ' as cr'),
                DB::raw('COALESCE(pr.return_no, sr.return_no, so.order_no, pu.invoice_no, sa.invoice_no, b.bill_no) as reference_no'),
            ])
            ->groupBy('party_id');

        $grand = ['parties' => 0, 'opening' => 0.0, 'dr' => 0.0, 'cr' => 0.0, 'closing' => 0.0, 'balance_dr' => 0.0, 'balance_cr' => 0.0];

        $items = $parties->map(function ($party) use ($ctx, $before, $rows, &$grand) {
            [$partyOpening, $openingDate] = $this->partyLedgerPartyOpening($ctx, $party);

            // Party opening balance counts as brought forward unless it is dated inside / after the period.
            $openingInPeriod = $openingDate && $openingDate->betweenIncluded($ctx->from, $ctx->to);
            $openingAfter    = $openingDate && $openingDate->gt($ctx->to);

            $opening = round((float) ($before[$party->id] ?? 0)
                + ($openingInPeriod || $openingAfter ? 0 : $partyOpening), 2);

            $balance = $opening;
            $dr = $cr = 0.0;
            $lines = [];

            if ($openingInPeriod && $partyOpening != 0) {
                $lines[] = $this->partyLedgerLine($ctx, $openingDate, null, 'Opening Balance', null, 'Balance as per party setup',
                    max($partyOpening, 0), max(-$partyOpening, 0), $balance);
            }

            foreach ($rows->get($party->id, collect()) as $r) {
                $lines[] = $this->partyLedgerLine($ctx, Carbon::parse($r->datetime), $r->code, $this->partyLedgerParticular($ctx, $r), $r->reference_no,
                    trim(implode(' · ', array_filter([$r->payment_method, $r->remarks]))),
                    (float) $r->dr, (float) $r->cr, $balance);
            }

            foreach ($lines as $l) {
                $dr += $l['dr'];
                $cr += $l['cr'];
            }

            if ($ctx->filters['hide_zero'] && !$lines && $opening == 0) {
                return null;
            }

            $balance = round($balance, 2);

            $grand['parties']++;
            $grand['opening'] += $opening;
            $grand['dr']      += $dr;
            $grand['cr']      += $cr;
            $grand['closing'] += $balance;
            $grand[$balance >= 0 ? 'balance_dr' : 'balance_cr'] += abs($balance);

            return [
                'id'      => $party->id,
                'name'    => $party->name,
                'code'    => $party->code,
                'type'    => $party->type,
                'phone'   => $party->phone,
                'address' => $party->address,
                'opening' => $opening,
                'rows'    => $lines,
                'dr'      => round($dr, 2),
                'cr'      => round($cr, 2),
                'closing' => $balance,
            ];
        })->filter()->values();

        return [
            'ledger'       => $ctx->ledger,
            'config'       => $ctx->config,
            'from'         => $ctx->from,
            'to'           => $ctx->to,
            'items'        => $items,
            'totals'       => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $grand),
            'filters_text' => $this->partyLedgerFiltersText($ctx),
            // for the views: "1,250.00 Dr" text and Receivable / Payable / Advance label
            'balance_text'  => fn (float $v) => self::partyBalanceText($v),
            'balance_label' => fn (float $v) => $this->partyLedgerBalanceLabel($ctx, $v),
        ];

    }

    /** "1,250.00 Dr" style balance text. */
    public static function partyBalanceText(float $value): string
    {
        $value = round($value, 2);

        return $value == 0 ? '0.00' : number_format(abs($value), 2) . ($value > 0 ? ' Dr' : ' Cr');
    }

    /** Receivable / Payable / Advance / Settled, for a signed balance. */
    private function partyLedgerBalanceLabel(object $ctx, float $value): string
    {
        $value = round($value, 2);

        return $value == 0 ? 'Settled' : $ctx->config['labels'][$value > 0 ? 'Dr' : 'Cr'];
    }

    /** Active sub-ledger rows of the given parties up to the period end. */
    private function partyLedgerEntries(object $ctx, array $ids)
    {
        return BranchController::scope(DB::table('payments as p'), 'p.branch_id')
            ->whereIn('p.party_id', $ids ?: [0])
            ->where('p.deleted', 'No')
            ->where('p.status', 'Active')
            // party_id also holds employee ids for expense rows
            ->where(fn ($q) => $q->whereNull('p.party_type')->orWhere('p.party_type', '<>', 'Employee'))
            ->where('p.datetime', '<=', $ctx->to);
    }

    /** Debit to the party account. A purchase return (stored as "Purchase Payment") reduces the payable. */
    private function partyLedgerDrSql(object $ctx): string
    {
        return "CASE WHEN p.purchase_return_id IS NOT NULL
                    THEN CASE WHEN p.payment_type = 'Purchase Payment' THEN COALESCE(p.debit, 0) + COALESCE(p.credit, 0) ELSE 0 END
                    ELSE COALESCE(p.credit, 0) END";
    }

    /** Credit to the party account. A supplier refund on a purchase return restores the payable. */
    private function partyLedgerCrSql(object $ctx): string
    {
        return "CASE WHEN p.purchase_return_id IS NOT NULL
                    THEN CASE WHEN p.payment_type = 'Purchase Payment' THEN 0 ELSE COALESCE(p.debit, 0) + COALESCE(p.credit, 0) END
                    ELSE COALESCE(p.debit, 0) END";
    }

    /** One ledger line; moves the running balance (passed by reference). */
    private function partyLedgerLine(object $ctx, Carbon $date, ?string $voucher, string $particular, ?string $reference, ?string $note, float $dr, float $cr, float &$balance): array
    {
        $balance = round($balance + $dr - $cr, 2);

        return [
            'date'       => $date,
            'voucher'    => $voucher,
            'particular' => $particular,
            'reference'  => $reference,
            'note'       => $note,
            'dr'         => round($dr, 2),
            'cr'         => round($cr, 2),
            'balance'    => $balance,
        ];
    }

    private ?bool $isMainMemo = null;

    /** Party setup opening balance, signed Dr - Cr, and its date. */
    private function partyLedgerPartyOpening(object $ctx, object $party): array
    {
        // The opening balance belongs to the main branch (looked up once per request, not per party).
        $this->isMainMemo ??= BranchController::mainVisible();
        $amount = $this->isMainMemo ? (float) $party->opening_balance : 0.0;
        $isDue  = $party->opening_balance_type !== 'Advance';

        // Customer due = receivable (Dr); supplier due = payable (Cr). Advance is the reverse.
        $signed = ($ctx->ledger === 'customer') === $isDue ? $amount : -$amount;

        return [round($signed, 2), $party->opening_balance_date ? Carbon::parse($party->opening_balance_date)->startOfDay() : null];
    }

    /** Readable narration for a sub-ledger row. */
    private function partyLedgerParticular(object $ctx, object $r): string
    {
        if ($r->purchase_return_id) {
            return $r->payment_type === 'Purchase Payment' ? 'Purchase Return' : 'Refund Received';
        }

        $customer = $ctx->ledger === 'customer';

        return match ($r->payment_type) {
            'Walk-In'                    => 'Sale (Walk-In)',
            'Wholesale'                  => 'Sale (Wholesale)',
            'Sale Order'                 => 'Sales Order Delivery',
            'Payment Receive',
            'Walk-In Payment Receive',
            'Wholesale Payment Receive',
            'Sale Order Payment Receive' => 'Payment Received',
            'Walk-In Sale Return',
            'Wholesale Return'           => 'Sales Return',
            'Discount'                   => $customer ? 'Discount Allowed' : 'Discount Received',
            'Payment'                    => $customer ? 'Refund Paid' : 'Payment Made',
            'Purchase'                   => 'Purchase',
            'Purchase Payment'           => 'Purchase Payment',
            'Bill'                       => 'Bill',
            'Bill Payment'               => 'Bill Payment',
            default                      => $r->payment_type ?: 'Adjustment',
        };
    }

    private function partyLedgerFiltersText(object $ctx): array
    {
        $f = $ctx->filters;
        $text = ['Period' => $ctx->from->format('d M Y') . ' to ' . $ctx->to->format('d M Y')];

        if ($f['party_id']) {
            $text[$ctx->ledger === 'customer' ? 'Customer' : 'Supplier'] = DB::table('parties')->where('id', $f['party_id'])->value('name');
        }
        if ($f['party_type']) {
            $text['Customer Type'] = $f['party_type'];
        }

        return array_filter($text);
    }

    /* ================================================================== */
    /*  Cash & bank book builder                                            */
    /* ================================================================== */

    /**
 * Daily cash & bank book: every money movement of the Liquid Asset accounts (payment
 * methods), day by day. A day's opening is the previous day's closing; the first
 * opening is the account's opening balance plus everything before the period.
 *
 * Movements (in = money into the account, out = money paid from it):
 *  - payments: "... Payment Receive" (sales, orders, customer receipts) in;
 *    Purchase Payment, Payment, Bill Payment out; a supplier refund on a purchase return in.
 *  - salary payments (Pay Salary on the salary sheet): out of the account they were paid from.
 *  - expenses (other employee expenses, and salary entered as an expense): out of the expense's payment account.
 *  - journal lines on a liquid account: debit in, credit out.
 *  - amount transfers: out of the sending account, into the receiving one.
 *  - suspense entries: advances paid / withdrawn and capital withdrawals out, returns, employee deposits and investments in.
 *
 * Old payment rows that carry only a method name (e.g. "Cash") and no account are shown
 * under "<method> — no account" so their money is not lost from the totals.
 *
 * Filters: from, to (dates, max 92 days), method_id (2nd layer), account_id (3rd layer).
     */

    public const LIQUID_MAX_DAYS = 92;
    public const LIQUID_RECEIVE_TYPES = ['Walk-In Payment Receive', 'Wholesale Payment Receive', 'Sale Order Payment Receive', 'Payment Receive'];
    public const LIQUID_PAY_TYPES     = ['Purchase Payment', 'Payment', 'Bill Payment'];
    private function buildLiquidAsset(array $filters): array
    {
        $ctx = new \stdClass();
        $ctx->filters = $filters;
        $ctx->from = Carbon::parse($filters['from'] ?? today())->startOfDay();
        $ctx->to   = Carbon::parse($filters['to'] ?? $ctx->from)->endOfDay();


        $accounts = $this->liquidAssetAccounts($ctx);

        // Opening = opening balance + net movement before the period.
        $opening = [];
        foreach ($accounts as $key => $a) {
            $opening[$key] = (float) $a['opening_balance'];
        }
        foreach ($this->liquidAssetMovements($ctx, null, $ctx->from->copy()->subSecond()) as $m) {
            $opening[$m->key] = ($opening[$m->key] ?? 0) + $m->in - $m->out;
        }

        $movements = $this->liquidAssetMovements($ctx, $ctx->from, $ctx->to)->groupBy(fn ($m) => substr($m->datetime, 0, 10));

        // Unassigned buckets that only show up through movements.
        foreach (array_unique(array_merge(array_keys($opening), $movements->flatten(1)->pluck('key')->all())) as $key) {
            if (!isset($accounts[$key])) {
                $accounts[$key] = $this->liquidAssetUnassigned($ctx, $key);
            }
        }
        $accounts = $this->liquidAssetApplyFilters($ctx, $accounts);

        $balance = [];
        foreach ($accounts as $key => $a) {
            $balance[$key] = round($opening[$key] ?? 0, 2);
        }

        $days   = [];
        $byType = [];
        foreach (CarbonPeriod::create($ctx->from->toDateString(), $ctx->to->toDateString()) as $day) {
            $date = $day->toDateString();
            $rows = ($movements[$date] ?? collect())->filter(fn ($m) => isset($accounts[$m->key]))->groupBy('key');
            $blocks = [];
            $dayTotals = ['opening' => 0.0, 'in' => 0.0, 'out' => 0.0, 'closing' => 0.0];

            foreach ($accounts as $key => $a) {
                $open = $balance[$key];
                $lines = [];
                $in = $out = 0.0;

                foreach ($rows[$key] ?? [] as $m) {
                    $balance[$key] = round($balance[$key] + $m->in - $m->out, 2);
                    $in  += $m->in;
                    $out += $m->out;
                    $lines[] = (array) $m + ['balance' => $balance[$key]];

                    $byType[$m->type] ??= ['type' => $m->type, 'count' => 0, 'in' => 0.0, 'out' => 0.0];
                    $byType[$m->type]['count']++;
                    $byType[$m->type]['in']  += $m->in;
                    $byType[$m->type]['out'] += $m->out;
                }

                // Quiet accounts with nothing on hand are left out of the day.
                if (!$lines && abs($open) < 0.005) {
                    continue;
                }

                $blocks[] = $a + ['opening' => $open, 'rows' => $lines, 'in' => round($in, 2), 'out' => round($out, 2), 'closing' => $balance[$key]];
                $dayTotals['opening'] += $open;
                $dayTotals['in']      += $in;
                $dayTotals['out']     += $out;
                $dayTotals['closing'] += $balance[$key];
            }

            $days[] = [
                'date'     => $day->copy(),
                'accounts' => $blocks,
                'methods'  => $this->liquidAssetMethodTotals($ctx, $blocks),
                'totals'   => array_map(fn ($v) => round($v, 2), $dayTotals),
                'entries'  => array_sum(array_map(fn ($b) => count($b['rows']), $blocks)),
            ];
        }

        $first = $days[0]['totals'] ?? ['opening' => 0];
        $last  = end($days) ?: ['totals' => ['closing' => 0]];

        return [
            'from'         => $ctx->from,
            'to'           => $ctx->to,
            'days'         => $days,
            'by_type'      => collect($byType)->sortBy('type')->values()->map(fn ($t) => ['in' => round($t['in'], 2), 'out' => round($t['out'], 2)] + $t)->all(),
            'totals'       => [
                'opening' => round($first['opening'], 2),
                'in'      => round(array_sum(array_column(array_column($days, 'totals'), 'in')), 2),
                'out'     => round(array_sum(array_column(array_column($days, 'totals'), 'out')), 2),
                'closing' => round($last['totals']['closing'], 2),
                'entries' => array_sum(array_column($days, 'entries')),
            ],
            'filters_text' => $this->liquidAssetFiltersText($ctx),
        ];

    }

    /**
     * Branches the Cash & Bank engine reads: $ctx->branches when set (the daily cash closing passes the working
     * branch only), otherwise the branches the user sees.
     */
    public static function ctxBranches(object $ctx): array
    {
        return !empty($ctx->branches) ? array_map('intval', (array) $ctx->branches) : BranchController::visibleIds();
    }

    /** Limit an engine query to ctxBranches(). */
    private static function ctxScope(object $ctx, $query, string $column)
    {
        return empty($ctx->branches) ? BranchController::scope($query, $column) : $query->whereIn($column, self::ctxBranches($ctx));
    }

    /** 3rd layer Liquid Asset accounts keyed "a:<id>", with their payment method (2nd layer). */
    public function liquidAssetAccounts(object $ctx): array
    {
        $branches = self::ctxBranches($ctx);
        $rows = DB::table('chart_of_accounts as a')
            ->join('chart_of_accounts as m', 'm.id', '=', 'a.parent_id')
            ->where('a.account_type', 'Liquid Asset')->where('a.layer_level', 3)->where('a.deleted', 'No')
            // The shared cash / bank ledgers and the branch-owned ledgers of the branches read.
            ->where(fn ($w) => $w->whereNull('a.branch_id')->orWhereIn('a.branch_id', $branches))
            ->orderBy('m.account_code')->orderBy('a.account_code')
            ->get(['a.id', 'a.account_code', 'a.account_name', 'a.opening_balance', 'a.branch_id', 'm.id as method_id', 'm.account_name as method']);

        // A shared ledger's opening balance belongs to the main branch: counted when the main branch is read.
        $isMain = in_array((int) BranchController::mainId(), $branches, true) || BranchController::current() === null;
        $out = [];
        foreach ($rows as $r) {
            $out['a:' . $r->id] = [
                'key' => 'a:' . $r->id, 'account_id' => $r->id, 'code' => $r->account_code, 'account' => $r->account_name,
                'method_id' => $r->method_id, 'method' => $r->method,
                // A shared ledger's opening balance belongs to the main branch; a branch ledger keeps its own.
                'opening_balance' => $r->branch_id || $isMain ? (float) $r->opening_balance : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Cash on hand in one Cash ledger for the WORKING branch, the way the Cash & Bank Book reads it: the ledger's
     * opening balance (a shared ledger's belongs to the main branch) + the working branch's movements on it up to
     * the end of today. Null when the account is not a Cash-method ledger (bank / mobile banking may be overdrawn).
     */
    public static function cashAvailable(int $accountId): ?float
    {
        $method = DB::table('chart_of_accounts as a')->join('chart_of_accounts as m', 'm.id', '=', 'a.parent_id')
            ->where('a.id', $accountId)->where('a.account_type', 'Liquid Asset')->value('m.account_name');
        if ($method === null || stripos($method, 'cash') === false) {
            return null;
        }

        $rc = new self();
        $ctx = (object) ['filters' => [], 'branches' => [(int) BranchController::activeId()], 'account_id' => $accountId];
        $balance = (float) ($rc->liquidAssetAccounts($ctx)['a:' . $accountId]['opening_balance'] ?? 0);
        foreach ($rc->liquidAssetMovements($ctx, null, Carbon::now()->endOfDay()) as $m) {
            $balance += $m->in - $m->out;
        }

        return round($balance, 2);
    }

    /**
     * Refuses (422) money going out of a Cash ledger beyond what the working branch has in it. Call it inside the
     * save's DB transaction: it locks the ledger row first, so two saves on the same drawer run one after the other.
     * $previous = what this document already took from the same ledger (an edit may re-use it); an edit that does
     * not take more than before is always allowed.
     */
    public static function ensureCash($accountId, $needed, $previous = 0.0): void
    {
        $accountId = (int) $accountId;
        $needed = round((float) $needed, 2);
        $previous = round((float) $previous, 2);
        if (!$accountId || $needed <= 0 || $needed <= $previous) {
            return;
        }
        $name = DB::table('chart_of_accounts')->where('id', $accountId)->lockForUpdate()->value('account_name');
        $available = self::cashAvailable($accountId);
        if ($available === null) {
            return;
        }
        $available = round($available + $previous, 2);
        if ($needed > $available + 0.004) {
            abort(422, self::cashShortMessage($name, $available, $needed));
        }
    }

    /** Several ledgers in one document: [account_id => amount out], [account_id => what it already took]. */
    public static function ensureCashLines(array $needed, array $previous = []): void
    {
        ksort($needed);   // lock ledgers in one order, so two documents never wait on each other crosswise
        foreach ($needed as $accountId => $amount) {
            self::ensureCash($accountId, $amount, $previous[$accountId] ?? 0);
        }
    }

    public static function cashShortMessage(?string $account, float $available, float $needed): string
    {
        return 'Not enough cash in ' . ($account ?: 'this cash account') . ': available ৳' . number_format($available, 2)
            . ', needed ৳' . number_format($needed, 2) . '.';
    }

    private function liquidAssetUnassigned(object $ctx, string $key): array
    {
        $method = substr($key, 2) ?: 'Unspecified';
        $methodId = DB::table('chart_of_accounts')->where('layer_level', 2)->where('account_type', 'Liquid Asset')
            ->where('account_name', $method)->where('deleted', 'No')->value('id');

        return [
            'key' => $key, 'account_id' => null, 'code' => '', 'account' => $method . ' — no account',
            'method_id' => $methodId, 'method' => $method, 'opening_balance' => 0.0,
        ];
    }

    private function liquidAssetApplyFilters(object $ctx, array $accounts): array
    {
        return array_filter($accounts, function ($a) use ($ctx) {
            if (!empty($ctx->filters['account_id'])) {
                return (int) $a['account_id'] === (int) $ctx->filters['account_id'];
            }
            if (!empty($ctx->filters['method_id'])) {
                return (int) $a['method_id'] === (int) $ctx->filters['method_id'];
            }

            return true;
        });
    }

    private function liquidAssetMethodTotals(object $ctx, array $blocks): array
    {
        $out = [];
        foreach ($blocks as $b) {
            $out[$b['method']] ??= ['method' => $b['method'], 'opening' => 0.0, 'in' => 0.0, 'out' => 0.0, 'closing' => 0.0];
            foreach (['opening', 'in', 'out', 'closing'] as $k) {
                $out[$b['method']][$k] = round($out[$b['method']][$k] + $b[$k], 2);
            }
        }

        return array_values($out);
    }

    /**
     * Movements between two date-times ($from null = from the beginning), as objects:
     * key, datetime, type, voucher, party, reference, remarks, in, out.
     */
    public function liquidAssetMovements(object $ctx, ?Carbon $from, Carbon $to): Collection
    {
        $range = fn ($q, string $col) => $from ? $q->whereBetween($col, [$from, $to]) : $q->where($col, '<=', $to);
        // $ctx->account_id: one ledger only (cashAvailable()).
        $only = !empty($ctx->account_id) ? (int) $ctx->account_id : null;

        // Payments that move money: receipts in, payments out, supplier refunds in.
        $payments = self::ctxScope($ctx, DB::table('payments as p'), 'p.branch_id')
            ->leftJoin('parties as pa', function ($j) use ($ctx) {
                $j->on('pa.id', '=', 'p.party_id')->where(fn ($w) => $w->whereNull('p.party_type')->orWhere('p.party_type', '<>', 'Employee'));
            })
            ->leftJoin('sales as sa', 'sa.id', '=', 'p.sale_id')
            ->leftJoin('sale_orders as so', 'so.id', '=', 'p.order_id')
            ->leftJoin('purchases as pu', 'pu.id', '=', 'p.purchase_id')
            ->leftJoin('purchase_returns as pr', 'pr.id', '=', 'p.purchase_return_id')
            ->leftJoin('bills as b', 'b.id', '=', 'p.bill_id')
            ->where('p.deleted', 'No')->where('p.status', 'Active')
            ->whereIn('p.payment_type', [...self::LIQUID_RECEIVE_TYPES, ...self::LIQUID_PAY_TYPES])
            ->when($only, fn ($q) => $q->where('p.payment_method_account_id', $only))
            ->where(fn ($q) => $range($q, 'p.datetime'))
            ->get(['p.id', 'p.branch_id', 'p.datetime', 'p.code', 'p.payment_type', 'p.debit', 'p.credit', 'p.remarks', 'p.purchase_return_id',
                'p.payment_method', 'p.payment_method_account_id', 'pa.name as party',
                DB::raw('COALESCE(pr.return_no, so.order_no, pu.invoice_no, sa.invoice_no, b.bill_no) as reference')])
            ->map(function ($p) use ($ctx) {
                $receive = in_array($p->payment_type, self::LIQUID_RECEIVE_TYPES, true);
                if ($p->purchase_return_id) {
                    // On a purchase return "Purchase Payment" only lowers the payable; "Payment" is the supplier's refund.
                    if ($p->payment_type !== 'Payment') {
                        return null;
                    }
                    $in = (float) $p->credit + (float) $p->debit;
                    $out = 0.0;
                    $type = 'Purchase Return Refund';
                } else {
                    $in  = $receive ? (float) $p->debit : 0.0;
                    $out = $receive ? 0.0 : (float) $p->credit;
                    $type = match (true) {
                        $p->payment_type === 'Purchase Payment' => 'Purchase Payment',
                        $p->payment_type === 'Bill Payment'     => 'Bill Payment',
                        $p->payment_type === 'Payment'          => 'Payment to Supplier',
                        $p->payment_type === 'Payment Receive'  => 'Payment Receive',
                        default                                 => 'Sale Receive (' . str_replace(' Payment Receive', '', $p->payment_type) . ')',
                    };
                }
                if ($in < 0.005 && $out < 0.005) {
                    return null;
                }

                return (object) [
                    'key'      => $p->payment_method_account_id ? 'a:' . $p->payment_method_account_id : 'm:' . trim((string) $p->payment_method),
                    'datetime' => $p->datetime, 'type' => $type, 'voucher' => $p->code, 'party' => $p->party, 'branch_id' => $p->branch_id,
                    'reference' => $p->reference, 'remarks' => $p->remarks, 'in' => round($in, 2), 'out' => round($out, 2),
                ];
            })->filter();

        // Expenses (salary is entered as an expense to the employee).
        $expenses = self::ctxScope($ctx, DB::table('expenses as e'), 'e.branch_id')
            ->leftJoin('employees as em', 'em.id', '=', 'e.employee_id')
            ->where('e.deleted', 'No')->whereNotNull('e.payment_account_id')
            ->when($only, fn ($q) => $q->where('e.payment_account_id', $only))
            ->where(fn ($q) => $range($q, 'e.expense_date'))
            ->get(['e.id', 'e.branch_id', 'e.expense_no', 'e.expense_date', 'e.created_date', 'e.payment_account_id', 'e.total_amount', 'e.reference_no', 'e.remarks', 'em.name as employee']);
        $heads = DB::table('expense_details as d')->join('chart_of_accounts as a', 'a.id', '=', 'd.expense_account_id')
            ->whereIn('d.expense_id', $expenses->pluck('id')->all() ?: [0])->where('d.deleted', 'No')
            ->get(['d.expense_id', 'a.account_name'])->groupBy('expense_id');
        $expenses = $expenses->map(function ($e) use ($ctx, $heads) {
            $names = ($heads[$e->id] ?? collect())->pluck('account_name')->unique();
            $salary = $names->contains(fn ($n) => preg_match('/salar|wage|payroll/i', $n));

            return (object) [
                'key' => 'a:' . $e->payment_account_id,
                'datetime' => substr($e->expense_date, 0, 10) . ' ' . ($e->created_date ? substr($e->created_date, 11, 8) : '00:00:00'),
                'type' => $salary ? 'Salary' : 'Expense', 'voucher' => $e->expense_no, 'party' => $e->employee, 'branch_id' => $e->branch_id,
                'reference' => $e->reference_no, 'remarks' => trim($names->implode(', ') . ($e->remarks ? ' · ' . $e->remarks : ''), ' ·'),
                'in' => 0.0, 'out' => round((float) $e->total_amount, 2),
            ];
        });

        // Journal lines on liquid accounts.
        $when = "TIMESTAMP(j.journal_date, TIME(COALESCE(j.created_date, '00:00:00')))";
        $journals = self::ctxScope($ctx, DB::table('journal_details as jd'), 'j.branch_id')
            ->join('journals as j', 'j.id', '=', 'jd.journal_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jd.account_id')
            ->where('a.account_type', 'Liquid Asset')
            ->when($only, fn ($q) => $q->where('jd.account_id', $only))
            ->where('j.deleted', 'No')->where('j.status', 'Active')->where('jd.deleted', 'No')->where('jd.status', 'Active')
            ->when($from, fn ($q) => $q->whereRaw("$when BETWEEN ? AND ?", [$from, $to]), fn ($q) => $q->whereRaw("$when <= ?", [$to]))
            ->get(['jd.account_id', 'j.branch_id', DB::raw("$when as datetime"), 'j.journal_no', 'j.reference_no', 'jd.debit', 'jd.credit',
                DB::raw("COALESCE(NULLIF(jd.description, ''), j.remarks) as remarks")])
            ->map(fn ($j) => (object) [
                'key' => 'a:' . $j->account_id, 'datetime' => $j->datetime, 'type' => 'Journal', 'voucher' => $j->journal_no, 'branch_id' => $j->branch_id,
                'party' => null, 'reference' => $j->reference_no, 'remarks' => $j->remarks,
                'in' => round((float) $j->debit, 2), 'out' => round((float) $j->credit, 2),
            ]);

        // Amount transfers: two lines.
        $when = "TIMESTAMP(t.transfer_date, TIME(COALESCE(t.created_date, '00:00:00')))";
        $transfers = self::ctxScope($ctx, DB::table('internal_amount_transfers as t'), 't.branch_id')
            ->leftJoin('chart_of_accounts as fa', 'fa.id', '=', 't.from_account_id')
            ->leftJoin('chart_of_accounts as ta', 'ta.id', '=', 't.to_account_id')
            ->where('t.deleted', 'No')->where('t.status', 'Active')
            ->when($only, fn ($q) => $q->where(fn ($w) => $w->where('t.from_account_id', $only)->orWhere('t.to_account_id', $only)))
            ->when($from, fn ($q) => $q->whereRaw("$when BETWEEN ? AND ?", [$from, $to]), fn ($q) => $q->whereRaw("$when <= ?", [$to]))
            ->get(['t.from_account_id', 't.to_account_id', 't.branch_id', DB::raw("$when as datetime"), 't.transfer_no', 't.amount', 't.reference_no', 't.remarks',
                'fa.account_name as from_account', 'ta.account_name as to_account'])
            ->flatMap(fn ($t) => [
                (object) ['key' => 'a:' . $t->from_account_id, 'datetime' => $t->datetime, 'type' => 'Amount Transfer', 'voucher' => $t->transfer_no, 'branch_id' => $t->branch_id,
                    'party' => null, 'reference' => $t->reference_no, 'remarks' => trim('To ' . $t->to_account . ($t->remarks ? ' · ' . $t->remarks : '')),
                    'in' => 0.0, 'out' => round((float) $t->amount, 2)],
                (object) ['key' => 'a:' . $t->to_account_id, 'datetime' => $t->datetime, 'type' => 'Amount Transfer', 'voucher' => $t->transfer_no, 'branch_id' => $t->branch_id,
                    'party' => null, 'reference' => $t->reference_no, 'remarks' => trim('From ' . $t->from_account . ($t->remarks ? ' · ' . $t->remarks : '')),
                    'in' => round((float) $t->amount, 2), 'out' => 0.0],
            ]);

        // Salary paid from the salary sheet (cancelled payments are reversed, so left out).
        $salaries = self::ctxScope($ctx, DB::table('salary_payments as s'), 's.branch_id')
            ->where('s.payment_status', 'Posted')
            ->when($only, fn ($q) => $q->where('s.payment_account_id', $only))
            ->where(fn ($q) => $range($q, 's.payment_date'))
            ->get(['s.payment_account_id', 's.branch_id', 's.payment_date', 's.created_date', 's.payment_no', 's.month', 's.employee_count', 's.total_paid', 's.remarks'])
            ->map(fn ($s) => (object) [
                'key' => 'a:' . $s->payment_account_id,
                'datetime' => substr($s->payment_date, 0, 10) . ' ' . ($s->created_date ? substr($s->created_date, 11, 8) : '00:00:00'),
                'type' => 'Salary', 'voucher' => $s->payment_no, 'party' => $s->employee_count . ' employee(s)', 'branch_id' => $s->branch_id,
                'reference' => Carbon::parse($s->month)->format('M Y'), 'remarks' => $s->remarks,
                'in' => 0.0, 'out' => round((float) $s->total_paid, 2),
            ]);

        // Employee loans / advance salary: payout out, cash repayment in (cancelled payouts are reversed, so left out).
        $loans = self::ctxScope($ctx, DB::table('employee_loan_transactions as t'), 'l.branch_id')
            ->join('employee_loans as l', 'l.id', '=', 't.employee_loan_id')
            ->join('employees as em', 'em.id', '=', 'l.employee_id')
            ->whereIn('t.txn_type', ['Disbursement', 'Cash Repayment'])->where('t.txn_status', 'Posted')
            ->whereNotNull('t.payment_account_id')
            ->when($only, fn ($q) => $q->where('t.payment_account_id', $only))
            ->where(fn ($q) => $range($q, 't.txn_date'))
            ->get(['t.payment_account_id', 'l.branch_id', 't.txn_date', 't.created_date', 't.txn_type', 't.amount', 't.voucher_no', 't.remarks', 'l.loan_no', 'l.loan_type', 'em.name'])
            ->map(fn ($t) => (object) [
                'key' => 'a:' . $t->payment_account_id,
                'datetime' => substr($t->txn_date, 0, 10) . ' ' . ($t->created_date ? substr($t->created_date, 11, 8) : '00:00:00'),
                'type' => $t->txn_type === 'Disbursement' ? 'Loan / Advance Paid' : 'Loan Repayment', 'voucher' => $t->voucher_no ?: $t->loan_no, 'branch_id' => $t->branch_id,
                'party' => $t->name, 'reference' => $t->loan_no, 'remarks' => trim($t->loan_type . ($t->remarks ? ' · ' . $t->remarks : '')),
                'in' => $t->txn_type === 'Disbursement' ? 0.0 : round((float) $t->amount, 2),
                'out' => $t->txn_type === 'Disbursement' ? round((float) $t->amount, 2) : 0.0,
            ]);

        // Suspense module: advances paid / returned, employee deposits, investor capital in / out (adjustments move no money).
        $suspense = self::ctxScope($ctx, DB::table('suspense_transactions as su'), 'su.branch_id')
            ->leftJoin('parties as spa', 'spa.id', '=', 'su.party_id')
            ->leftJoin('employees as sem', 'sem.id', '=', 'su.employee_id')
            ->where('su.deleted', 'No')->whereNotNull('su.account_id')->whereIn('su.txn_type', array_keys(SuspenseController::CASH_BOOK_TYPES))
            ->when($only, fn ($q) => $q->where('su.account_id', $only))
            ->where(fn ($q) => $range($q, 'su.txn_date'))
            ->get(['su.account_id', 'su.branch_id', 'su.txn_date', 'su.created_date', 'su.txn_type', 'su.person_type', 'su.amount', 'su.voucher_no',
                'su.reference', 'su.purpose', DB::raw("CASE WHEN su.person_type = 'Investor' THEN spa.name ELSE sem.name END AS person")])
            ->map(function ($t) {
                $out = SuspenseController::TYPES[$t->txn_type][3] === 'out';

                return (object) [
                    'key' => 'a:' . $t->account_id,
                    'datetime' => substr($t->txn_date, 0, 10) . ' ' . ($t->created_date ? substr($t->created_date, 11, 8) : '00:00:00'),
                    'type' => SuspenseController::CASH_BOOK_TYPES[$t->txn_type], 'voucher' => $t->voucher_no, 'branch_id' => $t->branch_id,
                    'party' => $t->person, 'reference' => $t->reference,
                    'remarks' => trim($t->person_type . ' ' . strtolower($t->txn_type) . ($t->purpose ? ' · ' . $t->purpose : '')),
                    'in' => $out ? 0.0 : round((float) $t->amount, 2), 'out' => $out ? round((float) $t->amount, 2) : 0.0,
                ];
            });

        $tagBranch = empty($ctx->branches) && BranchController::multiBranch();

        return $payments->concat($expenses)->concat($salaries)->concat($loans)->concat($suspense)->concat($journals)->concat($transfers)
            ->when($only, fn ($c) => $c->filter(fn ($m) => $m->key === 'a:' . $only))
            ->map(function ($m) use ($ctx, $tagBranch) {
                $m->datetime = (string) $m->datetime;
                $m->branch = $tagBranch ? BranchController::label($m->branch_id ?? null) : '';
                if ($m->branch !== '') {
                    $m->party = trim(($m->party ? $m->party . ' · ' : '') . $m->branch);
                }
                if ($m->key === 'm:') {
                    $m->key = 'm:Unspecified';
                }

                return $m;
            })
            ->sortBy([['datetime', 'asc'], ['voucher', 'asc']])->values();
    }

    private function liquidAssetFiltersText(object $ctx): array
    {
        $text = ['Period' => $ctx->from->format('d M Y') . ($ctx->from->isSameDay($ctx->to) ? '' : '  to  ' . $ctx->to->format('d M Y'))];

        if (!empty($ctx->filters['account_id'])) {
            $text['Account'] = DB::table('chart_of_accounts')->where('id', (int) $ctx->filters['account_id'])->value('account_name');
        } elseif (!empty($ctx->filters['method_id'])) {
            $text['Payment method'] = DB::table('chart_of_accounts')->where('id', (int) $ctx->filters['method_id'])->value('account_name');
        } else {
            $text['Payment method'] = 'All liquid asset accounts';
        }

        return $text;
    }

    /* ================================================================== */
    /*  PF ledger builder                                                   */
    /* ================================================================== */

    /**
 * Provident fund ledger of one employee, month by month, grouped by salary period
 * (joining, each increment / salary change). Each month's PF is the one in the salary
 * history effective at the month end; the balance runs from the first month.
 *
 * Filters: employee_id (required), from / to (Y-m, optional: whole service by default).
     */

    private function buildPfLedger(array $filters): array
    {
        $ctx = new \stdClass();
        $ctx->filters = $filters;


        // only an employee of a branch the user sees (the id comes from the request)
        $employee = BranchController::scope(DB::table('employees'), 'branch_id')->where('id', (int) $ctx->filters['employee_id'])->first();
        abort_unless($employee, 404, 'Employee not found.');

        $history = DB::table('employee_salary_histories')
            ->where('employee_id', $employee->id)
            ->orderBy('effective_date')->orderBy('id')
            ->get();

        $result = [
            'employee'     => $employee,
            'periods'      => [],
            'opening'      => 0.0,
            'total'        => 0.0,
            'closing'      => 0.0,
            'months'       => 0,
            'paid_total'     => 0.0,
            'expected_total' => 0.0,
            'deducted_total' => 0.0,
            'projected_total' => 0.0,
            'increments'   => 0,
            'filters_text' => [],
        ];

        if ($history->isEmpty()) {
            $result['filters_text'] = ['Period' => 'No salary history'];

            return $result;
        }

        $first = Carbon::parse($history->first()->effective_date)->startOfMonth();
        $last  = $employee->resign_date ? Carbon::parse($employee->resign_date)->startOfMonth() : now()->startOfMonth();
        $from  = !empty($ctx->filters['from']) ? Carbon::createFromFormat('!Y-m', $ctx->filters['from'])->startOfMonth() : $first->copy();
        $to    = !empty($ctx->filters['to']) ? Carbon::createFromFormat('!Y-m', $ctx->filters['to'])->startOfMonth() : $last->copy();
        $to    = $to->min($last);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from->min($last)];
        }

        // PF actually deducted when the month's payroll was processed (frozen in the run); other months show the expected PF.
        $paid = DB::table('payroll_run_items as d')
            ->join('payroll_runs as p', 'p.id', '=', 'd.payroll_run_id')
            ->where('d.employee_id', $employee->id)->where('p.run_status', 'Posted')
            ->get(['p.month', 'p.run_no as payment_no', 'd.basic_salary', 'd.gross_salary', 'd.pf_amount'])
            ->keyBy(fn ($r) => substr($r->month, 0, 7));

        // Deducted = PF frozen in a processed payroll run (the only PF that is really in the fund);
        // Projected = months not processed yet, at the salary history's PF, pro-rated for the joining /
        // leaving month exactly like PayrollRunController::monthLines(). The balance counts deducted PF only.
        $joined = $employee->joining_date ? Carbon::parse($employee->joining_date)->startOfDay() : null;
        $left   = $employee->resign_date ? Carbon::parse($employee->resign_date)->startOfDay() : null;
        $projectedPf = function (Carbon $month, float $pf) use ($joined, $left): float {
            $days  = $month->daysInMonth;
            $start = $joined && $joined->gt($month) ? $joined : $month->copy()->startOfDay();
            $end   = $left && $left->lt($month->copy()->endOfMonth()->startOfDay()) ? $left : $month->copy()->endOfMonth()->startOfDay();
            $employed = max(0, (int) $start->diffInDays($end, false) + 1);

            return $employed < $days ? round($pf * $employed / $days, 2) : $pf;
        };

        $balance = 0.0;      // deducted PF only
        $before  = 0.0;      // deducted PF before the shown range (opening balance)
        $periods = [];

        for ($month = $first->copy(); $month->lte($to); $month->addMonth()) {
            $monthEnd = $month->copy()->endOfMonth()->toDateString();
            $entry = $history->filter(fn ($h) => $h->effective_date <= $monthEnd)->last();
            $p = $paid[$month->format('Y-m')] ?? null;
            $pf = $p ? (float) $p->pf_amount : $projectedPf($month, (float) $entry->pf_amount);

            if ($month->lt($from)) {
                if ($p) {
                    $before += $pf;   // before the shown range: deducted PF goes into the opening balance
                }
                continue;
            }

            if (!isset($periods[$entry->id])) {
                $periods[$entry->id] = ['entry' => $entry, 'rows' => [], 'total' => 0.0, 'deducted' => 0.0, 'projected' => 0.0];
            }

            if ($p) {
                $balance += $pf;
            }
            $periods[$entry->id]['rows'][] = [
                'month'     => $month->copy(),
                'basic'     => $p ? (float) $p->basic_salary : (float) $entry->basic_salary,
                'gross'     => $p ? (float) $p->gross_salary : (float) $entry->gross_salary,
                'pf'        => $pf,
                'deducted'  => $p ? $pf : 0.0,
                'projected' => $p ? 0.0 : $pf,
                'balance'   => round($before + $balance, 2),
                'source'    => $p ? $p->payment_no : null,   // run no.; null = not processed yet (projected PF)
            ];
            $periods[$entry->id][$p ? 'deducted' : 'projected'] += $pf;
            $periods[$entry->id]['total'] += $p ? $pf : 0.0;
            $result[$p ? 'paid_total' : 'expected_total'] += $pf;
            $result['months']++;
        }

        $result['periods']        = array_values($periods);
        $result['paid_total']     = round($result['paid_total'], 2);
        $result['expected_total'] = round($result['expected_total'], 2);
        $result['deducted_total'] = $result['paid_total'];
        $result['projected_total'] = $result['expected_total'];
        $result['total']          = $result['paid_total'];          // total PF in the fund for the period (deducted only)
        $result['opening']        = round($before, 2);
        $result['closing']        = round($before + $balance, 2);
        $result['increments'] = $history->where('reason', 'Annual increment')->count();
        $result['filters_text'] = [
            'Employee' => $employee->name . ' (' . $employee->employee_code . ')',
            'Designation' => trim(($employee->designation ?? '') . ($employee->department ? ', ' . $employee->department : ''), ', ') ?: '-',
            'Joining'  => $employee->joining_date ? Carbon::parse($employee->joining_date)->format('d M Y') : '-',
            'Period'   => $from->format('M Y') . ' – ' . $to->format('M Y'),
        ];

        return $result;

    }

    /* ================================================================== */
    /*  Salary sheet builder                                                */
    /* ================================================================== */

    /**
 * Monthly salary sheet of active employees, grouped by department. Its columns follow
 * the salary structure of the payroll settings (Scale or Contractual).
 *
 * Filters: month (Y-m, required), department_id, include_bonus.
     */

    public static function buildSalarySheet(array $filters): array
    {
        $ctx = new \stdClass();
        $ctx->filters = $filters;

        $settings = PayrollSetupController::salarySettings();
        $isScale  = $settings->salary_structure === 'Scale';
        $month    = Carbon::createFromFormat('!Y-m', $ctx->filters['month'])->startOfMonth();
        $deptId   = !empty($ctx->filters['department_id']) ? (int) $ctx->filters['department_id'] : null;

        // Processed rows (frozen) of the month, and live lines for everyone not processed yet.
        $items = BranchController::scope(DB::table('payroll_run_items as i'), 'e.branch_id')
            ->join('payroll_runs as r', 'r.id', '=', 'i.payroll_run_id')
            ->join('employees as e', 'e.id', '=', 'i.employee_id')
            ->where('r.month', $month->toDateString())->where('r.run_status', 'Posted')
            ->when($deptId, fn ($q) => $q->where('e.department_id', $deptId))
            ->get(['i.*', 'r.run_no', 'e.employee_code', 'e.name', 'e.salary_payment_mode', 'e.bank_name', 'e.bank_account_no',
                'e.mobile_banking_provider', 'e.mobile_banking_no'])
            ->keyBy('employee_id');

        $live = collect(PayrollRunController::monthLines($month, $deptId, !empty($ctx->filters['include_bonus'])))
            ->reject(fn ($l) => isset($items[$l['employee_id']]));

        // Paid so far, per item and payment method (Cash, Bank, Mobile Banking ...).
        $paidRows = DB::table('salary_payment_details as d')
            ->join('salary_payments as p', 'p.id', '=', 'd.salary_payment_id')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'p.payment_account_id')
            ->leftJoin('chart_of_accounts as m', 'm.id', '=', 'a.parent_id')
            ->where('p.payment_status', 'Posted')->whereIn('d.payroll_run_item_id', $items->pluck('id')->all() ?: [0])
            ->groupBy('d.payroll_run_item_id', 'm.account_name')
            ->selectRaw("d.payroll_run_item_id, COALESCE(m.account_name, 'Other') AS method, SUM(d.amount) AS amount")
            ->get();
        $methods = $paidRows->pluck('method')->unique()->sort()->values()->all();
        $paidBy = [];
        foreach ($paidRows as $p) {
            $paidBy[$p->payroll_run_item_id][$p->method] = (float) $p->amount;
        }

        $employees = DB::table('employees')->whereIn('id', array_merge($items->keys()->all(), $live->keys()->all()) ?: [0])
            ->get(['id', 'department', 'salary_payment_mode', 'bank_name', 'bank_account_no', 'mobile_banking_provider', 'mobile_banking_no'])
            ->keyBy('id');

        $rows = [];
        foreach ($items as $it) {
            $row = (array) $it;
            $row['item_id']           = $it->id;
            $row['grade_step']        = $it->grade_step ?: '-';
            $row['bonus_detail']      = json_decode($it->bonus_detail ?? '[]', true) ?: [];
            $row['additions_detail']  = json_decode($it->additions_detail ?? '[]', true) ?: [];
            $row['deductions_detail'] = json_decode($it->deductions_detail ?? '[]', true) ?: [];
            $row['loan_detail']       = json_decode($it->loan_detail ?? '[]', true) ?: [];
            foreach (['basic_salary', 'house_rent', 'compensation', 'medical_allowance', 'gross_salary', 'bonus_amount', 'additions',
                'deductions', 'lwp_days', 'lwp_deduction', 'pf_amount', 'loan_deduction', 'net_payable', 'paid_amount'] as $k) {
                $row[$k] = (float) $it->$k;
            }
            $row['paid_by'] = $paidBy[$it->id] ?? [];
            $row['due']     = round($row['net_payable'] - $row['paid_amount'], 2);
            $row['state']   = $row['due'] <= 0.004 ? 'Paid' : ($row['paid_amount'] > 0 ? 'Partly paid' : 'Processed');
            $rows[] = $row;
        }
        foreach ($live as $l) {
            $rows[] = $l + ['item_id' => null, 'run_no' => null, 'paid_by' => [], 'paid_amount' => 0.0, 'due' => $l['net_payable'], 'state' => 'Not processed'];
        }

        // Joined / left during the month: monthLines() deducts the days outside employment ("Not employed (n days)").
        // The sheet shows that as a pro-rated gross next to the full gross instead of hiding it inside Deductions.
        foreach ($rows as &$row) {
            $off = 0.0;
            $note = [];
            $kept = [];
            foreach ($row['deductions_detail'] as $d) {
                if (empty($d['id']) && str_starts_with((string) ($d['name'] ?? ''), 'Not employed')) {
                    $off += (float) $d['amount'];
                    $note[] = trim($d['name'] . ' ' . ($d['remarks'] ?? ''));
                } else {
                    $kept[] = $d;
                }
            }
            $row['prorate_off']       = round($off, 2);
            $row['earned_gross']      = round($row['gross_salary'] - $off, 2);
            $row['earned_note']       = implode('; ', $note);
            $row['deductions']        = round($row['deductions'] - $off, 2);
            $row['deductions_detail'] = $kept;
        }
        unset($row);

        // Department, then employee code.
        usort($rows, fn ($a, $b) => [$employees[$a['employee_id']]->department === null, $employees[$a['employee_id']]->department, $a['employee_code']]
            <=> [$employees[$b['employee_id']]->department === null, $employees[$b['employee_id']]->department, $b['employee_code']]);

        $any = fn ($k) => collect($rows)->contains(fn ($r) => (float) $r[$k] != 0);

        // key => [heading, is money]
        $columns = ['employee_code' => ['ID', false], 'name' => ['Employee', false], 'designation' => ['Designation', false]];
        if ($isScale) {
            $columns += [
                'grade_step'        => ['Grade / Step', false],
                'basic_salary'      => ['Basic', true],
                'house_rent'        => ['House Rent', true],
                'compensation'      => ['Compensation', true],
                'medical_allowance' => ['Medical', true],
                'gross_salary'      => ['Gross', true],
            ];
        } else {
            $columns['gross_salary'] = ['Contract Salary', true];
        }
        if ($any('prorate_off')) {
            $columns['earned_gross'] = ['Pro-rated Gross', true];
        }
        foreach (['bonus_amount' => 'Bonus', 'additions' => 'Additions', 'deductions' => 'Deductions'] as $k => $label) {
            if ($any($k)) {
                $columns[$k] = [$label, true];
            }
        }
        if ($any('lwp_days')) {
            $columns['lwp_days']      = ['Unpaid Leave Days', true];
            $columns['lwp_deduction'] = ['Leave Deduction', true];
        }
        if ($any('pf_amount')) {
            $columns['pf_amount'] = ['PF', true];
        }
        if ($any('loan_deduction')) {
            $columns['loan_deduction'] = ['Loan / Advance', true];
        }
        $columns['net_payable'] = ['Net Payable', true];
        foreach ($methods as $method) {
            $columns['paid:' . $method] = ['Paid · ' . $method, true];
        }
        $columns['paid_amount'] = ['Total Paid', true];
        $columns['due']         = ['Due', true];

        $moneyKeys = array_keys(array_filter($columns, fn ($c) => $c[1]));
        $blank     = array_fill_keys($moneyKeys, 0.0);
        $groups    = [];
        $totals    = $blank;

        foreach ($rows as $row) {
            foreach ($methods as $method) {
                $row['paid:' . $method] = (float) ($row['paid_by'][$method] ?? 0);
            }
            $emp = $employees[$row['employee_id']];
            $row['bank'] = self::salarySheetPaymentText($ctx, $emp);

            $dept = $emp->department ?: 'No department';
            $groups[$dept] ??= ['name' => $dept, 'rows' => [], 'totals' => $blank];
            $groups[$dept]['rows'][] = $row;

            foreach ($moneyKeys as $k) {
                $groups[$dept]['totals'][$k] += $row[$k];
                $totals[$k] += $row[$k];
            }
        }

        $unprocessed = collect($rows)->where('state', 'Not processed')->filter(fn ($r) => $r['gross_salary'] > 0 || $r['bonus_amount'] > 0 || $r['additions'] > 0);
        $payable = collect($rows)->filter(fn ($r) => $r['item_id'] && $r['due'] > 0.004)->values();

        $filtersText = [
            'Month'     => $month->format('F Y'),
            'Structure' => $settings->salary_structure . ' salary'
                . ($isScale ? ' (HR ' . (float) $settings->house_rent_percent . '%, Compensation ' . (float) $settings->compensation_percent . '%, Medical ' . (float) $settings->medical_percent . '% of basic)' : ''),
            'PF'        => $settings->pf_enabled === 'Yes' ? (float) $settings->pf_percent . '% of ' . ($isScale ? 'basic' : 'salary') : 'Not deducted',
        ];
        if (isset($columns['earned_gross'])) {
            $filtersText['Pro-rated'] = 'Joined / left during the month: gross for the days employed only (gross / ' . $month->daysInMonth . ' per day)';
        }
        if (isset($columns['lwp_days'])) {
            $filtersText['Unpaid leave'] = 'Deducted at gross / ' . $month->daysInMonth . ' days per approved unpaid leave day';
        }
        if ($deptId) {
            $filtersText['Department'] = DB::table('departments')->where('id', $deptId)->value('name');
        }
        $filtersText['Status'] = count($items) . ' processed, ' . $unprocessed->count() . ' not processed · '
            . number_format($totals['paid_amount'], 2) . ' paid, ' . number_format($totals['due'], 2) . ' due';

        return [
            'month'        => $month,
            'is_scale'     => $isScale,
            'columns'      => $columns,
            'money_keys'   => $moneyKeys,
            'groups'       => array_values($groups),
            'totals'       => $totals,
            'count'        => count($rows),
            'methods'      => $methods,
            // For "Process Salary": employees not processed yet.
            'to_process'   => [
                'count' => $unprocessed->count(),
                'gross' => round($unprocessed->sum('earned_gross'), 2),
                'bonus' => round($unprocessed->sum('bonus_amount'), 2),
                'additions' => round($unprocessed->sum('additions'), 2),
                'deductions' => round($unprocessed->sum('deductions') + $unprocessed->sum('lwp_deduction'), 2),
                'pf'    => round($unprocessed->sum('pf_amount'), 2),
                'loan'  => round($unprocessed->sum('loan_deduction'), 2),
                'net'   => round($unprocessed->sum('net_payable'), 2),
            ],
            // For "Pay Salary": processed employees with something due.
            'to_pay'       => $payable->map(fn ($r) => [
                'item_id' => $r['item_id'], 'name' => $r['name'], 'code' => $r['employee_code'],
                'department' => $employees[$r['employee_id']]->department, 'net' => $r['net_payable'],
                'paid' => $r['paid_amount'], 'due' => $r['due'], 'bank' => self::salarySheetPaymentText($ctx, $employees[$r['employee_id']]),
            ])->all(),
            'paid_count'   => collect($rows)->where('state', 'Paid')->count(),
            'filters_text' => $filtersText,
        ];
    }

    private static function salarySheetPaymentText(object $ctx, object $emp): string
    {
        return match ($emp->salary_payment_mode) {
            'Bank'           => trim('Bank ' . $emp->bank_account_no),
            'Mobile Banking' => trim(($emp->mobile_banking_provider ?: 'Mobile') . ' ' . $emp->mobile_banking_no),
            default          => (string) ($emp->salary_payment_mode ?: 'Cash'),
        };
    }
}
