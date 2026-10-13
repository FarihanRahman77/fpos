<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Purchase-return stock rows were saved without a reference type
     * (an isset() check on a new model was always false). The stock ledger
     * report groups movements by this column, so fill it in.
     */
    public function up(): void
    {
        DB::table('stocks')
            ->whereNotNull('purchase_return_id')
            ->whereNull('reference_type')
            ->update(['reference_type' => 'Purchase Return', 'reference_id' => DB::raw('purchase_return_id')]);
    }

    public function down(): void
    {
        // Data fix only; nothing to undo.
    }
};
