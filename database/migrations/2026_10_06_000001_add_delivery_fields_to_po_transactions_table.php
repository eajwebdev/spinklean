<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive and nullable only: existing PO transactions keep all their data, with blank delivery fields.
        Schema::table('po_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('po_transactions', 'date_delivered')) {
                $table->date('date_delivered')->nullable()->after('transaction_date');
            }

            if (! Schema::hasColumn('po_transactions', 'dr_number')) {
                $table->string('dr_number', 100)->nullable()->after('date_delivered');
            }
        });
    }

    public function down(): void
    {
        Schema::table('po_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('po_transactions', 'dr_number')) {
                $table->dropColumn('dr_number');
            }

            if (Schema::hasColumn('po_transactions', 'date_delivered')) {
                $table->dropColumn('date_delivered');
            }
        });
    }
};
