<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_places', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('passenger_id')->constrained('passengers')->onDelete('cascade');
            
            $table->string('name', 50);
            $table->string('address');
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->string('icon', 30)->default('location'); // home, work, star, heart
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_places');
    }
};