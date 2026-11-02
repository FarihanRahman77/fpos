<?php

namespace App\Traits;

use App\Models\Admin\Party;
use App\Models\Admin\Payment;

trait PartyDueTrait
{
    /**
     * Current due of a party: opening balance plus the ledger, signed by party type.
     * Always returns a number (0 for an unknown party) so callers can do math with it;
     * query errors are thrown and handled by the caller's try/catch.
     */
    public function partyDue($partyId): float
    {
        $party = Party::find($partyId);

        if (!$party) {
            return 0.0;
        }

        // Only this branch's ledger; the opening balance belongs to the main branch.
        $ledger = Payment::where('party_id', $partyId)
            ->where('branch_id', \App\Http\Controllers\Admin\BranchController::activeId())
            ->where('deleted', 'No')
            ->where('status', 'Active')
            // party_id also holds employee ids on employee expense rows
            ->where(fn ($q) => $q->whereNull('party_type')->orWhere('party_type', '<>', 'Employee'))
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        $opening = \App\Http\Controllers\Admin\BranchController::isMainBranch() ? (float) ($party->opening_balance ?? 0) : 0.0;
        if (($party->opening_balance_type ?? 'Due') === 'Advance') {
            $opening = -$opening; // an opening advance reduces what is owed
        }
        $debit = (float) $ledger->debit;
        $credit = (float) $ledger->credit;

        if ($party->type == 'Supplier') {
            return $opening + $debit - $credit;
        }

        if ($party->type == 'Customer' || $party->type == 'Walk-In Customer') {
            return $opening + $credit - $debit;
        }

        return $opening;
    }
}
