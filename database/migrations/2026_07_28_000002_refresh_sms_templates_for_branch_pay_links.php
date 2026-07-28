<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $defaults = SystemSetting::defaultSmsTemplates();

        foreach ($this->priorDefaultMarkers() as $column => $markers) {
            if (! Schema::hasColumn('system_settings', $column) || ! isset($defaults[$column])) {
                continue;
            }

            // Refresh rows that still hold an app-generated default, plus empty
            // ones. Custom templates typed by an admin match none of these
            // markers and are left untouched.
            DB::table('system_settings')
                ->where(function ($query) use ($column, $markers) {
                    $query->whereNull($column)->orWhere($column, '');

                    foreach ($markers as $marker) {
                        $query->orWhere($column, 'like', $marker);
                    }
                })
                ->update([$column => $defaults[$column]]);
        }
    }

    public function down(): void
    {
        // The prior template text cannot be reconstructed precisely, so this
        // migration is not reversible. The refreshed defaults remain in place.
    }

    /**
     * Distinctive fragments unique to each column's previous seeded default,
     * so only un-customized rows are rewritten.
     */
    private function priorDefaultMarkers(): array
    {
        return [
            'sms_template_order_received' => ['%Please keep this order number. Present it when claiming your laundry.%'],
            'sms_template_delivery_received' => ['%picked up your laundry for Spin Klean%'],
            'sms_template_ready_for_pickup' => ['%present your order number during pick up%'],
            'sms_template_ready_for_delivery' => ['%Your laundry is READY for delivery. Order #%'],
        ];
    }
};
