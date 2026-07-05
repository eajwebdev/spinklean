<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('branches', 'branch_type')) {
            DB::table('branches')
                ->whereNull('branch_type')
                ->orWhere('branch_type', '')
                ->update(['branch_type' => 'full_service']);
        }

        if (Schema::hasColumn('job_orders', 'processing_branch_id')) {
            DB::table('job_orders')
                ->whereNull('processing_branch_id')
                ->update(['processing_branch_id' => DB::raw('branch_id')]);
        }
    }

    public function down(): void
    {
        // Data backfill only; no destructive rollback needed.
    }
};
