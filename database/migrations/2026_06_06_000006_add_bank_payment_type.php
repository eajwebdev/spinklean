<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('cash', 'gcash', 'bank', 'credit', 'po', 'monthly_billing') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_type ENUM('cash', 'gcash', 'credit', 'po', 'monthly_billing') NOT NULL");
        }
    }
};
