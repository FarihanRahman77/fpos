<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cash closings (Z-report: what each cashier counted in the drawer at day end)
 * and monthly expense budgets (Budget vs Actual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->date('closing_date');
            $table->foreignId('cashier_id')->constrained('users');
            $table->decimal('expected_cash', 18, 2)->default(0);
            $table->decimal('counted_cash', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->string('remarks', 255)->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'closing_date', 'cashier_id']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->date('month');                                   // first day of the month
            $table->foreignId('account_id')->constrained('chart_of_accounts');   // expense ledger (layer 3)
            $table->decimal('amount', 18, 2)->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'month', 'account_id']);
        });

        foreach (['cash_closings', 'budgets'] as $t) {
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
        foreach (['cash_closings', 'budgets'] as $t) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$t}_branch");
        }
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('cash_closings');
    }
};
