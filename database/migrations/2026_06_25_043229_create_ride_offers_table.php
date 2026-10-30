<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ride_id')->constrained('rides')->onDelete('cascade');
            $table->foreignUuid('rider_id')->constrained('riders');
            
            $table->timestamp('offered_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamp('responded_at')->nullable();
            $table->enum('response', ['accepted', 'declined', 'expired'])->nullable();
            
            $table->unique(['ride_id', 'rider_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_offers');
    }
};