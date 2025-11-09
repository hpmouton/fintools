<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Strategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'ordering_rule',
    ];

    public function amortizations()
    {
        return $this->hasMany(Amortization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
