<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ironing cycles record the plastics used (the branch's existing "Plastic ..."
 * inventory items: Small, Big, Packaging). Each deduction is linked to its
 * cycle so removing the cycle can return the stock.
 *
 * No inventory items are created here: branches keep their real plastic items.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_movements', 'cycle_record_id')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->foreignId('cycle_record_id')->nullable()->after('user_id')
                    ->constrained('cycle_records')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_movements', 'cycle_record_id')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cycle_record_id');
            });
        }
    }
};
