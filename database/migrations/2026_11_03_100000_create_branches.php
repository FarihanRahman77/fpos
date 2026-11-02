<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branches. Every transactional table gets branch_id; a BEFORE INSERT trigger fills it from the
 * connection variable @fpos_branch (set per request by the SetBranch middleware) or the main branch,
 * so existing insert code needs no change. Existing rows go to the main branch.
 */
return new class extends Migration
{
    public const TABLES = [
        'sales', 'sale_returns', 'sale_orders', 'sale_quotations', 'purchases', 'purchase_returns',
        'payments', 'expenses', 'bills', 'bill_payments', 'journals', 'internal_amount_transfers', 'stocks',
        'employees', 'payroll_runs', 'salary_payments', 'employee_loans',
    ];

    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 120)->nullable();
            $table->enum('is_main', ['Yes', 'No'])->default('No');
            $table->enum('deleted', ['Yes', 'No'])->default('No');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->dateTime('deleted_datetime')->nullable();
            $table->timestamps();
        });

        $company = DB::table('settings')->first();
        $mainId = DB::table('branches')->insertGetId([
            'name' => 'Main Branch', 'code' => 'MAIN', 'address' => $company->address ?? null, 'phone' => $company->phone ?? null,
            'is_main' => 'Yes', 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('role_id')->constrained('branches');
            $table->enum('all_branches', ['Yes', 'No'])->default('No')->after('branch_id');
        });
        // Existing users keep seeing everything.
        DB::table('users')->update(['branch_id' => $mainId, 'all_branches' => 'Yes']);

        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('user_id')->constrained('users');
            $table->unique(['branch_id', 'user_id']);
        });

        // Cash / bank ledgers may belong to one branch (null = shared).
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('parent_id')->constrained('branches');
        });

        foreach (self::TABLES as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches');
                $table->index('branch_id');
            });
            DB::table($t)->whereNull('branch_id')->update(['branch_id' => $mainId]);
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$t}_branch");
            DB::unprepared("CREATE TRIGGER trg_{$t}_branch BEFORE INSERT ON `{$t}` FOR EACH ROW
                BEGIN
                    IF NEW.branch_id IS NULL THEN
                        SET NEW.branch_id = COALESCE(@fpos_branch, (SELECT id FROM branches WHERE is_main = 'Yes' ORDER BY id LIMIT 1));
                    END IF;
                END");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$t}_branch");
            Schema::table($t, function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
        Schema::dropIfExists('branch_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'all_branches']);
        });
        Schema::dropIfExists('branches');
    }
};
