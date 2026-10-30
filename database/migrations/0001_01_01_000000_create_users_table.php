<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Auth
            $table->string('phone', 15)->unique();
            $table->timestamp('phone_verified_at')->nullable();

            // Registration Flow Status
            $table->enum('registration_status', [
                'phone_verified',      // OTP done, ab step 1
                'personal_info_done',  // Step 1 complete
                'vehicle_info_done',   // Step 2 complete
                'documents_done',      // Step 3 complete
                'under_review',        // Submitted for admin
                'approved',            // Admin approved
                'rejected'             // Admin rejected
            ])->default('phone_verified');

            // Temporary data storage during registration
            $table->jsonb('registration_data')->nullable();

            // Profile
            $table->string('name', 100)->nullable();
            $table->string('email', 100)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            // Avatar
            $table->string('avatar', 500)->nullable();

            // User Type
            $table->enum('user_type', ['rider', 'passenger', 'admin'])->default('rider');

            // Referral
            $table->string('referral_code', 20)->unique();
            $table->foreignUuid('referred_by')->nullable()->constrained('users')->onDelete('set null');

            // Wallet
            $table->decimal('wallet_balance', 12, 2)->default(0.00);

            // Device & Notifications
            $table->string('fcm_token', 500)->nullable();
            $table->jsonb('device_info')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_banned')->default(false);
            $table->text('ban_reason')->nullable();

            // Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();

            // Laravel Default
            $table->rememberToken();
            $table->timestamps();

            // Indexes
            $table->index('user_type');
            $table->index(['is_active', 'is_banned']);
            $table->index('referral_code');
            $table->index('registration_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};