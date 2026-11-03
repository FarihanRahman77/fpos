<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The supplier's own invoice / reference number on a purchase (our number stays in invoice_no). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchases', 'supplier_invoice_no')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->string('supplier_invoice_no', 100)->nullable()->after('invoice_no');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchases', 'supplier_invoice_no')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn('supplier_invoice_no');
            });
        }
    }
};
