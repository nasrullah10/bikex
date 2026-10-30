<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ride_id')->unique()->constrained('rides')->onDelete('cascade');
            
            $table->enum('cancelled_by', ['passenger', 'rider']);
            $table->string('reason_code', 30);
            $table->text('custom_reason')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_reasons');
    }
};