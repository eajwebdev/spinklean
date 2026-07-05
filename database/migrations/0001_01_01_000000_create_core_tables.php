<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('address')->nullable();
                $table->string('contact_number')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('branches', function (Blueprint $table) {
                if (! Schema::hasColumn('branches', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('username')->unique();
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->enum('role', ['super_admin', 'admin', 'branch_manager', 'cashier', 'staff'])->default('staff');
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->json('access')->nullable();
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
                $table->timestamp('last_login_at')->nullable();
                $table->string('profile_photo')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['role', 'status']);
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'access')) {
                    $table->json('access')->nullable()->after('branch_id');
                }

                if (! Schema::hasColumn('users', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('business_name')->nullable();
                $table->string('business_logo')->nullable();
                $table->string('business_email')->nullable();
                $table->string('contact_number')->nullable();
                $table->text('business_address')->nullable();
                $table->text('receipt_header')->nullable();
                $table->text('receipt_footer')->nullable();
                $table->string('currency')->default('PHP');
                $table->boolean('vat_enabled')->default(false);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->json('operating_hours')->nullable();
                $table->decimal('default_price_per_kilo', 10, 2)->default(0);
                $table->decimal('default_price_per_load', 10, 2)->default(0);
                $table->decimal('default_price_per_piece', 10, 2)->default(0);
                $table->string('job_order_prefix')->default('JO');
                $table->string('invoice_prefix')->default('INV');
                $table->string('sms_provider')->nullable();
                $table->text('sms_api_key')->nullable();
                $table->string('unisms_sender_id')->nullable();
                $table->string('twilio_account_sid')->nullable();
                $table->text('twilio_auth_token')->nullable();
                $table->string('twilio_from_number')->nullable();
                $table->boolean('sms_enabled')->default(false);
                $table->string('primary_color')->default('#2E7D32');
                $table->boolean('dark_mode_default')->default(false);
                $table->boolean('is_completed')->default(false);
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');
    }
};
