<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'collected_branch_id')) {
                $table->foreignId('collected_branch_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 'settlement_status')) {
                $table->string('settlement_status')->default('local')->after('remarks');
            }
        });

        if (Schema::hasColumn('payments', 'collected_branch_id')) {
            DB::table('payments')
                ->whereNull('collected_branch_id')
                ->update(['collected_branch_id' => DB::raw('branch_id')]);
        }

        if (Schema::hasColumn('payments', 'settlement_status')) {
            DB::table('payments')
                ->update([
                    'settlement_status' => DB::raw("CASE WHEN collected_branch_id = branch_id THEN 'local' ELSE 'pending' END"),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'settlement_status')) {
                $table->dropColumn('settlement_status');
            }

            if (Schema::hasColumn('payments', 'collected_branch_id')) {
                $table->dropConstrainedForeignId('collected_branch_id');
            }
        });
    }
};
