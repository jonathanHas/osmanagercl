<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $connection = 'pos';
    protected $table = 'CUSTOMERS';
    protected $primaryKey = 'ID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'CUSTOMER', 'ID');
    }
}