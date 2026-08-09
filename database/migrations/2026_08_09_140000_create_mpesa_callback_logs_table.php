<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_callback_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'stk_callback' or 'b2c_callback'
            $table->string('reference')->nullable()->index();
            $table->string('merchant_request_id')->nullable()->index();
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('conversation_id')->nullable()->index();
            $table->string('originator_conversation_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->index();
            $table->integer('result_code')->nullable();
            $table->text('result_desc')->nullable();
            $table->json('payload'); // Full raw incoming JSON payload
            $table->string('processing_status')->default('received'); // 'received', 'processed', 'duplicate', 'failed'
            $table->text('error_message')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_callback_logs');
    }
};
