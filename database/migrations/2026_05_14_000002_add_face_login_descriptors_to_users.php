<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'face_descriptors')) {
                $table->json('face_descriptors')->nullable()->after('face_image_path');
            }

            if (! Schema::hasColumn('users', 'face_enrolled_at')) {
                $table->timestamp('face_enrolled_at')->nullable()->after('face_descriptors');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['face_descriptors', 'face_enrolled_at']);
        });
    }
};
