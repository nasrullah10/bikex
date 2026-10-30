<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rider_id')->constrained('riders');
            
            $table->enum('badge_code', [
                'first_ride', '10_rides', '100_rides', '1000_rides',
                '7_day_streak', '30_day_streak', 'peak_hero',
                '5_star_week', 'speed_demon', 'night_owl',
                'referral_star', 'top_10_weekly', 'top_3_weekly', 'champion'
            ]);
            
            $table->string('badge_name', 50);
            $table->string('badge_icon', 50);
            $table->string('badge_color', 7)->default('#01411C');
            
            $table->timestamp('earned_at')->useCurrent();
            $table->timestamp('shown_at')->nullable();
            
            $table->unique(['rider_id', 'badge_code']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};