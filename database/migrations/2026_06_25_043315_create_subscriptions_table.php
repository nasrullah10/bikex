<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rider_id')->unique()->constrained('riders');
            $table->foreignUuid('plan_id')->constrained('subscription_plans');
            
            $table->decimal('amount_paid', 8, 2);
            $table->enum('payment_method', ['jazzcash', 'easypaisa', 'bank_transfer', 'cash']);
            $table->string('payment_proof')->nullable();
            
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('active');
            $table->boolean('auto_renew')->default(false);
            
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->foreignUuid('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};