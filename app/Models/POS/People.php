<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class People extends Model
{
    protected $connection = 'pos';

    protected $table = 'PEOPLE';

    protected $primaryKey = 'ID';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'PERSON', 'ID');
    }
}
