<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ride_id')->constrained('rides')->onDelete('cascade');
            
            // Event type
            $table->enum('event_type', [
                'ride_requested',      // Passenger ne request ki
                'rider_assigned',      // Rider mil gaya
                'rider_arrived',       // Rider pickup pe pahuncha
                'pickup_confirmed',    // Passenger ne pickup confirm ki
                'ride_started',        // Ride start
                'location_update',     // Location update
                'ride_completed',      // Ride complete
                'ride_cancelled',      // Ride cancel
                'payment_received',    // Payment mil gayi
                'rider_rated',         // Rider ko rating mili
                'passenger_rated',     // Passenger ko rating mili
                'dispute_raised',      // Dispute
                'emergency_triggered', // Emergency
            ]);
            
            // Location jahan event hua
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            
            // Details
            $table->jsonb('metadata')->nullable(); // Extra info
            $table->text('notes')->nullable();
            
            // Who triggered
            $table->enum('triggered_by', ['system', 'rider', 'passenger', 'admin'])->default('system');
            
            $table->timestamp('event_at'); // When event happened
            $table->timestamps();
            
            // Indexes
            $table->index('ride_id');
            $table->index('event_type');
            $table->index('event_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_events');
    }
};