<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TerminalTillMapping extends Model
{
    protected $fillable = [
        'terminal_id',
        'terminal_name',
        'pos_host',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the POS HOST for a given terminal ID.
     */
    public static function getPosHostForTerminal(string $terminalId): ?string
    {
        return static::where('terminal_id', $terminalId)
            ->where('is_active', true)
            ->value('pos_host');
    }

    /**
     * Get all active mappings.
     */
    public static function getActiveMappings(): Collection
    {
        return static::where('is_active', true)->get();
    }

    /**
     * Check if a terminal has a mapping configured.
     */
    public static function hasMapping(string $terminalId): bool
    {
        return static::where('terminal_id', $terminalId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Scope for active mappings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
