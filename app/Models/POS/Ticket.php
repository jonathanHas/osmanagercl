<?php

namespace App\Models\POS;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $connection = 'pos';
    protected $table = 'TICKETS';
    protected $primaryKey = 'ID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'TICKETID' => 'integer',
        'TICKETTYPE' => 'integer',
        'STATUS' => 'integer',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'ID', 'ID');
    }

    public function ticketLines()
    {
        return $this->hasMany(TicketLine::class, 'TICKET', 'ID');
    }

    public function person()
    {
        return $this->belongsTo(People::class, 'PERSON', 'ID');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'CUSTOMER', 'ID');
    }
}