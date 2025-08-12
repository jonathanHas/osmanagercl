<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class DrawerOpened extends Model
{
    protected $connection = 'pos';

    protected $table = 'DRAWEROPENED';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [];

    protected $casts = [
        'OPENDATE' => 'datetime',
    ];
}
