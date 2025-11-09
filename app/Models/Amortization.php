<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Amortization extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'strategy_id',
        'payment_number',
        'payment_date',
        'payment_amount',
        'principal',
        'interest',
        'remaining_balance',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function strategy()
    {
        return $this->belongsTo(Strategy::class);
    }
}
