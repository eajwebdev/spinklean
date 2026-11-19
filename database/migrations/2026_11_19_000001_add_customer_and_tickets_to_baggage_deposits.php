<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Baggage tickets: one customer (name + number) can occupy several cubicles under a
 * single claim tag. Each baggage_deposits row is still one cubicle; rows of the same
 * ticket share ticket_code, claim_tag and the Job Order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baggage_deposits', function (Blueprint $table) {
            if (! Schema::hasColumn('baggage_deposits', 'ticket_code')) {
                $table->string('ticket_code', 36)->nullable()->after('job_order_id');
                $table->index('ticket_code');
            }
            if (! Schema::hasColumn('baggage_deposits', 'claim_tag')) {
                $table->unsignedSmallInteger('claim_tag')->nullable()->after('tag_number');
            }
            if (! Schema::hasColumn('baggage_deposits', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('claim_tag')->constrained('customers')->nullOnDelete();
            }
            if (! Schema::hasColumn('baggage_deposits', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('baggage_deposits', 'customer_phone')) {
                $table->string('customer_phone', 30)->nullable()->after('customer_name');
            }
        });

        // Existing deposits: each one becomes its own single-cubicle ticket.
        DB::table('baggage_deposits')->whereNull('ticket_code')->orderBy('id')->select('id', 'tag_number')
            ->each(function ($row) {
                DB::table('baggage_deposits')->where('id', $row->id)->update([
                    'ticket_code' => (string) Str::uuid(),
                    'claim_tag' => $row->tag_number,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('baggage_deposits', function (Blueprint $table) {
            if (Schema::hasColumn('baggage_deposits', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
            if (Schema::hasColumn('baggage_deposits', 'ticket_code')) {
                $table->dropIndex(['ticket_code']);
            }
        });

        Schema::table('baggage_deposits', function (Blueprint $table) {
            foreach (['ticket_code', 'claim_tag', 'customer_name', 'customer_phone'] as $column) {
                if (Schema::hasColumn('baggage_deposits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
