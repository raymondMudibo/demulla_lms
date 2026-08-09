<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_account_number')->unique(); // Internal account reference
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained();
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('interest_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->enum('status', ['pending', 'approved', 'disbursed', 'active', 'closed', 'rejected'])
                ->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
