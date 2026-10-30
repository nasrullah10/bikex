<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Link to users table
            $table->foreignUuid('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');

            // Role hierarchy
            $table->enum('role', [
                'super_admin',   // Full access, can create admins
                'admin',         // Manage riders, rides, withdrawals
                'moderator',     // View + edit riders, support tickets
                'support'        // Read-only + reply to tickets
            ])->default('moderator');

            // Granular permissions (JSON array)
            // [
            //   "riders.view", "riders.edit", "riders.verify",
            //   "rides.view", "rides.cancel",
            //   "withdrawals.view", "withdrawals.approve",
            //   "subscriptions.view",
            //   "promos.manage",
            //   "settings.view", "settings.edit",
            //   "reports.view",
            //   "support.tickets", "support.reply",
            //   "admins.view"  // super_admin only
            // ]
            $table->jsonb('permissions')->default('[]');

            // Who created this admin (super_admin only)
            $table->foreignUuid('created_by')
                  ->nullable()
                  ->constrained('admins')
                  ->onDelete('set null');

            // Audit
            $table->timestamp('last_active_at')->nullable();
            $table->ipAddress('last_active_ip')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};