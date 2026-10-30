<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Participants
            $table->foreignUuid('passenger_id')->constrained('passengers');
            $table->foreignUuid('rider_id')->nullable()->constrained('riders');
            
            // Locations
            $table->string('pickup_address');
            $table->decimal('pickup_lat', 10, 8);
            $table->decimal('pickup_lng', 11, 8);
            $table->string('pickup_geohash', 12)->nullable();
            
            $table->string('drop_address');
            $table->decimal('drop_lat', 10, 8);
            $table->decimal('drop_lng', 11, 8);
            
            // Ride Details
            $table->enum('vehicle_type', ['bike', 'rickshaw', 'car', 'car_ac']);
            $table->decimal('estimated_distance', 6, 2)->nullable();
            $table->unsignedInteger('estimated_duration')->nullable(); // seconds
            $table->decimal('actual_distance', 6, 2)->nullable();
            $table->unsignedInteger('actual_duration')->nullable();
            
            // Pricing
            $table->decimal('base_fare', 8, 2);
            $table->decimal('distance_fare', 8, 2);
            $table->decimal('waiting_fare', 8, 2)->default(0.00);
            $table->decimal('surge_multiplier', 3, 2)->default(1.00);
            $table->decimal('promo_discount', 8, 2)->default(0.00);
            $table->decimal('total_fare', 8, 2);
            
            // Status
            $table->enum('status', [
                'searching', 'offered', 'accepted', 'arrived',
                'started', 'completed', 'cancelled_by_passenger',
                'cancelled_by_rider', 'no_rider_found', 'disputed'
            ])->default('searching');
            
            // Timestamps
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Ratings
            $table->unsignedTinyInteger('passenger_rating')->nullable();
            $table->unsignedTinyInteger('rider_rating')->nullable();
            $table->text('passenger_comment')->nullable();
            $table->text('rider_comment')->nullable();
            
            // Tracking
            $table->longText('route_polyline')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};