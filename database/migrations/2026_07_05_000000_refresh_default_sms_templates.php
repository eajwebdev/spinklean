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

        foreach ($this->refreshableColumns() as $column => $previousDefault) {
            if (! Schema::hasColumn('system_settings', $column) || ! isset($defaults[$column])) {
                continue;
            }

            // Only touch rows that were never customized: empty values, or
            // values still holding the previous seeder default. Custom
            // templates typed by an admin are left untouched.
            DB::table('system_settings')
                ->where(function ($query) use ($column, $previousDefault) {
                    $query->whereNull($column)
                        ->orWhere($column, '')
                        ->orWhere($column, $previousDefault);
                })
                ->update([$column => $defaults[$column]]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $defaults = SystemSetting::defaultSmsTemplates();

        foreach ($this->refreshableColumns() as $column => $previousDefault) {
            if (! Schema::hasColumn('system_settings', $column) || ! isset($defaults[$column])) {
                continue;
            }

            DB::table('system_settings')
                ->where($column, $defaults[$column])
                ->update([$column => $previousDefault]);
        }
    }

    /**
     * Templates whose seeded default changed, mapped to their previous default
     * text so we can recognize (and safely overwrite) un-customized rows.
     */
    private function refreshableColumns(): array
    {
        return [
            'sms_template_order_received' => 'Hi {customer_name}, {store_name} has received your laundry order {job_order_number}. It is now recorded and queued for processing. We will notify you when it is ready.',
            'sms_template_delivery_received' => 'Hi {customer_name}, {store_name} has picked up and received your laundry order {job_order_number}. It is now recorded and queued for processing. We will notify you when it is ready.',
        ];
    }
};
