<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->date('week_start');
            $table->date('week_end');
            
            $table->foreignUuid('rider_id')->constrained('riders');
            $table->unsignedSmallInteger('rank');
            
            $table->unsignedInteger('rides_completed')->default(0);
            $table->decimal('earnings', 12, 2)->default(0.00);
            $table->decimal('rating_avg', 2, 1)->default(5.0);
            $table->decimal('online_hours', 5, 2)->default(0.00);
            
            $table->decimal('reward_amount', 8, 2)->nullable();
            
            $table->timestamps();
            
            $table->unique(['week_start', 'rider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};