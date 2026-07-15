// database/migrations/2024_01_01_000003_create_transactions_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('wallet_id')->constrained();
            $table->enum('type', ['deposit', 'transfer', 'bank_transfer', 'bill_payment', 'fee', 'refund']);
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'reversed']);
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->json('metadata')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'type', 'status']);
            $table->index('transaction_id');
            $table->index('reference_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
