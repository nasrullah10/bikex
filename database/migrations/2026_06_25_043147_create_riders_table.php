<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->onDelete('cascade');

            // Personal Documents
            $table->string('cnic_number', 15)->unique();
            $table->string('cnic_front_photo');
            $table->string('cnic_back_photo');
            $table->date('cnic_expiry')->nullable();

            $table->string('license_number', 50);
            $table->string('license_photo');
            $table->date('license_expiry');

            // Vehicle Info
            $table->enum('vehicle_type', ['bike', 'rickshaw', 'car', 'car_ac']);
            $table->string('vehicle_company', 50)->nullable();
            $table->string('vehicle_model', 50)->nullable();
            $table->year('vehicle_year')->nullable();
            $table->string('vehicle_number', 20);
            $table->string('vehicle_color', 30)->nullable();
            $table->string('vehicle_photo');

            // Verification
            $table->enum('verification_status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->foreignUuid('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Status & Location
            $table->boolean('is_online')->default(false);
            $table->boolean('is_available')->default(false);
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 11, 8)->nullable();
            $table->decimal('current_heading', 5, 2)->nullable();
            $table->timestamp('location_updated_at')->nullable();

            // Performance
            $table->decimal('rating', 2, 1)->default(5.0);
            $table->unsignedInteger('total_rides')->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0.00);
            $table->unsignedInteger('total_cancelled')->default(0);

            // Subscription
            $table->enum('current_plan', ['free', 'basic', 'pro', 'rickshaw_pro', 'car_pro'])->nullable();
            $table->timestamp('subscription_expires_at')->nullable();

            // Emergency
            $table->string('emergency_contact_1', 15)->nullable();
            $table->string('emergency_contact_2', 15)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('verification_status');
            $table->index(['is_online', 'is_available']);
            $table->index('cnic_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};