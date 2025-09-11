<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconciliationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'expense_category',
        'expense_description',
        'credit_category',
        'reconciliation_type',
        'is_non_supplier_expense',
        'match_pattern',
        'description_fingerprint',
        'match_count',
        'last_matched_at',
        'confidence_score',
        'priority',
        'auto_approve',
    ];

    protected $casts = [
        'auto_approve' => 'boolean',
        'is_non_supplier_expense' => 'boolean',
        'match_count' => 'integer',
        'confidence_score' => 'integer',
        'last_matched_at' => 'datetime',
    ];

    /**
     * Get the supplier associated with this rule
     */
    public function supplier()
    {
        return $this->belongsTo(\App\Models\AccountingSupplier::class, 'supplier_id');
    }

    /**
     * Increment match count and update last matched timestamp
     */
    public function recordMatch(): void
    {
        $this->increment('match_count');
        $this->update([
            'last_matched_at' => now(),
            'confidence_score' => min(100, $this->confidence_score + 5), // Increase confidence up to 100
        ]);
    }

    /**
     * Create a description fingerprint for exact matching
     */
    public static function createDescriptionFingerprint(string $description): string
    {
        // Normalize description for consistent matching
        $normalized = strtolower(trim($description));

        // Remove common banking prefixes/suffixes that might change
        $normalized = preg_replace('/^(payment|transfer)\s+/i', '', $normalized);
        $normalized = preg_replace('/\s+(payment|transfer)$/i', '', $normalized);

        // Remove dates in various formats
        $normalized = preg_replace('/\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b/', '', $normalized);
        $normalized = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', '', $normalized);

        // Remove amounts (decimal numbers)
        $normalized = preg_replace('/\b\d+\.\d{2}\b/', '', $normalized);

        // Remove specific reference patterns but keep company names
        $normalized = preg_replace('/\bref\d+/i', '', $normalized);
        $normalized = preg_replace('/\binv[-_]?\d+[-_]?\d*/i', '', $normalized);

        // Only remove purely numeric sequences of 4+ digits
        $normalized = preg_replace('/\b\d{4,}\b/', '', $normalized);

        // Clean up multiple spaces and trim
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Find supplier by description fingerprint
     */
    public static function findSupplierByDescription(string $description): ?\App\Models\AccountingSupplier
    {
        $fingerprint = self::createDescriptionFingerprint($description);

        if (empty($fingerprint) || strlen($fingerprint) < 3) {
            return null;
        }

        $rule = self::where('description_fingerprint', $fingerprint)
            ->whereNotNull('supplier_id')
            ->orderByDesc('confidence_score')
            ->orderByDesc('match_count')
            ->first();

        return $rule?->supplier;
    }

    /**
     * Get high-confidence rules for a supplier
     */
    public static function getHighConfidenceRulesForSupplier(int $supplierId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('supplier_id', $supplierId)
            ->where('confidence_score', '>=', 70)
            ->where('match_count', '>=', 2)
            ->orderByDesc('confidence_score')
            ->orderByDesc('match_count')
            ->get();
    }

    /**
     * Scope for active rules (recently matched)
     */
    public function scopeActive($query)
    {
        return $query->where('last_matched_at', '>=', now()->subMonths(6))
            ->orWhere('match_count', '>=', 3);
    }

    /**
     * Scope for high confidence rules
     */
    public function scopeHighConfidence($query)
    {
        return $query->where('confidence_score', '>=', 70)
            ->where('match_count', '>=', 2);
    }

    /**
     * Find non-supplier expense pattern by description fingerprint
     */
    public static function findNonSupplierExpenseByDescription(string $description): ?self
    {
        $fingerprint = self::createDescriptionFingerprint($description);

        if (empty($fingerprint) || strlen($fingerprint) < 3) {
            return null;
        }

        return self::where('description_fingerprint', $fingerprint)
            ->where('is_non_supplier_expense', true)
            ->orderByDesc('confidence_score')
            ->orderByDesc('match_count')
            ->first();
    }

    /**
     * Create a non-supplier expense learning rule
     */
    public static function createNonSupplierRule(
        string $description,
        string $expenseCategory,
        string $expenseDescription
    ): self {
        $fingerprint = self::createDescriptionFingerprint($description);

        return self::create([
            'supplier_id' => null,
            'expense_category' => $expenseCategory,
            'expense_description' => $expenseDescription,
            'is_non_supplier_expense' => true,
            'match_pattern' => $description, // Store original for reference
            'description_fingerprint' => $fingerprint,
            'match_count' => 1,
            'last_matched_at' => now(),
            'confidence_score' => 60, // Start with moderate confidence
            'priority' => 100,
            'auto_approve' => false,
        ]);
    }

    /**
     * Scope for non-supplier expense rules
     */
    public function scopeNonSupplierExpense($query)
    {
        return $query->where('is_non_supplier_expense', true);
    }

    /**
     * Get high-confidence non-supplier rules for a specific expense category
     */
    public static function getHighConfidenceNonSupplierRules(string $expenseCategory): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_non_supplier_expense', true)
            ->where('expense_category', $expenseCategory)
            ->where('confidence_score', '>=', 70)
            ->where('match_count', '>=', 2)
            ->orderByDesc('confidence_score')
            ->orderByDesc('match_count')
            ->get();
    }
}
