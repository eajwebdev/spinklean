<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('job_orders', 'current_branch_id')) {
                $table->foreignId('current_branch_id')
                    ->nullable()
                    ->after('processing_branch_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('job_orders', 'release_branch_id')) {
                $table->foreignId('release_branch_id')
                    ->nullable()
                    ->after('current_branch_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('job_orders', 'production_completed_at')) {
                $table->timestamp('production_completed_at')->nullable()->after('completed_at');
            }

            if (! Schema::hasColumn('job_orders', 'returned_to_branch_at')) {
                $table->timestamp('returned_to_branch_at')->nullable()->after('production_completed_at');
            }

            if (! Schema::hasColumn('job_orders', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('returned_to_branch_at');
            }
        });

        if (Schema::hasColumn('job_orders', 'current_branch_id')) {
            DB::table('job_orders')
                ->whereNull('current_branch_id')
                ->update(['current_branch_id' => DB::raw('branch_id')]);
        }

        if (Schema::hasColumn('job_orders', 'release_branch_id')) {
            DB::table('job_orders')
                ->whereNull('release_branch_id')
                ->update(['release_branch_id' => DB::raw('branch_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            foreach ([
                'released_at',
                'returned_to_branch_at',
                'production_completed_at',
            ] as $column) {
                if (Schema::hasColumn('job_orders', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('job_orders', 'release_branch_id')) {
                $table->dropConstrainedForeignId('release_branch_id');
            }

            if (Schema::hasColumn('job_orders', 'current_branch_id')) {
                $table->dropConstrainedForeignId('current_branch_id');
            }
        });
    }
};
