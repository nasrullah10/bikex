<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->string('name_urdu', 50)->nullable();
            
            $table->enum('vehicle_type', ['bike', 'rickshaw', 'car', 'car_ac']);
            $table->decimal('commission_rate', 4, 2);
            $table->decimal('monthly_price', 8, 2);
            
            $table->json('features');
            
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('display_order')->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};