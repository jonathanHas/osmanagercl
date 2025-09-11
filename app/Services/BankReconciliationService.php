<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\BankTransactionAllocation;
use App\Models\CostCategory;
use App\Models\Invoice;
use App\Models\ReconciliationRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    /**
     * Find potential invoice matches for a bank transaction
     */
    public function findPotentialMatches(BankTransaction $transaction, ?int $filterSupplierId = null): Collection
    {
        $matches = collect();

        // Get amount to match (use debit amount for expenses, credit for income)
        $amount = $transaction->debit_amount > 0 ? $transaction->debit_amount : $transaction->credit_amount;

        if ($amount <= 0) {
            return $matches;
        }

        // Enhanced search strategy combining invoice_date and payment_date logic
        $invoices = $this->getInvoiceCandidates($transaction, $filterSupplierId);

        foreach ($invoices as $invoice) {
            $confidence = $this->calculateMatchConfidence($transaction, $invoice);

            if ($confidence > 0) {
                $matches->push([
                    'invoice' => $invoice,
                    'confidence' => $confidence,
                    'match_reasons' => $this->getMatchReasons($transaction, $invoice, $confidence),
                ]);
            }
        }

        // Sort by confidence score (highest first)
        return $matches->sortByDesc('confidence');
    }

    /**
     * Calculate confidence score for a potential match
     */
    private function calculateMatchConfidence(BankTransaction $transaction, Invoice $invoice): int
    {
        $confidence = 0;
        $transactionAmount = $transaction->debit_amount > 0 ? $transaction->debit_amount : $transaction->credit_amount;

        // Exact amount match: +50 points
        if (abs($transactionAmount - $invoice->total_amount) < 0.01) {
            $confidence += 50;
        }
        // Close amount match (within 5%): +30 points
        elseif (abs($transactionAmount - $invoice->total_amount) <= ($invoice->total_amount * 0.05)) {
            $confidence += 30;
        }
        // Somewhat close amount match (within 10%): +15 points
        elseif (abs($transactionAmount - $invoice->total_amount) <= ($invoice->total_amount * 0.10)) {
            $confidence += 15;
        }
        // Check for common payment adjustments (early payment discounts, fees)
        elseif ($this->isLikelyPaymentVariant($transactionAmount, $invoice->total_amount)) {
            $confidence += 25;
        }

        // Date matching scoring (max 45 points)
        $dateScore = $this->scoreDateMatching($transaction, $invoice);
        $confidence += $dateScore;

        // Supplier name matching (max 20 points)
        $supplierScore = $this->scoreSupplierNameMatch($transaction->description, $invoice->supplier_name);
        $confidence += $supplierScore;

        // Apply learned reconciliation rules (max 25 points)
        $ruleScore = $this->scoreReconciliationRules($transaction, $invoice);
        $confidence += $ruleScore;

        // Reference number matching (max 40 points - highest priority)
        $referenceScore = $this->scoreReferenceNumberMatch($transaction, $invoice);
        $confidence += $referenceScore;

        return min($confidence, 100); // Cap at 100
    }

    /**
     * Score supplier name matching between transaction description and invoice
     */
    private function scoreSupplierNameMatch(string $description, string $supplierName): int
    {
        if (empty($supplierName)) {
            return 0;
        }

        $description = strtolower($description);
        $supplierName = strtolower($supplierName);

        // Exact match: +20 points
        if (str_contains($description, $supplierName)) {
            return 20;
        }

        // Try matching individual words
        $supplierWords = array_filter(explode(' ', $supplierName), fn ($word) => strlen($word) > 3);
        $matchedWords = 0;

        foreach ($supplierWords as $word) {
            if (str_contains($description, $word)) {
                $matchedWords++;
            }
        }

        // Score based on proportion of matched words
        if (count($supplierWords) > 0) {
            $proportion = $matchedWords / count($supplierWords);

            return intval($proportion * 15);
        }

        return 0;
    }

    /**
     * Score transaction against learned reconciliation rules
     */
    private function scoreReconciliationRules(BankTransaction $transaction, Invoice $invoice): int
    {
        $score = 0;

        // Get all active reconciliation rules, ordered by priority
        $rules = ReconciliationRule::orderBy('priority', 'asc')->get();

        foreach ($rules as $rule) {
            // Check if rule pattern matches transaction description
            if ($this->matchesPattern($transaction->description, $rule->match_pattern)) {
                // Check if this rule is for the same supplier
                if ($rule->supplier_id && $invoice->supplier_id == $rule->supplier_id) {
                    $score += 25; // High confidence for supplier-specific learned pattern
                    break; // Stop at first high-confidence match
                } elseif (! $rule->supplier_id) {
                    // General pattern rule (not supplier-specific)
                    $score += 15;
                }
            }
        }

        return min($score, 25); // Cap at 25 points
    }

    /**
     * Check if transaction description matches a pattern
     */
    private function matchesPattern(string $description, string $pattern): bool
    {
        $description = strtolower(trim($description));

        // If pattern contains wildcards or regex markers, treat as regex
        if (str_contains($pattern, '*') || str_contains($pattern, '|') || str_contains($pattern, '^') || str_contains($pattern, '$')) {
            // Convert simple wildcard pattern to regex
            $regexPattern = str_replace('*', '.*', preg_quote(strtolower($pattern), '/'));
            try {
                return preg_match('/'.$regexPattern.'/i', $description) === 1;
            } catch (\Exception $e) {
                // Fallback to simple string matching if regex fails
                return str_contains($description, strtolower(str_replace('*', '', $pattern)));
            }
        }

        // Simple substring matching
        return str_contains($description, strtolower($pattern));
    }

    /**
     * Get human-readable match reasons
     */
    private function getMatchReasons(BankTransaction $transaction, Invoice $invoice, int $confidence): array
    {
        $reasons = [];
        $transactionAmount = $transaction->debit_amount > 0 ? $transaction->debit_amount : $transaction->credit_amount;

        // Amount matching
        $amountDiff = abs($transactionAmount - $invoice->total_amount);
        if ($amountDiff < 0.01) {
            $reasons[] = 'Exact amount match';
        } elseif ($amountDiff <= ($invoice->total_amount * 0.05)) {
            $reasons[] = 'Very close amount match';
        } elseif ($amountDiff <= ($invoice->total_amount * 0.10)) {
            $reasons[] = 'Close amount match';
        } elseif ($this->isLikelyPaymentVariant($transactionAmount, $invoice->total_amount)) {
            $reasons[] = '💳 Likely payment with discount/fee/partial amount';
        }

        // Date matching reasons (prioritize payment_date over invoice_date)
        $dateReasons = $this->getDateMatchReasons($transaction, $invoice);
        $reasons = array_merge($reasons, $dateReasons);

        // Reference number matching
        $referenceScore = $this->scoreReferenceNumberMatch($transaction, $invoice);
        if ($referenceScore >= 40) {
            $reasons[] = '🎯 Exact invoice number match found';
        } elseif ($referenceScore >= 35) {
            $reasons[] = '🎯 Invoice number pattern match found';
        }

        // Supplier matching
        if (str_contains(strtolower($transaction->description), strtolower($invoice->supplier_name))) {
            $reasons[] = 'Supplier name found in transaction description';
        }

        // Check for learned pattern matches
        $rules = ReconciliationRule::orderBy('priority', 'asc')->get();
        foreach ($rules as $rule) {
            if ($this->matchesPattern($transaction->description, $rule->match_pattern)) {
                if ($rule->supplier_id && $invoice->supplier_id == $rule->supplier_id) {
                    $reasons[] = '🎯 Learned supplier-specific pattern match';
                    break;
                } elseif (! $rule->supplier_id) {
                    $reasons[] = '📚 Learned general pattern match';
                }
            }
        }

        return $reasons;
    }

    /**
     * Reconcile a bank transaction with an invoice
     */
    public function reconcileWithInvoice(BankTransaction $transaction, Invoice $invoice, ?string $notes = null): bool
    {
        try {
            $transaction->update([
                'status' => 'matched',
                'reconciliation_type' => 'invoice',
                'reconciliation_id' => $invoice->id,
                'reconciliation_model' => Invoice::class,
                'reconciled_at' => now(),
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            // Learn from this successful match
            $this->learnFromSuccessfulMatch($transaction, $invoice);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Learn from successful manual matches to improve future matching
     */
    private function learnFromSuccessfulMatch(BankTransaction $transaction, Invoice $invoice): void
    {
        try {
            // Create description fingerprint for exact matching
            $fingerprint = ReconciliationRule::createDescriptionFingerprint($transaction->description);

            // Store exact description fingerprint if it's meaningful
            if (! empty($fingerprint) && strlen($fingerprint) >= 3) {
                $existingFingerprintRule = ReconciliationRule::where('supplier_id', $invoice->supplier_id)
                    ->where('description_fingerprint', $fingerprint)
                    ->first();

                if ($existingFingerprintRule) {
                    // Update existing fingerprint rule
                    $existingFingerprintRule->recordMatch();
                } else {
                    // Determine if this is a non-supplier expense
                    $isNonSupplier = is_null($invoice->supplier_id) ||
                                   in_array($invoice->expense_category, ['WAGES', 'TAX', 'INSURANCE', 'BANK_FEES']);

                    // Set expense description for non-supplier expenses
                    $expenseDescription = '';
                    if ($isNonSupplier && $invoice->expense_category === 'WAGES') {
                        $expenseDescription = 'Wages & Salaries';
                    }

                    // Create new fingerprint rule
                    ReconciliationRule::create([
                        'supplier_id' => $invoice->supplier_id,
                        'expense_category' => $invoice->expense_category,
                        'expense_description' => $expenseDescription,
                        'is_non_supplier_expense' => $isNonSupplier,
                        'match_pattern' => $fingerprint,
                        'description_fingerprint' => $fingerprint,
                        'match_count' => 1,
                        'last_matched_at' => now(),
                        'confidence_score' => 60, // Start with good confidence for exact matches
                        'priority' => 50, // High priority for exact matches
                        'auto_approve' => false,
                    ]);
                }
            }

            // Extract meaningful patterns from the transaction description
            $patterns = $this->extractLearningPatterns($transaction->description);

            foreach ($patterns as $pattern) {
                // Check if we already have this pattern for this supplier
                $existingRule = ReconciliationRule::where('supplier_id', $invoice->supplier_id)
                    ->where('match_pattern', $pattern)
                    ->whereNull('description_fingerprint') // Only update pattern-based rules
                    ->first();

                if ($existingRule) {
                    // Update existing pattern rule
                    $existingRule->recordMatch();
                } else {
                    // Determine if this is a non-supplier expense
                    $isNonSupplier = is_null($invoice->supplier_id) ||
                                   in_array($invoice->expense_category, ['WAGES', 'TAX', 'INSURANCE', 'BANK_FEES']);

                    // Set expense description for non-supplier expenses
                    $expenseDescription = '';
                    if ($isNonSupplier && $invoice->expense_category === 'WAGES') {
                        $expenseDescription = 'Wages & Salaries';
                    }

                    // Create new learned pattern rule
                    ReconciliationRule::create([
                        'supplier_id' => $invoice->supplier_id,
                        'expense_category' => $invoice->expense_category,
                        'expense_description' => $expenseDescription,
                        'is_non_supplier_expense' => $isNonSupplier,
                        'match_pattern' => $pattern,
                        'match_count' => 1,
                        'last_matched_at' => now(),
                        'confidence_score' => 50, // Default confidence for patterns
                        'priority' => 100, // Lower priority than exact matches
                        'auto_approve' => false,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Learning failures shouldn't break the reconciliation
            logger()->warning('Failed to learn from reconciliation match', [
                'transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract meaningful patterns from transaction description for learning
     */
    private function extractLearningPatterns(string $description): array
    {
        $patterns = [];
        $description = trim($description);

        // Don't learn from very short or generic descriptions
        if (strlen($description) < 5) {
            return $patterns;
        }

        // Extract meaningful words (longer than 3 characters)
        $words = array_filter(
            explode(' ', strtolower($description)),
            fn ($word) => strlen(trim($word)) > 3 && ! in_array($word, ['payment', 'invoice', 'bill', 'from', 'transfer'])
        );

        // Create patterns from significant words
        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^a-z0-9]/', '', $word);
            if (strlen($cleanWord) > 3) {
                $patterns[] = "*{$cleanWord}*"; // Wildcard pattern
            }
        }

        // Also create a pattern from the first significant part (up to first space or punctuation)
        $firstPart = preg_replace('/[\s\-_,\.]+.*/', '', strtolower($description));
        if (strlen($firstPart) > 4) {
            $patterns[] = $firstPart.'*';
        }

        // Remove duplicates and return unique patterns
        return array_unique($patterns);
    }

    /**
     * Score reference number matching between transaction and invoice
     */
    private function scoreReferenceNumberMatch(BankTransaction $transaction, Invoice $invoice): int
    {
        $description = strtolower($transaction->description);
        $invoiceNumber = strtolower($invoice->invoice_number ?? '');

        if (empty($invoiceNumber)) {
            return 0;
        }

        // Exact invoice number match
        if (str_contains($description, $invoiceNumber)) {
            return 40;
        }

        // Extract potential reference numbers from description
        $extractedRefs = $this->extractReferenceNumbers($transaction->description);

        foreach ($extractedRefs as $ref) {
            // Check if extracted reference matches invoice number (case-insensitive)
            if (strtolower($ref) === $invoiceNumber) {
                return 40;
            }

            // Check for partial matches (e.g., "12345" matches "INV-12345")
            $cleanInvoiceNumber = preg_replace('/[^a-z0-9]/', '', $invoiceNumber);
            $cleanRef = preg_replace('/[^a-z0-9]/', '', strtolower($ref));

            if ($cleanRef === $cleanInvoiceNumber ||
                (strlen($cleanRef) > 4 && str_contains($cleanInvoiceNumber, $cleanRef))) {
                return 35;
            }
        }

        return 0;
    }

    /**
     * Extract potential reference numbers from transaction description
     */
    private function extractReferenceNumbers(string $description): array
    {
        $references = [];

        // Common invoice number patterns
        $patterns = [
            '/(?:inv|invoice|ref|reference)[\s\-_#:]*([a-z0-9\-_]+)/i',
            '/\b([a-z]{2,4}[\-_]?\d{3,8})\b/i', // Pattern like "INV-12345" or "AB1234"
            '/\b(\d{4,8})\b/', // Plain numbers 4-8 digits
            '/(?:^|\s)([a-z0-9]{5,15})(?:\s|$)/i', // Standalone alphanumeric codes
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $description, $matches)) {
                $references = array_merge($references, $matches[1]);
            }
        }

        // Clean and filter references
        $references = array_filter(
            array_unique($references),
            fn ($ref) => strlen(trim($ref)) >= 3 && strlen(trim($ref)) <= 15
        );

        return array_values($references);
    }

    /**
     * Check if transaction amount is likely a variant of the invoice amount
     * (e.g., early payment discount, bank fees, partial payment)
     */
    private function isLikelyPaymentVariant(float $transactionAmount, float $invoiceAmount): bool
    {
        // Common early payment discount percentages: 2%, 3%, 5%
        $discounts = [0.02, 0.03, 0.05];
        foreach ($discounts as $discount) {
            $discountedAmount = $invoiceAmount * (1 - $discount);
            if (abs($transactionAmount - $discountedAmount) < 0.01) {
                return true;
            }
        }

        // Common bank transfer fees: €1-€10
        $commonFees = [1.00, 1.50, 2.00, 2.50, 3.00, 5.00, 7.50, 10.00];
        foreach ($commonFees as $fee) {
            if (abs($transactionAmount - ($invoiceAmount - $fee)) < 0.01 ||
                abs($transactionAmount - ($invoiceAmount + $fee)) < 0.01) {
                return true;
            }
        }

        // Round number partial payments (50%, 25%, 75%, etc.)
        $partialPercents = [0.25, 0.33, 0.50, 0.66, 0.75];
        foreach ($partialPercents as $percent) {
            $partialAmount = $invoiceAmount * $percent;
            if (abs($transactionAmount - $partialAmount) < ($invoiceAmount * 0.02)) { // Within 2% of partial amount
                return true;
            }
        }

        return false;
    }

    /**
     * Comprehensive date matching including payment_date from invoice
     */
    private function scoreDateMatching(BankTransaction $transaction, Invoice $invoice): int
    {
        $score = 0;

        // HIGHEST PRIORITY: Exact payment_date match (if invoice has payment_date recorded)
        if ($invoice->payment_date && $invoice->payment_status === 'paid') {
            $paymentDateDiff = abs($transaction->transaction_date->diffInDays($invoice->payment_date));

            if ($paymentDateDiff === 0) {
                // Exact match on recorded payment date - highest confidence
                return 45;
            } elseif ($paymentDateDiff <= 1) {
                // Within 1 day of recorded payment (bank processing delays)
                return 40;
            } elseif ($paymentDateDiff <= 3) {
                // Within 3 days of recorded payment (weekend/holiday processing)
                return 35;
            }
            // If we have a payment_date but it's far from transaction, continue with invoice_date logic
        }

        // SECONDARY: Invoice date proximity (standard logic for unpaid or no payment_date)
        $daysDiff = $transaction->transaction_date->diffInDays($invoice->invoice_date, false);

        if ($daysDiff < 0) {
            // Payment before invoice - should not happen, heavily penalize
            $score -= 20;
        } elseif ($daysDiff <= 3) {
            // Very quick payment
            $score += 35;
        } elseif ($daysDiff <= 7) {
            // Quick payment
            $score += 30;
        } elseif ($daysDiff <= 14) {
            // Standard quick payment
            $score += 25;
        } elseif ($daysDiff <= 30) {
            // Standard 30-day payment terms
            $score += 20;
        } elseif ($daysDiff <= 60) {
            // Slower payment
            $score += 10;
        } elseif ($daysDiff <= 90) {
            // Very slow payment
            $score += 5;
        }
        // Beyond 90 days gets no bonus

        return $score;
    }

    /**
     * Get human-readable date match reasons (prioritizes payment_date)
     */
    private function getDateMatchReasons(BankTransaction $transaction, Invoice $invoice): array
    {
        $reasons = [];

        // Check for payment_date match first (highest priority)
        if ($invoice->payment_date && $invoice->payment_status === 'paid') {
            $paymentDateDiff = abs($transaction->transaction_date->diffInDays($invoice->payment_date));

            if ($paymentDateDiff === 0) {
                $reasons[] = '🎯 Exact match with recorded payment date';

                return $reasons; // Stop here - this is definitive
            } elseif ($paymentDateDiff <= 1) {
                $reasons[] = '🎯 Transaction within 1 day of recorded payment date';

                return $reasons;
            } elseif ($paymentDateDiff <= 3) {
                $reasons[] = '🎯 Transaction within 3 days of recorded payment date (processing delays)';

                return $reasons;
            }
            // Continue to invoice_date logic if payment_date doesn't match well
        }

        // Fallback to invoice_date matching
        $daysDiff = $transaction->transaction_date->diffInDays($invoice->invoice_date, false);

        if ($daysDiff < 0) {
            $reasons[] = '⚠️ Payment appears before invoice date';
        } elseif ($daysDiff <= 3) {
            $reasons[] = 'Payment within 3 days of invoice';
        } elseif ($daysDiff <= 7) {
            $reasons[] = 'Payment within 1 week of invoice';
        } elseif ($daysDiff <= 14) {
            $reasons[] = 'Payment within 2 weeks of invoice';
        } elseif ($daysDiff <= 30) {
            $reasons[] = 'Payment within standard 30-day terms';
        } elseif ($daysDiff <= 60) {
            $reasons[] = 'Payment within 60 days of invoice';
        } elseif ($daysDiff <= 90) {
            $reasons[] = 'Payment within 90 days of invoice';
        }

        return $reasons;
    }

    /**
     * Get candidate invoices for matching using enhanced search strategy
     */
    private function getInvoiceCandidates(BankTransaction $transaction, ?int $filterSupplierId = null): Collection
    {
        // Strategy 1: Look for invoices with payment_date matching the transaction date
        $paymentDateMatches = Invoice::where('payment_date', $transaction->transaction_date)
            ->where('payment_status', 'paid')
            ->whereNull('vat_return_id')
            ->when($filterSupplierId, function ($query) use ($filterSupplierId) {
                return $query->where('supplier_id', $filterSupplierId);
            })
            ->get();

        // Strategy 2: Look for invoices with payment_date within 3 days (processing delays)
        $paymentDateNear = Invoice::whereBetween('payment_date', [
            $transaction->transaction_date->subDays(3),
            $transaction->transaction_date->addDays(3),
        ])
            ->where('payment_status', 'paid')
            ->whereNull('vat_return_id')
            ->when($filterSupplierId, function ($query) use ($filterSupplierId) {
                return $query->where('supplier_id', $filterSupplierId);
            })
            ->get();

        // Strategy 3: Traditional invoice_date search (for unpaid invoices or invoices without payment_date)
        $dateFrom = $transaction->transaction_date->subDays(90); // Look back up to 90 days
        $dateTo = $transaction->transaction_date; // Up to the transaction date

        $invoiceDateMatches = Invoice::whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->whereNull('vat_return_id') // Not yet processed in VAT return
            ->where(function ($query) {
                // Either unpaid or no payment_date recorded
                $query->whereIn('payment_status', ['pending', 'overdue', 'partial'])
                    ->orWhereNull('payment_date');
            })
            ->when($filterSupplierId, function ($query) use ($filterSupplierId) {
                return $query->where('supplier_id', $filterSupplierId);
            })
            ->get();

        // Combine all strategies and remove duplicates
        $allInvoices = $paymentDateMatches
            ->merge($paymentDateNear)
            ->merge($invoiceDateMatches)
            ->unique('id');

        return $allInvoices;
    }

    /**
     * Detect supplier from transaction description using learned patterns
     */
    public function detectSupplierFromDescription(BankTransaction $transaction): ?array
    {
        // Try exact fingerprint match first
        $supplier = ReconciliationRule::findSupplierByDescription($transaction->description);

        if ($supplier) {
            // Get the rule that matched
            $fingerprint = ReconciliationRule::createDescriptionFingerprint($transaction->description);
            $rule = ReconciliationRule::where('description_fingerprint', $fingerprint)
                ->where('supplier_id', $supplier->id)
                ->orderByDesc('confidence_score')
                ->first();

            return [
                'supplier' => $supplier,
                'rule' => $rule,
                'match_type' => 'exact_fingerprint',
                'confidence' => $rule->confidence_score ?? 50,
            ];
        }

        // Fallback to pattern matching (existing logic)
        $rules = ReconciliationRule::active()
            ->highConfidence()
            ->orderByDesc('confidence_score')
            ->orderByDesc('match_count')
            ->get();

        foreach ($rules as $rule) {
            if ($this->matchesPattern($transaction->description, $rule->match_pattern)) {
                return [
                    'supplier' => $rule->supplier,
                    'rule' => $rule,
                    'match_type' => 'pattern',
                    'confidence' => $rule->confidence_score,
                ];
            }
        }

        return null;
    }

    /**
     * Get enhanced potential matches with supplier filtering
     */
    public function findPotentialMatchesWithSupplierFiltering(
        BankTransaction $transaction,
        ?int $filterSupplierId = null
    ): array {
        $matches = $this->findPotentialMatches($transaction, $filterSupplierId);

        // Detect supplier from description if not explicitly filtering
        $detectedSupplier = null;
        if (! $filterSupplierId) {
            $detection = $this->detectSupplierFromDescription($transaction);
            $detectedSupplier = $detection;
        }

        return [
            'matches' => $matches,
            'detected_supplier' => $detectedSupplier,
            'is_supplier_detected' => ! is_null($detectedSupplier),
        ];
    }

    /**
     * Mark a transaction as ignored
     */
    public function ignoreTransaction(BankTransaction $transaction, ?string $notes = null): bool
    {
        try {
            $transaction->update([
                'status' => 'ignored',
                'reconciliation_type' => null,
                'reconciliation_id' => null,
                'reconciliation_model' => null,
                'reconciled_at' => now(),
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Categorize a credit transaction
     */
    public function categorizeCreditTransaction(BankTransaction $transaction, string $category, ?string $notes = null): bool
    {
        try {
            // Validate that this is a credit transaction
            if (! $transaction->isCreditTransaction()) {
                return false;
            }

            // Validate category
            $validCategories = array_keys(BankTransaction::getCreditCategories());
            if (! in_array($category, $validCategories)) {
                return false;
            }

            $transaction->update([
                'credit_category' => $category,
                'status' => 'categorized',
                'reconciliation_type' => 'credit_categorization',
                'reconciled_at' => now(),
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            // Learn from this categorization for future predictions
            $this->learnFromCreditCategorization($transaction, $category);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Automatically predict credit category based on transaction description
     */
    public function predictCreditCategory(BankTransaction $transaction): ?array
    {
        if (! $transaction->isCreditTransaction()) {
            return null;
        }

        $description = strtolower($transaction->description);

        // Card lodgement patterns
        // Check for MTBTS bank terminal codes (high confidence)
        if (preg_match('/^mtbts/i', $description)) {
            return [
                'category' => 'card_lodgement',
                'description' => 'Card Lodgements',
                'confidence' => 90,
                'reason' => 'MTBTS bank terminal transaction code detected',
                'icon' => '💳',
                'color' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            ];
        }

        // Check for general card/POS keywords
        if (preg_match('/(card|pos|terminal|merchant|payment|visa|mastercard|contactless)/i', $description) ||
            preg_match('/till/i', $description)) {
            return [
                'category' => 'card_lodgement',
                'description' => 'Card Lodgements',
                'confidence' => 85,
                'reason' => 'Contains card/POS terminal keywords',
                'icon' => '💳',
                'color' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            ];
        }

        // Cash lodgement patterns
        if (preg_match('/(cash|lodgement|deposit|branch|counter)/i', $description)) {
            return [
                'category' => 'cash_lodgement',
                'description' => 'Cash Lodgements',
                'confidence' => 80,
                'reason' => 'Contains cash/lodgement keywords',
                'icon' => '💰',
                'color' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            ];
        }

        // Rent patterns
        if (preg_match('/(rent|rental|lease|landlord|tenant|property)/i', $description)) {
            return [
                'category' => 'rent',
                'description' => 'Rent',
                'confidence' => 85,
                'reason' => 'Contains rent/property keywords',
                'icon' => '🏠',
                'color' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            ];
        }

        // Check learned patterns from reconciliation rules
        $rule = ReconciliationRule::where('reconciliation_type', 'credit_categorization')
            ->where(function ($query) use ($description) {
                $query->where('match_pattern', 'like', '%'.$description.'%')
                    ->orWhere('description_fingerprint', ReconciliationRule::createDescriptionFingerprint($description));
            })
            ->orderByDesc('confidence_score')
            ->first();

        if ($rule) {
            $categories = BankTransaction::getCreditCategories();

            return [
                'category' => $rule->credit_category ?? 'other_credit',
                'description' => $categories[$rule->credit_category] ?? 'Other Credits',
                'confidence' => $rule->confidence_score,
                'reason' => 'Learned from previous categorizations',
                'icon' => '🎯',
                'color' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            ];
        }

        // Default to other credit with low confidence
        return [
            'category' => 'other_credit',
            'description' => 'Other Credits',
            'confidence' => 30,
            'reason' => 'No specific pattern matched',
            'icon' => '📈',
            'color' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
        ];
    }

    /**
     * Learn from credit categorization for future predictions
     */
    private function learnFromCreditCategorization(BankTransaction $transaction, string $category): void
    {
        try {
            $fingerprint = ReconciliationRule::createDescriptionFingerprint($transaction->description);

            if (! empty($fingerprint) && strlen($fingerprint) >= 3) {
                // Check if rule already exists
                $existingRule = ReconciliationRule::where('description_fingerprint', $fingerprint)
                    ->where('reconciliation_type', 'credit_categorization')
                    ->first();

                if ($existingRule) {
                    $existingRule->recordMatch();
                    $existingRule->update(['credit_category' => $category]);
                } else {
                    // Create new learning rule
                    ReconciliationRule::create([
                        'supplier_id' => null,
                        'expense_category' => null,
                        'expense_description' => null,
                        'credit_category' => $category,
                        'reconciliation_type' => 'credit_categorization',
                        'is_non_supplier_expense' => false,
                        'match_pattern' => $transaction->description,
                        'description_fingerprint' => $fingerprint,
                        'match_count' => 1,
                        'last_matched_at' => now(),
                        'confidence_score' => 70, // Start with good confidence for credit categorization
                        'priority' => 50,
                        'auto_approve' => false,
                    ]);
                }
            }
        } catch (\Exception $e) {
            logger()->warning('Failed to learn from credit categorization', [
                'transaction_id' => $transaction->id,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create expense from transaction
     */
    public function createExpenseFromTransaction(BankTransaction $transaction, array $expenseData): bool
    {
        try {
            // Determine if this is a non-supplier expense
            $isNonSupplier = $expenseData['is_non_supplier'] ?? false;

            // Generate appropriate invoice number
            $prefix = $isNonSupplier ? 'NSE' : 'EXP'; // Non-Supplier Expense vs regular Expense
            $invoiceNumber = $expenseData['invoice_number'] ?? $prefix.'-'.date('Y').'-'.str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);

            // Build notes with context
            $notes = 'Created from bank transaction: '.$transaction->description;
            if ($isNonSupplier) {
                $notes .= ' [Non-supplier expense]';
            }

            // Create a new invoice/expense record
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'supplier_id' => $isNonSupplier ? null : ($expenseData['supplier_id'] ?? null),
                'supplier_name' => $expenseData['supplier_name'],
                'invoice_date' => $transaction->transaction_date,
                'due_date' => $transaction->transaction_date,
                'total_amount' => $transaction->debit_amount > 0 ? $transaction->debit_amount : $transaction->credit_amount,
                'subtotal' => $expenseData['subtotal'] ?? 0,
                'vat_amount' => $expenseData['vat_amount'] ?? 0,
                'payment_status' => 'paid',
                'payment_date' => $transaction->transaction_date,
                'payment_method' => 'bank_transfer',
                'expense_category' => $expenseData['category'] ?? 'general',
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Create learning rule for non-supplier expenses
            if ($isNonSupplier && ! empty($expenseData['category']) && ! empty($expenseData['expense_description'])) {
                $this->createNonSupplierLearningRule(
                    $transaction->description,
                    $expenseData['category'],
                    $expenseData['expense_description']
                );
            }

            // Link the transaction to this new expense
            return $this->reconcileWithInvoice($transaction, $invoice, 'Created expense from transaction');

        } catch (\Exception $e) {
            \Log::error('Failed to create expense from transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'expenseData' => $expenseData,
            ]);

            return false;
        }
    }

    /**
     * Create or update a learning rule for non-supplier expenses
     */
    private function createNonSupplierLearningRule(string $description, string $category, string $expenseDescription): void
    {
        try {
            // Check if a rule already exists for this description pattern
            $existingRule = ReconciliationRule::findNonSupplierExpenseByDescription($description);

            if ($existingRule) {
                // Update existing rule - increment confidence if it matches
                if ($existingRule->expense_category === $category) {
                    $existingRule->recordMatch();
                } else {
                    // Different category - create a new rule or lower confidence
                    $existingRule->update(['confidence_score' => max(30, $existingRule->confidence_score - 10)]);

                    // Create new rule for this pattern/category combination
                    ReconciliationRule::createNonSupplierRule($description, $category, $expenseDescription);
                }
            } else {
                // Create new learning rule
                ReconciliationRule::createNonSupplierRule($description, $category, $expenseDescription);
            }

            \Log::info('Created/updated non-supplier learning rule', [
                'description' => $description,
                'category' => $category,
                'expense_description' => $expenseDescription,
            ]);

        } catch (\Exception $e) {
            \Log::warning('Failed to create non-supplier learning rule', [
                'description' => $description,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Undo reconciliation
     */
    public function undoReconciliation(BankTransaction $transaction): bool
    {
        try {
            $transaction->update([
                'status' => 'pending',
                'reconciliation_type' => null,
                'reconciliation_id' => null,
                'reconciliation_model' => null,
                'reconciled_at' => null,
                'notes' => null,
                'user_id' => null,
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reconcile a bank transaction with multiple invoices
     */
    public function reconcileWithMultipleInvoices(
        BankTransaction $transaction,
        array $allocations
    ): bool {
        DB::beginTransaction();
        try {
            $totalAllocated = 0;
            $transactionAmount = $transaction->debit_amount ?: $transaction->credit_amount;

            // Validate allocations
            foreach ($allocations as $allocation) {
                if (! isset($allocation['invoice_id']) || ! isset($allocation['amount'])) {
                    throw new \InvalidArgumentException('Each allocation must have invoice_id and amount');
                }

                $invoice = Invoice::find($allocation['invoice_id']);
                if (! $invoice) {
                    throw new \InvalidArgumentException("Invoice {$allocation['invoice_id']} not found");
                }

                if ($allocation['amount'] <= 0) {
                    throw new \InvalidArgumentException('Allocation amount must be positive');
                }

                $totalAllocated += $allocation['amount'];
            }

            // Create allocations
            foreach ($allocations as $allocation) {
                BankTransactionAllocation::create([
                    'bank_transaction_id' => $transaction->id,
                    'invoice_id' => $allocation['invoice_id'],
                    'allocated_amount' => $allocation['amount'],
                    'allocation_type' => $allocation['type'] ?? 'standard',
                    'notes' => $allocation['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                // Update invoice payment status
                $this->updateInvoicePaymentStatus($allocation['invoice_id']);
            }

            // Determine transaction status based on allocation
            $status = $this->determineTransactionStatus($transactionAmount, $totalAllocated);

            // Update transaction
            $transaction->update([
                'status' => $status,
                'reconciled_at' => now(),
                'user_id' => auth()->id(),
                'reconciliation_type' => 'multi_invoice',
                'notes' => 'Allocated to '.count($allocations).' invoices',
            ]);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Find invoice combinations that match transaction amount
     */
    public function findInvoiceCombinations(
        BankTransaction $transaction,
        float $tolerance = 0.01
    ): array {
        $targetAmount = $transaction->debit_amount ?: $transaction->credit_amount;
        $candidates = $this->getInvoiceCandidates($transaction);
        $combinations = [];

        // Limit candidates to prevent timeout - take only the most relevant ones
        // Sort by amount difference from target and take top 20
        $sortedCandidates = $candidates->sortBy(function ($invoice) use ($targetAmount) {
            return abs($invoice->total_amount - $targetAmount);
        })->take(20);

        // Single invoice exact matches
        foreach ($sortedCandidates as $invoice) {
            if (abs($invoice->total_amount - $targetAmount) <= $tolerance) {
                $combinations[] = [
                    'invoices' => [$invoice],
                    'total' => $invoice->total_amount,
                    'difference' => $targetAmount - $invoice->total_amount,
                    'confidence' => 95,
                    'match_type' => 'exact_single',
                ];
            }
        }

        // Only try multi-invoice combinations if we have reasonable number of candidates
        // and no perfect single matches found
        if (count($combinations) < 3 && $sortedCandidates->count() > 1 && $sortedCandidates->count() <= 20) {
            // Limit to max 3 invoices for better performance
            $this->findCombinationsRecursive(
                $sortedCandidates->values()->all(),
                $targetAmount,
                [],
                0,
                $combinations,
                $tolerance,
                3, // Reduced from 5 to 3 for performance
                100 // Max combinations to prevent timeout
            );
        }

        // Sort by confidence and difference
        usort($combinations, function ($a, $b) {
            if ($a['confidence'] == $b['confidence']) {
                return abs($a['difference']) <=> abs($b['difference']);
            }

            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($combinations, 0, 10); // Return top 10 combinations
    }

    /**
     * Recursive helper to find invoice combinations
     */
    private function findCombinationsRecursive(
        array $invoices,
        float $targetAmount,
        array $currentCombination,
        float $currentTotal,
        array &$combinations,
        float $tolerance,
        int $maxInvoices,
        int $maxCombinations = 100
    ): void {
        // Stop if we've reached max invoices or max combinations
        if (count($currentCombination) >= $maxInvoices || count($combinations) >= $maxCombinations) {
            return;
        }

        foreach ($invoices as $index => $invoice) {
            // Early termination if we already have enough combinations
            if (count($combinations) >= $maxCombinations) {
                return;
            }

            $newTotal = $currentTotal + $invoice->total_amount;
            $newCombination = array_merge($currentCombination, [$invoice]);

            // Check if this combination is close to target
            $difference = $targetAmount - $newTotal;
            if (abs($difference) <= $tolerance) {
                $combinations[] = [
                    'invoices' => $newCombination,
                    'total' => $newTotal,
                    'difference' => $difference,
                    'confidence' => $this->calculateCombinationConfidence($newCombination, $difference),
                    'match_type' => count($newCombination) == 1 ? 'exact_single' : 'exact_multiple',
                ];
            }

            // Continue searching if we haven't exceeded the target (with some margin)
            // and we have room for more invoices
            if ($newTotal < $targetAmount + 100 && count($newCombination) < $maxInvoices) {
                $remainingInvoices = array_slice($invoices, $index + 1);
                $this->findCombinationsRecursive(
                    $remainingInvoices,
                    $targetAmount,
                    $newCombination,
                    $newTotal,
                    $combinations,
                    $tolerance,
                    $maxInvoices,
                    $maxCombinations
                );
            }
        }
    }

    /**
     * Calculate confidence for an invoice combination
     */
    private function calculateCombinationConfidence(array $invoices, float $difference): int
    {
        $baseConfidence = 90;

        // Exact match gets highest confidence
        if (abs($difference) < 0.01) {
            $baseConfidence = 95;
        } elseif (abs($difference) < 1.00) {
            $baseConfidence = 85;
        } elseif (abs($difference) < 10.00) {
            $baseConfidence = 75;
        }

        // Reduce confidence for more invoices (complexity penalty)
        $complexityPenalty = (count($invoices) - 1) * 5;
        $baseConfidence -= min($complexityPenalty, 20);

        // Check if all invoices are from the same supplier
        $supplierIds = array_unique(array_map(fn ($inv) => $inv->supplier_id, $invoices));
        if (count($supplierIds) == 1) {
            $baseConfidence += 10; // Bonus for same supplier
        }

        return max(min($baseConfidence, 100), 0);
    }

    /**
     * Update invoice payment status based on allocations
     */
    private function updateInvoicePaymentStatus(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return;
        }

        $totalAllocated = $invoice->bankAllocations()->sum('allocated_amount');
        $outstanding = $invoice->total_amount - $totalAllocated;

        if (abs($outstanding) < 0.01) {
            $invoice->update([
                'payment_status' => 'paid',
                'payment_date' => now(),
            ]);
        } elseif ($totalAllocated > 0) {
            $invoice->update([
                'payment_status' => 'partial',
            ]);
        }
    }

    /**
     * Determine transaction status based on allocation
     */
    private function determineTransactionStatus(float $transactionAmount, float $allocated): string
    {
        $difference = abs($transactionAmount - $allocated);

        if ($difference < 0.01) {
            return 'fully_matched';
        } elseif ($allocated < $transactionAmount) {
            return 'partially_matched';
        } else {
            return 'over_allocated';
        }
    }

    /**
     * Bulk categorize multiple credit transactions
     */
    public function bulkCategorizeCreditTransactions(array $transactionIds, string $category): array
    {
        try {
            // Validate category
            $validCategories = array_keys(BankTransaction::getCreditCategories());
            if (! in_array($category, $validCategories)) {
                return [
                    'success' => false,
                    'message' => 'Invalid credit category provided.',
                    'processed' => 0,
                    'skipped' => count($transactionIds),
                ];
            }

            // Get credit transactions that can be processed
            $transactions = BankTransaction::whereIn('id', $transactionIds)
                ->where('credit_amount', '>', 0)
                ->where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhereNull('status')
                        ->orWhere('status', ''); // Handle empty status
                })
                ->get();

            $processed = 0;
            $skipped = count($transactionIds) - $transactions->count();
            $categoryDisplay = BankTransaction::getCreditCategories()[$category] ?? ucfirst(str_replace('_', ' ', $category));

            // Process each transaction
            foreach ($transactions as $transaction) {
                if ($this->categorizeCreditTransaction($transaction, $category, "Bulk categorized as {$categoryDisplay}")) {
                    $processed++;
                } else {
                    $skipped++;
                }
            }

            return [
                'success' => true,
                'processed' => $processed,
                'skipped' => $skipped,
                'category_display' => $categoryDisplay,
                'message' => "Successfully processed {$processed} transactions.",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error during bulk categorization: '.$e->getMessage(),
                'processed' => 0,
                'skipped' => count($transactionIds),
            ];
        }
    }

    /**
     * Bulk categorize multiple debit transactions as expenses
     */
    public function bulkCategorizeDebitTransactions(array $transactionIds, string $category): array
    {
        \Log::info('bulkCategorizeDebitTransactions starting', [
            'transactionIds' => $transactionIds,
            'category' => $category,
            'transactionCount' => count($transactionIds),
        ]);

        try {
            // Validate category exists in cost_categories
            $costCategory = CostCategory::where('code', $category)->where('is_active', true)->first();
            if (! $costCategory) {
                \Log::error('Invalid category provided', ['category' => $category]);

                return [
                    'success' => false,
                    'message' => 'Invalid expense category provided.',
                    'processed' => 0,
                    'skipped' => count($transactionIds),
                ];
            }

            \Log::info('Found cost category', ['category' => $costCategory->toArray()]);

            // Get debit transactions that can be processed
            $transactions = BankTransaction::whereIn('id', $transactionIds)
                ->where('debit_amount', '>', 0)
                ->where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhere('status', 'unmatched') // Include unmatched transactions
                        ->orWhereNull('status')
                        ->orWhere('status', ''); // Handle empty status
                })
                ->get();

            \Log::info('Found transactions to process', [
                'totalRequested' => count($transactionIds),
                'foundTransactions' => $transactions->count(),
                'transactionDetails' => $transactions->map(fn ($t) => [
                    'id' => $t->id,
                    'debit_amount' => $t->debit_amount,
                    'status' => $t->status,
                    'description' => $t->description,
                ])->toArray(),
            ]);

            $processed = 0;
            $skipped = count($transactionIds) - $transactions->count();

            // Process each transaction by creating an expense
            foreach ($transactions as $transaction) {
                \Log::info('Processing transaction', ['id' => $transaction->id, 'amount' => $transaction->debit_amount]);

                // Check if this is a non-supplier category (wages, rent, insurance, etc.)
                $isNonSupplier = in_array($category, ['WAGES', 'RENT', 'INSURANCE', 'UTILITIES']);

                $expenseData = [
                    'supplier_name' => $isNonSupplier ? 'Non-Supplier Expense' : 'Bulk Expense Entry',
                    'expense_description' => "Bulk categorized: {$costCategory->name}",
                    'category' => $category,
                    'subtotal' => $transaction->debit_amount,
                    'vat_amount' => 0, // Set VAT to 0 for bulk entries - can be adjusted manually later
                    'is_non_supplier' => $isNonSupplier,
                ];

                \Log::info('Creating expense with data', $expenseData);

                $expenseCreated = $this->createExpenseFromTransaction($transaction, $expenseData);

                if ($expenseCreated) {
                    $processed++;
                    \Log::info('Successfully created expense for transaction', ['id' => $transaction->id]);
                } else {
                    $skipped++;
                    \Log::error('Failed to create expense for transaction', ['id' => $transaction->id]);
                }
            }

            return [
                'success' => true,
                'processed' => $processed,
                'skipped' => $skipped,
                'category_display' => $costCategory->name,
                'message' => "Successfully processed {$processed} transactions as {$costCategory->name} expenses.",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error during bulk categorization: '.$e->getMessage(),
                'processed' => 0,
                'skipped' => count($transactionIds),
            ];
        }
    }
}
