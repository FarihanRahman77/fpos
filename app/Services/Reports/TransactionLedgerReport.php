<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transactions ledger (day book): every money transaction in a date-time range,
 * from the payments table (sales, orders, purchases, returns, receipts, payments,
 * discounts, expenses, bills) plus journal vouchers and amount transfers.
 * Shared by the screen, the PDF and the Excel export so all three match.
 *
 * Debit / credit are the company-side entries as posted:
 *  - payments: the stored debit / credit columns (sale = Cr, receipt = Dr,
 *    purchase = Dr, payment made = Cr, ...). Purchase-return rows are the
 *    exception and are classified by their type: return = Cr, refund = Dr.
 *  - journals: each journal line with its own debit / credit.
 *  - amount transfers: Dr the receiving account, Cr the sending account.
 */
class TransactionLedgerReport
{
    /** Transaction types as shown in the report, keyed by type_key. */
    public const TYPES = [
        'Walk-In'                    => 'Walk-In Sale',
        'Wholesale'                  => 'Wholesale Sale',
        'Sale Order'                 => 'Sale Order',
        'Walk-In Sale Return'        => 'Walk-In Sale Return',
        'Wholesale Return'           => 'Wholesale Return',
        'Walk-In Payment Receive'    => 'Walk-In Payment Receive',
        'Wholesale Payment Receive'  => 'Wholesale Payment Receive',
        'Sale Order Payment Receive' => 'Sale Order Payment Receive',
        'Payment Receive'            => 'Payment Receive',
        'Purchase'                   => 'Purchase',
        'Purchase Payment'           => 'Purchase Payment',
        'Purchase Return'            => 'Purchase Return',
        'Purchase Return Refund'     => 'Purchase Return Refund',
        'Payment'                    => 'Payment',
        'Discount'                   => 'Discount',
        'Expense'                    => 'Expense',
        'Bill'                       => 'Bill',
        'Bill Payment'               => 'Bill Payment',
        'Journal'                    => 'Journal',
        'Amount Transfer'            => 'Amount Transfer',
    ];

    public Carbon $from;
    public Carbon $to;
    public array $filters;

    public function __construct(array $filters)
    {
        $this->from = Carbon::parse($filters['from'] ?? now()->startOfDay());
        $this->to   = Carbon::parse($filters['to'] ?? now()->endOfDay());

        // An input without seconds ("...T23:59") still covers the whole last minute.
        if ($this->to->second === 0 && !preg_match('/:\d{2}:\d{2}/', (string) ($filters['to'] ?? ''))) {
            $this->to->second(59);
        }

        $this->filters = [
            'type'     => $filters['type'] ?? null,
            'party_id' => $filters['party_id'] ?? null,
        ];
    }

    public function build(): array
    {
        $type = $this->filters['type'];

        $rows = collect();

        if (!$type || !in_array($type, ['Journal', 'Amount Transfer'], true)) {
            $rows = $rows->merge($this->payments());
        }
        if (!$this->filters['party_id'] && (!$type || $type === 'Journal')) {
            $rows = $rows->merge($this->journals());
        }
        if (!$this->filters['party_id'] && (!$type || $type === 'Amount Transfer')) {
            $rows = $rows->merge($this->transfers());
        }

        $rows = $rows->sortBy([['datetime', 'asc'], ['source', 'asc'], ['id', 'asc']])->values()
            ->map(function ($r) {
                $r->datetime = Carbon::parse($r->datetime);
                $r->type     = self::TYPES[$r->type_key] ?? ($r->type_key ?: 'Other');
                $r->dr       = round((float) $r->dr, 2);
                $r->cr       = round((float) $r->cr, 2);

                return $r;
            });

        $byType = $rows->groupBy('type_key')->map(fn (Collection $g, $key) => [
            'type'  => self::TYPES[$key] ?? ($key ?: 'Other'),
            'count' => $g->count(),
            'dr'    => round($g->sum('dr'), 2),
            'cr'    => round($g->sum('cr'), 2),
        ])->sortBy(fn ($v, $k) => array_search($k, array_keys(self::TYPES), true))->values();

        return [
            'from'         => $this->from,
            'to'           => $this->to,
            'rows'         => $rows,
            'by_type'      => $byType,
            'totals'       => [
                'entries' => $rows->count(),
                'dr'      => round($rows->sum('dr'), 2),
                'cr'      => round($rows->sum('cr'), 2),
            ],
            'filters_text' => $this->filtersText(),
        ];
    }

    /* ------------------------------------------------------------------ */

    private function payments(): Collection
    {
        $f = $this->filters;
        $typeSql = "CASE WHEN p.purchase_return_id IS NOT NULL
                        THEN CASE WHEN p.payment_type = 'Purchase Payment' THEN 'Purchase Return' ELSE 'Purchase Return Refund' END
                        ELSE p.payment_type END";
        $amount = 'COALESCE(p.debit, 0) + COALESCE(p.credit, 0)';

        return DB::table('payments as p')
            ->where('p.deleted', 'No')
            ->where('p.status', 'Active')
            ->whereBetween('p.datetime', [$this->from, $this->to])
            ->when($f['type'], fn ($q) => $q->whereRaw("($typeSql) = ?", [$f['type']]))
            ->when($f['party_id'], fn ($q) => $q->where('p.party_id', (int) $f['party_id'])
                ->where(fn ($w) => $w->whereNull('p.party_type')->orWhere('p.party_type', '<>', 'Employee')))
            ->leftJoin('parties as pa', function ($j) {
                $j->on('pa.id', '=', 'p.party_id')
                    ->where(fn ($w) => $w->whereNull('p.party_type')->orWhere('p.party_type', '<>', 'Employee'));
            })
            ->leftJoin('employees as em', function ($j) {
                $j->on('em.id', '=', 'p.party_id')->where('p.party_type', '=', 'Employee');
            })
            ->leftJoin('sales as sa', 'sa.id', '=', 'p.sale_id')
            ->leftJoin('sale_returns as sr', 'sr.id', '=', 'p.sale_return_id')
            ->leftJoin('sale_orders as so', 'so.id', '=', 'p.order_id')
            ->leftJoin('purchases as pu', 'pu.id', '=', 'p.purchase_id')
            ->leftJoin('purchase_returns as pr', 'pr.id', '=', 'p.purchase_return_id')
            ->leftJoin('bills as b', 'b.id', '=', 'p.bill_id')
            ->leftJoin('expenses as e', 'e.id', '=', 'p.expense_id')
            ->get([
                DB::raw("'1' as source"), 'p.id', 'p.datetime', 'p.code as voucher', 'p.remarks',
                DB::raw("$typeSql as type_key"),
                DB::raw("COALESCE(pa.name, em.name) as party"),
                DB::raw("CASE WHEN em.id IS NOT NULL THEN 'Employee' ELSE pa.type END as party_type"),
                DB::raw('COALESCE(pr.return_no, sr.return_no, so.order_no, pu.invoice_no, sa.invoice_no, b.bill_no, e.expense_no) as reference'),
                DB::raw("NULLIF(COALESCE(NULLIF(p.payment_method_account, ''), p.payment_method), '') as account"),
                DB::raw("CASE WHEN p.purchase_return_id IS NOT NULL
                            THEN CASE WHEN p.payment_type = 'Purchase Payment' THEN 0 ELSE $amount END
                            ELSE COALESCE(p.debit, 0) END as dr"),
                DB::raw("CASE WHEN p.purchase_return_id IS NOT NULL
                            THEN CASE WHEN p.payment_type = 'Purchase Payment' THEN $amount ELSE 0 END
                            ELSE COALESCE(p.credit, 0) END as cr"),
            ]);
    }

    /** Journal voucher lines. Journals carry a date only; the entry time comes from created_date. */
    private function journals(): Collection
    {
        $when = "TIMESTAMP(j.journal_date, TIME(COALESCE(j.created_date, '00:00:00')))";

        return DB::table('journal_details as jd')
            ->join('journals as j', 'j.id', '=', 'jd.journal_id')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'jd.account_id')
            ->where('j.deleted', 'No')->where('j.status', 'Active')
            ->where('jd.deleted', 'No')->where('jd.status', 'Active')
            ->whereRaw("$when BETWEEN ? AND ?", [$this->from, $this->to])
            ->get([
                DB::raw("'2' as source"), 'jd.id', DB::raw("$when as datetime"), 'j.journal_no as voucher',
                DB::raw("'Journal' as type_key"),
                DB::raw('NULL as party'), DB::raw('NULL as party_type'),
                'j.reference_no as reference', 'a.account_name as account',
                DB::raw("COALESCE(NULLIF(jd.description, ''), j.remarks) as remarks"),
                'jd.debit as dr', 'jd.credit as cr',
            ]);
    }

    /** Amount transfers as two lines: Dr receiving account, Cr sending account. */
    private function transfers(): Collection
    {
        $when = "TIMESTAMP(t.transfer_date, TIME(COALESCE(t.created_date, '00:00:00')))";

        return DB::table('internal_amount_transfers as t')
            ->leftJoin('chart_of_accounts as fa', 'fa.id', '=', 't.from_account_id')
            ->leftJoin('chart_of_accounts as ta', 'ta.id', '=', 't.to_account_id')
            ->where('t.deleted', 'No')->where('t.status', 'Active')
            ->whereRaw("$when BETWEEN ? AND ?", [$this->from, $this->to])
            ->get(['t.id', DB::raw("$when as datetime"), 't.transfer_no', 't.amount', 't.reference_no', 't.remarks',
                'fa.account_name as from_account', 'ta.account_name as to_account'])
            ->flatMap(function ($t) {
                $note = trim('Transfer from ' . $t->from_account . ' to ' . $t->to_account . ($t->remarks ? ' · ' . $t->remarks : ''));
                $line = fn ($id, $account, $dr, $cr) => (object) [
                    'source' => '3', 'id' => $id, 'datetime' => $t->datetime, 'voucher' => $t->transfer_no,
                    'type_key' => 'Amount Transfer', 'party' => null, 'party_type' => null,
                    'reference' => $t->reference_no, 'account' => $account, 'remarks' => $note, 'dr' => $dr, 'cr' => $cr,
                ];

                return [
                    $line($t->id * 2, $t->to_account, $t->amount, 0),
                    $line($t->id * 2 + 1, $t->from_account, 0, $t->amount),
                ];
            });
    }

    private function filtersText(): array
    {
        $f = $this->filters;
        $text = ['Period' => $this->from->format('d M Y, h:i:s A') . '  to  ' . $this->to->format('d M Y, h:i:s A')];

        if ($f['type']) {
            $text['Type'] = self::TYPES[$f['type']] ?? $f['type'];
        }
        if ($f['party_id']) {
            $text['Party'] = DB::table('parties')->where('id', $f['party_id'])->value('name');
        }

        return array_filter($text);
    }
}
