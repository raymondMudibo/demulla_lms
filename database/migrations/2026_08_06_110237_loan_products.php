<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('interest_type', ['flat', 'reducing_balance']); // Explicit calculation strategy
            $table->decimal('interest_rate', 5, 2); // e.g. 10.00%
            $table->integer('term_length'); // Duration value
            $table->enum('term_unit', ['weeks', 'months']);
            $table->decimal('processing_fee', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
