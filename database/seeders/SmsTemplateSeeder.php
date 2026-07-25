<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    /**
     * Seed the default short, UniSMS-compliant SMS templates including the payment link.
     */
    public function run(): void
    {
        $settings = SystemSetting::current();
        $defaults = SystemSetting::defaultSmsTemplates();

        $settings->update($defaults);
    }
}
