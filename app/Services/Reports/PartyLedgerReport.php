<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
class PartyLedgerReport
{
    public const LEDGERS = [
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

    public string $ledger;
    public array $config;
    public Carbon $from;
    public Carbon $to;
    public array $filters;

    public function __construct(string $ledger, array $filters)
    {
        $this->ledger = $ledger;
        $this->config = self::LEDGERS[$ledger];
        $this->from   = Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $this->to     = Carbon::parse($filters['to'] ?? now())->endOfDay();

        $this->filters = [
            'party_id'   => $filters['party_id'] ?? null,
            'party_type' => $filters['party_type'] ?? null,
            'hide_zero'  => (bool) ($filters['hide_zero'] ?? true),
        ];
    }

    public function build(): array
    {
        $f = $this->filters;

        $parties = DB::table('parties')
            ->where('deleted', 'No')
            ->whereIn('type', $f['party_type'] ? [$f['party_type']] : $this->config['types'])
            ->when($f['party_id'], fn ($q) => $q->where('id', (int) $f['party_id']))
            ->orderBy('name')
            ->get(['id', 'code', 'type', 'name', 'phone', 'address', 'opening_balance', 'opening_balance_type', 'opening_balance_date']);

        $ids = $parties->pluck('id')->all();

        // Net (Dr - Cr) of every transaction before the period, per party.
        $before = $this->entries($ids)
            ->where('p.datetime', '<', $this->from)
            ->groupBy('p.party_id')
            ->selectRaw('p.party_id, SUM((' . $this->drSql() . ') - (' . $this->crSql() . ')) as net')
            ->pluck('net', 'party_id');

        $rows = $this->entries($ids)
            ->whereBetween('p.datetime', [$this->from, $this->to])
            ->leftJoin('sales as sa', 'sa.id', '=', 'p.sale_id')
            ->leftJoin('sale_returns as sr', 'sr.id', '=', 'p.sale_return_id')
            ->leftJoin('sale_orders as so', 'so.id', '=', 'p.order_id')
            ->leftJoin('purchases as pu', 'pu.id', '=', 'p.purchase_id')
            ->leftJoin('purchase_returns as pr', 'pr.id', '=', 'p.purchase_return_id')
            ->leftJoin('bills as b', 'b.id', '=', 'p.bill_id')
            ->orderBy('p.datetime')->orderBy('p.id')
            ->get([
                'p.id', 'p.party_id', 'p.code', 'p.payment_type', 'p.datetime', 'p.payment_method', 'p.remarks',
                'p.purchase_return_id',
                DB::raw($this->drSql() . ' as dr'),
                DB::raw($this->crSql() . ' as cr'),
                DB::raw('COALESCE(pr.return_no, sr.return_no, so.order_no, pu.invoice_no, sa.invoice_no, b.bill_no) as reference_no'),
            ])
            ->groupBy('party_id');

        $grand = ['parties' => 0, 'opening' => 0.0, 'dr' => 0.0, 'cr' => 0.0, 'closing' => 0.0, 'balance_dr' => 0.0, 'balance_cr' => 0.0];

        $items = $parties->map(function ($party) use ($before, $rows, &$grand) {
            [$partyOpening, $openingDate] = $this->partyOpening($party);

            // Party opening balance counts as brought forward unless it is dated inside / after the period.
            $openingInPeriod = $openingDate && $openingDate->betweenIncluded($this->from, $this->to);
            $openingAfter    = $openingDate && $openingDate->gt($this->to);

            $opening = round((float) ($before[$party->id] ?? 0)
                + ($openingInPeriod || $openingAfter ? 0 : $partyOpening), 2);

            $balance = $opening;
            $dr = $cr = 0.0;
            $lines = [];

            if ($openingInPeriod && $partyOpening != 0) {
                $lines[] = $this->line($openingDate, null, 'Opening Balance', null, 'Balance as per party setup',
                    max($partyOpening, 0), max(-$partyOpening, 0), $balance);
            }

            foreach ($rows->get($party->id, collect()) as $r) {
                $lines[] = $this->line(Carbon::parse($r->datetime), $r->code, $this->particular($r), $r->reference_no,
                    trim(implode(' · ', array_filter([$r->payment_method, $r->remarks]))),
                    (float) $r->dr, (float) $r->cr, $balance);
            }

            foreach ($lines as $l) {
                $dr += $l['dr'];
                $cr += $l['cr'];
            }

            if ($this->filters['hide_zero'] && !$lines && $opening == 0) {
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
            'ledger'       => $this->ledger,
            'config'       => $this->config,
            'from'         => $this->from,
            'to'           => $this->to,
            'items'        => $items,
            'totals'       => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $grand),
            'filters_text' => $this->filtersText(),
        ];
    }

    /** "1,250.00 Dr" style balance text. */
    public static function balanceText(float $value): string
    {
        $value = round($value, 2);

        return $value == 0 ? '0.00' : number_format(abs($value), 2) . ($value > 0 ? ' Dr' : ' Cr');
    }

    /** Receivable / Payable / Advance / Settled, for a signed balance. */
    public function balanceLabel(float $value): string
    {
        $value = round($value, 2);

        return $value == 0 ? 'Settled' : $this->config['labels'][$value > 0 ? 'Dr' : 'Cr'];
    }

    /* ------------------------------------------------------------------ */

    /** Active sub-ledger rows of the given parties up to the period end. */
    private function entries(array $ids)
    {
        return DB::table('payments as p')
            ->whereIn('p.party_id', $ids ?: [0])
            ->where('p.deleted', 'No')
            ->where('p.status', 'Active')
            // party_id also holds employee ids for expense rows
            ->where(fn ($q) => $q->whereNull('p.party_type')->orWhere('p.party_type', '<>', 'Employee'))
            ->where('p.datetime', '<=', $this->to);
    }

    /** Debit to the party account. A purchase return (stored as "Purchase Payment") reduces the payable. */
    private function drSql(): string
    {
        return "CASE WHEN p.purchase_return_id IS NOT NULL
                    THEN CASE WHEN p.payment_type = 'Purchase Payment' THEN COALESCE(p.debit, 0) + COALESCE(p.credit, 0) ELSE 0 END
                    ELSE COALESCE(p.credit, 0) END";
    }

    /** Credit to the party account. A supplier refund on a purchase return restores the payable. */
    private function crSql(): string
    {
        return "CASE WHEN p.purchase_return_id IS NOT NULL
                    THEN CASE WHEN p.payment_type = 'Purchase Payment' THEN 0 ELSE COALESCE(p.debit, 0) + COALESCE(p.credit, 0) END
                    ELSE COALESCE(p.debit, 0) END";
    }

    /** One ledger line; moves the running balance (passed by reference). */
    private function line(Carbon $date, ?string $voucher, string $particular, ?string $reference, ?string $note, float $dr, float $cr, float &$balance): array
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

    /** Party setup opening balance, signed Dr - Cr, and its date. */
    private function partyOpening(object $party): array
    {
        $amount = (float) $party->opening_balance;
        $isDue  = $party->opening_balance_type !== 'Advance';

        // Customer due = receivable (Dr); supplier due = payable (Cr). Advance is the reverse.
        $signed = ($this->ledger === 'customer') === $isDue ? $amount : -$amount;

        return [round($signed, 2), $party->opening_balance_date ? Carbon::parse($party->opening_balance_date)->startOfDay() : null];
    }

    /** Readable narration for a sub-ledger row. */
    private function particular(object $r): string
    {
        if ($r->purchase_return_id) {
            return $r->payment_type === 'Purchase Payment' ? 'Purchase Return' : 'Refund Received';
        }

        $customer = $this->ledger === 'customer';

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

    private function filtersText(): array
    {
        $f = $this->filters;
        $text = ['Period' => $this->from->format('d M Y') . ' to ' . $this->to->format('d M Y')];

        if ($f['party_id']) {
            $text[$this->ledger === 'customer' ? 'Customer' : 'Supplier'] = DB::table('parties')->where('id', $f['party_id'])->value('name');
        }
        if ($f['party_type']) {
            $text['Customer Type'] = $f['party_type'];
        }

        return array_filter($text);
    }
}
