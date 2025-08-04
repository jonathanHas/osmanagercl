<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesImportLog extends Model
{
    protected $table = 'sales_import_log';

    protected $fillable = [
        'import_type', 'start_date', 'end_date', 'records_processed',
        'records_inserted', 'records_updated', 'execution_time_seconds',
        'status', 'error_message',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'execution_time_seconds' => 'decimal:2',
    ];
}
