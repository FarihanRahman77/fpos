<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

class PaymentController extends Controller
{
    use PartyDueTrait;

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

        return view('admin.payment.index', compact('type', 'parties'));
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

                $action = '
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deletepayment(' . (int) $row->id . ')">
                        <i class="fa fa-trash"></i>
                    </button>';

                $data[] = [
                    $index + 1,
                    '<strong class="text-primary">' . e($row->code ?? '') . '</strong>',
                    e($date),
                    $party,
                    e($row->payment_method ?? ''),
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

        $rules = [
            'type'           => 'required',
            'payment_date'   => 'required|date',
            'party_id'       => 'required|exists:parties,id',
            'amount'         => 'required|numeric|gt:0',
            'payment_method' => $type == 'Discount' ? 'nullable' : 'required',
            'remarks'        => 'nullable|string|max:1000',
        ];

        $validator = Validator::make($request->all(), $rules, [
            'party_id.required'       => 'Please select a party.',
            'party_id.exists'         => 'Selected party is invalid.',
            'payment_method.required' => 'Please select a payment method.',
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

            $lastPayment = Payment::max('code');
            $nextP = $lastPayment ? ((int) preg_replace('/[^0-9]/', '', $lastPayment)) + 1 : 1;
            $code = 'VO-' . str_pad($nextP, 6, '0', STR_PAD_LEFT);

            $payment = new Payment();
            $payment->party_id         = $party->id;
            $payment->party_type       = $party->type;
            $payment->code             = $code;
            $payment->payment_type     = $type;
            $payment->datetime         = $request->payment_date . ' ' . date('H:i:s');
            if($type === 'Discount') {
                if($party->type === 'Supplier') {
                    $payment->debit = 0;
                    $payment->credit = $amount;
                } elseif($party->type === 'Customer' || $party->type === 'Walk-In Customer') {
                    $payment->debit = $amount;
                    $payment->credit = 0;
                } else {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Invalid party type for discount.',
                    ], 400);
                }
            } else {
                $payment->debit = $type === 'Payment Receive' ? $amount : 0;
                $payment->credit = $type === 'Payment' ? $amount : 0;
            }
            $payment->payment_method   = $type == 'Discount' ? null : $request->payment_method;
            $payment->remarks          = $request->remarks;
            $payment->status           = 'Active';
            $payment->deleted          = 'No';
            $payment->created_by       = auth()->id();
            $payment->created_datetime = now();
            $payment->save();

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

            $now = now();

            DB::table('payments')->where('id', $id)->update([
                'deleted'          => 'Yes',
                'deleted_by'       => Auth::id(),
                'deleted_datetime' => $now,
                'updated_at'       => $now,
            ]);

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
