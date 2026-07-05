<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('branches', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('branches', 'attendance_radius_meters')) {
                $table->unsignedInteger('attendance_radius_meters')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            foreach (['latitude', 'longitude', 'attendance_radius_meters'] as $column) {
                if (Schema::hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
