<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\InternalAmountTransfer;
use App\Traits\AccountBalanceTrait;
use App\Traits\SequentialCodeTrait;
use App\Traits\VoucherHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Moves money between two Liquid Asset accounts (e.g. Shop Cash -> NCC Bank).
 * Posting: Dr "to" account, Cr "from" account.
 */
class AmountTransferController extends Controller
{
    use AccountBalanceTrait, SequentialCodeTrait, VoucherHelperTrait;

    private const PAYMENT_TYPE = 'Liquid Asset';

    /* ------------------------------------------------------------------ */
    /*  Page                                                              */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        $groups = $this->coaGroups(self::PAYMENT_TYPE);

        return view('admin.accounts.transfer.index', [
            'paymentGroups' => $groups,
            // For the account filter (grouped by 2nd layer).
            'ledgers' => DB::table('chart_of_accounts as c')
                ->join('chart_of_accounts as g', 'g.id', '=', 'c.parent_id')
                ->where('c.layer_level', 3)
                ->where('c.account_type', self::PAYMENT_TYPE)
                ->where('c.deleted', 'No')
                ->orderBy('g.account_code')->orderBy('c.account_code')
                ->get(['c.id', 'c.account_code', 'c.account_name', 'g.account_name as group_name']),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable                                                         */
    /* ------------------------------------------------------------------ */

    public function list(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
            'account_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['data' => [], 'message' => $validator->errors()->first()], 422);
        }

        try {
            $query = $this->baseQuery();

            if ($request->filled('from')) {
                $query->whereDate('t.transfer_date', '>=', $request->query('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('t.transfer_date', '<=', $request->query('to'));
            }

            if ($request->filled('account_id')) {
                $accountId = (int) $request->query('account_id');
                $query->where(fn ($q) => $q->where('t.from_account_id', $accountId)->orWhere('t.to_account_id', $accountId));
            }

            $transfers = $query->orderByDesc('t.transfer_date')->orderByDesc('t.id')->get();

            $accountCell = fn ($code, $name, $group) => $code
                ? '<div><span class="coa-code">' . e($code) . '</span> ' . e($name) . '</div><div class="text-muted small">' . e($group) . '</div>'
                : '<span class="text-muted">-</span>';

            $output = ['data' => []];
            $i = 1;

            foreach ($transfers as $t) {
                $date = Carbon::parse($t->transfer_date);

                $output['data'][] = [
                    $i++,
                    '<span class="fw-semibold">' . e($t->transfer_no) . '</span>'
                        . ($t->reference_no ? '<div class="text-muted small">Ref: ' . e($t->reference_no) . '</div>' : ''),
                    '<span class="d-none">' . $date->format('Y-m-d') . '</span>' . e($date->format('d M Y')),
                    $accountCell($t->from_code, $t->from_name, $t->from_group_name),
                    $accountCell($t->to_code, $t->to_name, $t->to_group_name),
                    '<span class="d-none">' . sprintf('%021.2f', (float) $t->amount) . '</span>'
                        . '<span class="d-block text-end fw-semibold">' . number_format((float) $t->amount, 2) . '</span>',
                    $this->actionButtons('Transfer', (int) $t->id),
                ];
            }

            $output['summary'] = [
                'filtered_total' => number_format((float) $transfers->sum('amount'), 2),
                'filtered_count' => $transfers->count(),
                'month_total'    => number_format((float) DB::table('internal_amount_transfers')->where('deleted', 'No')
                    ->whereBetween('transfer_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('amount'), 2),
                'today_total'    => number_format((float) DB::table('internal_amount_transfers')->where('deleted', 'No')
                    ->whereDate('transfer_date', now()->toDateString())->sum('amount'), 2),
            ];

            return response()->json($output);
        } catch (Throwable $e) {
            Log::error('Transfer list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load transfers.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  One transfer (view / edit modal)                                  */
    /* ------------------------------------------------------------------ */

    public function show(Request $request)
    {
        try {
            $t = $this->baseQuery()->where('t.id', (int) $request->query('id'))->first();

            if (!$t) {
                return response()->json(['status' => false, 'message' => 'Transfer not found.'], 404);
            }

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'                 => $t->id,
                    'transfer_no'        => $t->transfer_no,
                    'transfer_date'      => Carbon::parse($t->transfer_date)->format('Y-m-d'),
                    'transfer_date_label'=> Carbon::parse($t->transfer_date)->format('d M Y'),
                    'from_group_id'      => $t->from_group_id,
                    'from_group_label'   => $t->from_group_code . ' - ' . $t->from_group_name,
                    'from_account_id'    => $t->from_account_id,
                    'from_account_label' => $t->from_code . ' - ' . $t->from_name,
                    'to_group_id'        => $t->to_group_id,
                    'to_group_label'     => $t->to_group_code . ' - ' . $t->to_group_name,
                    'to_account_id'      => $t->to_account_id,
                    'to_account_label'   => $t->to_code . ' - ' . $t->to_name,
                    'amount'             => number_format((float) $t->amount, 2, '.', ''),
                    'reference_no'       => $t->reference_no,
                    'remarks'            => $t->remarks,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Transfer show error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to load transfer.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Create / update (update when "id" is posted)                      */
    /* ------------------------------------------------------------------ */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'              => ['nullable', 'integer'],
            'transfer_date'   => ['required', 'date'],
            'from_group_id'   => ['required', 'integer', $this->coaExists(2, self::PAYMENT_TYPE)],
            'from_account_id' => ['required', 'integer', $this->coaExists(3, self::PAYMENT_TYPE, $request->input('from_group_id'))],
            'to_group_id'     => ['required', 'integer', $this->coaExists(2, self::PAYMENT_TYPE)],
            'to_account_id'   => ['required', 'integer', 'different:from_account_id', $this->coaExists(3, self::PAYMENT_TYPE, $request->input('to_group_id'))],
            'amount'          => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'reference_no'    => ['nullable', 'string', 'max:100'],
            'remarks'         => ['nullable', 'string', 'max:1000'],
        ], [
            'from_group_id.required'    => 'Select the group to transfer from.',
            'from_account_id.required'  => 'Select the account to transfer from.',
            'from_account_id.exists'    => 'Select a valid account of the selected group.',
            'to_group_id.required'      => 'Select the group to transfer to.',
            'to_account_id.required'    => 'Select the account to transfer to.',
            'to_account_id.exists'      => 'Select a valid account of the selected group.',
            'to_account_id.different'   => 'From and To accounts must be different.',
            'amount.min'                => 'Amount must be greater than zero.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->filled('id')) {
                $transfer = InternalAmountTransfer::where('id', (int) $request->input('id'))
                    ->where('deleted', 'No')->lockForUpdate()->first();

                if (!$transfer) {
                    abort(404, 'Transfer not found.');
                }

                $this->post($transfer, -1);   // undo the old movement

                $transfer->updated_by   = Auth::id();
                $transfer->updated_date = now();
            } else {
                $transfer = new InternalAmountTransfer();
                $transfer->transfer_no  = $this->nextSequentialCode(InternalAmountTransfer::class, 'transfer_no', 'TRF');
                $transfer->created_by   = Auth::id();
                $transfer->created_date = now();
            }

            $transfer->transfer_date   = $request->input('transfer_date');
            $transfer->from_group_id   = (int) $request->input('from_group_id');
            $transfer->from_account_id = (int) $request->input('from_account_id');
            $transfer->to_group_id     = (int) $request->input('to_group_id');
            $transfer->to_account_id   = (int) $request->input('to_account_id');
            $transfer->amount          = round((float) $request->input('amount'), 2);
            $transfer->reference_no    = $this->nullIfBlank($request->input('reference_no'));
            $transfer->remarks         = $this->nullIfBlank($request->input('remarks'));
            $transfer->save();

            $this->post($transfer, 1);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Transfer ' . $transfer->transfer_no . ($request->filled('id') ? ' updated' : ' saved') . ' successfully.',
            ]);
        } catch (HttpExceptionInterface $e) {
            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Transfer store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the transfer. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Delete (soft) and reverse the balances                            */
    /* ------------------------------------------------------------------ */

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => ['required', 'integer', 'min:1']]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid transfer id is required.'], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $transfer = InternalAmountTransfer::where('id', (int) $request->input('id'))
                    ->where('deleted', 'No')->lockForUpdate()->first();

                if (!$transfer) {
                    abort(404, 'Transfer not found.');
                }

                $this->post($transfer, -1);

                DB::table('internal_amount_transfers')->where('id', $transfer->id)->update($this->deletedColumns());
            }, 3);

            return response()->json(['status' => true, 'message' => 'Transfer deleted and balances reversed.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Transfer delete error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to delete the transfer. Please try again.'], 500);
        }
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    /** Dr "to", Cr "from" (direction -1 reverses it). */
    private function post(InternalAmountTransfer $t, int $direction): void
    {
        $amount = (float) $t->amount;

        $this->postBalances([(int) $t->to_account_id => $amount], [(int) $t->from_account_id => $amount], $direction);
    }

    private function baseQuery()
    {
        return DB::table('internal_amount_transfers as t')
            ->leftJoin('chart_of_accounts as fg', 'fg.id', '=', 't.from_group_id')
            ->leftJoin('chart_of_accounts as fa', 'fa.id', '=', 't.from_account_id')
            ->leftJoin('chart_of_accounts as tg', 'tg.id', '=', 't.to_group_id')
            ->leftJoin('chart_of_accounts as ta', 'ta.id', '=', 't.to_account_id')
            ->where('t.deleted', 'No')
            ->select(
                't.*',
                'fg.account_code as from_group_code', 'fg.account_name as from_group_name',
                'fa.account_code as from_code', 'fa.account_name as from_name',
                'tg.account_code as to_group_code', 'tg.account_name as to_group_name',
                'ta.account_code as to_code', 'ta.account_name as to_name'
            );
    }
}
