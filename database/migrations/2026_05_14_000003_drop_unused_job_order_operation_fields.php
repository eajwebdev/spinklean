<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('job_orders', 'load_count') ? 'load_count' : null,
                Schema::hasColumn('job_orders', 'drying_cycles') ? 'drying_cycles' : null,
                Schema::hasColumn('job_orders', 'drying_extension_minutes') ? 'drying_extension_minutes' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('job_orders', 'load_count')) {
                $table->unsignedInteger('load_count')->default(0)->after('status');
            }

            if (! Schema::hasColumn('job_orders', 'drying_cycles')) {
                $table->unsignedInteger('drying_cycles')->default(0)->after('load_count');
            }

            if (! Schema::hasColumn('job_orders', 'drying_extension_minutes')) {
                $table->unsignedInteger('drying_extension_minutes')->default(0)->after('drying_cycles');
            }
        });
    }
};
