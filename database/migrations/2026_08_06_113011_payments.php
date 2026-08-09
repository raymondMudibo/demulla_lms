<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stk_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mpesa_receipt_number')->unique()->nullable(); // External confirmation receipt
            $table->decimal('amount_paid', 12, 2);
            $table->string('payer_phone_number');
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
