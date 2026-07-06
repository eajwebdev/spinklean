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

            // Refresh rows that still hold an app-generated default (the
            // original one-liners or the long branch templates), plus empty
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
        // migration is not reversible. The short defaults remain in place.
    }

    /**
     * Distinctive fragments unique to each column's previous seeded defaults.
     * Covers both the original one-line templates and the long branch
     * templates they replaced.
     */
    private function priorDefaultMarkers(): array
    {
        return [
            'sms_template_order_received' => ['%queued for processing%', '%Collect 10 stamps%'],
            'sms_template_delivery_received' => ['%queued for processing%', '%Collect 10 stamps%'],
            'sms_template_ready_for_pickup' => ['%is ready for pickup.%'],
            'sms_template_ready_for_delivery' => ['%is ready for delivery.%'],
            'sms_template_completed' => ['%has been completed%'],
        ];
    }
};
