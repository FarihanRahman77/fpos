<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------- Journal ---------------- */
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_no', 30)->unique();
            $table->date('journal_date');
            $table->string('reference_no', 100)->nullable();
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->longText('remarks')->nullable();
            $this->auditColumns($table);
        });

        Schema::create('journal_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journals');
            // 2nd layer group and 3rd layer ledger of chart_of_accounts.
            $table->foreignId('account_group_id')->constrained('chart_of_accounts');
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->string('description', 255)->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $this->auditColumns($table);
        });

        /* ---------------- Bill ---------------- */
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_no', 30)->unique();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->foreignId('party_id')->constrained('parties');   // Supplier
            $table->string('reference_no', 100)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('due_amount', 18, 2)->default(0);
            $table->enum('payment_status', ['Unpaid', 'Partial', 'Paid'])->default('Unpaid');
            $table->longText('remarks')->nullable();
            $this->auditColumns($table);
        });

        Schema::create('bill_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills');
            // Expense type group (layer 2) and ledger (layer 3).
            $table->foreignId('account_group_id')->constrained('chart_of_accounts');
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->string('description', 255)->nullable();
            $table->decimal('qty', 18, 2)->default(1);
            $table->decimal('rate', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $this->auditColumns($table);
        });

        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 30)->unique();
            $table->foreignId('bill_id')->constrained('bills');
            $table->foreignId('party_id')->constrained('parties');
            $table->date('payment_date');
            // Liquid Asset group (layer 2) and account (layer 3) the money is paid from.
            $table->foreignId('payment_group_id')->constrained('chart_of_accounts');
            $table->foreignId('payment_account_id')->constrained('chart_of_accounts');
            $table->decimal('amount', 18, 2);
            $table->string('reference_no', 100)->nullable();
            $table->longText('remarks')->nullable();
            $this->auditColumns($table);
        });

        /* ---------------- Amount transfer ---------------- */
        Schema::create('internal_amount_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no', 30)->unique();
            $table->date('transfer_date');
            $table->foreignId('from_group_id')->constrained('chart_of_accounts');
            $table->foreignId('from_account_id')->constrained('chart_of_accounts');
            $table->foreignId('to_group_id')->constrained('chart_of_accounts');
            $table->foreignId('to_account_id')->constrained('chart_of_accounts');
            $table->decimal('amount', 18, 2);
            $table->string('reference_no', 100)->nullable();
            $table->longText('remarks')->nullable();
            $this->auditColumns($table);
        });

        /* ---------------- Supplier ledger links ---------------- */
        DB::statement("ALTER TABLE `payments` MODIFY `payment_type` ENUM('Walk-In','Wholesale','Purchase','Expense','Walk-In Sale Return','Wholesale Return','Sale Order','Payment','Payment Receive','Discount','Purchase Payment','Wholesale Payment Receive','Walk-In Payment Receive','Sale Order Payment Receive','Bill','Bill Payment') NULL DEFAULT NULL");

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bill_payment_id')->nullable()->after('bill_id')->constrained('bill_payments');
        });

        if (!$this->hasForeignKey('payments', 'payments_bill_id_foreign')) {
            DB::statement('ALTER TABLE `payments` MODIFY `bill_id` BIGINT UNSIGNED NULL DEFAULT NULL');
            DB::statement('ALTER TABLE `payments` ADD CONSTRAINT `payments_bill_id_foreign` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`)');
        }

        /* ---------------- Running balances start at 0, never NULL ---------------- */
        // NULL + amount stays NULL, so balances on existing accounts were never moving.
        DB::table('chart_of_accounts')->whereNull('current_balance')->update(['current_balance' => 0]);
        DB::statement('ALTER TABLE `chart_of_accounts` MODIFY `current_balance` DECIMAL(18,2) NOT NULL DEFAULT 0');

        /* ---------------- Accounts Payable (system account) ---------------- */
        $this->ensurePayableAccount();
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['bill_payment_id']);
            $table->dropColumn('bill_payment_id');
            $table->dropForeign(['bill_id']);
        });

        DB::statement("ALTER TABLE `payments` MODIFY `payment_type` ENUM('Walk-In','Wholesale','Purchase','Expense','Walk-In Sale Return','Wholesale Return','Sale Order','Payment','Payment Receive','Discount','Purchase Payment','Wholesale Payment Receive','Walk-In Payment Receive','Sale Order Payment Receive') NULL DEFAULT NULL");

        Schema::dropIfExists('internal_amount_transfers');
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bill_details');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('journal_details');
        Schema::dropIfExists('journals');
        // The Accounts Payable account is kept: journals may have used it.
    }

    /** Same audit columns as the rest of the schema. */
    private function auditColumns(Blueprint $table): void
    {
        $table->enum('deleted', ['Yes', 'No'])->default('No');
        $table->enum('status', ['Active', 'Inactive'])->default('Active');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->unsignedBigInteger('deleted_by')->nullable();
        $table->dateTime('created_date')->nullable();
        $table->dateTime('updated_date')->nullable();
        $table->dateTime('deleted_datetime')->nullable();
        $table->timestamps();
    }

    /**
     * Bills are credited to "Accounts Payable" (layer 3) under a "Current Liability"
     * group (layer 2) of the Liabilities root. Created once, flagged is_system.
     */
    private function ensurePayableAccount(): void
    {
        if (DB::table('chart_of_accounts')->where('slug', 'accounts-payable')->where('layer_level', 3)->exists()) {
            return;
        }

        $root = DB::table('chart_of_accounts')
            ->where('layer_level', 1)->where('account_type', 'Liability')->orderBy('id')->first();

        if (!$root) {
            throw new RuntimeException('The Liabilities root account is missing from chart_of_accounts.');
        }

        $base = [
            'account_type'    => 'Liability',
            'balance'         => 'Credit',
            'current_balance' => 0,
            'opening_balance' => 0,
            'is_system'       => 1,
            'deleted'         => 'No',
            'status'          => 'Active',
            'created_date'    => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        $group = DB::table('chart_of_accounts')
            ->where('parent_id', $root->id)->where('layer_level', 2)->where('slug', 'current-liability')->first();

        $groupId = $group->id ?? DB::table('chart_of_accounts')->insertGetId($base + [
            // Layer 2 codes step by 100000 under the root (e.g. 20100000).
            'account_code' => (string) ((int) (DB::table('chart_of_accounts')->where('parent_id', $root->id)->max('account_code') ?: $root->account_code) + 100000),
            'account_name' => 'Current Liability',
            'slug'         => 'current-liability',
            'layer_level'  => 2,
            'parent_id'    => $root->id,
        ]);

        $groupCode = DB::table('chart_of_accounts')->where('id', $groupId)->value('account_code');

        DB::table('chart_of_accounts')->insert($base + [
            // Layer 3 codes step by 1000 under the group (e.g. 20101000).
            'account_code' => (string) ((int) (DB::table('chart_of_accounts')->where('parent_id', $groupId)->max('account_code') ?: $groupCode) + 1000),
            'account_name' => 'Accounts Payable',
            'slug'         => 'accounts-payable',
            'layer_level'  => 3,
            'parent_id'    => $groupId,
        ]);
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $name)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
