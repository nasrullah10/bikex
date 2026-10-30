<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->string('loggable_type', 50);
            $table->uuid('loggable_id');
            
            $table->string('action', 50);
            $table->enum('actor_type', ['system', 'user', 'admin'])->default('system');
            $table->foreignUuid('actor_id')->nullable()->constrained('users');
            
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};