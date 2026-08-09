<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'interest_type',
        'interest_rate',
        'term_length',
        'term_unit',
        'processing_fee',
    ];

    protected $casts = [
        'interest_rate' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'term_length' => 'integer',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
