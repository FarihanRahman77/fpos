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
        if ($type == 'Payment') {
            $parties = Party::where('type', 'Supplier')->where('deleted', 'No')->get();
        } elseif ($type == 'Payment Receive') {
            $parties = Party::where('type', 'Customer')->where('deleted', 'No')->get();
        } else {
            $parties = Party::where('deleted', 'No')->get();
        }

        // Payment methods = 2nd layer groups under the Liquid Asset root (Cash, Bank ...), as on the sale page.
        $coa       = ChartOfAccount::where('slug', 'liquid-asset')->where('deleted', 'No')->first();
        $childCoas = $coa
            ? ChartOfAccount::where('deleted', 'No')->where('status', 'Active')->where('parent_id', $coa->id)->orderBy('account_code')->get()
            : collect();

        return view('admin.payment.index', compact('type', 'parties', 'childCoas'));
    }

    public function list($type)
    {
        try {
            $payments = DB::table('payments')
                ->leftJoin('parties', 'parties.id', '=', 'payments.party_id')
                ->select(
                    'payments.*',
                    'parties.name as party_name',
                    'parties.phone as party_phone'
                )
                ->where('payments.deleted', 'No')
                ->where('payments.payment_type', $type)
                ->orderByDesc('payments.id')
                ->get();

            $data = [];

            foreach ($payments as $index => $row) {

                $party = '<strong>' . e($row->party_name ?? 'N/A') . '</strong>';
                if (!empty($row->party_phone)) {
                    $party .= '<br><small class="text-muted"><i class="fas fa-phone-alt me-1"></i>'
                        . e($row->party_phone) . '</small>';
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
                ]);

                $data[] = [
                    $index + 1,
                    '<strong class="text-primary">' . e($row->code ?? '') . '</strong>',
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
                'message' => $e->getMessage(),
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

            return response()->json([
                'status' => true,
                'due'    => $this->partyDue($partyId),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }




    public function store(Request $request)
    {
        $type = $request->type;
        $isDiscount = $type == 'Discount';

        $rules = [
            'type'           => 'required',
            'payment_date'   => 'required|date',
            'party_id'       => 'required|exists:parties,id',
            'amount'         => 'required|numeric|gt:0',
            // A discount moves no money, so it has no payment method / account.
            'payment_method' => $isDiscount ? ['nullable'] : [
                'required', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where('layer_level', 2)->where('account_type', 'Liquid Asset')->where('deleted', 'No'),
            ],
            'payment_method_account' => $isDiscount ? ['nullable'] : [
                'required', 'integer',
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
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $party  = Party::findOrFail($request->party_id);
            $amount = (float) $request->amount;

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
                    return response()->json([
                        'status'  => false,
                        'message' => 'Invalid party type for discount.',
                    ], 400);
                }
            } else {
                if ($type === 'Payment Receive') {
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
                'message' => $type . '  successfull.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $id = $request->id;
        try {
            $payment = DB::table('payments')->where('id', $id)->where('deleted', 'No')->first();

            if (!$payment) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Record not found.',
                ], 404);
            }

            $this->deletePaymentsBy('id', $id);

            return response()->json([
                'status'  => true,
                'message' => 'Deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
