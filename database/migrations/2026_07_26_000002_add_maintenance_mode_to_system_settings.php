<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'maintenance_mode')) {
                $table->boolean('maintenance_mode')->default(false)->after('is_completed');
            }
            if (! Schema::hasColumn('system_settings', 'maintenance_message')) {
                $table->text('maintenance_message')->nullable()->after('maintenance_mode');
            }
            if (! Schema::hasColumn('system_settings', 'maintenance_started_at')) {
                $table->timestamp('maintenance_started_at')->nullable()->after('maintenance_message');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        Schema::table('system_settings', function (Blueprint $table) {
            foreach (['maintenance_mode', 'maintenance_message', 'maintenance_started_at'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
