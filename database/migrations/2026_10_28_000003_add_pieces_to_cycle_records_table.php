<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batch size of a PO wash cycle, so large PO orders can be washed over several days.
        Schema::table('cycle_records', function (Blueprint $table) {
            $table->decimal('pieces', 10, 2)->nullable()->after('cycle_number');
        });
    }

    public function down(): void
    {
        Schema::table('cycle_records', function (Blueprint $table) {
            $table->dropColumn('pieces');
        });
    }
};
