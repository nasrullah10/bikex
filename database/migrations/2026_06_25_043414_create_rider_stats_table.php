<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rider_id')->constrained('riders');
            
            $table->enum('period_type', ['daily', 'weekly', 'monthly']);
            $table->date('period_start');
            
            $table->unsignedInteger('rides_completed')->default(0);
            $table->unsignedInteger('rides_cancelled')->default(0);
            $table->unsignedInteger('rides_missed')->default(0);
            
            $table->decimal('earnings', 12, 2)->default(0.00);
            $table->decimal('commission_saved', 12, 2)->default(0.00);
            
            $table->decimal('online_hours', 5, 2)->default(0.00);
            $table->decimal('peak_hours', 5, 2)->default(0.00);
            
            $table->decimal('rating_avg', 2, 1)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            
            $table->timestamps();
            
            $table->unique(['rider_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_stats');
    }
};