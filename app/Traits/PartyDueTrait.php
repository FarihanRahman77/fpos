<?php

namespace App\Traits;

use App\Models\Admin\Party;
use App\Models\Admin\Payment;
use Illuminate\Http\Request;
use Exception;

trait PartyDueTrait
{
    public function partyDue($partyId)
    {
        try {
            

           $party = Party::find($partyId);

            if (!$party) {
                return response()->json([
                    'status' => false,
                    'message' => 'Party not found.',
                    'due' => 0,
                ], 404);
            }

            $ledger = Payment::where('party_id', $partyId)
                ->where('deleted', 'No')
                ->where('status', 'Active')
                ->get();

            $sumDebit = 0;
            $sumCredit = 0;

            foreach ($ledger as $row) {
                $sumDebit += (float) $row->debit;
                $sumCredit += (float) $row->credit;
            }
            
           $opening = (float) ($party->opening_balance ?? 0);

            if ($party->type == 'Supplier') {

                $due = $opening + $sumDebit - $sumCredit;

            } elseif ( $party->type == 'Customer' || $party->type == 'Walk-In Customer') {

                $due = $opening + $sumCredit - $sumDebit;

            } else {

                $due = $opening;
            }

            return $due;

        } catch (Exception $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

