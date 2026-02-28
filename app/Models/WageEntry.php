<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WageEntry extends Model
{
    protected $fillable = [
        'year',
        'week_number',
        'week_start_date',
        'week_end_date',
        'gross_pay',
        'taxable_benefits',
        'taxable_adds',
        'allow_deds',
        'tax',
        'usc_levy',
        'prsi_ee',
        'lpt',
        'non_tax_adds',
        'non_allow_deds',
        'net_pay',
        'prsi_er',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'gross_pay' => 'float',
        'taxable_benefits' => 'float',
        'taxable_adds' => 'float',
        'allow_deds' => 'float',
        'tax' => 'float',
        'usc_levy' => 'float',
        'prsi_ee' => 'float',
        'lpt' => 'float',
        'non_tax_adds' => 'float',
        'non_allow_deds' => 'float',
        'net_pay' => 'float',
        'prsi_er' => 'float',
    ];

    /**
     * Total employer cost = gross pay + employer PRSI
     */
    public function getTotalEmployerCostAttribute(): float
    {
        return $this->gross_pay + $this->prsi_er;
    }

    /**
     * Scope: entries whose weeks overlap with the given date range.
     */
    public function scopeForDateRange($query, $start, $end)
    {
        return $query->where('week_start_date', '<=', $end)
            ->where('week_end_date', '>=', $start);
    }

    /**
     * Scope: entries for a specific payroll year.
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
