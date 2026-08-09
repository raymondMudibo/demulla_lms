<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique(); // Internal tracking UUID/Reference
            $table->decimal('amount', 12, 2);
            $table->string('phone_number');
            $table->string('conversation_id')->nullable(); // From Daraja B2C
            $table->string('originator_conversation_id')->nullable(); // From Daraja B2C
            $table->string('mpesa_receipt_number')->nullable();
            $table->enum('status', ['initiated', 'successful', 'failed'])->default('initiated');
            $table->text('failure_reason')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
