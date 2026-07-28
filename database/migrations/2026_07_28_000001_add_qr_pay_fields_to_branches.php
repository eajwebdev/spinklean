<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purely additive: the column starts NULL for every branch. Until an
        // admin fills it in (Admin > Branches), {pay_link} in SMS keeps using
        // the existing site-wide /pay fallback, so behaviour is unchanged.
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'qr_pay_url')) {
                $table->string('qr_pay_url')->nullable()->after('subscription_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'qr_pay_url')) {
                $table->dropColumn('qr_pay_url');
            }
        });
    }
};
