<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            
            $table->enum('type', [
                'ride_earning', 'ride_payment', 'referral_bonus',
                'milestone_bonus', 'subscription_payment', 'subscription_renewal',
                'withdrawal', 'withdrawal_fee', 'tip', 'promo_credit',
                'refund', 'adjustment'
            ]);
            
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            
            $table->string('reference_type', 30)->nullable();
            $table->uuid('reference_id')->nullable();
            
            $table->string('description');
            $table->string('description_urdu')->nullable();
            
            $table->json('metadata')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};