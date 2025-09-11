<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankImportMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date_col',
        'description_col',
        'debit_col',
        'credit_col',
        'balance_col',
        'date_format',
    ];
}
