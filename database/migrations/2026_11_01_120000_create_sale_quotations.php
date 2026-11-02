<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no', 20)->unique();
            $table->foreignId('customer_id')->constrained('parties');
            $table->string('title', 200)->nullable();
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('revision_no')->default(1);   // current revision (every re-price adds one)
            $table->enum('quotation_status', ['Draft', 'Sent', 'Accepted', 'Rejected', 'Expired'])->default('Draft');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_percent', 6, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->enum('deleted', ['Yes', 'No'])->default('No');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->dateTime('updated_date')->nullable();
            $table->dateTime('deleted_datetime')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'quotation_date']);
        });

        // Lines of the current revision.
        Schema::create('sale_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_quotation_id')->constrained('sale_quotations');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
            $table->string('item_name', 255);                       // snapshot shown to the customer
            $table->string('description', 500)->nullable();
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount', 18, 2)->default(0);          // per line, amount
            $table->decimal('line_total', 18, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Frozen copy of every revision: the re-pricing history.
        Schema::create('sale_quotation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_quotation_id')->constrained('sale_quotations');
            $table->unsignedInteger('revision_no');
            $table->string('reason', 255)->nullable();
            $table->json('items');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_percent', 6, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['sale_quotation_id', 'revision_no']);
        });

        // Activity of a quotation; customer_id makes the per-customer quotation log.
        Schema::create('sale_quotation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_quotation_id')->constrained('sale_quotations');
            $table->foreignId('customer_id')->constrained('parties');
            $table->string('action', 30);   // created, repriced, emailed, shared, downloaded, status, duplicated
            $table->string('message', 500);
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_quotation_logs');
        Schema::dropIfExists('sale_quotation_revisions');
        Schema::dropIfExists('sale_quotation_items');
        Schema::dropIfExists('sale_quotations');
    }
};
