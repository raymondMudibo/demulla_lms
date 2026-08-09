<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StkRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'checkout_reference',
        'merchant_request_id',
        'checkout_request_id',
        'amount_requested',
        'phone_number',
        'status',
        'raw_callback_payload',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'raw_callback_payload' => 'json',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
