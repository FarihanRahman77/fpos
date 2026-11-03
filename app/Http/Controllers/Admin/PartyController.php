<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Party;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    /**
     * Investor = an owner of the business: not a customer or supplier (no dues, no sales / purchases). Their money moves
     * through the Suspense module (SuspenseController): investments, capital withdrawals, advances. Opening capital is
     * kept in opening_capital (main branch, like other opening balances), so opening_balance stays 0 for them.
     */
    private array $types = ['Customer', 'Walk-In Customer', 'Supplier', 'Investor'];

    private array $prefixes = [
        'Customer'         => 'CUS',
        'Walk-In Customer' => 'WIC',
        'Supplier'         => 'SUP',
        'Investor'         => 'INVR',
    ];

    /* ---------- Page ---------- */
    public function index($type)
    {
        abort_unless(in_array($type, $this->types), 404);

        if ($type === 'Investor') {
            return view('admin.inventory.party.investors', ['party_type' => $type]);
        }

        return view('admin.inventory.party.index', ['party_type' => $type]);
    }

    /* ---------- List (ajax) ---------- */
    public function list(Request $request, $type)
    {
        if (!in_array($type, $this->types)) {
            return response()->json(['message' => 'Invalid party type'], 404);
        }

        $query = Party::where('deleted','No')->where('type', $type);

        if ($search = trim((string) $request->search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $parties = $query->orderByDesc('id')->paginate(10);
        if ($type === 'Investor') {
            // capital and outstanding suspense in the branches the user sees
            $totals = SuspenseController::totals('Investor', collect($parties->items())->pluck('id')->all());
            foreach ($parties->items() as $p) {
                $t = $totals[$p->id] ?? [];
                $p->capital = $t['capital'] ?? 0;
                $p->invested = round(($t['opening_capital'] ?? 0) + ($t['invested'] ?? 0), 2);
                $p->capital_withdrawn = $t['capital_withdrawn'] ?? 0;
                $p->outstanding = $t['outstanding'] ?? 0;
            }
        }

        return response()->json([
            'data'         => $parties->items(),
            'current_page' => $parties->currentPage(),
            'last_page'    => $parties->lastPage(),
            'total'        => $parties->total(),
            'from'         => $parties->firstItem() ?? 0,
        ]);
    }

    /* ---------- Show single (for edit) ---------- */
    public function show(Request $request)
    {
        $party = Party::where('deleted', 'No')->find($request->id);

        if (!$party) {
            return response()->json(['message' => 'Party not found'], 404);
        }

        return response()->json($party);
    }

    /* ---------- Store ---------- */
    public function store(Request $request)
    {

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $party = DB::transaction(function () use ($request) {
            $party = new Party();
            $this->fill($party, $request);
            $party->code         = $this->generateCode($request->type);
            $party->type         = $request->type;
            $party->deleted      = 'No';
            $party->created_by   = auth()->id();
            $party->created_date = now();
            $party->save();

            return $party;
        });

        return response()->json(['message' => $request->type . ' created successfully', 'data' => $party]);
    }

    /* ---------- Update ---------- */
    public function update(Request $request)
    {
        $party = Party::where('deleted', 'No')->find($request->id);
        if (!$party) {
            return response()->json(['message' => 'Party not found'], 404);
        }
        $request->merge(['type' => $party->type]);   // the type of a saved party never changes

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $this->fill($party, $request);
        $party->updated_by   = auth()->id();
        $party->updated_date = now();
        $party->save();

        return response()->json(['message' => $party->type . ' updated successfully', 'data' => $party]);
    }

    /* ---------- Toggle status ---------- */
    public function toggleStatus(Request $request)
    {
        $party = Party::find($request->id);
        if (!$party) {
            return response()->json(['message' => 'Party not found'], 404);
        }

        $party->status       = $party->status === 'Active' ? 'Inactive' : 'Active';
        $party->updated_by   = auth()->id();
        $party->updated_date = now();
        $party->save();

        return response()->json(['message' => 'Status changed to ' . $party->status]);
    }

    /* ---------- Delete (soft, deleted = Yes) ---------- */
    public function destroy(Request $request)
    {
        $party = Party::find($request->id);
        if (!$party) {
            return response()->json(['message' => 'Party not found'], 404);
        }

        // A party with ledger entries (sales, purchases, payments) would leave dues and reports pointing at a
        // deleted party; such a party can only be made Inactive.
        $used = DB::table('suspense_transactions')->where('person_type', 'Investor')->where('party_id', $party->id)->where('deleted', 'No')->exists()
            || DB::table('payments')->where('party_id', $party->id)->where('deleted', 'No')->exists()
            || DB::table('sales')->where('customer_id', $party->id)->where('deleted', 'No')->exists()
            || DB::table('purchases')->where('supplier_id', $party->id)->where('deleted', 'No')->exists();
        if ($used) {
            return response()->json([
                'message' => 'This ' . strtolower($party->type) . ' has transactions and cannot be deleted. Set it to Inactive instead.',
            ], 422);
        }

        $party->deleted      = 'Yes';
        $party->status      = 'Inactive';
        $party->deleted_by   = auth()->id();
        $party->deleted_date = now();
        $party->save();

        return response()->json(['message' => $party->type . ' deleted successfully']);
    }

    /* ---------- Helpers ---------- */
   private function validator(Request $request)
{
    $money = 'regex:/^\d{1,13}(\.\d{1,2})?$/';        // positive, max 2 decimals, fits decimal(15,2)
    $phone = 'regex:/^\+?[0-9]{8,15}$/';              // optional +, 8-15 digits

    if ($request->input('type') === 'Investor') {
        return Validator::make($request->all(), [
            'type'                 => ['required', Rule::in($this->types)],
            'name'                 => ['required', 'string', 'min:1', 'max:255', "regex:/^[\pL\pN\s.,&()'\-\/]+$/u"],
            'email'                => ['nullable', 'email:rfc', 'max:255'],
            'phone'                => ['required', $phone],
            'alt_phone'            => ['nullable', $phone, 'different:phone'],
            'address'              => ['nullable', 'string', 'max:1000'],
            'nid'                  => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-\s]+$/'],
            'share_percent'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'opening_capital'      => ['nullable', $money],
            'opening_balance_date' => ['nullable', 'date'],
            'status'               => ['required', Rule::in(['Active', 'Inactive'])],
        ], [
            'name.regex'            => 'Name contains invalid characters.',
            'phone.regex'           => 'Phone must be 8-15 digits and may start with +.',
            'alt_phone.regex'       => 'Alt phone must be 8-15 digits and may start with +.',
            'alt_phone.different'   => 'Alt phone must be different from the phone number.',
            'nid.regex'             => 'NID may contain letters, digits and dashes only.',
            'share_percent.max'     => 'Share cannot be more than 100%.',
            'opening_capital.regex' => 'Enter a positive amount with up to 2 decimals.',
        ]);
    }

    return Validator::make($request->all(), [
        'type'                 => ['required', Rule::in($this->types)],
        'name'                 => ['required', 'string', 'min:1', 'max:255', "regex:/^[\pL\pN\s.,&()'\-\/]+$/u"],
        'email'                => ['nullable', 'email:rfc', 'max:255'],
        'phone'                => ['required', $phone],
        'alt_phone'            => ['nullable', $phone, 'different:phone'],
        'address'              => ['required', 'string',  'max:1000'],
        'opening_balance'      => ['nullable', $money],
        'opening_balance_type' => ['required', Rule::in(['Due', 'Advance'])],
        'opening_balance_date' => ['nullable', 'date'],
        'credit_limit'         => ['nullable', $money],
        'status'               => ['required', Rule::in(['Active', 'Inactive'])],
    ], [
        'name.regex'          => 'Name contains invalid characters.',
        'phone.regex'         => 'Phone must be 8-15 digits and may start with +.',
        'alt_phone.regex'     => 'Alt phone must be 8-15 digits and may start with +.',
        'alt_phone.different' => 'Alt phone must be different from the phone number.',
        'opening_balance.regex' => 'Enter a positive amount with up to 2 decimals.',
        'credit_limit.regex'    => 'Enter a positive amount with up to 2 decimals.',
    ]);
}

    private function fill(Party $party, Request $request): void
    {
        if ($request->input('type') === 'Investor') {
            $party->name                 = $request->name;
            $party->email                = $request->email;
            $party->phone                = $request->phone;
            $party->alt_phone            = $request->alt_phone;
            $party->address              = $request->address;
            $party->nid                  = $request->nid ?: null;
            $party->share_percent        = $request->share_percent !== null && $request->share_percent !== '' ? round((float) $request->share_percent, 2) : null;
            $party->opening_capital      = $request->opening_capital ?: 0;
            $party->opening_balance_date = $request->opening_balance_date ?: null;   // the opening capital's date
            $party->opening_balance      = 0;   // investors have no dues
            $party->opening_balance_type = 'Due';
            $party->credit_limit         = 0;
            $party->status               = $request->status;

            return;
        }

        $party->name                 = $request->name;
        $party->email                = $request->email;
        $party->phone                = $request->phone;
        $party->alt_phone            = $request->alt_phone;
        $party->address              = $request->address;
        $party->opening_balance      = $request->opening_balance ?: 0;
        $party->opening_balance_type = $request->opening_balance_type;
        $party->opening_balance_date = $request->opening_balance_date ?: null;
        $party->credit_limit         = $request->credit_limit ?: 0;
        $party->status               = $request->status;
    }

    private function generateCode(string $type): string
    {
        $next = (Party::lockForUpdate()->max('id') ?? 0) + 1;

        return $this->prefixes[$type] . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
