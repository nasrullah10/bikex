<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            
            $table->enum('milestone_type', ['3_direct', '10_direct', '25_direct', '50_direct', '100_direct']);
            $table->unsignedSmallInteger('target_count');
            $table->unsignedSmallInteger('achieved_count')->default(0);
            
            $table->decimal('reward_amount', 8, 2);
            $table->enum('status', ['in_progress', 'achieved', 'claimed', 'expired'])->default('in_progress');
            
            $table->timestamp('achieved_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignUuid('claimed_transaction_id')->nullable()->constrained('transactions');
            
            $table->timestamps();
            
            $table->unique(['user_id', 'milestone_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_milestones');
    }
};