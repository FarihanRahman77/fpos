<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * VAT, loyalty points and customer credit limits. The sale (SaleController::store) calls the rules here:
 *   vat()          — VAT of a sale's lines after the sale discount (inclusive or added on top)
 *   redeemValue()  — money value of points, capped by the rules
 *   earnPoints()   — points earned for an amount
 *   creditCheck()  — null when the new due fits the customer's credit limit, else the reason
 *   addPoints()    — writes a loyalty transaction and the customer's balance
 * Settings are stored in system_settings.
 */
class CustomerProgramController extends Controller
{
    use VoucherHelperTrait;

    public const DEFAULTS = [
        'vat_enabled'          => '0',
        'vat_rate'             => '15',
        'vat_inclusive'        => '0',
        'vat_purchase_enabled' => '0',
        'loyalty_enabled'      => '0',
        'loyalty_earn_amount'  => '100',   // spend this much ...
        'loyalty_earn_points'  => '1',     // ... to earn this many points
        'loyalty_point_value'  => '1',     // 1 point = this much money off
        'loyalty_min_redeem'   => '50',    // fewest points that can be redeemed
        'loyalty_max_percent'  => '50',    // points can pay at most this % of a sale
        'credit_mode'          => 'block', // block | warn | off
    ];

    public static function setting(string $key)
    {
        return SystemSettingController::get($key, self::DEFAULTS[$key] ?? null);
    }

    public static function on(string $key): bool
    {
        return (string) self::setting($key) === '1';
    }

    /* ------------------------------------------------------------------ */
    /*  Rules                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * $lines: [['product_id' => .., 'total' => line total after line discount], ...]; $discount = sale discount.
     * Returns ['inclusive' => bool, 'amount' => total VAT, 'lines' => [index => ['rate' => %, 'vat' => amount]]].
     */
    public static function vat(array $lines, float $discount): array
    {
        $out = ['inclusive' => self::on('vat_inclusive'), 'amount' => 0.0, 'lines' => []];
        if (!self::on('vat_enabled')) {
            return $out;
        }

        $subtotal = array_sum(array_column($lines, 'total'));
        $share = $subtotal > 0 ? max(0, 1 - $discount / $subtotal) : 0;   // sale discount spread over the lines
        $rates = DB::table('products')->whereIn('id', array_column($lines, 'product_id') ?: [0])->pluck('vat_percent', 'id');
        $default = (float) self::setting('vat_rate');

        foreach ($lines as $i => $l) {
            $rate = $rates[$l['product_id']] ?? null;
            $rate = $rate === null ? $default : (float) $rate;
            $base = (float) $l['total'] * $share;
            $vat = $out['inclusive'] ? $base - $base / (1 + $rate / 100) : $base * $rate / 100;
            $out['lines'][$i] = ['rate' => $rate, 'vat' => round($vat, 2)];
            $out['amount'] += $vat;
        }
        $out['amount'] = round($out['amount'], 2);

        return $out;
    }

    public static function earnPoints(float $amount): float
    {
        $per = (float) self::setting('loyalty_earn_amount');
        if (!self::on('loyalty_enabled') || $per <= 0 || $amount <= 0) {
            return 0.0;
        }

        return floor($amount / $per) * (float) self::setting('loyalty_earn_points');
    }

    /** [points actually used, money value] for a requested redemption on a sale total. */
    public static function redeemValue(int $partyId, float $points, float $saleTotal): array
    {
        if (!self::on('loyalty_enabled') || $points <= 0) {
            return [0.0, 0.0];
        }
        $balance = (float) DB::table('parties')->where('id', $partyId)->value('loyalty_points');
        abort_if($points > $balance + 0.004, 422, 'The customer has only ' . number_format($balance, 2) . ' points.');
        abort_if($points < (float) self::setting('loyalty_min_redeem'), 422, 'At least ' . (float) self::setting('loyalty_min_redeem') . ' points must be redeemed at a time.');

        $value = (float) self::setting('loyalty_point_value');
        $cap = $saleTotal * (float) self::setting('loyalty_max_percent') / 100;
        $money = min($points * $value, $cap);
        $used = $value > 0 ? round($money / $value, 2) : 0;

        return [$used, round($money, 2)];
    }

    /** Null if allowed, otherwise the message. $newDue = due this sale adds. */
    public static function creditCheck(int $partyId, float $newDue): ?string
    {
        $mode = self::setting('credit_mode');
        if ($mode === 'off' || $newDue <= 0.004) {
            return null;
        }
        $party = DB::table('parties')->where('id', $partyId)->first(['name', 'type', 'credit_limit', 'opening_balance', 'opening_balance_type']);
        $limit = (float) ($party->credit_limit ?? 0);
        if (!$party || $limit <= 0) {
            return null;
        }
        // An "Advance" opening balance is money the customer already has with us, not a due.
        $opening = (float) $party->opening_balance * (($party->opening_balance_type ?? 'Due') === 'Advance' ? -1 : 1);
        $ledger = DB::table('payments')->where('party_id', $partyId)->where('branch_id', BranchController::activeId())->where('deleted', 'No')->where('status', 'Active')
            ->selectRaw('COALESCE(SUM(credit), 0) c, COALESCE(SUM(debit), 0) d')->first();
        $current = (BranchController::isMainBranch() ? $opening : 0) + (float) $ledger->c - (float) $ledger->d;

        if ($current + $newDue <= $limit + 0.004) {
            return null;
        }

        return $party->name . ' would owe ' . number_format($current + $newDue, 2) . ', over the credit limit of ' . number_format($limit, 2)
            . ' (now owes ' . number_format($current, 2) . ').';
    }

    public static function addPoints(int $partyId, float $points, string $type, ?int $saleId, float $amount, ?string $remarks = null, ?int $saleReturnId = null): void
    {
        if (abs($points) < 0.004) {
            return;
        }
        DB::table('parties')->where('id', $partyId)->increment('loyalty_points', $points);
        DB::table('loyalty_transactions')->insert([
            'party_id'      => $partyId,
            'sale_id'       => $saleId,
            'sale_return_id' => $saleReturnId,
            'txn_type'      => $type,
            'points'        => round($points, 2),
            'balance_after' => (float) DB::table('parties')->where('id', $partyId)->value('loyalty_points'),
            'amount'        => round($amount, 2),
            'remarks'       => $remarks,
            'branch_id'     => BranchController::activeId(),
            'created_by'    => Auth::id(),
            'created_date'  => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /** Undo a sale's loyalty (sale deleted). */
    public static function reverseSale(int $saleId): void
    {
        // Only the sale's own Earn / Redeem: a return's point reversal is undone when that return is deleted.
        foreach (DB::table('loyalty_transactions')->where('sale_id', $saleId)->whereIn('txn_type', ['Earn', 'Redeem'])->get() as $t) {
            self::addPoints((int) $t->party_id, -(float) $t->points, 'Reverse', $saleId, (float) $t->amount, 'Sale deleted');
        }
    }

    /** Customer info for the POS / sale screen. */
    public static function customerInfo(int $partyId): array
    {
        $p = DB::table('parties')->where('id', $partyId)->first(['credit_limit', 'loyalty_points']);

        return [
            'credit_limit'     => (float) ($p->credit_limit ?? 0),
            'loyalty_enabled'  => self::on('loyalty_enabled'),
            'loyalty_points'   => (float) ($p->loyalty_points ?? 0),
            'point_value'      => (float) self::setting('loyalty_point_value'),
            'min_redeem'       => (float) self::setting('loyalty_min_redeem'),
            'max_percent'      => (float) self::setting('loyalty_max_percent'),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Settings page                                                      */
    /* ------------------------------------------------------------------ */

    public function settings()
    {
        $values = [];
        foreach (array_keys(self::DEFAULTS) as $k) {
            $values[$k] = self::setting($k);
        }

        return view('admin.customers.settings', ['s' => $values]);
    }

    public function saveSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vat_rate'            => ['required', 'numeric', 'min:0', 'max:100'],
            'loyalty_earn_amount' => ['required', 'numeric', 'gt:0'],
            'loyalty_earn_points' => ['required', 'numeric', 'gt:0'],
            'loyalty_point_value' => ['required', 'numeric', 'gt:0'],
            'loyalty_min_redeem'  => ['required', 'numeric', 'min:0'],
            'loyalty_max_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'credit_mode'         => ['required', Rule::in(['block', 'warn', 'off'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        foreach (['vat_enabled', 'vat_inclusive', 'vat_purchase_enabled', 'loyalty_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        SystemSettingController::put($data);

        return response()->json(['status' => true, 'message' => 'Settings saved.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Loyalty                                                            */
    /* ------------------------------------------------------------------ */

    public function loyalty()
    {
        return view('admin.customers.loyalty', ['enabled' => self::on('loyalty_enabled')]);
    }

    public function loyaltyList()
    {
        $rows = DB::table('parties as p')->where('p.deleted', 'No')->whereIn('p.type', ['Customer', 'Walk-In Customer'])
            ->leftJoin('loyalty_transactions as t', 't.party_id', '=', 'p.id')
            ->groupBy('p.id', 'p.name', 'p.phone', 'p.type', 'p.loyalty_points', 'p.credit_limit')
            ->selectRaw("p.id, p.name, p.phone, p.type, p.loyalty_points, p.credit_limit,
                COALESCE(SUM(CASE WHEN t.txn_type = 'Earn' THEN t.points END), 0) earned,
                COALESCE(-SUM(CASE WHEN t.txn_type = 'Redeem' THEN t.points END), 0) redeemed, MAX(t.created_at) last_at")
            ->orderByDesc('p.loyalty_points')->get();

        return response()->json(['status' => true, 'data' => $rows, 'point_value' => (float) self::setting('loyalty_point_value')]);
    }

    public function loyaltyHistory(int $partyId)
    {
        // A sale return's point reversal shows as "INV-… / SR-…".
        $rows = BranchController::scope(DB::table('loyalty_transactions as t'), 't.branch_id')->leftJoin('sales as s', 's.id', '=', 't.sale_id')
            ->leftJoin('sale_returns as sr', 'sr.id', '=', 't.sale_return_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')->where('t.party_id', $partyId)
            ->orderByDesc('t.id')->limit(200)
            ->get(['t.*', DB::raw("CONCAT_WS(' / ', s.invoice_no, sr.return_no) as invoice_no"), 'u.name as by_name']);

        return response()->json(['status' => true, 'data' => $rows]);
    }

    /** Manual adjustment of a customer's points or credit limit. */
    public function adjust(Request $request, int $partyId)
    {
        $validator = Validator::make($request->all(), [
            'points'       => ['nullable', 'numeric'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'remarks'      => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }
        if (!DB::table('parties')->where('id', $partyId)->where('deleted', 'No')->whereIn('type', ['Customer', 'Walk-In Customer'])->exists()) {
            return response()->json(['status' => false, 'message' => 'Customer not found.'], 404);
        }

        try {
            DB::transaction(function () use ($request, $partyId) {
                if ($request->filled('credit_limit')) {
                    DB::table('parties')->where('id', $partyId)->update(['credit_limit' => round((float) $request->input('credit_limit'), 2)]);
                }
                $points = round((float) $request->input('points'), 2);
                if ($points) {
                    $balance = (float) DB::table('parties')->where('id', $partyId)->value('loyalty_points');
                    abort_if($balance + $points < -0.004, 422, 'Points cannot go below zero.');
                    self::addPoints($partyId, $points, 'Adjust', null, 0, $this->nullIfBlank($request->input('remarks')) ?? 'Manual adjustment');
                }
            });

            return response()->json(['status' => true, 'message' => 'Saved.']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Loyalty adjust error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  VAT report                                                         */
    /* ------------------------------------------------------------------ */

    public function vatReport()
    {
        return view('admin.customers.vat-report');
    }

    private function buildVat(Request $request): array
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->toDateString();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->toDateString();

        $sales = BranchController::scope(DB::table('sales as s'), 's.branch_id')->leftJoin('parties as p', 'p.id', '=', 's.customer_id')
            ->where('s.deleted', 'No')->where('s.status', 'Active')->whereBetween('s.sale_date', [$from, $to])->where('s.vat_amount', '>', 0)
            ->orderBy('s.sale_date')->orderBy('s.id')
            ->get(['s.invoice_no as ref', 's.sale_date as date', 'p.name as party', 's.grand_total', 's.vat_amount', 's.vat_inclusive']);
        $purchases = BranchController::scope(DB::table('purchases as s'), 's.branch_id')->leftJoin('parties as p', 'p.id', '=', 's.supplier_id')
            ->where('s.deleted', 'No')->where('s.status', 'Active')->whereBetween('s.purchase_date', [$from, $to])->where('s.vat_amount', '>', 0)
            ->orderBy('s.purchase_date')->orderBy('s.id')
            ->get(['s.invoice_no as ref', 's.purchase_date as date', 'p.name as party', 's.grand_total', 's.vat_amount', 's.vat_percent']);

        // Sale returns in the period give back their share of the sale's VAT (saved on the return).
        $returns = BranchController::scope(DB::table('sale_returns as r'), 'r.branch_id')->leftJoin('parties as p', 'p.id', '=', 'r.customer_id')
            ->leftJoin('sales as s', 's.id', '=', 'r.sale_id')
            ->where('r.deleted', 'No')->where('r.status', 'Active')->whereBetween(DB::raw('DATE(r.datetime)'), [$from, $to])->where('r.vat_amount', '>', 0)
            ->orderBy('r.datetime')->orderBy('r.id')
            ->get(['r.return_no as ref', DB::raw('DATE(r.datetime) as date'), 's.invoice_no', 'p.name as party', 'r.grand_total', 'r.vat_amount']);

        $net = fn ($r) => (float) $r->grand_total - (float) $r->vat_amount;
        $salesVat = round($sales->sum('vat_amount'), 2);
        $returnsVat = round($returns->sum('vat_amount'), 2);
        $output = round($salesVat - $returnsVat, 2);
        $input = round($purchases->sum('vat_amount'), 2);

        return [
            'from' => $from, 'to' => $to,
            'sales' => $sales->map(fn ($r) => ['ref' => $r->ref, 'date' => $r->date, 'party' => $r->party ?: 'Walk-In Customer',
                'taxable' => round($net($r), 2), 'vat' => (float) $r->vat_amount, 'total' => (float) $r->grand_total])->values()->all(),
            'returns' => $returns->map(fn ($r) => ['ref' => $r->ref . ($r->invoice_no ? ' (' . $r->invoice_no . ')' : ''), 'date' => $r->date,
                'party' => $r->party ?: 'Walk-In Customer', 'taxable' => -round($net($r), 2), 'vat' => -(float) $r->vat_amount,
                'total' => -(float) $r->grand_total])->values()->all(),
            'sales_vat' => $salesVat, 'returns_vat' => $returnsVat,
            'taxable_returns' => round($returns->sum($net), 2),
            'purchases' => $purchases->map(fn ($r) => ['ref' => $r->ref, 'date' => $r->date, 'party' => $r->party ?: '-',
                'taxable' => round($net($r), 2), 'vat' => (float) $r->vat_amount, 'total' => (float) $r->grand_total])->values()->all(),
            'output' => $output, 'input' => $input, 'payable' => round($output - $input, 2),
            'taxable_sales' => round($sales->sum($net) - $returns->sum($net), 2), 'taxable_purchases' => round($purchases->sum($net), 2),
            'branch' => BranchController::visibleLabel(),
        ];
    }

    public function vatData(Request $request)
    {
        try {
            return response()->json(['status' => true] + $this->buildVat($request));
        } catch (Throwable $e) {
            Log::error('VAT report error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to build the VAT report.'], 500);
        }
    }

    public function vatExcel(Request $request)
    {
        $r = $this->buildVat($request);
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('VAT');
        $sheet->fromArray([['VAT Report', $r['from'] . ' to ' . $r['to'], $r['branch']], [],
            ['VAT on sales', $r['sales_vat']], ['Less VAT on sale returns', -$r['returns_vat']],
            ['Output VAT (sales less returns)', $r['output']], ['Input VAT (purchases)', $r['input']], ['VAT payable', $r['payable']], [],
            ['Sales', '', '', '', '', ''], ['Invoice', 'Date', 'Customer', 'Taxable', 'VAT', 'Total']]);
        $row = 11;
        foreach ($r['sales'] as $l) {
            $sheet->fromArray([array_values($l)], null, 'A' . $row++);
        }
        $row++;
        $sheet->fromArray([['Sale returns'], ['Return (invoice)', 'Date', 'Customer', 'Taxable', 'VAT', 'Total']], null, 'A' . $row);
        $row += 2;
        foreach ($r['returns'] as $l) {
            $sheet->fromArray([array_values($l)], null, 'A' . $row++);
        }
        $row++;
        $sheet->fromArray([['Purchases'], ['Invoice', 'Date', 'Supplier', 'Taxable', 'VAT', 'Total']], null, 'A' . $row);
        $row += 2;
        foreach ($r['purchases'] as $l) {
            $sheet->fromArray([array_values($l)], null, 'A' . $row++);
        }
        foreach (range('A', 'F') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'VAT-' . $r['from'] . '-' . $r['to'] . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
