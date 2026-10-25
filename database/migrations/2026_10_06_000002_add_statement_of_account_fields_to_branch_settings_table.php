<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'soa_tin' => 100,
        'soa_bank_name' => 191,
        'soa_account_name' => 191,
        'soa_account_number' => 100,
        'soa_email' => 191,
        'soa_viber' => 100,
    ];

    public function up(): void
    {
        // Additive and nullable only: existing branch settings are untouched.
        Schema::table('branch_settings', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $length) {
                if (! Schema::hasColumn('branch_settings', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $column) {
                if (Schema::hasColumn('branch_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
