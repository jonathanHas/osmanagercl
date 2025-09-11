<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransactionHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'bank_transaction_id',
        'user_id',
        'action',
        'details',
    ];

    protected $casts = [
        'details' => 'json',
    ];

    public function getUpdatedAtColumn()
    {
        return null;
    }
}
