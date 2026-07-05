<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'machine_count')) {
                $table->unsignedInteger('machine_count')->default(0)->after('attendance_radius_meters');
            }
        });

        Schema::table('cycle_records', function (Blueprint $table) {
            if (! Schema::hasColumn('cycle_records', 'machine_number')) {
                $table->unsignedInteger('machine_number')->nullable()->after('cycle_type');
            }
        });

        Schema::table('branch_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('branch_expenses', 'paid_from')) {
                $table->string('paid_from')->default('store_cash')->after('payment_method');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('cash', 'gcash', 'bank', 'credit', 'po', 'monthly_billing') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('branch_expenses', 'paid_from')) {
            Schema::table('branch_expenses', function (Blueprint $table) {
                $table->dropColumn('paid_from');
            });
        }

        if (Schema::hasColumn('cycle_records', 'machine_number')) {
            Schema::table('cycle_records', function (Blueprint $table) {
                $table->dropColumn('machine_number');
            });
        }

        if (Schema::hasColumn('branches', 'machine_count')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('machine_count');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('cash', 'credit', 'po', 'monthly_billing') NOT NULL");
        }
    }
};
