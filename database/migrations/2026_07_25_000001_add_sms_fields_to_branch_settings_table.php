<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('branch_settings', 'sms_provider')) {
                $table->string('sms_provider')->nullable()->after('invoice_prefix');
            }
            if (! Schema::hasColumn('branch_settings', 'sms_api_key')) {
                $table->string('sms_api_key')->nullable()->after('sms_provider');
            }
            if (! Schema::hasColumn('branch_settings', 'unisms_sender_id')) {
                $table->string('unisms_sender_id')->nullable()->after('sms_api_key');
            }
            if (! Schema::hasColumn('branch_settings', 'sms_enabled')) {
                $table->boolean('sms_enabled')->nullable()->after('unisms_sender_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            if (Schema::hasColumn('branch_settings', 'sms_enabled')) {
                $table->dropColumn('sms_enabled');
            }
            if (Schema::hasColumn('branch_settings', 'unisms_sender_id')) {
                $table->dropColumn('unisms_sender_id');
            }
            if (Schema::hasColumn('branch_settings', 'sms_api_key')) {
                $table->dropColumn('sms_api_key');
            }
            if (Schema::hasColumn('branch_settings', 'sms_provider')) {
                $table->dropColumn('sms_provider');
            }
        });
    }
};
