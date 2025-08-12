<?php

namespace App\Models\POS;

use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Model;

class TicketLine extends Model
{
    protected $connection = 'pos';

    protected $table = 'TICKETLINES';

    protected $primaryKey = 'TICKET';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'LINE' => 'integer',
        'UNITS' => 'decimal:2',
        'PRICE' => 'decimal:2',
        'ATTRIBUTES' => 'array',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'TICKET', 'ID');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'PRODUCT', 'ID');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'TAXID', 'ID');
    }
}
