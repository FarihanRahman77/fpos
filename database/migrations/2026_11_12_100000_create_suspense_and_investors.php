<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\SuspenseController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Investors (a party type: the owners of the business) and the Suspense module (advances / imprest to investors and
 * employees, investor capital in / out): parties.type gets 'Investor' plus NID, share % and opening capital;
 * suspense_transactions (branch_id + BEFORE INSERT trigger like the create_branches migration); the "Suspense Account"
 * Current Asset ledger (Owner's Capital / Drawings when missing); the 'investors' and 'suspense' permissions, granted to
 * the roles that already hold the related modules so nobody loses access.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE parties MODIFY `type` ENUM('Customer','Walk-In Customer','Supplier','Investor') NOT NULL");
        Schema::table('parties', function (Blueprint $table) {
            $table->string('nid', 50)->nullable()->after('address');
            $table->decimal('share_percent', 5, 2)->nullable()->after('credit_limit');
            $table->decimal('opening_capital', 15, 2)->default(0)->after('share_percent');
        });

        Schema::create('suspense_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->string('voucher_no', 30)->unique();
            $table->date('txn_date');
            $table->enum('person_type', ['Investor', 'Employee']);
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->enum('txn_type', ['Paid', 'Withdraw', 'Return', 'Adjustment', 'Deposit', 'Investment', 'Capital Withdrawal']);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('account_id')->nullable()->comment('cash / bank ledger (money-moving types)');
            $table->unsignedBigInteger('adjust_account_id')->nullable()->comment('Adjustment: expense / drawings ledger; Capital Withdrawal: equity ledger');
            $table->unsignedBigInteger('dr_account_id')->comment('ledger debited');
            $table->unsignedBigInteger('cr_account_id')->comment('ledger credited');
            $table->string('purpose', 255)->nullable();
            $table->string('reference', 100)->nullable();
            $table->enum('deleted', ['Yes', 'No'])->default('No');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->dateTime('deleted_datetime')->nullable();
            $table->timestamps();

            $table->index('branch_id');
            $table->index(['person_type', 'party_id']);
            $table->index(['person_type', 'employee_id']);
            $table->index('txn_date');
        });

        DB::unprepared('DROP TRIGGER IF EXISTS trg_suspense_transactions_branch');
        DB::unprepared("CREATE TRIGGER trg_suspense_transactions_branch BEFORE INSERT ON `suspense_transactions` FOR EACH ROW
            BEGIN
                IF NEW.branch_id IS NULL THEN
                    SET NEW.branch_id = COALESCE(@fpos_branch, (SELECT id FROM branches WHERE is_main = 'Yes' ORDER BY id LIMIT 1));
                END IF;
            END");

        // The ledgers the module posts to (only when the chart has its groups, i.e. not on an empty database).
        if (DB::table('chart_of_accounts')->where('layer_level', 2)->exists()) {
            SuspenseController::ledgers(true);
        }

        // Permissions: investors follow Customers & suppliers; suspense follows payments / expenses.
        PermissionController::syncPermissions();
        $perm = DB::table('permissions')->where('deleted', 'No')->pluck('id', 'slug');
        $grants = [
            'investors.view'   => ['parties.view'],
            'investors.create' => ['parties.create'],
            'investors.edit'   => ['parties.edit'],
            'investors.delete' => ['parties.delete'],
            'suspense.view'    => ['payments.view', 'expenses.view', 'transfers.view'],
            'suspense.create'  => ['payments.create', 'expenses.create', 'transfers.create'],
            'suspense.edit'    => ['payments.create', 'expenses.create', 'transfers.create'],
            'suspense.delete'  => ['payments.delete', 'expenses.delete', 'transfers.delete'],
        ];
        foreach ($grants as $new => $from) {
            if (!isset($perm[$new])) {
                continue;
            }
            $fromIds = collect($from)->map(fn ($s) => $perm[$s] ?? null)->filter()->values()->all();
            $roles = DB::table('role_permissions')->whereIn('permission_id', $fromIds ?: [0])->distinct()->pluck('role_id');
            foreach ($roles as $roleId) {
                if (!DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $perm[$new])->exists()) {
                    DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $perm[$new], 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where(fn ($q) => $q->where('slug', 'like', 'investors.%')->orWhere('slug', 'like', 'suspense.%'))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        DB::unprepared('DROP TRIGGER IF EXISTS trg_suspense_transactions_branch');
        Schema::dropIfExists('suspense_transactions');

        DB::table('parties')->where('type', 'Investor')->update(['deleted' => 'Yes']);
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['nid', 'share_percent', 'opening_capital']);
        });
        DB::statement("UPDATE parties SET type = 'Customer' WHERE type = 'Investor'");
        DB::statement("ALTER TABLE parties MODIFY `type` ENUM('Customer','Walk-In Customer','Supplier') NOT NULL");
    }
};
