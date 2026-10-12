<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait AccountBalanceTrait
{
    /**
     * Applies (direction = 1) or reverses (direction = -1) a posting on
     * chart_of_accounts.current_balance. Call inside a DB transaction.
     *
     * $debits / $credits: [3rd layer account id => amount]
     *
     * An account whose normal balance is Debit goes up on debit and down on credit
     * (Credit-normal accounts are the opposite). The same movement is added to the
     * account's 2nd and 1st layer parents so group / root totals stay in step.
     */
    protected function postBalances(array $debits, array $credits, int $direction): void
    {
        $ledgerIds = array_values(array_unique(array_merge(array_keys($debits), array_keys($credits))));

        if (!$ledgerIds) {
            return;
        }

        $ledgers = DB::table('chart_of_accounts')->whereIn('id', $ledgerIds)
            ->get(['id', 'parent_id', 'balance'])->keyBy('id');

        $groups = DB::table('chart_of_accounts')
            ->whereIn('id', $ledgers->pluck('parent_id')->filter()->unique())
            ->get(['id', 'parent_id'])->keyBy('id');

        $totals = [];

        $collect = function (array $map, int $side) use (&$totals, $ledgers, $groups, $direction) {
            foreach ($map as $id => $amount) {
                $ledger = $ledgers->get($id);

                if (!$ledger) {
                    continue;
                }

                $normal = $ledger->balance === 'Credit' ? -1 : 1;
                $delta  = (float) $amount * $side * $normal * $direction;

                $chain = [$id, $ledger->parent_id, $groups->get($ledger->parent_id)->parent_id ?? null];

                foreach (array_filter($chain) as $accountId) {
                    $totals[$accountId] = ($totals[$accountId] ?? 0) + $delta;
                }
            }
        };

        $collect($debits, 1);     // debit  side
        $collect($credits, -1);   // credit side

        ksort($totals);           // fixed lock order avoids deadlocks

        DB::table('chart_of_accounts')->whereIn('id', array_keys($totals))
            ->orderBy('id')->lockForUpdate()->pluck('id');

        foreach ($totals as $accountId => $delta) {
            $delta = round($delta, 2);

            if (abs($delta) < 0.005) {
                continue;
            }

            // COALESCE: a NULL balance would otherwise stay NULL (NULL + x = NULL).
            DB::table('chart_of_accounts')->where('id', $accountId)->update([
                'current_balance' => DB::raw('COALESCE(current_balance, 0) + ' . number_format($delta, 2, '.', '')),
            ]);
        }
    }

    /** [account id => summed amount] from rows (objects or arrays). */
    protected function sumByColumn($rows, string $idColumn, string $amountColumn): array
    {
        $map = [];

        foreach ($rows as $row) {
            $row = (object) $row;
            $id  = (int) $row->{$idColumn};

            $map[$id] = round(($map[$id] ?? 0) + (float) $row->{$amountColumn}, 2);
        }

        return array_filter($map, fn ($amount) => abs($amount) >= 0.005);
    }

    /** Validation rule: an existing, non-deleted chart_of_accounts row of one layer (and optionally type / parent). */
    protected function coaExists(int $layer, ?string $type = null, $parentId = null)
    {
        return \Illuminate\Validation\Rule::exists('chart_of_accounts', 'id')->where(function ($q) use ($layer, $type, $parentId) {
            $q->where('layer_level', $layer)->where('deleted', 'No');

            if ($type !== null) {
                $q->where('account_type', $type);
            }

            if ($parentId !== null) {
                $q->where('parent_id', (int) $parentId);
            }
        });
    }

    /** Active 2nd layer accounts, optionally of one account type, with their root type. */
    protected function coaGroups(?string $type = null)
    {
        return DB::table('chart_of_accounts')
            ->where('layer_level', 2)
            ->when($type, fn ($q) => $q->where('account_type', $type))
            ->where('deleted', 'No')
            ->where('status', 'Active')
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name', 'account_type']);
    }
}
