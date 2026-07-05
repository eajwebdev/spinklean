<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'twilio_account_sid')) {
                $table->string('twilio_account_sid')->nullable()->after('sms_api_key');
            }

            if (! Schema::hasColumn('system_settings', 'twilio_auth_token')) {
                $table->text('twilio_auth_token')->nullable()->after('twilio_account_sid');
            }

            if (! Schema::hasColumn('system_settings', 'twilio_from_number')) {
                $table->string('twilio_from_number')->nullable()->after('twilio_auth_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (['twilio_from_number', 'twilio_auth_token', 'twilio_account_sid'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
