<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();

            $table->string('return_no', 100)->unique();

            $table->unsignedBigInteger('sale_id');

            $table->unsignedBigInteger('customer_id')->nullable();

            $table->enum('sale_type',['Wholesale','Walk-In']);

            $table->dateTime('datetime');

            $table->decimal('subtotal', 15, 2)->default(0);

            $table->decimal('grand_total', 15, 2)->default(0);

            $table->longtext('remarks')->nullable();
            $table->enum('deleted', ['Yes','No'])->default('No');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->enum('status', ['Active','Inactive'])->default('Active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_date')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('updated_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
