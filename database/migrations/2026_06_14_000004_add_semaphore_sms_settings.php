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
            if (! Schema::hasColumn('system_settings', 'semaphore_sender_name')) {
                $table->string('semaphore_sender_name')->nullable()->after('sms_api_key');
            }
        });

        DB::table('system_settings')
            ->whereNull('sms_provider')
            ->orWhere('sms_provider', '')
            ->update(['sms_provider' => 'semaphore']);
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'semaphore_sender_name')) {
                $table->dropColumn('semaphore_sender_name');
            }
        });
    }
};
