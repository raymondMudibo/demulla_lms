<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stk_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('checkout_reference')->unique(); // Internal idempotent token/reference sent in STK payload
            $table->string('merchant_request_id')->nullable(); // Daraja confirmation ID
            $table->string('checkout_request_id')->nullable(); // Daraja confirmation ID
            $table->decimal('amount_requested', 12, 2);
            $table->string('phone_number');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'mismatched'])->default('pending');
            $table->json('raw_callback_payload')->nullable(); // For auditing delayed/unmatched requests
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stk_requests');
    }
};
