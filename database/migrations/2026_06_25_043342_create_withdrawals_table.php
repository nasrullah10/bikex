<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rider_id')->constrained('riders');
            
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 8, 2)->default(10.00);
            $table->decimal('net_amount', 10, 2);
            
            $table->enum('method', ['jazzcash', 'easypaisa', 'bank_transfer']);
            $table->string('account_number', 50);
            $table->string('account_title', 100)->nullable();
            
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected'])->default('pending');
            
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users');
            
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};