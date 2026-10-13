<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock ledger: per product (and variant) the opening balance, every stock
 * movement in the period with a running balance, and the closing balance.
 * Shared by the screen, the PDF and the Excel export so all three match.
 */
class StockLedgerReport
{
    /** Movement types as shown in the report, keyed by stocks.reference_type. */
    public const TYPES = [
        'Opening Stock'   => 'Opening Stock',
        'Purchase'        => 'Purchase',
        'Purchase Return' => 'Purchase Return',
        'Sale'            => 'Sale',
        'Sale Return'     => 'Sale Return',
        'Order'           => 'Order Delivery',
    ];

    public Carbon $from;
    public Carbon $to;
    public array $filters;

    public function __construct(array $filters)
    {
        $this->from = Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $this->to   = Carbon::parse($filters['to'] ?? now())->endOfDay();

        $this->filters = [
            'product_id'    => $filters['product_id'] ?? null,
            'variant_id'    => $filters['variant_id'] ?? null,
            'category_id'   => $filters['category_id'] ?? null,
            'brand_id'      => $filters['brand_id'] ?? null,
            'type'          => $filters['type'] ?? null,
            'only_movement' => (bool) ($filters['only_movement'] ?? true),
        ];
    }

    /**
     * @return array{items: Collection, totals: array, filters_text: array}
     */
    public function build(): array
    {
        $type = $this->filters['type'];

        // Every product / variant that has stock rows and matches the product filters.
        $items = $this->stockQuery()
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
                DB::raw("SUM(CASE WHEN s.date < '{$this->from->toDateTimeString()}' THEN s.in_qty - s.out_qty ELSE 0 END) as opening"),
            ]);

        // Movements inside the period (all types, so the running balance stays true).
        $movements = $this->stockQuery()
            ->whereBetween('s.date', [$this->from, $this->to])
            ->leftJoin('purchases as pu', 'pu.id', '=', 's.purchase_id')
            ->leftJoin('purchase_returns as pr', 'pr.id', '=', 's.purchase_return_id')
            ->leftJoin('sales as sa', 'sa.id', '=', 's.sale_id')
            ->leftJoin('sale_returns as sr', 'sr.id', '=', 's.sale_return_id')
            ->leftJoin('sale_orders as so', 'so.id', '=', 's.order_id')
            ->leftJoin('parties as party', 'party.id', '=', DB::raw('COALESCE(pr.supplier_id, pu.supplier_id, sr.customer_id, sa.customer_id, so.customer_id)'))
            ->orderBy('s.date')->orderBy('s.id')
            ->get([
                's.id', 's.product_id', 's.product_variant_id', 's.date', 's.in_qty', 's.out_qty', 's.remarks',
                DB::raw($this->typeSql() . ' as type_key'),
                DB::raw('COALESCE(pr.return_no, sr.return_no, so.order_no, pu.invoice_no, sa.invoice_no) as reference_no'),
                'party.name as party_name',
            ])
            ->groupBy(fn ($m) => $m->product_id . '-' . ($m->product_variant_id ?? 0));

        $grand = ['opening' => 0.0, 'in' => 0.0, 'out' => 0.0, 'closing' => 0.0, 'items' => 0, 'rows' => 0];

        $report = $items->map(function ($item) use ($movements, $type, &$grand) {
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
                    'type'      => self::TYPES[$m->type_key] ?? ($m->type_key ?: 'Adjustment'),
                    'type_key'  => $m->type_key,
                    'reference' => $m->reference_no,
                    'party'     => $m->party_name,
                    'remarks'   => $m->remarks,
                    'in'        => (float) $m->in_qty,
                    'out'       => (float) $m->out_qty,
                    'balance'   => $balance,
                ];
            }

            if ($this->filters['only_movement'] && !$rows) {
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
            'from'         => $this->from,
            'to'           => $this->to,
            'items'        => $report,
            'totals'       => array_map(fn ($v) => is_float($v) ? round($v, 3) : $v, $grand),
            'filters_text' => $this->filtersText(),
        ];
    }

    /** Active stock rows, narrowed by the product filters. */
    private function stockQuery()
    {
        $f = $this->filters;

        return DB::table('stocks as s')
            ->where('s.deleted', 'No')
            ->where('s.date', '<=', $this->to)
            ->when($f['product_id'], fn ($q) => $q->where('s.product_id', (int) $f['product_id']))
            ->when($f['variant_id'], fn ($q) => $q->where('s.product_variant_id', (int) $f['variant_id']))
            ->when($f['category_id'] || $f['brand_id'], function ($q) use ($f) {
                $q->whereExists(function ($w) use ($f) {
                    $w->select(DB::raw(1))->from('products as fp')->whereColumn('fp.id', 's.product_id')
                        ->when($f['category_id'], fn ($x) => $x->where('fp.category_id', (int) $f['category_id']))
                        ->when($f['brand_id'], fn ($x) => $x->where('fp.brand_id', (int) $f['brand_id']));
                });
            });
    }

    /** Movement type: the stored reference type, or derived from the linked document for old rows. */
    private function typeSql(): string
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
    private function filtersText(): array
    {
        $f = $this->filters;
        $text = ['Period' => $this->from->format('d M Y') . ' to ' . $this->to->format('d M Y')];

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
            $text['Movement'] = self::TYPES[$f['type']] ?? $f['type'];
        }

        return array_filter($text);
    }
}
