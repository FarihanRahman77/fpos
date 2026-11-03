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
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Stock tools: transfers between branches, stock counts / adjustments, batches & expiry.
 *
 * Shared rules (used by sales and purchases too):
 *   onHand()   — stock of a product / variant / batch in a branch
 *   allocate() — which batches a sale takes from: earliest expiry first (FEFO); untracked products → one line
 *   move()     — writes a stock row with an explicit branch
 */
class StockController extends Controller
{
    use VoucherHelperTrait;

    public const ADJUSTMENT_TYPES = ['Stock Count', 'Damage', 'Expired', 'Lost', 'Found', 'Correction'];

    /* ------------------------------------------------------------------ */
    /*  Shared rules                                                       */
    /* ------------------------------------------------------------------ */

    public static function onHand(int $productId, ?int $variantId, int $branchId, ?int $batchId = null): float
    {
        return (float) DB::table('stocks')->where('deleted', 'No')->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('product_variant_id', $variantId), fn ($q) => $q->whereNull('product_variant_id'))
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->sum(DB::raw('in_qty - out_qty'));
    }

    /** [[batch_id|null, qty], ...] — FEFO over batches with stock in the branch; any rest comes without a batch. */
    public static function allocate(int $productId, ?int $variantId, int $branchId, float $qty): array
    {
        $tracked = DB::table('products')->where('id', $productId)->value('track_batch') === 'Yes';
        if (!$tracked) {
            return [[null, $qty]];
        }

        $batches = DB::table('stocks as s')->join('product_batches as b', 'b.id', '=', 's.batch_id')
            ->where('s.deleted', 'No')->where('s.branch_id', $branchId)->where('s.product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('s.product_variant_id', $variantId), fn ($q) => $q->whereNull('s.product_variant_id'))
            ->groupBy('b.id', 'b.expiry_date')
            ->havingRaw('SUM(s.in_qty - s.out_qty) > 0')
            ->orderByRaw('b.expiry_date IS NULL')->orderBy('b.expiry_date')->orderBy('b.id')
            ->selectRaw('b.id, SUM(s.in_qty - s.out_qty) AS left_qty')->get();

        $out = [];
        foreach ($batches as $b) {
            if ($qty <= 0.0005) {
                break;
            }
            $take = min($qty, (float) $b->left_qty);
            $out[] = [(int) $b->id, round($take, 3)];
            $qty -= $take;
        }
        if ($qty > 0.0005) {
            $out[] = [null, round($qty, 3)];
        }

        return $out;
    }

    /** Insert one stock movement (positive qty = in, negative = out). */
    public static function move(array $row): void
    {
        $qty = (float) $row['qty'];
        DB::table('stocks')->insert([
            'branch_id'          => $row['branch_id'],
            'product_id'         => $row['product_id'],
            'product_variant_id' => $row['product_variant_id'] ?? null,
            'batch_id'           => $row['batch_id'] ?? null,
            'in_qty'             => $qty > 0 ? $qty : 0,
            'out_qty'            => $qty < 0 ? -$qty : 0,
            'adjustment_id'      => $row['adjustment_id'] ?? null,
            'reference_type'     => $row['reference_type'],
            'reference_id'       => $row['reference_id'] ?? null,
            'date'               => $row['date'],
            'remarks'            => $row['remarks'] ?? null,
            'deleted'            => 'No',
            'status'             => 'Active',
            'created_by'         => Auth::id(),
            'created_date'       => now(),
        ]);
    }

    /** Batch for a purchase line (creates it the first time). */
    public static function batchFor(int $productId, ?int $variantId, string $batchNo, ?string $expiry, ?string $mfg, float $cost, ?int $purchaseId): int
    {
        $existing = DB::table('product_batches')->where('product_id', $productId)
            ->when($variantId, fn ($q) => $q->where('product_variant_id', $variantId), fn ($q) => $q->whereNull('product_variant_id'))
            ->where('batch_no', $batchNo)->first();
        if ($existing) {
            if ($expiry && !$existing->expiry_date) {
                DB::table('product_batches')->where('id', $existing->id)->update(['expiry_date' => $expiry, 'updated_at' => now()]);
            }

            return (int) $existing->id;
        }

        return DB::table('product_batches')->insertGetId([
            'product_id' => $productId, 'product_variant_id' => $variantId, 'batch_no' => $batchNo,
            'expiry_date' => $expiry ?: null, 'manufacture_date' => $mfg ?: null, 'unit_cost' => $cost, 'purchase_id' => $purchaseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Products / variants with on-hand stock in a branch, for the pickers. */
    public function products(Request $request)
    {
        $branch = (int) BranchController::activeId();

        $stock = DB::table('stocks')->where('deleted', 'No')->where('branch_id', $branch)
            ->groupBy('product_id', 'product_variant_id')
            ->selectRaw('product_id, product_variant_id, SUM(in_qty - out_qty) AS qty')->get()
            ->keyBy(fn ($r) => $r->product_id . ':' . ($r->product_variant_id ?: 0));

        $rows = DB::table('products as p')->leftJoin('product_variants as v', fn ($j) => $j->on('v.product_id', '=', 'p.id')->where('v.deleted', 'No'))
            ->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
            ->where('p.deleted', 'No')->where('p.status', 'Active')
            ->when($request->filled('category_id'), fn ($q) => $q->where('p.category_id', (int) $request->query('category_id')))
            ->orderBy('p.name')->orderBy('a.name')
            ->get(['p.id', 'p.name', 'p.sku', 'p.purchase_price', 'p.track_batch', 'v.id as variant_id', 'a.name as variant', 'v.barcode'])
            ->map(function ($r) use ($stock) {
                $r->qty = round((float) ($stock[$r->id . ':' . ($r->variant_id ?: 0)]->qty ?? 0), 3);
                $r->label = $r->name . ($r->variant ? ' (' . $r->variant . ')' : '');

                return $r;
            });

        // Batch-tracked products: their batches with stock in the branch, earliest expiry first (the adjustment picker asks which one).
        $batches = DB::table('stocks as s')->join('product_batches as b', 'b.id', '=', 's.batch_id')
            ->where('s.deleted', 'No')->where('s.branch_id', $branch)
            ->groupBy('b.id', 'b.batch_no', 'b.expiry_date', 's.product_id', 's.product_variant_id')
            ->havingRaw('SUM(s.in_qty - s.out_qty) > 0')
            ->orderByRaw('b.expiry_date IS NULL')->orderBy('b.expiry_date')->orderBy('b.id')
            ->selectRaw('b.id, b.batch_no, b.expiry_date, s.product_id, s.product_variant_id, SUM(s.in_qty - s.out_qty) AS qty')->get()
            ->groupBy(fn ($b) => $b->product_id . ':' . ($b->product_variant_id ?: 0));
        $rows = $rows->map(function ($r) use ($batches) {
            $r->batches = $r->track_batch === 'Yes'
                ? ($batches[$r->id . ':' . ($r->variant_id ?: 0)] ?? collect())->map(fn ($b) => [
                    'id' => (int) $b->id, 'batch_no' => $b->batch_no, 'expiry_date' => $b->expiry_date, 'qty' => round((float) $b->qty, 3),
                ])->values()
                : [];

            return $r;
        });

        return response()->json(['status' => true, 'data' => $rows]);
    }

    /* ------------------------------------------------------------------ */
    /*  Transfers                                                          */
    /* ------------------------------------------------------------------ */

    public function transfers()
    {
        return view('admin.stock.transfers', [
            'branches' => DB::table('branches')->where('deleted', 'No')->where('status', 'Active')->where('id', '<>', BranchController::activeId())
                ->orderByDesc('is_main')->orderBy('name')->get(['id', 'name']),
            'active'   => BranchController::activeId(),
            'activeName' => BranchController::name(BranchController::activeId()),
        ]);
    }

    public function transferList(Request $request)
    {
        try {
            $visible = BranchController::visibleIds();
            $rows = DB::table('stock_transfers as t')
                ->join('branches as f', 'f.id', '=', 't.from_branch_id')->join('branches as d', 'd.id', '=', 't.to_branch_id')
                ->where('t.deleted', 'No')
                ->where(fn ($q) => $q->whereIn('t.from_branch_id', $visible)->orWhereIn('t.to_branch_id', $visible))
                ->orderByDesc('t.transfer_date')->orderByDesc('t.id')
                ->get(['t.*', 'f.name as from_name', 'd.name as to_name']);

            $badge = ['In Transit' => 'bg-warning text-dark', 'Received' => 'bg-success', 'Cancelled' => 'bg-secondary'];

            return response()->json(['data' => $rows->values()->map(function ($t, $i) use ($badge) {
                $id = (int) $t->id;
                $items = [['label' => 'View', 'icon' => 'fas fa-eye', 'onclick' => "Transfer.view($id)"]];
                if ($t->transfer_status === 'In Transit') {
                    if ((int) $t->to_branch_id === (int) BranchController::activeId()) {
                        $items[] = ['label' => 'Receive', 'icon' => 'fas fa-check', 'onclick' => "Transfer.receive($id)"];
                    } elseif (BranchController::multiBranch() && (int) $t->from_branch_id !== (int) BranchController::activeId()) {
                        $items[] = ['label' => 'Receive', 'icon' => 'fas fa-check', 'disabled' => true, 'title' => 'Sign in to ' . $t->to_name . ' to receive this'];
                    }
                    $items[] = ['divider' => true];
                    $items[] = (int) $t->from_branch_id === (int) BranchController::activeId()
                        ? ['label' => 'Cancel', 'icon' => 'fas fa-ban', 'class' => 'text-danger', 'onclick' => "Transfer.cancel($id)"]
                        : ['label' => 'Cancel', 'icon' => 'fas fa-ban', 'disabled' => true, 'title' => 'Sign in to ' . $t->from_name . ' to change this'];
                }

                return [
                    $i + 1,
                    '<span class="fw-semibold">' . e($t->transfer_no) . '</span>',
                    e(Carbon::parse($t->transfer_date)->format('d M Y')),
                    e($t->from_name) . ' <i class="bi bi-arrow-right text-muted"></i> ' . e($t->to_name),
                    '<span class="d-block text-end">' . rtrim(rtrim(number_format((float) $t->total_qty, 3), '0'), '.') . '</span>',
                    '<span class="d-block text-end">' . number_format((float) $t->total_value, 2) . '</span>',
                    '<span class="badge ' . ($badge[$t->transfer_status] ?? 'bg-secondary') . '">' . e($t->transfer_status) . '</span>',
                    action_dropdown($items),
                ];
            })]);
        } catch (Throwable $e) {
            Log::error('Stock transfer list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load transfers.'], 500);
        }
    }

    public function transferShow(int $id)
    {
        $t = DB::table('stock_transfers as t')->join('branches as f', 'f.id', '=', 't.from_branch_id')->join('branches as d', 'd.id', '=', 't.to_branch_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')->where('t.id', $id)
            ->first(['t.*', 'f.name as from_name', 'd.name as to_name', 'u.name as created_by_name']);
        // Only a user who sees the sending or receiving branch may open a transfer.
        abort_unless($t && (BranchController::sees($t->from_branch_id) || BranchController::sees($t->to_branch_id)), 404);

        $items = DB::table('stock_transfer_items as i')->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'i.product_variant_id')->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
            ->leftJoin('product_batches as b', 'b.id', '=', 'i.batch_id')->where('i.stock_transfer_id', $id)
            ->get(['i.*', 'p.name', 'a.name as variant', 'b.batch_no', 'b.expiry_date']);

        return response()->json(['status' => true, 'data' => $t, 'items' => $items]);
    }

    public function transferStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_branch_id'             => ['nullable'],
            'to_branch_id'               => ['required', 'integer', Rule::notIn([BranchController::activeId()]), Rule::exists('branches', 'id')->where('deleted', 'No')->where('status', 'Active')],
            'transfer_date'              => ['required', 'date'],
            'remarks'                    => ['nullable', 'string', 'max:500'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.qty'                => ['required', 'numeric', 'gt:0'],
        ], [
            'to_branch_id.not_in'    => 'Send to a different branch.',
            'items.required'         => 'Add at least one product.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $transfer = DB::transaction(function () use ($request) {
                $from = (int) BranchController::activeId();   // always the branch the user is signed in to
                $date = $request->input('transfer_date');

                $last = DB::table('stock_transfers')->lockForUpdate()->max('transfer_no');
                $next = $last ? ((int) substr($last, 3)) + 1 : 1;
                $id = DB::table('stock_transfers')->insertGetId([
                    'transfer_no'    => 'ST-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                    'from_branch_id' => $from,
                    'to_branch_id'   => (int) $request->input('to_branch_id'),
                    'transfer_date'  => $date,
                    'remarks'        => $this->nullIfBlank($request->input('remarks')),
                    'created_by'     => Auth::id(),
                    'created_date'   => now(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $totalQty = $totalValue = 0;
                foreach ($request->input('items') as $line) {
                    $pid = (int) $line['product_id'];
                    $vid = !empty($line['product_variant_id']) ? (int) $line['product_variant_id'] : null;
                    $qty = round((float) $line['qty'], 3);
                    $name = DB::table('products')->where('id', $pid)->value('name');
                    $have = self::onHand($pid, $vid, $from);
                    abort_if($qty > $have + 0.0005, 422, $name . ': only ' . rtrim(rtrim(number_format($have, 3), '0'), '.') . ' in stock at the sending branch.');

                    $cost = (float) DB::table('products')->where('id', $pid)->value('purchase_price');
                    foreach (self::allocate($pid, $vid, $from, $qty) as [$batchId, $part]) {
                        DB::table('stock_transfer_items')->insert([
                            'stock_transfer_id' => $id, 'product_id' => $pid, 'product_variant_id' => $vid, 'batch_id' => $batchId,
                            'qty' => $part, 'unit_cost' => $cost, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        self::move(['branch_id' => $from, 'product_id' => $pid, 'product_variant_id' => $vid, 'batch_id' => $batchId,
                            'qty' => -$part, 'reference_type' => 'Transfer Out', 'reference_id' => $id, 'date' => $date]);
                    }
                    $totalQty += $qty;
                    $totalValue += $qty * $cost;
                }

                DB::table('stock_transfers')->where('id', $id)->update(['total_qty' => $totalQty, 'total_value' => round($totalValue, 2)]);

                return DB::table('stock_transfers')->where('id', $id)->first();
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => $transfer->transfer_no . ' sent. The receiving branch confirms it on arrival.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Stock transfer error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the transfer.'], 500);
        }
    }

    public function transferReceive(Request $request, int $id)
    {
        try {
            $t = DB::transaction(function () use ($request, $id) {
                $t = DB::table('stock_transfers')->where('id', $id)->lockForUpdate()->first();
                abort_unless($t && (BranchController::sees($t->from_branch_id) || BranchController::sees($t->to_branch_id)), 404, 'Transfer not found.');
                abort_unless($t->transfer_status === 'In Transit', 422, 'Only a transfer in transit can be received.');
                abort_unless((int) $t->to_branch_id === (int) BranchController::activeId(), 403, 'Only the receiving branch can confirm this transfer. Sign in to '
                    . BranchController::name((int) $t->to_branch_id) . ' to receive it.');
                $date = $request->input('received_date') ?: now()->toDateString();

                foreach (DB::table('stock_transfer_items')->where('stock_transfer_id', $id)->get() as $i) {
                    self::move(['branch_id' => $t->to_branch_id, 'product_id' => $i->product_id, 'product_variant_id' => $i->product_variant_id,
                        'batch_id' => $i->batch_id, 'qty' => (float) $i->qty, 'reference_type' => 'Transfer In', 'reference_id' => $id, 'date' => $date]);
                    DB::table('stock_transfer_items')->where('id', $i->id)->update(['received_qty' => $i->qty]);
                }
                DB::table('stock_transfers')->where('id', $id)->update([
                    'transfer_status' => 'Received', 'received_date' => $date, 'received_by' => Auth::id(), 'updated_at' => now(),
                ]);

                return $t;
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => $t->transfer_no . ' received into stock.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Stock transfer receive error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to receive the transfer.'], 500);
        }
    }

    /** Cancelling a transfer in transit puts the stock back at the sending branch. */
    public function transferCancel(int $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $t = DB::table('stock_transfers')->where('id', $id)->lockForUpdate()->first();
                abort_unless($t && (BranchController::sees($t->from_branch_id) || BranchController::sees($t->to_branch_id)), 404, 'Transfer not found.');
                abort_unless($t->transfer_status === 'In Transit', 422, 'Only a transfer in transit can be cancelled.');
                abort_unless((int) $t->from_branch_id === (int) BranchController::activeId(), 403,
                    'Only the sending branch can cancel this transfer. Sign in to ' . BranchController::name((int) $t->from_branch_id) . ' to cancel it.');
                DB::table('stocks')->where('reference_type', 'Transfer Out')->where('reference_id', $id)
                    ->update(['deleted' => 'Yes', 'deleted_by' => Auth::id(), 'deleted_date' => now()]);
                DB::table('stock_transfers')->where('id', $id)->update(['transfer_status' => 'Cancelled', 'updated_by' => Auth::id(), 'updated_at' => now()]);
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => 'Transfer cancelled; the stock is back at the sending branch.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Stock transfer cancel error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to cancel.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Stock count / adjustment                                           */
    /* ------------------------------------------------------------------ */

    public function adjustments()
    {
        return view('admin.stock.adjustments', [
            'types'      => self::ADJUSTMENT_TYPES,
            'categories' => DB::table('categories')->where('deleted', 'No')->orderBy('name')->get(['id', 'name']),
            'branchName' => BranchController::name(BranchController::activeId()),
        ]);
    }

    public function adjustmentList()
    {
        try {
            $rows = BranchController::scope(DB::table('stock_adjustments as a'), 'a.branch_id')
                ->join('branches as b', 'b.id', '=', 'a.branch_id')->where('a.deleted', 'No')
                ->orderByDesc('a.adjustment_date')->orderByDesc('a.id')->get(['a.*', 'b.name as branch']);
            $badge = ['Draft' => 'bg-secondary', 'Posted' => 'bg-success', 'Cancelled' => 'bg-light text-muted'];

            return response()->json(['data' => $rows->values()->map(function ($a, $i) use ($badge) {
                $id = (int) $a->id;
                $items = [['label' => 'View', 'icon' => 'fas fa-eye', 'onclick' => "Adjust.view($id)"]];
                if ($a->adjustment_status === 'Draft') {
                    $items[] = ['label' => 'Continue counting', 'icon' => 'fas fa-edit', 'onclick' => "Adjust.edit($id)"];
                }
                if ($a->adjustment_status !== 'Cancelled') {
                    $items[] = ['divider' => true];
                    $items[] = ['label' => $a->adjustment_status === 'Posted' ? 'Cancel (reverse)' : 'Discard', 'icon' => 'fas fa-ban', 'class' => 'text-danger', 'onclick' => "Adjust.cancel($id)"];
                }

                return [
                    $i + 1,
                    '<span class="fw-semibold">' . e($a->adjustment_no) . '</span><div class="text-muted small">' . e($a->branch) . '</div>',
                    e(Carbon::parse($a->adjustment_date)->format('d M Y')),
                    e($a->adjustment_type),
                    '<span class="d-block text-end text-success">' . ((float) $a->total_in ? '+' . rtrim(rtrim(number_format((float) $a->total_in, 3), '0'), '.') : '-') . '</span>',
                    '<span class="d-block text-end text-danger">' . ((float) $a->total_out ? '−' . rtrim(rtrim(number_format((float) $a->total_out, 3), '0'), '.') : '-') . '</span>',
                    '<span class="d-block text-end">' . number_format((float) $a->value_change, 2) . '</span>',
                    '<span class="badge ' . ($badge[$a->adjustment_status] ?? 'bg-secondary') . '">' . e($a->adjustment_status) . '</span>',
                    action_dropdown($items, $a->branch_id),
                ];
            })]);
        } catch (Throwable $e) {
            Log::error('Stock adjustment list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load adjustments.'], 500);
        }
    }

    public function adjustmentShow(int $id)
    {
        $a = BranchController::scope(DB::table('stock_adjustments as a'), 'a.branch_id')
            ->join('branches as b', 'b.id', '=', 'a.branch_id')->where('a.id', $id)->first(['a.*', 'b.name as branch']);
        abort_unless($a, 404);
        $items = DB::table('stock_adjustment_items as i')->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'i.product_variant_id')->leftJoin('attributes as at', 'at.id', '=', 'v.attribute_id')
            ->leftJoin('product_batches as bt', 'bt.id', '=', 'i.batch_id')->where('i.stock_adjustment_id', $id)->orderBy('p.name')
            ->get(['i.*', 'p.name', 'at.name as variant', 'bt.batch_no']);

        return response()->json(['status' => true, 'data' => $a, 'items' => $items]);
    }

    /**
     * Save a count / adjustment. Stock Count lines carry counted_qty (difference = counted − system);
     * other types carry qty (Found adds, the rest take away). post=1 writes the stock movements.
     */
    public function adjustmentStore(Request $request)
    {
        $type = $request->input('adjustment_type');
        $validator = Validator::make($request->all(), [
            'id'                         => ['nullable', 'integer'],
            'adjustment_date'            => ['required', 'date'],
            'adjustment_type'            => ['required', Rule::in(self::ADJUSTMENT_TYPES)],
            'remarks'                    => ['nullable', 'string', 'max:500'],
            'post'                       => ['nullable', 'boolean'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.batch_id'           => ['nullable', 'integer'],
            'items.*.counted_qty'        => [$type === 'Stock Count' ? 'nullable' : 'prohibited', 'numeric', 'min:0'],
            'items.*.qty'                => [$type === 'Stock Count' ? 'prohibited' : 'required', 'numeric', 'gt:0'],
            'items.*.note'               => ['nullable', 'string', 'max:255'],
        ], ['items.required' => 'Add at least one product.']);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $adj = DB::transaction(function () use ($request, $type) {
                $branch = BranchController::activeId();
                $date = $request->input('adjustment_date');
                $id = $request->input('id') ? (int) $request->input('id') : null;

                if ($id) {
                    $old = DB::table('stock_adjustments')->where('id', $id)->lockForUpdate()->first();
                    if ($old) {
                        BranchController::guardOwn($old);
                    }
                    abort_unless($old && (int) $old->branch_id === (int) $branch, 404, 'Adjustment not found.');
                    abort_unless($old->adjustment_status === 'Draft', 422, 'Only a draft can be changed.');
                    $branch = (int) $old->branch_id;
                    DB::table('stock_adjustment_items')->where('stock_adjustment_id', $id)->delete();
                    DB::table('stock_adjustments')->where('id', $id)->update([
                        'adjustment_date' => $date, 'adjustment_type' => $type, 'remarks' => $this->nullIfBlank($request->input('remarks')),
                        'updated_by' => Auth::id(), 'updated_date' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    $last = DB::table('stock_adjustments')->lockForUpdate()->max('adjustment_no');
                    $next = $last ? ((int) substr($last, 4)) + 1 : 1;
                    $id = DB::table('stock_adjustments')->insertGetId([
                        'adjustment_no' => 'ADJ-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT), 'branch_id' => $branch,
                        'adjustment_date' => $date, 'adjustment_type' => $type, 'remarks' => $this->nullIfBlank($request->input('remarks')),
                        'created_by' => Auth::id(), 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                foreach ($request->input('items') as $line) {
                    $pid = (int) $line['product_id'];
                    $vid = !empty($line['product_variant_id']) ? (int) $line['product_variant_id'] : null;
                    $product = DB::table('products')->where('id', $pid)->first(['name', 'track_batch']);
                    // Batch-tracked products: an adjustment says which batch it touches (a stock count stays per product).
                    $batchId = null;
                    if ($type !== 'Stock Count' && ($product->track_batch ?? 'No') === 'Yes') {
                        $batchId = !empty($line['batch_id']) ? (int) $line['batch_id'] : null;
                        abort_if(!$batchId && $type !== 'Found', 422, 'Choose the batch for "' . $product->name . '".');
                        if ($batchId) {
                            $ok = DB::table('product_batches')->where('id', $batchId)->where('product_id', $pid)
                                ->when($vid, fn ($q) => $q->where('product_variant_id', $vid), fn ($q) => $q->whereNull('product_variant_id'))->exists();
                            abort_unless($ok, 422, 'The batch chosen for "' . $product->name . '" belongs to another product.');
                        }
                    }
                    $system = self::onHand($pid, $vid, $branch, $batchId);
                    if ($type === 'Stock Count') {
                        $counted = isset($line['counted_qty']) && $line['counted_qty'] !== '' ? round((float) $line['counted_qty'], 3) : null;
                        $diff = $counted === null ? 0 : round($counted - $system, 3);
                    } else {
                        $counted = null;
                        $diff = round((float) $line['qty'], 3) * ($type === 'Found' ? 1 : -1);
                    }
                    DB::table('stock_adjustment_items')->insert([
                        'stock_adjustment_id' => $id, 'product_id' => $pid, 'product_variant_id' => $vid, 'batch_id' => $batchId,
                        'system_qty' => $system, 'counted_qty' => $counted, 'difference' => $diff,
                        'unit_cost' => (float) DB::table('products')->where('id', $pid)->value('purchase_price'),
                        'note' => $this->nullIfBlank($line['note'] ?? null), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                if ($request->boolean('post')) {
                    self::postAdjustment($id);
                }

                return DB::table('stock_adjustments')->where('id', $id)->first();
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => $adj->adjustment_no . ($adj->adjustment_status === 'Posted' ? ' posted to stock.' : ' saved as draft.')]);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Stock adjustment error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the adjustment.'], 500);
        }
    }

    private static function postAdjustment(int $id): void
    {
        $a = DB::table('stock_adjustments')->where('id', $id)->first();
        $in = $out = $value = 0;

        foreach (DB::table('stock_adjustment_items')->where('stock_adjustment_id', $id)->get() as $i) {
            $diff = (float) $i->difference;
            if (abs($diff) < 0.0005) {
                continue;
            }
            if ($diff < 0) {
                // Damage / lost / expired / correction cannot take out more than the branch holds.
                // With a chosen batch, the limit is that batch's stock.
                $have = self::onHand((int) $i->product_id, $i->product_variant_id ? (int) $i->product_variant_id : null, (int) $a->branch_id, $i->batch_id ? (int) $i->batch_id : null);
                abort_if(-$diff > $have + 0.0005, 422, DB::table('products')->where('id', $i->product_id)->value('name')
                    . ($i->batch_id ? ' (batch ' . DB::table('product_batches')->where('id', $i->batch_id)->value('batch_no') . ')' : '')
                    . ': only ' . rtrim(rtrim(number_format(max(0, $have), 3), '0'), '.') . ' in stock, cannot take out ' . rtrim(rtrim(number_format(-$diff, 3), '0'), '.') . '.');
            }
            if ($i->batch_id) {
                $parts = [[(int) $i->batch_id, abs($diff)]];
            } else {
                $parts = $diff < 0 ? self::allocate((int) $i->product_id, $i->product_variant_id ? (int) $i->product_variant_id : null, (int) $a->branch_id, -$diff) : [[null, $diff]];
            }
            foreach ($parts as [$batchId, $qty]) {
                self::move(['branch_id' => $a->branch_id, 'product_id' => $i->product_id, 'product_variant_id' => $i->product_variant_id,
                    'batch_id' => $batchId, 'qty' => $diff < 0 ? -$qty : $qty, 'adjustment_id' => $id, 'reference_type' => 'Adjustment',
                    'reference_id' => $id, 'date' => $a->adjustment_date, 'remarks' => $a->adjustment_type . ($i->note ? ' · ' . $i->note : '')]);
            }
            $diff > 0 ? $in += $diff : $out += -$diff;
            $value += $diff * (float) $i->unit_cost;
        }

        DB::table('stock_adjustments')->where('id', $id)->update([
            'adjustment_status' => 'Posted', 'total_in' => $in, 'total_out' => $out, 'value_change' => round($value, 2),
            'posted_by' => Auth::id(), 'posted_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function adjustmentCancel(int $id)
    {
        try {
            DB::transaction(function () use ($id) {
                $a = DB::table('stock_adjustments')->where('id', $id)->lockForUpdate()->first();
                if ($a) {
                    BranchController::guardOwn($a);
                }
                abort_unless($a && (int) $a->branch_id === (int) BranchController::activeId(), 404, 'Adjustment not found.');
                abort_unless($a->adjustment_status !== 'Cancelled', 422, 'Already cancelled.');
                // Reversing stock that came IN (Found / count surplus) needs that stock still to be here.
                $added = DB::table('stocks')->where('reference_type', 'Adjustment')->where('adjustment_id', $id)->where('deleted', 'No')
                    ->where('in_qty', '>', 0)->get(['product_id', 'product_variant_id', 'in_qty']);
                DB::table('stocks')->where('reference_type', 'Adjustment')->where('adjustment_id', $id)
                    ->update(['deleted' => 'Yes', 'deleted_by' => Auth::id(), 'deleted_date' => now()]);
                foreach ($added as $s) {
                    $left = self::onHand((int) $s->product_id, $s->product_variant_id ? (int) $s->product_variant_id : null, (int) $a->branch_id);
                    abort_if($left < -0.0005, 422, DB::table('products')->where('id', $s->product_id)->value('name')
                        . ': the stock this adjustment added has already been used, so it cannot be reversed.');
                }
                DB::table('stock_adjustments')->where('id', $id)->update(['adjustment_status' => 'Cancelled', 'updated_by' => Auth::id(), 'updated_at' => now()]);
            }, self::SAVE_ATTEMPTS);

            return response()->json(['status' => true, 'message' => 'Adjustment cancelled.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Stock adjustment cancel error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to cancel.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Batches & expiry                                                   */
    /* ------------------------------------------------------------------ */

    public function batches()
    {
        return view('admin.stock.batches');
    }

    /** Correct a batch's expiry (and manufacture) date later - only a batch this branch holds or held. */
    public function batchExpiry(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'expiry_date'      => ['required', 'date'],
            'manufacture_date' => ['nullable', 'date', 'before_or_equal:expiry_date'],
        ], [
            'expiry_date.required' => 'Enter the expiry date.',
            'manufacture_date.before_or_equal' => 'The manufacture date cannot be after the expiry date.',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $batch = DB::table('product_batches')->where('id', $id)->first();
        $inBranch = $batch && BranchController::scopeOwn(DB::table('stocks'), 'branch_id')->where('batch_id', $id)->where('deleted', 'No')->exists();
        if (!$inBranch) {
            return response()->json(['status' => false, 'message' => 'Batch not found in this branch.'], 404);
        }

        $update = ['expiry_date' => $request->input('expiry_date'), 'updated_at' => now()];
        if ($request->has('manufacture_date')) {
            $update['manufacture_date'] = $request->input('manufacture_date') ?: null;
        }
        DB::table('product_batches')->where('id', $id)->update($update);

        return response()->json(['status' => true, 'message' => 'Batch ' . $batch->batch_no . ' now expires on ' . Carbon::parse($request->input('expiry_date'))->format('d M Y') . '.']);
    }

    public function batchList(Request $request)
    {
        $days = (int) ($request->query('days') ?: 30);
        $state = $request->query('state');   // expired | soon | all

        $rows = BranchController::scope(DB::table('stocks as s'), 's.branch_id')
            ->join('product_batches as b', 'b.id', '=', 's.batch_id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->join('branches as br', 'br.id', '=', 's.branch_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'b.product_variant_id')->leftJoin('attributes as a', 'a.id', '=', 'v.attribute_id')
            ->where('s.deleted', 'No')
            ->when($state === 'expired', fn ($q) => $q->where('b.expiry_date', '<', now()->toDateString()))
            ->when($state === 'soon', fn ($q) => $q->whereBetween('b.expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]))
            ->groupBy('b.id', 'b.batch_no', 'b.expiry_date', 'b.manufacture_date', 'b.unit_cost', 'p.name', 'a.name', 'br.name', 's.branch_id')
            ->havingRaw('SUM(s.in_qty - s.out_qty) > 0')
            ->orderByRaw('b.expiry_date IS NULL')->orderBy('b.expiry_date')
            ->selectRaw('b.id, b.batch_no, b.expiry_date, b.manufacture_date, b.unit_cost, p.name AS product, a.name AS variant, br.name AS branch, SUM(s.in_qty - s.out_qty) AS qty')
            ->get()
            ->map(function ($r) {
                $r->days_left = $r->expiry_date ? (int) now()->startOfDay()->diffInDays(Carbon::parse($r->expiry_date), false) : null;
                $r->value = round((float) $r->qty * (float) $r->unit_cost, 2);

                return $r;
            });

        return response()->json(['status' => true, 'data' => $rows, 'summary' => [
            'expired' => $rows->filter(fn ($r) => $r->days_left !== null && $r->days_left < 0)->count(),
            'soon'    => $rows->filter(fn ($r) => $r->days_left !== null && $r->days_left >= 0 && $r->days_left <= $days)->count(),
            'value'   => round($rows->filter(fn ($r) => $r->days_left !== null && $r->days_left < 0)->sum('value'), 2),
        ]]);
    }
}
