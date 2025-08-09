<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $connection = 'pos';
    protected $table = 'RECEIPTS';
    protected $primaryKey = 'ID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'DATENEW' => 'datetime',
        'ATTRIBUTES' => 'array',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class, 'RECEIPT', 'ID');
    }

    public function ticket()
    {
        return $this->hasOne(Ticket::class, 'ID', 'ID');
    }

    public function closedCash()
    {
        return $this->belongsTo(ClosedCash::class, 'MONEY', 'MONEY');
    }
}