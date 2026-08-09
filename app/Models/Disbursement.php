<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'reference',
        'amount',
        'phone_number',
        'conversation_id',
        'originator_conversation_id',
        'mpesa_receipt_number',
        'status',
        'failure_reason',
        'raw_callback_payload',
        'disbursed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_callback_payload' => 'array',
        'disbursed_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
