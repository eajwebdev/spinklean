<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'job_orders_status_created_index');
            $table->index(['customer_id', 'status'], 'job_orders_customer_status_index');
            $table->index(['processing_branch_id', 'status'], 'job_orders_processing_status_index');
            $table->index(['current_branch_id', 'status'], 'job_orders_current_status_index');
            $table->index(['release_branch_id', 'status'], 'job_orders_release_status_index');
        });

        Schema::table('cycle_records', function (Blueprint $table) {
            $table->index(
                ['cycle_type', 'machine_number', 'ended_at'],
                'cycle_records_active_machine_index'
            );
            $table->index(
                ['started_at', 'cycle_type', 'machine_number', 'job_order_id'],
                'cycle_records_activity_index'
            );
            $table->index(
                ['job_order_id', 'started_at', 'id'],
                'cycle_records_order_history_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cycle_records', function (Blueprint $table) {
            $table->dropIndex('cycle_records_active_machine_index');
            $table->dropIndex('cycle_records_activity_index');
            $table->dropIndex('cycle_records_order_history_index');
        });

        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropIndex('job_orders_status_created_index');
            $table->dropIndex('job_orders_customer_status_index');
            $table->dropIndex('job_orders_processing_status_index');
            $table->dropIndex('job_orders_current_status_index');
            $table->dropIndex('job_orders_release_status_index');
        });
    }
};
