<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            
            $table->enum('type', [
                'ride_request', 'ride_accepted', 'ride_cancelled',
                'ride_completed', 'payment_received', 'withdrawal_status',
                'referral_bonus', 'milestone_achieved',
                'subscription_expiring', 'subscription_expired',
                'promo', 'system', 'support_reply'
            ]);
            
            $table->string('title', 100);
            $table->string('title_urdu', 100)->nullable();
            $table->text('body');
            $table->text('body_urdu')->nullable();
            
            $table->json('data')->nullable();
            $table->string('image_url', 500)->nullable();
            
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            
            $table->enum('sent_via', ['push', 'sms', 'in_app', 'email'])->default('push');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};