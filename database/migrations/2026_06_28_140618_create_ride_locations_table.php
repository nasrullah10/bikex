<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
   public function up(): void
    {
        Schema::create('ride_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ride_id')->constrained('rides')->onDelete('cascade');
            
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 6, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable();
            $table->enum('source', ['rider_app', 'passenger_app', 'server'])->default('rider_app');
            
            $table->timestamp('recorded_at');
            $table->timestamp('received_at')->useCurrent();
            
            $table->index('ride_id');
            $table->index('recorded_at');
        });

        // NOT NULL instead of NULL
        DB::statement('ALTER TABLE ride_locations ADD COLUMN location POINT NOT NULL AFTER lng');
        
        DB::statement('ALTER TABLE ride_locations ADD SPATIAL INDEX idx_location (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_locations');
    }
};