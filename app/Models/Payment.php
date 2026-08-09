<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'stk_request_id',
        'mpesa_receipt_number',
        'amount_paid',
        'payer_phone_number',
        'paid_at',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function stkRequest(): BelongsTo
    {
        return $this->belongsTo(StkRequest::class);
    }

    public function installmentPayments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class);
    }

    public function installments(): BelongsToMany
    {
        return $this->belongsToMany(Installment::class, 'installment_payments')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }
}
