<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock tools: transfers between branches, stock counts / adjustments, batches with expiry.
 * Stock rows they create carry an explicit branch_id (a transfer touches two branches).
 */
return new class extends Migration
{
    private function audit(Blueprint $table): void
    {
        $table->enum('deleted', ['Yes', 'No'])->default('No');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->unsignedBigInteger('deleted_by')->nullable();
        $table->dateTime('created_date')->nullable();
        $table->dateTime('updated_date')->nullable();
        $table->dateTime('deleted_datetime')->nullable();
        $table->timestamps();
    }

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('track_batch', ['Yes', 'No'])->default('No')->after('stock_alert');
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->string('batch_no', 60);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'product_variant_id', 'batch_no']);
            $table->index('expiry_date');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('product_variant_id')->constrained('product_batches');
        });
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('product_variant_id')->constrained('product_batches');
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no', 20)->unique();
            $table->foreignId('from_branch_id')->constrained('branches');
            $table->foreignId('to_branch_id')->constrained('branches');
            $table->date('transfer_date');
            $table->date('received_date')->nullable();
            $table->enum('transfer_status', ['In Transit', 'Received', 'Cancelled'])->default('In Transit');
            $table->decimal('total_qty', 18, 3)->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $this->audit($table);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('batch_id')->nullable()->constrained('product_batches');
            $table->decimal('qty', 18, 3);
            $table->decimal('received_qty', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 20)->unique();
            $table->foreignId('branch_id')->constrained('branches');
            $table->date('adjustment_date');
            $table->enum('adjustment_type', ['Stock Count', 'Damage', 'Expired', 'Lost', 'Found', 'Correction']);
            $table->enum('adjustment_status', ['Draft', 'Posted', 'Cancelled'])->default('Draft');
            $table->decimal('total_in', 18, 3)->default(0);
            $table->decimal('total_out', 18, 3)->default(0);
            $table->decimal('value_change', 18, 2)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->dateTime('posted_at')->nullable();
            $this->audit($table);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('batch_id')->nullable()->constrained('product_batches');
            $table->decimal('system_qty', 18, 3)->default(0);
            $table->decimal('counted_qty', 18, 3)->nullable();
            $table->decimal('difference', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
        Schema::dropIfExists('product_batches');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('track_batch');
        });
    }
};
