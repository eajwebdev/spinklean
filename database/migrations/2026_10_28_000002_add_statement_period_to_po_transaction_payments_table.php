<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Statement of Account a payment was made for (nullable: older payments have none).
        Schema::table('po_transaction_payments', function (Blueprint $table) {
            $table->date('statement_from')->nullable()->after('remarks');
            $table->date('statement_to')->nullable()->after('statement_from');
        });
    }

    public function down(): void
    {
        Schema::table('po_transaction_payments', function (Blueprint $table) {
            $table->dropColumn(['statement_from', 'statement_to']);
        });
    }
};
