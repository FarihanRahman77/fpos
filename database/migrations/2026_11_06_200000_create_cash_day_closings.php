<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily cash closing of a branch: the day's cash ledger is saved with its opening (= the previous saved
     * closing) and closing amount, which becomes the next day's opening.
     */
    public function up(): void
    {
        Schema::create('cash_day_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->date('closing_date');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('today_amount', 18, 2)->default(0);
            $table->decimal('closing_amount', 18, 2)->default(0);
            $table->json('details')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->unsignedBigInteger('saved_by')->nullable();
            $table->dateTime('saved_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'closing_date']);
        });

        DB::unprepared('DROP TRIGGER IF EXISTS trg_cash_day_closings_branch');
        DB::unprepared("CREATE TRIGGER trg_cash_day_closings_branch BEFORE INSERT ON `cash_day_closings` FOR EACH ROW
            BEGIN
                IF NEW.branch_id IS NULL THEN
                    SET NEW.branch_id = COALESCE(@fpos_branch, (SELECT id FROM branches WHERE is_main = 'Yes' ORDER BY id LIMIT 1));
                END IF;
            END");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_cash_day_closings_branch');
        Schema::dropIfExists('cash_day_closings');
    }
};
