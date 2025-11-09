<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'creditor_id',
        'name',
        'balance',
        'interest_rate',
        'minimum_payment',
        'start_date',
        'term_months',
    ];

    public function creditor()
    {
        return $this->belongsTo(Creditor::class);
    }

    public function amortizations()
    {
        return $this->hasMany(Amortization::class);
    }
}
