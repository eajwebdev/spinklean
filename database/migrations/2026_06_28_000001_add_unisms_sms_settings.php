<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'unisms_sender_id')) {
                $table->string('unisms_sender_id')->nullable()->after('sms_api_key');
            }
        });

        DB::table('system_settings')
            ->whereNull('sms_provider')
            ->orWhere('sms_provider', '')
            ->orWhereIn('sms_provider', ['semaphore', 'twilio'])
            ->update(['sms_provider' => 'unisms']);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('sms_provider', 'unisms')
            ->update(['sms_provider' => 'semaphore']);

        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'unisms_sender_id')) {
                $table->dropColumn('unisms_sender_id');
            }
        });
    }
};
