<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('laundry_services', function (Blueprint $table) {
            $table->foreignId('service_category_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('laundry_service_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('laundry_services', function (Blueprint $table) {
            $table->dropForeign(['service_category_id']);
            $table->dropColumn('service_category_id');
        });
    }
};
