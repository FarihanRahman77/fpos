<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Bill;
use App\Models\Admin\BillDetail;
use App\Models\Admin\BillPayment;
use App\Models\Admin\Payment;
use App\Traits\AccountBalanceTrait;
use App\Traits\PaymentTrait;
use App\Traits\SequentialCodeTrait;
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
 * Supplier bills (services / expenses bought on credit) and their payments.
 *
 * Posting:
 *   Bill         Dr expense accounts      Cr Accounts Payable
 *   Bill payment Dr Accounts Payable      Cr cash / bank account
 *
 * Supplier ledger (payments table, same as purchases):
 *   Bill         debit  = grand total     (supplier due goes up)
 *   Bill payment credit = amount paid     (supplier due goes down)
 */
class BillController extends Controller
{
    use AccountBalanceTrait, PaymentTrait, SequentialCodeTrait, VoucherHelperTrait;

    private const EXPENSE_TYPE = 'Expense';
    private const PAYMENT_TYPE = 'Liquid Asset';
    private const MAX_LINES    = 100;

    /* ------------------------------------------------------------------ */
    /*  Page                                                              */
    /* ------------------------------------------------------------------ */

    public function index()
    {
        return view('admin.accounts.bill.index', [
            'suppliers'     => $this->suppliers(),
            'expenseGroups' => $this->coaGroups(self::EXPENSE_TYPE),
            'paymentGroups' => $this->coaGroups(self::PAYMENT_TYPE),
            'hasPayable'    => (bool) $this->payableAccountId(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  DataTable                                                         */
    /* ------------------------------------------------------------------ */

    public function list(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from'           => ['nullable', 'date'],
            'to'             => ['nullable', 'date', 'after_or_equal:from'],
            'party_id'       => ['nullable', 'integer'],
            'payment_status' => ['nullable', Rule::in(['Unpaid', 'Partial', 'Paid'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['data' => [], 'message' => $validator->errors()->first()], 422);
        }

        try {
            $query = DB::table('bills as b')
                ->leftJoin('parties as p', 'p.id', '=', 'b.party_id')
                ->where('b.deleted', 'No')
                ->select('b.*', 'p.name as party_name', 'p.code as party_code');

            if ($request->filled('from')) {
                $query->whereDate('b.bill_date', '>=', $request->query('from'));
            }

            if ($request->filled('to')) {
                $query->whereDate('b.bill_date', '<=', $request->query('to'));
            }

            if ($request->filled('party_id')) {
                $query->where('b.party_id', (int) $request->query('party_id'));
            }

            if ($request->filled('payment_status')) {
                $query->where('b.payment_status', $request->query('payment_status'));
            }

            $bills = $query->orderByDesc('b.bill_date')->orderByDesc('b.id')->get();

            $badge = [
                'Paid'    => 'bg-success',
                'Partial' => 'bg-warning text-dark',
                'Unpaid'  => 'bg-danger',
            ];

            $money = fn ($v) => '<span class="d-none">' . sprintf('%021.2f', (float) $v) . '</span>'
                . '<span class="d-block text-end">' . number_format((float) $v, 2) . '</span>';

            $output = ['data' => []];
            $i = 1;

            foreach ($bills as $bill) {
                $id   = (int) $bill->id;
                $date = Carbon::parse($bill->bill_date);
                $due  = $bill->due_date ? Carbon::parse($bill->due_date) : null;

                $overdue = $due && (float) $bill->due_amount > 0 && $due->lt(now()->startOfDay());

                $payButton = [['label' => 'Payments', 'icon' => 'fas fa-money-bill-wave', 'class' => 'text-success', 'onclick' => 'openBillPayments(' . $id . ')']];

                $output['data'][] = [
                    $i++,
                    '<span class="fw-semibold">' . e($bill->bill_no) . '</span>'
                        . ($bill->reference_no ? '<div class="text-muted small">Ref: ' . e($bill->reference_no) . '</div>' : ''),
                    '<span class="d-none">' . $date->format('Y-m-d') . '</span>' . e($date->format('d M Y')),
                    e($bill->party_name ?: '-') . ($bill->party_code ? ' <span class="text-muted small">(' . e($bill->party_code) . ')</span>' : ''),
                    $due
                        ? '<span class="d-none">' . $due->format('Y-m-d') . '</span><span class="' . ($overdue ? 'text-danger fw-semibold' : '') . '">' . e($due->format('d M Y')) . '</span>'
                        : '<span class="text-muted">-</span>',
                    $money($bill->grand_total),
                    $money($bill->paid_amount),
                    $money($bill->due_amount),
                    '<span class="badge ' . ($badge[$bill->payment_status] ?? 'bg-secondary') . '">' . e($bill->payment_status) . '</span>',
                    $this->actionButtons('Bill', $id, $payButton),
                ];
            }

            $active = DB::table('bills')->where('deleted', 'No');

            $output['summary'] = [
                'filtered_total' => number_format((float) $bills->sum('grand_total'), 2),
                'filtered_count' => $bills->count(),
                'total_due'      => number_format((float) (clone $active)->sum('due_amount'), 2),
                'month_total'    => number_format((float) (clone $active)
                    ->whereBetween('bill_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->sum('grand_total'), 2),
            ];

            return response()->json($output);
        } catch (Throwable $e) {
            Log::error('Bill list error', $this->ctx($e));

            return response()->json(['data' => [], 'message' => 'Unable to load bills.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  One bill with its lines and payments                              */
    /* ------------------------------------------------------------------ */

    public function show(Request $request)
    {
        try {
            $bill = DB::table('bills as b')
                ->leftJoin('parties as p', 'p.id', '=', 'b.party_id')
                ->where('b.id', (int) $request->query('id'))
                ->where('b.deleted', 'No')
                ->first(['b.*', 'p.name as party_name', 'p.code as party_code', 'p.phone as party_phone']);

            if (!$bill) {
                return response()->json(['status' => false, 'message' => 'Bill not found.'], 404);
            }

            $details = DB::table('bill_details as d')
                ->leftJoin('chart_of_accounts as g', 'g.id', '=', 'd.account_group_id')
                ->leftJoin('chart_of_accounts as c', 'c.id', '=', 'd.account_id')
                ->where('d.bill_id', $bill->id)
                ->where('d.deleted', 'No')
                ->orderBy('d.id')
                ->get([
                    'd.account_group_id', 'd.account_id', 'd.description', 'd.qty', 'd.rate', 'd.amount',
                    'g.account_code as g_code', 'g.account_name as g_name',
                    'c.account_code as c_code', 'c.account_name as c_name',
                ])
                ->map(fn ($d) => [
                    'account_group_id' => $d->account_group_id,
                    'account_id'       => $d->account_id,
                    'group_label'      => $d->g_code . ' - ' . $d->g_name,
                    'account_label'    => $d->c_code . ' - ' . $d->c_name,
                    'description'      => $d->description,
                    'qty'              => (float) $d->qty,
                    'rate'             => number_format((float) $d->rate, 2, '.', ''),
                    'amount'           => number_format((float) $d->amount, 2, '.', ''),
                ]);

            return response()->json([
                'status' => true,
                'data'   => [
                    'id'              => $bill->id,
                    'bill_no'         => $bill->bill_no,
                    'bill_date'       => Carbon::parse($bill->bill_date)->format('Y-m-d'),
                    'due_date'        => $bill->due_date ? Carbon::parse($bill->due_date)->format('Y-m-d') : '',
                    'party_id'        => $bill->party_id,
                    'party_label'     => $bill->party_name . ($bill->party_code ? ' (' . $bill->party_code . ')' : ''),
                    'party_phone'     => $bill->party_phone,
                    'reference_no'    => $bill->reference_no,
                    'remarks'         => $bill->remarks,
                    'subtotal'        => number_format((float) $bill->subtotal, 2, '.', ''),
                    'discount_amount' => number_format((float) $bill->discount_amount, 2, '.', ''),
                    'grand_total'     => number_format((float) $bill->grand_total, 2, '.', ''),
                    'paid_amount'     => number_format((float) $bill->paid_amount, 2, '.', ''),
                    'due_amount'      => number_format((float) $bill->due_amount, 2, '.', ''),
                    'payment_status'  => $bill->payment_status,
                    'details'         => $details,
                    'payments'        => $this->paymentsOf($bill->id),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Bill show error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to load bill.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Create / update bill (update when "id" is posted)                 */
    /* ------------------------------------------------------------------ */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'              => ['nullable', 'integer'],
            'bill_date'       => ['required', 'date'],
            'due_date'        => ['nullable', 'date', 'after_or_equal:bill_date'],
            'party_id'        => ['required', 'integer', Rule::exists('parties', 'id')->where('type', 'Supplier')->where('deleted', 'No')],
            'reference_no'    => ['nullable', 'string', 'max:100'],
            'remarks'         => ['nullable', 'string', 'max:1000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],

            'details'                    => ['required', 'array', 'min:1', 'max:' . self::MAX_LINES],
            'details.*.account_group_id' => ['required', 'integer', $this->coaExists(2, self::EXPENSE_TYPE)],
            'details.*.account_id'       => ['required', 'integer', $this->coaExists(3, self::EXPENSE_TYPE)],
            'details.*.description'      => ['nullable', 'string', 'max:255'],
            'details.*.qty'              => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'details.*.rate'             => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ], [
            'party_id.required'                   => 'Please select the supplier.',
            'party_id.exists'                     => 'Please select a valid supplier.',
            'due_date.after_or_equal'             => 'Due date cannot be before the bill date.',
            'details.required'                    => 'Add at least one bill line.',
            'details.min'                         => 'Add at least one bill line.',
            'details.*.account_group_id.required' => 'Select an expense group.',
            'details.*.account_group_id.exists'   => 'Select a valid expense group.',
            'details.*.account_id.required'       => 'Select an expense account.',
            'details.*.account_id.exists'         => 'Select a valid expense account.',
            'details.*.qty.required'              => 'Enter the quantity.',
            'details.*.rate.required'             => 'Enter the rate.',
        ]);

        $validator->after(function ($v) use ($request) {
            $subtotal = 0;

            foreach ((array) $request->input('details', []) as $i => $line) {
                if (!empty($line['account_id']) && !empty($line['account_group_id'])
                    && !DB::table('chart_of_accounts')->where('id', (int) $line['account_id'])->where('parent_id', (int) $line['account_group_id'])->exists()) {
                    $v->errors()->add("details.$i.account_id", 'This account does not belong to the selected group.');
                }

                $subtotal += round((float) ($line['qty'] ?? 0) * (float) ($line['rate'] ?? 0), 2);
            }

            if ($subtotal <= 0) {
                $v->errors()->add('details', 'The bill total must be greater than zero.');
            } elseif ((float) $request->input('discount_amount', 0) >= $subtotal) {
                $v->errors()->add('discount_amount', 'Discount must be less than the subtotal.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $payableId = $this->payableAccountId();

        if (!$payableId) {
            return response()->json(['status' => false, 'message' => 'The Accounts Payable account is missing. Run the database migrations.'], 422);
        }

        DB::beginTransaction();

        try {
            $lines = collect($request->input('details'))->map(fn ($d) => [
                'account_group_id' => (int) $d['account_group_id'],
                'account_id'       => (int) $d['account_id'],
                'description'      => $this->nullIfBlank($d['description'] ?? null),
                'qty'              => round((float) $d['qty'], 2),
                'rate'             => round((float) $d['rate'], 2),
                'amount'           => round((float) $d['qty'] * (float) $d['rate'], 2),
            ]);

            $subtotal   = round($lines->sum('amount'), 2);
            $discount   = round((float) $request->input('discount_amount', 0), 2);
            $grandTotal = round($subtotal - $discount, 2);
            $partyId    = (int) $request->input('party_id');

            if ($request->filled('id')) {
                $bill = Bill::where('id', (int) $request->input('id'))->where('deleted', 'No')->lockForUpdate()->first();

                if (!$bill) {
                    abort(404, 'Bill not found.');
                }

                $paid = $this->paidTotal($bill->id);

                if ($paid > 0 && (int) $bill->party_id !== $partyId) {
                    abort(422, 'This bill has payments, so its supplier cannot be changed.');
                }

                if ($grandTotal + 0.005 < $paid) {
                    abort(422, 'The new total (' . number_format($grandTotal, 2) . ') is less than the amount already paid (' . number_format($paid, 2) . ').');
                }

                $this->reverseBill($bill, $payableId);

                $bill->updated_by   = Auth::id();
                $bill->updated_date = now();
            } else {
                $bill = new Bill();
                $bill->bill_no      = $this->nextSequentialCode(Bill::class, 'bill_no', 'BILL');
                $bill->created_by   = Auth::id();
                $bill->created_date = now();
            }

            $bill->bill_date       = $request->input('bill_date');
            $bill->due_date        = $request->input('due_date') ?: null;
            $bill->party_id        = $partyId;
            $bill->reference_no    = $this->nullIfBlank($request->input('reference_no'));
            $bill->remarks         = $this->nullIfBlank($request->input('remarks'));
            $bill->subtotal        = $subtotal;
            $bill->discount_amount = $discount;
            $bill->grand_total     = $grandTotal;
            $bill->save();

            foreach ($lines as $line) {
                $detail = new BillDetail();
                $detail->bill_id          = $bill->id;
                $detail->account_group_id = $line['account_group_id'];
                $detail->account_id       = $line['account_id'];
                $detail->description      = $line['description'];
                $detail->qty              = $line['qty'];
                $detail->rate             = $line['rate'];
                $detail->amount           = $line['amount'];
                $detail->created_by       = Auth::id();
                $detail->created_date     = now();
                $detail->save();
            }

            // Supplier ledger: the bill raises what we owe.
            $this->storeDebitPayment([
                'party_id'     => $partyId,
                'party_type'   => 'Supplier',
                'payment_type' => 'Bill',
                'date'         => $bill->bill_date,
                'debit'        => $grandTotal,
                'bill_id'      => $bill->id,
                'remarks'      => 'Bill ' . $bill->bill_no,
            ]);

            $this->postBalances($this->expenseDebits($lines, $subtotal, $grandTotal), [$payableId => $grandTotal], 1);

            $this->refreshBillTotals($bill->id);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Bill ' . $bill->bill_no . ($request->filled('id') ? ' updated' : ' created') . ' successfully.',
                'id'      => $bill->id,
            ]);
        } catch (HttpExceptionInterface $e) {
            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Bill store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the bill. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Delete bill (only when it has no payments)                        */
    /* ------------------------------------------------------------------ */

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => ['required', 'integer', 'min:1']]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid bill id is required.'], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $bill = Bill::where('id', (int) $request->input('id'))->where('deleted', 'No')->lockForUpdate()->first();

                if (!$bill) {
                    abort(404, 'Bill not found.');
                }

                if ($this->paidTotal($bill->id) > 0) {
                    abort(422, 'This bill has payments. Delete its payments first.');
                }

                $this->reverseBill($bill, (int) $this->payableAccountId());

                DB::table('bills')->where('id', $bill->id)->update($this->deletedColumns());
            }, 3);

            return response()->json(['status' => true, 'message' => 'Bill deleted and balances reversed.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Bill delete error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to delete the bill. Please try again.'], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Bill payments: create / update (update when "id" is posted)       */
    /* ------------------------------------------------------------------ */

    public function paymentStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                 => ['nullable', 'integer'],
            'bill_id'            => ['required', 'integer'],
            'payment_date'       => ['required', 'date'],
            'payment_group_id'   => ['required', 'integer', $this->coaExists(2, self::PAYMENT_TYPE)],
            'payment_account_id' => ['required', 'integer', $this->coaExists(3, self::PAYMENT_TYPE, $request->input('payment_group_id'))],
            'amount'             => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'reference_no'       => ['nullable', 'string', 'max:100'],
            'remarks'            => ['nullable', 'string', 'max:1000'],
        ], [
            'payment_group_id.required'   => 'Please select the payment group.',
            'payment_account_id.required' => 'Please select the payment account.',
            'payment_account_id.exists'   => 'Please select a valid account of the selected group.',
            'amount.min'                  => 'Amount must be greater than zero.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $payableId = $this->payableAccountId();

        if (!$payableId) {
            return response()->json(['status' => false, 'message' => 'The Accounts Payable account is missing. Run the database migrations.'], 422);
        }

        DB::beginTransaction();

        try {
            $bill = Bill::where('id', (int) $request->input('bill_id'))->where('deleted', 'No')->lockForUpdate()->first();

            if (!$bill) {
                abort(404, 'Bill not found.');
            }

            $amount = round((float) $request->input('amount'), 2);
            $oldAmount = 0;

            if ($request->filled('id')) {
                $payment = BillPayment::where('id', (int) $request->input('id'))
                    ->where('bill_id', $bill->id)->where('deleted', 'No')->lockForUpdate()->first();

                if (!$payment) {
                    abort(404, 'Payment not found.');
                }

                $oldAmount = (float) $payment->amount;
                $this->reverseBillPayment($payment, $payableId);

                $payment->updated_by   = Auth::id();
                $payment->updated_date = now();
            } else {
                $payment = new BillPayment();
                $payment->payment_no   = $this->nextSequentialCode(BillPayment::class, 'payment_no', 'BP');
                $payment->bill_id      = $bill->id;
                $payment->party_id     = $bill->party_id;
                $payment->created_by   = Auth::id();
                $payment->created_date = now();
            }

            $payable = round($this->dueOf($bill) + $oldAmount, 2);

            if ($amount > $payable + 0.005) {
                abort(422, 'Amount cannot be more than the bill due (' . number_format($payable, 2) . ').');
            }

            $payment->payment_date       = $request->input('payment_date');
            $payment->payment_group_id   = (int) $request->input('payment_group_id');
            $payment->payment_account_id = (int) $request->input('payment_account_id');
            $payment->amount             = $amount;
            $payment->reference_no       = $this->nullIfBlank($request->input('reference_no'));
            $payment->remarks            = $this->nullIfBlank($request->input('remarks'));
            $payment->save();

            // Supplier ledger: paying lowers what we owe.
            $this->storeCreditPayment([
                'party_id'               => $bill->party_id,
                'party_type'             => 'Supplier',
                'payment_type'           => 'Bill Payment',
                'date'                   => $payment->payment_date,
                'credit'                 => $amount,
                'bill_id'                => $bill->id,
                'bill_payment_id'        => $payment->id,
                'payment_method'         => $payment->payment_group_id,
                'payment_method_account' => $payment->payment_account_id,
                'remarks'                => 'Payment ' . $payment->payment_no . ' for bill ' . $bill->bill_no,
            ]);

            $this->postBalances([$payableId => $amount], [(int) $payment->payment_account_id => $amount], 1);

            $this->refreshBillTotals($bill->id);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Payment ' . $payment->payment_no . ($request->filled('id') ? ' updated' : ' saved') . ' successfully.',
            ]);
        } catch (HttpExceptionInterface $e) {
            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Bill payment store error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to save the payment. Please try again.'], 500);
        }
    }

    public function paymentDelete(Request $request)
    {
        $validator = Validator::make($request->all(), ['id' => ['required', 'integer', 'min:1']]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'A valid payment id is required.'], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $payment = BillPayment::where('id', (int) $request->input('id'))->where('deleted', 'No')->lockForUpdate()->first();

                if (!$payment) {
                    abort(404, 'Payment not found.');
                }

                Bill::where('id', $payment->bill_id)->lockForUpdate()->first();

                $this->reverseBillPayment($payment, (int) $this->payableAccountId());

                DB::table('bill_payments')->where('id', $payment->id)->update($this->deletedColumns());

                $this->refreshBillTotals($payment->bill_id);
            }, 3);

            return response()->json(['status' => true, 'message' => 'Payment deleted and balances reversed.']);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            Log::error('Bill payment delete error', $this->ctx($e));

            return response()->json(['status' => false, 'message' => 'Unable to delete the payment. Please try again.'], 500);
        }
    }

    /* ================================================================== */
    /*  Helpers                                                           */
    /* ================================================================== */

    /** Undo a bill's posting: balances, its "Bill" ledger row and its lines. Payments are left alone. */
    private function reverseBill(Bill $bill, int $payableId): void
    {
        $old = DB::table('bill_details')->where('bill_id', $bill->id)->where('deleted', 'No')
            ->get(['account_id', 'amount']);

        $this->postBalances(
            $this->expenseDebits($old, (float) $bill->subtotal, (float) $bill->grand_total),
            [$payableId => (float) $bill->grand_total],
            -1
        );

        DB::table('bill_details')->where('bill_id', $bill->id)->where('deleted', 'No')->update($this->deletedColumns());

        Payment::where('bill_id', $bill->id)->where('payment_type', 'Bill')->where('deleted', 'No')
            ->update($this->ledgerDeletedColumns());
    }

    /** Undo one payment's posting: balances and its ledger row. */
    private function reverseBillPayment(BillPayment $payment, int $payableId): void
    {
        $this->postBalances([$payableId => (float) $payment->amount], [(int) $payment->payment_account_id => (float) $payment->amount], -1);

        Payment::where('bill_payment_id', $payment->id)->where('deleted', 'No')
            ->update($this->ledgerDeletedColumns());
    }

    /**
     * Expense debits per account, net of the bill discount, so debits always equal
     * the grand total credited to Accounts Payable. The discount is spread across
     * accounts by their share; any rounding cent goes to the largest account.
     */
    private function expenseDebits($lines, float $subtotal, float $grandTotal): array
    {
        $gross = $this->sumByColumn($lines, 'account_id', 'amount');

        if (!$gross || $subtotal <= 0) {
            return [];
        }

        $factor = $grandTotal / $subtotal;
        $net    = array_map(fn ($amount) => round($amount * $factor, 2), $gross);

        $diff = round($grandTotal - array_sum($net), 2);
        if (abs($diff) >= 0.005) {
            $largest = array_search(max($net), $net, true);
            $net[$largest] = round($net[$largest] + $diff, 2);
        }

        return $net;
    }

    /** Recomputes paid / due / status from the active payments. */
    private function refreshBillTotals(int $billId): void
    {
        $grand = (float) DB::table('bills')->where('id', $billId)->value('grand_total');
        $paid  = $this->paidTotal($billId);
        $due   = round(max($grand - $paid, 0), 2);

        DB::table('bills')->where('id', $billId)->update([
            'paid_amount'    => $paid,
            'due_amount'     => $due,
            'payment_status' => $paid <= 0 ? 'Unpaid' : ($due <= 0 ? 'Paid' : 'Partial'),
        ]);
    }

    private function paidTotal(int $billId): float
    {
        return round((float) DB::table('bill_payments')->where('bill_id', $billId)->where('deleted', 'No')->sum('amount'), 2);
    }

    private function dueOf(Bill $bill): float
    {
        return round((float) $bill->grand_total - $this->paidTotal($bill->id), 2);
    }

    private function paymentsOf(int $billId)
    {
        return DB::table('bill_payments as bp')
            ->leftJoin('chart_of_accounts as g', 'g.id', '=', 'bp.payment_group_id')
            ->leftJoin('chart_of_accounts as a', 'a.id', '=', 'bp.payment_account_id')
            ->where('bp.bill_id', $billId)
            ->where('bp.deleted', 'No')
            ->orderBy('bp.payment_date')->orderBy('bp.id')
            ->get([
                'bp.id', 'bp.payment_no', 'bp.payment_date', 'bp.amount', 'bp.reference_no', 'bp.remarks',
                'bp.payment_group_id', 'bp.payment_account_id',
                'g.account_code as g_code', 'g.account_name as g_name',
                'a.account_code as a_code', 'a.account_name as a_name',
            ])
            ->map(fn ($p) => [
                'id'                    => $p->id,
                'payment_no'            => $p->payment_no,
                'payment_date'          => Carbon::parse($p->payment_date)->format('Y-m-d'),
                'payment_date_label'    => Carbon::parse($p->payment_date)->format('d M Y'),
                'amount'                => number_format((float) $p->amount, 2, '.', ''),
                'reference_no'          => $p->reference_no,
                'remarks'               => $p->remarks,
                'payment_group_id'      => $p->payment_group_id,
                'payment_group_label'   => $p->g_code . ' - ' . $p->g_name,
                'payment_account_id'    => $p->payment_account_id,
                'payment_account_label' => $p->a_code . ' - ' . $p->a_name,
            ]);
    }

    private function payableAccountId(): ?int
    {
        $id = DB::table('chart_of_accounts')
            ->where('slug', 'accounts-payable')->where('layer_level', 3)->where('deleted', 'No')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function suppliers()
    {
        return DB::table('parties')
            ->where('type', 'Supplier')->where('deleted', 'No')->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'phone']);
    }

    /** The payments table names its audit columns *_datetime. */
    private function ledgerDeletedColumns(): array
    {
        return [
            'deleted'          => 'Yes',
            'status'           => 'Inactive',
            'deleted_by'       => Auth::id(),
            'updated_by'       => Auth::id(),
            'updated_datetime' => now(),
            'deleted_datetime' => now(),
        ];
    }
}
