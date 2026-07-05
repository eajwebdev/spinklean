<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach ($this->columns() as $column) {
                if (! Schema::hasColumn('system_settings', $column)) {
                    $table->text($column)->nullable()->after('sms_enabled');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (array_reverse($this->columns()) as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function columns(): array
    {
        return [
            'sms_template_order_received',
            'sms_template_delivery_received',
            'sms_template_ready_for_pickup',
            'sms_template_ready_for_delivery',
            'sms_template_completed',
        ];
    }
};
