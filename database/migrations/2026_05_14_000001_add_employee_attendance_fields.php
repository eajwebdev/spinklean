<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'monthly_salary')) {
                $table->decimal('monthly_salary', 12, 2)->default(0)->after('status');
            }

            if (! Schema::hasColumn('users', 'face_image_path')) {
                $table->string('face_image_path')->nullable()->after('profile_photo');
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'time_in_latitude')) {
                $table->decimal('time_in_latitude', 10, 7)->nullable()->after('time_in');
                $table->decimal('time_in_longitude', 10, 7)->nullable()->after('time_in_latitude');
                $table->string('time_in_photo_path')->nullable()->after('time_in_longitude');
            }

            if (! Schema::hasColumn('attendances', 'time_out_latitude')) {
                $table->decimal('time_out_latitude', 10, 7)->nullable()->after('time_out');
                $table->decimal('time_out_longitude', 10, 7)->nullable()->after('time_out_latitude');
                $table->string('time_out_photo_path')->nullable()->after('time_out_longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'time_in_latitude',
                'time_in_longitude',
                'time_in_photo_path',
                'time_out_latitude',
                'time_out_longitude',
                'time_out_photo_path',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['monthly_salary', 'face_image_path']);
        });
    }
};
