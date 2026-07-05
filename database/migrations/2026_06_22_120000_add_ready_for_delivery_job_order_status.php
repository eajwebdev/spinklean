<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'washing',
                'drying',
                'folding',
                'ready_for_pickup',
                'ready_for_delivery',
                'completed',
                'cancelled',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'washing',
                'drying',
                'folding',
                'ready_for_pickup',
                'completed',
                'cancelled',
            ])->default('pending')->change();
        });
    }
};
