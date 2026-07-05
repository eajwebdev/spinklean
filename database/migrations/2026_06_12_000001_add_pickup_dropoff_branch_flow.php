<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'branch_type')) {
                $table->string('branch_type')->default('full_service')->after('contact_number');
            }
        });

        Schema::table('job_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('job_orders', 'processing_branch_id')) {
                $table->foreignId('processing_branch_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            if (Schema::hasColumn('job_orders', 'processing_branch_id')) {
                $table->dropConstrainedForeignId('processing_branch_id');
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'branch_type')) {
                $table->dropColumn('branch_type');
            }
        });
    }
};
