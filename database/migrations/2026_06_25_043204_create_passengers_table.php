<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passengers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->onDelete('cascade');
            
            // Saved Places
            $table->string('home_address')->nullable();
            $table->decimal('home_lat', 10, 8)->nullable();
            $table->decimal('home_lng', 11, 8)->nullable();
            
            $table->string('work_address')->nullable();
            $table->decimal('work_lat', 10, 8)->nullable();
            $table->decimal('work_lng', 11, 8)->nullable();
            
            // Stats
            $table->unsignedInteger('total_rides')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0.00);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passengers');
    }
};