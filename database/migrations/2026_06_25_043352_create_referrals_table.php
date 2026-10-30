<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_id')->constrained('users');
            $table->foreignUuid('referred_id')->unique()->constrained('users');
            
            $table->unsignedTinyInteger('level')->default(1);
            $table->decimal('reward_amount', 8, 2);
            $table->enum('reward_status', ['pending', 'eligible', 'released', 'expired'])->default('pending');
            
            $table->string('milestone_triggered', 30)->nullable();
            
            $table->unsignedTinyInteger('rides_required')->default(5);
            $table->unsignedTinyInteger('rides_completed')->default(0);
            
            $table->timestamp('released_at')->nullable();
            $table->foreignUuid('released_transaction_id')->nullable()->constrained('transactions');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};