<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaCallbackLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'reference',
        'merchant_request_id',
        'checkout_request_id',
        'conversation_id',
        'originator_conversation_id',
        'transaction_id',
        'result_code',
        'result_desc',
        'payload',
        'processing_status',
        'error_message',
        'ip_address',
    ];

    protected $casts = [
        'payload' => 'array',
        'result_code' => 'integer',
    ];
}
