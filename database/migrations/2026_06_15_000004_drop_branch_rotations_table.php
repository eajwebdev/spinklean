<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('branch_rotations');
    }

    public function down(): void
    {
        // Branch rotations were intentionally removed.
    }
};
