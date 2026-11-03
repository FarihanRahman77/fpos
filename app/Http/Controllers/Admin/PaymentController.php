<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ChartOfAccount;
use App\Models\Admin\Party;
use App\Models\Admin\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Exception;
use App\Traits\PartyDueTrait;
use App\Traits\PaymentTrait;

class PaymentController extends Controller
{
    use PartyDueTrait, PaymentTrait;

    private const TYPES = ['Payment', 'Payment Receive'];

    /** Payment => Supplier, Payment Receive => Customer */
    private function partyTypeFor($type)
    {
        return $type === 'Payment' ? 'Supplier' : 'Customer';
    }

    public function index($type)
    {
        $all = Party::where('deleted', 'No')->orderBy('name')->get();
        $balances = $this->balances(true);   // the form pays / receives in the working branch
        foreach ($all as $p) {
            $p->balance = round($balances[$p->id] ?? 0, 2);
        }
        $isCustomer = fn ($p) => in_array($p->type, ['Customer', 'Walk-In Customer'], true);

        if ($type == 'Payment') {
            // Suppliers, plus customers holding a credit (advance / returns) that can be paid back in cash.
            $parties = $all->filter(fn ($p) => $p->type === 'Supplier' || ($isCustomer($p) && $p->balance < -0.004))->values();
        } elseif ($type == 'Payment Receive') {
            // Customers, plus walk-in customers who owe something.
            $parties = $all->filter(fn ($p) => $p->type === 'Customer' || ($p->type === 'Walk-In Customer' && $p->balance > 0.004))->values();
        } else {
            $parties = $all->filter(fn ($p) => $p->type === 'Supplier' || $isCustomer($p))->values();
        }
        foreach ($parties as $p) {
            $p->label = $p->name . ($p->phone ? ' (' . $p->phone . ')' : '');
            if ($isCustomer($p)) {
                $p->label .= $p->type === 'Walk-In Customer' ? ' · walk-in' : '';
                if ($p->balance < -0.004) {
                    $p->label .= ' · customer credit ' . number_format(-$p->balance, 2);
                } elseif ($p->balance > 0.004) {
                    $p->label .= ' · due ' . number_format($p->balance, 2);
                }
            }
        }

        // Payment methods = 2nd layer groups under the Liquid Asset root (Cash, Bank ...), as on the sale page.
        $coa       = ChartOfAccount::where('slug', 'liquid-asset')->where('deleted', 'No')->first();
        $childCoas = $coa
            ? ChartOfAccount::where('deleted', 'No')->where('status', 'Active')->where('parent_id', $coa->id)->orderBy('account_code')->get()
            : collect();

        return view('admin.payment.index', compact('type', 'parties', 'childCoas'));
    }

    public function list(Request $request, $type)
    {
        try {
            $side = $request->query('side');   // Discount: customer = given, supplier = received
            $payments = BranchController::scope(DB::table('payments'), 'payments.branch_id')
                ->leftJoin('parties', 'parties.id', '=', 'payments.party_id')
                ->leftJoin('sales as applied', 'applied.id', '=', 'payments.applied_sale_id')
                ->select(
                    'payments.*',
                    'parties.name as party_name',
                    'parties.phone as party_phone',
                    'parties.type as party_kind',
                    'applied.invoice_no as applied_invoice_no'
                )
                ->where('payments.deleted', 'No')
                ->where('payments.payment_type', $type)
                ->when($type === 'Discount' && $side === 'customer', fn ($q) => $q->whereIn('parties.type', ['Customer', 'Walk-In Customer']))
                ->when($type === 'Discount' && $side === 'supplier', fn ($q) => $q->where('parties.type', 'Supplier'))
                ->orderByDesc('payments.id')
                ->get();

            $data = [];

            foreach ($payments as $index => $row) {

                $party = '<strong>' . e($row->party_name ?? 'N/A') . '</strong>';
                if (!empty($row->party_phone)) {
                    $party .= '<br><small class="text-muted"><i class="fas fa-phone-alt me-1"></i>'
                        . e($row->party_phone) . '</small>';
                }
                if ($type === 'Payment' && in_array($row->party_kind, ['Customer', 'Walk-In Customer'], true)) {
                    $party .= '<br><span class="badge bg-info">Customer refund</span>';
                }
                if ($type === 'Discount') {
                    $party .= '<br><span class="badge ' . ($row->party_kind === 'Supplier' ? 'bg-secondary">Received from supplier' : 'bg-info">Given to customer') . '</span>';
                }
                if (!empty($row->applied_invoice_no)) {
                    $party .= '<br><small class="text-muted">for invoice ' . e($row->applied_invoice_no) . '</small>';
                }

                if ($type === 'Payment') {
                    $amount = $row->credit;
                } elseif ($type === 'Payment Receive') {
                    $amount = $row->debit;
                } elseif ($type === 'Discount') {
                    $amount = ($row->credit > 0)
                        ? $row->credit
                        : $row->debit;
                } else {
                    $amount = 0;
                }

                $status = $row->status ?? 'Active';
                $badge  = $status === 'Active' ? 'bg-success' : 'bg-secondary';

                $date = $row->datetime ? Carbon::parse($row->datetime)->format('d M Y h:i A') : '';

                $action = action_dropdown([
                    ['label' => 'Delete', 'icon' => 'fas fa-trash', 'class' => 'text-danger', 'onclick' => 'deletepayment(' . (int) $row->id . ')'],
                ], $row->branch_id);

                $data[] = [
                    $index + 1,
                    '<strong class="text-primary">' . e($row->code ?? '') . '</strong>' . branch_tag($row->branch_id),
                    e($date),
                    $party,
                    $row->payment_method
                        ? e($row->payment_method) . ($row->payment_method_account ? '<br><small class="text-muted">' . e($row->payment_method_account) . '</small>' : '')
                        : '<span class="text-muted">-</span>',
                    '<span class="fw-bold">' . number_format((float) $amount, 2, '.', '') . '</span>',
                    '<span class="badge ' . $badge . '">' . e($status) . '</span>',
                    $action,
                ];
            }

            return response()->json([
                'status' => true,
                'data'   => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => self::safeError($e),
                'data'    => [],
            ], 500);
        }
    }

    /**
     * Due amount of a party (shown in the modal).
     */
    public function party_due(Request $request)
    {
        try {
            $partyId = $request->get('party');

            $due = round($this->partyDue($partyId), 2);
            $party = Party::find($partyId);

            return response()->json([
                'status' => true,
                'due'    => $due,
                'due_text' => number_format(abs($due), 2, '.', '') . ($due < 0 ? ' (credit)' : ''),
                'party_type' => $party->type ?? null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => self::safeError($e),
            ], 500);
        }
    }




    /** Open (unpaid) invoices of a customer in the working branch, for "apply to invoice" on Payment Receive. */
    public function openInvoices(Request $request)
    {
        $rows = BranchController::scopeOwn(DB::table('sales'), 'branch_id')
            ->where('customer_id', (int) $request->query('party'))->where('deleted', 'No')->where('due_amount', '>', 0.004)
            ->orderBy('sale_date')->orderBy('id')->get(['id', 'invoice_no', 'sale_date', 'grand_total', 'due_amount']);

        return response()->json(['status' => true, 'invoices' => $rows->map(fn ($r) => [
            'id' => $r->id, 'due' => round((float) $r->due_amount, 2),
            'text' => $r->invoice_no . ' · ' . $r->sale_date . ' · due ' . number_format((float) $r->due_amount, 2),
        ])]);
    }

    /**
     * party_id => balance (customer: + owes us, − credit; supplier: + we owe), in one query. Visible branches, or
     * the working branch only with $own (the opening balance belongs to the main branch).
     */
    private function balances(bool $own = false): array
    {
        $out = [];
        $base = DB::table('payments as p');
        $own ? BranchController::scopeOwn($base, 'p.branch_id') : BranchController::scope($base, 'p.branch_id');
        $rows = $base->join('parties as c', 'c.id', '=', 'p.party_id')
            ->where('p.deleted', 'No')->where('p.status', 'Active')
            ->where(fn ($q) => $q->whereNull('p.party_type')->orWhere('p.party_type', '<>', 'Employee'))
            ->groupBy('p.party_id', 'c.type')->selectRaw('p.party_id, c.type, SUM(p.debit) d, SUM(p.credit) c')->get();
        foreach ($rows as $r) {
            $out[$r->party_id] = $r->type === 'Supplier' ? (float) $r->d - (float) $r->c : (float) $r->c - (float) $r->d;
        }
        if ($own ? BranchController::isMainBranch() : BranchController::mainVisible()) {
            foreach (Party::where('deleted', 'No')->where('opening_balance', '<>', 0)->get(['id', 'opening_balance', 'opening_balance_type']) as $p) {
                $out[$p->id] = ($out[$p->id] ?? 0) + (float) $p->opening_balance * (($p->opening_balance_type ?? 'Due') === 'Advance' ? -1 : 1);
            }
        }

        return $out;
    }

    public function store(Request $request)
    {
        $type = $request->type;
        $isDiscount = $type == 'Discount';

        $rules = [
            'type'           => ['required', Rule::in(['Payment', 'Payment Receive', 'Discount'])],
            'payment_date'   => 'required|date',
            // Payment = to a supplier, Payment Receive = from a customer, Discount = either.
            'party_id'       => ['required', Rule::exists('parties', 'id')->where('deleted', 'No')
                // Payment also pays a customer's credit back (advance / returns kept as credit).
                ->whereIn('type', $type === 'Payment Receive' ? ['Customer', 'Walk-In Customer'] : ['Supplier', 'Customer', 'Walk-In Customer'])],
            'sale_id'        => ['nullable', 'integer'],
            'amount'         => 'required|numeric|gt:0',
            // A discount moves no money, so it has no payment method / account.
            'payment_method' => $isDiscount ? ['nullable'] : [
                'required', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where('layer_level', 2)->where('account_type', 'Liquid Asset')->where('deleted', 'No'),
            ],
            'payment_method_account' => $isDiscount ? ['nullable'] : [
                'required', 'integer', BranchController::ownLedgerRule(),
                Rule::exists('chart_of_accounts', 'id')->where('layer_level', 3)->where('deleted', 'No')
                    ->where('parent_id', (int) $request->input('payment_method')),
            ],
            'remarks'        => 'nullable|string|max:1000',
        ];

        $validator = Validator::make($request->all(), $rules, [
            'party_id.required'               => 'Please select a party.',
            'party_id.exists'                 => 'Selected party is invalid.',
            'payment_method.required'         => 'Please select a payment method.',
            'payment_method.exists'           => 'Please select a valid payment method.',
            'payment_method_account.required' => 'Please select a payment account.',
            'payment_method_account.exists'   => 'Please select an account of the selected payment method.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        return self::retryOnDeadlock(function () use ($request, $type, $isDiscount) {
            try {
                DB::beginTransaction();

                $party  = Party::findOrFail($request->party_id);
                $amount = round((float) $request->amount, 2);
                $isCustomer = in_array($party->type, ['Customer', 'Walk-In Customer'], true);

                // Paying a customer: only their credit can be paid back.
                if ($type === 'Payment' && $isCustomer) {
                    $credit = round(-$this->partyDue($party->id, true), 2);   // the working branch pays it back
                    if ($credit <= 0.004) {
                        DB::rollBack();
                        return response()->json(['status' => false, 'message' => $party->name . ' has no credit to pay back.'], 422);
                    }
                    if ($amount > $credit + 0.004) {
                        DB::rollBack();
                        return response()->json(['status' => false, 'message' => 'Only the customer credit of ' . number_format($credit, 2) . ' can be paid back.'], 422);
                    }
                }

                // Payment Receive applied to one invoice: it must be this customer's open invoice in this branch.
                $invoice = null;
                if ($type === 'Payment Receive' && $request->filled('sale_id')) {
                    $invoice = BranchController::scopeOwn(DB::table('sales'), 'branch_id')->where('id', (int) $request->sale_id)
                        ->where('customer_id', $party->id)->where('deleted', 'No')->lockForUpdate()->first();
                    if (!$invoice) {
                        DB::rollBack();
                        return response()->json(['status' => false, 'message' => 'Choose an invoice of this customer.'], 422);
                    }
                    if ($amount > (float) $invoice->due_amount + 0.004) {
                        DB::rollBack();
                        return response()->json(['status' => false, 'message' => 'Invoice ' . $invoice->invoice_no . ' has only ' . number_format((float) $invoice->due_amount, 2)
                            . ' due. Receive that much against it, or leave the invoice blank to receive on account.'], 422);
                    }
                }

                $datetime = $request->payment_date . ' ' . date('H:i:s');
                $method   = $isDiscount ? null : (int) $request->payment_method;
                $account  = $isDiscount ? null : (int) $request->payment_method_account;

                if ($type === 'Discount') {
                    if ($party->type === 'Supplier') {
                        $this->storeCreditPayment([
                            'party_id'       => $party->id,
                            'party_type'     => $party->type,
                            'payment_type'   => $type,
                            'datetime'       => $datetime,
                            'credit'         => $amount,
                            'payment_method' => $method,
                            'payment_method_account' => $account,
                            'remarks'        => $request->remarks,
                        ]);
                    } elseif ($party->type === 'Customer' || $party->type === 'Walk-In Customer') {
                        $this->storeDebitPayment([
                            'party_id'       => $party->id,
                            'party_type'     => $party->type,
                            'payment_type'   => $type,
                            'datetime'       => $datetime,
                            'debit'          => $amount,
                            'payment_method' => $method,
                            'payment_method_account' => $account,
                            'remarks'        => $request->remarks,
                        ]);
                    } else {
                        DB::rollBack();

                        return response()->json([
                            'status'  => false,
                            'message' => 'Invalid party type for discount.',
                        ], 422);
                    }
                } else {
                    if ($type === 'Payment Receive') {
                        $receipt = $this->storeDebitPayment([
                            'party_id'       => $party->id,
                            'party_type'     => $party->type,
                            'payment_type'   => $type,
                            'datetime'       => $datetime,
                            'debit'          => $amount,
                            'payment_method' => $method,
                            'payment_method_account' => $account,
                            'remarks'        => $request->remarks ?: ($invoice ? 'Against invoice ' . $invoice->invoice_no : null),
                        ]);
                        if ($invoice) {
                            $receipt->applied_sale_id = $invoice->id;
                            $receipt->save();
                            DB::table('sales')->where('id', $invoice->id)->update([
                                'paid_amount' => DB::raw('paid_amount + ' . $amount),
                                'due_amount'  => DB::raw('GREATEST(0, due_amount - ' . $amount . ')'),
                            ]);
                        }
                    } else {
                        // paying a supplier / a customer's credit back: never more than the cash drawer holds
                        ReportController::ensureCash($account, $amount);
                        $this->storeCreditPayment([
                            'party_id'       => $party->id,
                            'party_type'     => $party->type,
                            'payment_type'   => $type,
                            'datetime'       => $datetime,
                            'credit'         => $amount,
                            'payment_method' => $method,
                            'payment_method_account' => $account,
                            'remarks'        => $request->remarks,
                        ]);
                    }
                }

                DB::commit();

                return response()->json([
                    'status'  => true,
                    'message' => ($type === 'Payment Receive' ? 'Payment received' : ($type === 'Discount' ? 'Discount saved' : ($isCustomer ? 'Customer credit paid back' : 'Payment saved'))) . ' successfully.'
                        . ($invoice ? ' Applied to invoice ' . $invoice->invoice_no . '.' : ''),
                ]);
            } catch (Exception $e) {
                DB::rollBack();
                self::rethrowIfConcurrency($e);

                return response()->json([
                    'status'  => false,
                    'message' => self::safeError($e),
                ], $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500);
            }
        });
    }

    public function delete(Request $request)
    {
        $id = $request->id;
        BranchController::guardOwn('payments', $id);
        try {
            // Only the working branch's stand-alone payments / receipts / discounts. Rows written by a sale, purchase,
            // return, order, expense or bill are removed by deleting that document instead.
            $payment = BranchController::scopeOwn(DB::table('payments'), 'branch_id')
                ->where('id', $id)->where('deleted', 'No')
                ->whereIn('payment_type', ['Payment', 'Payment Receive', 'Discount'])
                ->first();

            if (!$payment) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Record not found.',
                ], 404);
            }

            foreach (['sale_id', 'sale_return_id', 'purchase_id', 'purchase_return_id', 'expense_id', 'bill_id', 'order_id'] as $link) {
                if (!empty($payment->{$link})) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'This entry belongs to another document. Delete or edit that document instead.',
                    ], 422);
                }
            }

            DB::transaction(function () use ($payment, $id) {
                // A receipt applied to an invoice: the invoice is unpaid by that much again.
                if (!empty($payment->applied_sale_id)) {
                    DB::table('sales')->where('id', $payment->applied_sale_id)->update([
                        'paid_amount' => DB::raw('GREATEST(0, paid_amount - ' . (float) $payment->debit . ')'),
                        'due_amount'  => DB::raw('LEAST(grand_total, due_amount + ' . (float) $payment->debit . ')'),
                    ]);
                }
                $this->deletePaymentsBy('id', $id);
            }, self::SAVE_ATTEMPTS);

            return response()->json([
                'status'  => true,
                'message' => 'Deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => self::safeError($e),
            ], 500);
        }
    }
}
