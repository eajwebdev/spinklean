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
            if (! Schema::hasColumn('job_orders', 'tag_number')) {
                $table->string('tag_number', 100)->nullable()->index()->after('job_order_number');
            }

            if (! Schema::hasColumn('job_orders', 'returned_received_at')) {
                $table->timestamp('returned_received_at')->nullable()->after('returned_to_branch_at');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `job_orders` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->string('status', 50)->default('pending')->change();
            });
        }

        if (! Schema::hasTable('job_order_transfers')) {
            Schema::create('job_order_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_order_id')->constrained('job_orders')->cascadeOnDelete();
                $table->string('job_order_number');
                $table->string('tag_number')->nullable()->index();
                $table->foreignId('origin_branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('destination_branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('transfer_type', 20)->default('outbound');
                $table->string('transfer_status', 20)->default('pending');
                $table->timestamp('transferred_at')->useCurrent();
                $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('received_at')->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['destination_branch_id', 'transfer_status'], 'jot_dest_status_idx');
                $table->index(['origin_branch_id', 'transfer_status'], 'jot_origin_status_idx');
                $table->index(['job_order_id', 'transfer_type', 'transfer_status'], 'jot_order_type_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_transfers');

        Schema::table('job_orders', function (Blueprint $table) {
            if (Schema::hasColumn('job_orders', 'returned_received_at')) {
                $table->dropColumn('returned_received_at');
            }

            if (Schema::hasColumn('job_orders', 'tag_number')) {
                $table->dropColumn('tag_number');
            }
        });
    }
};
