<?php

namespace App\Livewire;

use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\ReconciliationRule;
use App\Services\BankReconciliationService;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class BankReconciliationPanel extends Component
{
    public BankTransaction $transaction;

    public Collection $suggestedMatches;

    public Collection $searchResults;

    public bool $showPanel = false;

    public bool $showCreateExpense = false;

    public string $searchQuery = '';

    public string $notes = '';

    public ?int $selectedInvoiceId = null;

    // Multi-invoice allocation properties
    public Collection $suggestedCombinations;

    public array $selectedAllocations = [];

    public bool $showMultiInvoiceMode = false;

    public float $totalAllocated = 0;

    public float $remainingAmount = 0;

    public Collection $suggestedMatchesForAllocation;

    // Multi-select invoice allocation properties
    public array $selectedInvoiceIds = [];

    public array $invoiceAllocations = []; // [invoice_id => amount]

    // Supplier filtering properties
    public ?array $detectedSupplier = null;

    public ?int $filterSupplierId = null;

    public bool $showAllSuppliers = false;

    // Create expense form data
    public string $expenseSupplierName = '';

    public string $expenseDescription = '';

    public bool $isNonSupplierExpense = false;

    public string $expenseCategory = 'general';

    public float $expenseSubtotal = 0;

    public float $expenseVatAmount = 0;

    public $availableCategories = [];

    public ?array $learnedSuggestion = null;

    // Credit categorization properties
    public string $selectedCreditCategory = '';

    public ?array $creditPrediction = null;

    protected BankReconciliationService $reconciliationService;

    public function boot(BankReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    public function mount()
    {
        $this->transaction = new BankTransaction;
        $this->suggestedMatches = collect();
        $this->searchResults = collect();
        $this->suggestedCombinations = collect();
        $this->suggestedMatchesForAllocation = collect();
        $this->selectedInvoiceIds = [];
        $this->invoiceAllocations = [];
        $this->loadCategories();
        $this->resetForm();
    }

    public function loadCategories()
    {
        $this->availableCategories = \App\Models\CostCategory::getForDropdown();
    }

    #[On('openReconciliationPanel')]
    public function openPanel($transactionId)
    {
        if (! $transactionId) {
            \Log::error('BankReconciliationPanel: No transaction ID provided');

            return;
        }

        \Log::info('BankReconciliationPanel: Opening panel for transaction', [
            'transaction_id' => $transactionId,
            'session_id' => session()->getId(),
        ]);

        $this->transaction = BankTransaction::findOrFail($transactionId);
        $this->showPanel = true;
        $this->showCreateExpense = false;
        $this->resetForm();
        $this->loadSuggestedMatches();

        // Pre-populate expense form with transaction data OR detect credit category
        if ($this->transaction->isCreditTransaction()) {
            $this->detectCreditCategory();
        } else {
            $this->detectExpenseType();
            $this->expenseSubtotal = $this->transaction->debit_amount > 0 ? $this->transaction->debit_amount : $this->transaction->credit_amount;
        }
    }

    public function closePanel()
    {
        $this->showPanel = false;
        $this->showCreateExpense = false;
        $this->resetForm();

        // Emit event to refresh the main reconciliation table
        $this->dispatch('reconciliationUpdated');
    }

    public function loadSuggestedMatches()
    {
        if ($this->transaction->exists) {
            // Check for supplier detection and filtering
            $result = $this->reconciliationService->findPotentialMatchesWithSupplierFiltering(
                $this->transaction,
                $this->showAllSuppliers ? null : $this->filterSupplierId
            );

            $this->suggestedMatches = $result['matches'];

            // Store detected supplier info if not showing all suppliers
            if (! $this->showAllSuppliers && $result['is_supplier_detected']) {
                $this->detectedSupplier = $result['detected_supplier'];
                $this->filterSupplierId = $this->detectedSupplier['supplier']->id;
            }

            // Don't load combinations automatically - let user trigger it if needed
            // $this->loadSuggestedCombinations();
        }
    }

    public function loadSuggestedCombinations()
    {
        if ($this->transaction->exists) {
            // This can be triggered manually when needed
            $combinations = $this->reconciliationService->findInvoiceCombinations($this->transaction);
            $this->suggestedCombinations = collect($combinations);
        }
    }

    public function searchInvoices()
    {
        if (strlen($this->searchQuery) < 2) {
            $this->searchResults = collect();

            return;
        }

        $this->searchResults = Invoice::where(function ($query) {
            $query->where('invoice_number', 'like', "%{$this->searchQuery}%")
                ->orWhere('supplier_name', 'like', "%{$this->searchQuery}%")
                ->orWhere('notes', 'like', "%{$this->searchQuery}%");
        })
            ->whereNull('vat_return_id') // Only unprocessed invoices
            ->limit(10)
            ->get()
            ->map(function ($invoice) {
                return [
                    'invoice' => $invoice,
                    'confidence' => $this->reconciliationService->findPotentialMatches($this->transaction)
                        ->firstWhere('invoice.id', $invoice->id)['confidence'] ?? 0,
                    'match_reasons' => ['Manual search result'],
                ];
            });
    }

    public function selectInvoice($invoiceId)
    {
        $this->selectedInvoiceId = $invoiceId;
    }

    public function toggleInvoiceSelection($invoiceId)
    {
        if (in_array($invoiceId, $this->selectedInvoiceIds)) {
            // Remove from selection
            $this->selectedInvoiceIds = array_values(array_filter($this->selectedInvoiceIds, fn ($id) => $id !== $invoiceId));
            unset($this->invoiceAllocations[$invoiceId]);
        } else {
            // Add to selection with smart amount calculation
            $this->selectedInvoiceIds[] = $invoiceId;
            $this->autoCalculateAllocation($invoiceId);
        }

        $this->calculateTotals();
    }

    public function matchWithInvoice($invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if ($this->reconciliationService->reconcileWithInvoice($this->transaction, $invoice, $this->notes)) {
            session()->flash('success', "Transaction matched with invoice #{$invoice->invoice_number}");
            $this->closePanel();
            $this->dispatch('reconciliationUpdated');
        } else {
            session()->flash('error', 'Failed to match transaction with invoice.');
        }
    }

    public function enableMultiInvoiceMode()
    {
        $this->showMultiInvoiceMode = true;

        // Initialize new multi-select system
        $this->selectedInvoiceIds = [];
        $this->invoiceAllocations = [];

        // Clear old allocation system for backward compatibility
        $this->selectedAllocations = [];

        $this->calculateTotals();

        // Load suggested combinations only when entering multi-invoice mode
        if ($this->suggestedCombinations->isEmpty()) {
            $this->loadSuggestedCombinations();
        }
    }

    public function disableMultiInvoiceMode()
    {
        $this->showMultiInvoiceMode = false;

        // Clear both systems
        $this->selectedInvoiceIds = [];
        $this->invoiceAllocations = [];
        $this->selectedAllocations = [];

        $this->calculateTotals();
    }

    public function selectCombination($combination)
    {
        $this->showMultiInvoiceMode = true;
        $this->selectedAllocations = [];

        foreach ($combination['invoices'] as $invoice) {
            $this->selectedAllocations[] = [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total_amount,
                'notes' => '',
                'invoice' => $invoice,
                'suggested_matches' => collect(),
                'is_editing' => false,
            ];
        }

        $this->calculateTotals();
    }

    public function addNewAllocation()
    {
        $this->selectedAllocations[] = [
            'invoice_id' => null,
            'amount' => 0,
            'notes' => '',
            'invoice' => null,
            'suggested_matches' => collect(),
            'is_editing' => true,
        ];
        $this->calculateTotals();
        $this->loadSuggestedMatchesForAllocation(count($this->selectedAllocations) - 1);
    }

    public function removeAllocation($index)
    {
        if (isset($this->selectedAllocations[$index])) {
            unset($this->selectedAllocations[$index]);
            $this->selectedAllocations = array_values($this->selectedAllocations);
            $this->calculateTotals();
        }
    }

    public function updateAllocationInvoice($index, $invoiceId)
    {
        if (isset($this->selectedAllocations[$index]) && $invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice) {
                $this->selectedAllocations[$index]['invoice_id'] = $invoiceId;
                $this->selectedAllocations[$index]['invoice'] = $invoice;

                // Auto-fill amount if not set
                if (! $this->selectedAllocations[$index]['amount']) {
                    $this->selectedAllocations[$index]['amount'] = $invoice->total_amount;
                }
            }
        }
        $this->calculateTotals();
    }

    public function calculateTotals()
    {
        // Handle both old allocation system and new multi-select system
        if (! empty($this->invoiceAllocations)) {
            // New multi-select system
            $this->totalAllocated = array_sum($this->invoiceAllocations);
        } else {
            // Old allocation system (for backward compatibility)
            $this->totalAllocated = array_sum(array_column($this->selectedAllocations, 'amount'));
        }

        $transactionAmount = $this->transaction->exists ? $this->getTransactionAmountProperty() : 0;
        $this->remainingAmount = $transactionAmount - $this->totalAllocated;
    }

    public function autoCalculateAllocation($invoiceId)
    {
        $invoice = Invoice::find($invoiceId);
        if (! $invoice || ! $this->transaction->exists) {
            return;
        }

        $transactionAmount = $this->getTransactionAmountProperty();
        $currentlyAllocated = array_sum($this->invoiceAllocations);
        $remaining = $transactionAmount - $currentlyAllocated;

        // Smart allocation: use the smaller of invoice amount or remaining transaction amount
        $suggestedAmount = min($invoice->total_amount, abs($remaining));

        $this->invoiceAllocations[$invoiceId] = $suggestedAmount;
    }

    public function updateInvoiceAllocation($invoiceId, $amount)
    {
        if (in_array($invoiceId, $this->selectedInvoiceIds)) {
            $this->invoiceAllocations[$invoiceId] = max(0, (float) $amount);
            $this->calculateTotals();
        }
    }

    public function selectAllInvoices()
    {
        $availableInvoices = $this->getAvailableInvoicesForMultiSelect();

        foreach ($availableInvoices as $match) {
            $invoiceId = $match['invoice']->id;
            if (! in_array($invoiceId, $this->selectedInvoiceIds)) {
                $this->selectedInvoiceIds[] = $invoiceId;
                $this->autoCalculateAllocation($invoiceId);
            }
        }

        $this->calculateTotals();
    }

    public function deselectAllInvoices()
    {
        $this->selectedInvoiceIds = [];
        $this->invoiceAllocations = [];
        $this->calculateTotals();
    }

    public function showAllSuppliers()
    {
        $this->showAllSuppliers = true;
        $this->filterSupplierId = null;
        $this->loadSuggestedMatches();

        // Reset selections when changing filter
        $this->selectedInvoiceIds = [];
        $this->invoiceAllocations = [];
        $this->calculateTotals();
    }

    public function filterByDetectedSupplier()
    {
        $this->showAllSuppliers = false;

        if ($this->detectedSupplier) {
            $this->filterSupplierId = $this->detectedSupplier['supplier']->id;
        }

        $this->loadSuggestedMatches();

        // Reset selections when changing filter
        $this->selectedInvoiceIds = [];
        $this->invoiceAllocations = [];
        $this->calculateTotals();
    }

    public function getIsSupplierFilteredProperty()
    {
        return ! $this->showAllSuppliers && $this->filterSupplierId;
    }

    public function updatedExpenseCategory()
    {
        // Auto-populate expense description when category changes for non-supplier expenses
        if ($this->isNonSupplierExpense && ! empty($this->expenseCategory)) {
            $this->expenseDescription = $this->availableCategories[$this->expenseCategory] ?? 'Non-supplier expense';
        }
    }

    public function loadSuggestedMatchesForAllocation($index)
    {
        if (! $this->transaction->exists || ! isset($this->selectedAllocations[$index])) {
            return;
        }

        // Get already allocated invoice IDs to exclude them
        $allocatedInvoiceIds = array_filter(array_column($this->selectedAllocations, 'invoice_id'));

        // Get all potential matches
        $allMatches = $this->reconciliationService->findPotentialMatches($this->transaction);

        // Filter out already allocated invoices and sort by how well they match remaining amount
        $remainingAmount = $this->remainingAmount + $this->selectedAllocations[$index]['amount']; // Include current allocation's amount

        $suggestedMatches = $allMatches
            ->filter(function ($match) use ($allocatedInvoiceIds) {
                return ! in_array($match['invoice']->id, $allocatedInvoiceIds);
            })
            ->map(function ($match) use ($remainingAmount) {
                // Recalculate confidence based on remaining amount
                $amountDiff = abs($match['invoice']->total_amount - abs($remainingAmount));
                $amountMatchScore = 0;

                if ($amountDiff < 0.01) {
                    $amountMatchScore = 50; // Perfect match for remaining
                } elseif ($amountDiff <= abs($remainingAmount) * 0.05) {
                    $amountMatchScore = 40; // Within 5%
                } elseif ($amountDiff <= abs($remainingAmount) * 0.10) {
                    $amountMatchScore = 30; // Within 10%
                } elseif ($match['invoice']->total_amount <= abs($remainingAmount)) {
                    $amountMatchScore = 20; // Fits within remaining
                } else {
                    $amountMatchScore = 10; // Over remaining
                }

                // Combine with original confidence but weight amount match higher for allocations
                $match['allocation_confidence'] = min(100, $amountMatchScore + ($match['confidence'] * 0.5));
                $match['fits_remaining'] = $match['invoice']->total_amount <= abs($remainingAmount);

                return $match;
            })
            ->sortByDesc('allocation_confidence')
            ->take(5);

        $this->selectedAllocations[$index]['suggested_matches'] = $suggestedMatches;
    }

    public function selectInvoiceForAllocation($index, $invoiceId)
    {
        if (! isset($this->selectedAllocations[$index])) {
            return;
        }

        $invoice = Invoice::find($invoiceId);
        if ($invoice) {
            // Calculate the amount to allocate - either the full invoice amount or remaining transaction amount
            $currentRemaining = $this->remainingAmount + ($this->selectedAllocations[$index]['amount'] ?? 0);
            $optimalAmount = min($invoice->total_amount, abs($currentRemaining));

            $this->selectedAllocations[$index]['invoice_id'] = $invoiceId;
            $this->selectedAllocations[$index]['invoice'] = $invoice;
            $this->selectedAllocations[$index]['amount'] = $optimalAmount;
            $this->selectedAllocations[$index]['is_editing'] = false;

            $this->calculateTotals();

            // Refresh suggestions for other allocations if needed
            foreach ($this->selectedAllocations as $i => $allocation) {
                if ($i !== $index && isset($allocation['is_editing']) && $allocation['is_editing']) {
                    $this->loadSuggestedMatchesForAllocation($i);
                }
            }
        }
    }

    public function editAllocation($index)
    {
        if (isset($this->selectedAllocations[$index])) {
            $this->selectedAllocations[$index]['is_editing'] = true;
            $this->loadSuggestedMatchesForAllocation($index);
        }
    }

    public function hasValidAllocations()
    {
        return count(array_filter($this->selectedAllocations, function ($allocation) {
            return isset($allocation['invoice_id']) && $allocation['invoice_id'] &&
                   isset($allocation['amount']) && $allocation['amount'] > 0;
        })) > 0;
    }

    public function confirmMultiInvoiceAllocation()
    {
        // Handle both old and new systems for backward compatibility
        if (! empty($this->selectedInvoiceIds)) {
            // New multi-select system
            return $this->confirmMultiSelectAllocation();
        }

        // Old allocation system
        $validAllocations = array_filter($this->selectedAllocations, function ($allocation) {
            return isset($allocation['invoice_id']) && $allocation['invoice_id'] &&
                   isset($allocation['amount']) && $allocation['amount'] > 0;
        });

        if (empty($validAllocations)) {
            session()->flash('error', 'Please add at least one valid allocation.');

            return;
        }

        try {
            if ($this->reconciliationService->reconcileWithMultipleInvoices($this->transaction, $validAllocations)) {
                session()->flash('success', 'Transaction allocated across '.count($validAllocations).' invoices');
                $this->closePanel();
                $this->dispatch('reconciliationUpdated');
            } else {
                session()->flash('error', 'Failed to process multi-invoice allocation.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error: '.$e->getMessage());
        }
    }

    public function confirmMultiSelectAllocation()
    {
        // Validate multi-select allocations
        if (empty($this->selectedInvoiceIds)) {
            session()->flash('error', 'Please select at least one invoice.');

            return;
        }

        // Convert to the format expected by the service
        $validAllocations = [];
        foreach ($this->selectedInvoiceIds as $invoiceId) {
            $amount = $this->invoiceAllocations[$invoiceId] ?? 0;
            if ($amount > 0) {
                $invoice = Invoice::find($invoiceId);
                if ($invoice) {
                    $validAllocations[] = [
                        'invoice_id' => $invoiceId,
                        'amount' => $amount,
                        'notes' => '',
                        'invoice' => $invoice,
                    ];
                }
            }
        }

        if (empty($validAllocations)) {
            session()->flash('error', 'Please set valid amounts for selected invoices.');

            return;
        }

        try {
            if ($this->reconciliationService->reconcileWithMultipleInvoices($this->transaction, $validAllocations)) {
                session()->flash('success', 'Transaction allocated across '.count($validAllocations).' invoices');
                $this->closePanel();
                $this->dispatch('reconciliationUpdated');
            } else {
                session()->flash('error', 'Failed to process multi-invoice allocation.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error: '.$e->getMessage());
        }
    }

    public function ignoreTransaction()
    {
        if ($this->reconciliationService->ignoreTransaction($this->transaction, $this->notes)) {
            session()->flash('success', 'Transaction marked as ignored.');
            $this->closePanel();
            $this->dispatch('reconciliationUpdated');
        } else {
            session()->flash('error', 'Failed to ignore transaction.');
        }
    }

    public function showCreateExpenseForm()
    {
        $this->showCreateExpense = true;
    }

    public function createExpense()
    {
        $rules = [
            'expenseCategory' => 'required|string',
            'expenseSubtotal' => 'required|numeric|min:0',
            'expenseVatAmount' => 'required|numeric|min:0',
        ];

        if ($this->isNonSupplierExpense) {
            // Auto-populate description from category if empty
            if (empty($this->expenseDescription)) {
                $this->expenseDescription = $this->availableCategories[$this->expenseCategory] ?? 'Non-supplier expense';
            }
            $rules['expenseDescription'] = 'nullable|string|max:255';
        } else {
            $rules['expenseSupplierName'] = 'required|string|max:255';
        }

        $this->validate($rules);

        $expenseData = [
            'supplier_name' => $this->isNonSupplierExpense ? $this->expenseDescription : $this->expenseSupplierName,
            'expense_description' => $this->expenseDescription,
            'category' => $this->expenseCategory,
            'subtotal' => $this->expenseSubtotal,
            'vat_amount' => $this->expenseVatAmount,
            'is_non_supplier' => $this->isNonSupplierExpense,
        ];

        if ($this->reconciliationService->createExpenseFromTransaction($this->transaction, $expenseData)) {
            session()->flash('success', 'Expense created and matched with transaction.');
            $this->closePanel();
            $this->dispatch('reconciliationUpdated');
        } else {
            session()->flash('error', 'Failed to create expense.');
        }
    }

    public function undoReconciliation()
    {
        if ($this->reconciliationService->undoReconciliation($this->transaction)) {
            session()->flash('success', 'Reconciliation undone.');
            $this->closePanel();
            $this->dispatch('reconciliationUpdated');
        } else {
            session()->flash('error', 'Failed to undo reconciliation.');
        }
    }

    private function resetForm()
    {
        $this->searchQuery = '';
        $this->notes = '';
        $this->selectedInvoiceId = null;
        $this->searchResults = collect();
        $this->suggestedCombinations = collect();
        $this->suggestedMatchesForAllocation = collect();
        $this->selectedAllocations = [];
        $this->showMultiInvoiceMode = false;
        $this->totalAllocated = 0;
        $this->remainingAmount = 0;

        // Reset new multi-select system
        $this->selectedInvoiceIds = [];
        $this->invoiceAllocations = [];

        // Reset supplier filtering
        $this->detectedSupplier = null;
        $this->filterSupplierId = null;
        $this->showAllSuppliers = false;

        $this->expenseSupplierName = '';
        $this->expenseDescription = '';
        $this->isNonSupplierExpense = false;
        $this->expenseCategory = 'OTHER'; // Use a valid category code
        $this->expenseSubtotal = 0;
        $this->expenseVatAmount = 0;
        $this->learnedSuggestion = null;

        // Reset credit categorization properties
        $this->selectedCreditCategory = '';
        $this->creditPrediction = null;
    }

    private function detectExpenseType()
    {
        if (! $this->transaction || ! $this->transaction->description) {
            return;
        }

        // First, check for learned patterns for non-supplier expenses
        $learnedRule = ReconciliationRule::findNonSupplierExpenseByDescription($this->transaction->description);
        if ($learnedRule && $learnedRule->confidence_score >= 60) {
            $this->isNonSupplierExpense = true;
            $this->expenseCategory = $learnedRule->expense_category;
            $this->expenseDescription = $learnedRule->expense_description;
            $this->expenseVatAmount = 0; // Most non-supplier expenses have no VAT

            // Store learned suggestion info for UI display
            $this->learnedSuggestion = [
                'category' => $learnedRule->expense_category,
                'description' => $learnedRule->expense_description,
                'confidence' => $learnedRule->confidence_score,
                'match_count' => $learnedRule->match_count,
            ];

            return;
        }

        // Check for existing wage rules (even if not marked as non-supplier)
        $fingerprint = ReconciliationRule::createDescriptionFingerprint($this->transaction->description);
        $existingWageRule = ReconciliationRule::where('description_fingerprint', $fingerprint)
            ->whereNull('supplier_id')
            ->where('expense_category', 'WAGES')
            ->where('match_count', '>=', 1)
            ->first();

        if ($existingWageRule) {
            $this->isNonSupplierExpense = true;
            $this->expenseCategory = 'WAGES';
            $this->expenseDescription = 'Wages & Salaries';
            $this->expenseVatAmount = 0;

            // Store learned suggestion info for UI display
            $this->learnedSuggestion = [
                'category' => 'WAGES',
                'description' => 'Wages & Salaries',
                'confidence' => $existingWageRule->confidence_score,
                'match_count' => $existingWageRule->match_count,
            ];

            return;
        }

        $description = strtolower($this->transaction->description);

        // Detect wage/payroll expenses
        if (preg_match('/\b(wage|wages|payroll|salary|salaries|staff pay|employee|paye)\b/i', $description)) {
            $this->isNonSupplierExpense = true;
            $this->expenseCategory = 'WAGES';
            $this->expenseDescription = 'Wages & Salaries';
            $this->expenseVatAmount = 0; // Wages typically have no VAT

            return;
        }

        // Detect tax payments
        if (preg_match('/\b(revenue|tax payment|vat payment|corporation tax|paye|prsi)\b/i', $description)) {
            $this->isNonSupplierExpense = true;
            $this->expenseCategory = 'OTHER';
            $this->expenseDescription = 'Tax Payment';
            $this->expenseVatAmount = 0;

            return;
        }

        // Detect bank fees
        if (preg_match('/\b(bank fee|bank charge|service charge|transaction fee|maintenance fee)\b/i', $description)) {
            $this->isNonSupplierExpense = true;
            $this->expenseCategory = 'OTHER';
            $this->expenseDescription = 'Bank Fees & Charges';

            return;
        }

        // Detect insurance
        if (preg_match('/\b(insurance|premium|policy)\b/i', $description)) {
            $this->isNonSupplierExpense = true;
            $this->expenseCategory = 'INSURANCE';
            $this->expenseDescription = 'Insurance Premium';
            $this->expenseVatAmount = 0; // Insurance typically exempt from VAT

            return;
        }

        // Default - try to extract supplier name
        $this->expenseSupplierName = $this->extractSupplierFromDescription($this->transaction->description);
    }

    private function extractSupplierFromDescription(string $description): string
    {
        // Simple logic to extract potential supplier name from transaction description
        // This can be enhanced with more sophisticated NLP techniques
        $words = explode(' ', $description);
        $potentialSupplier = '';

        // Look for capitalized words (likely proper nouns/company names)
        foreach ($words as $word) {
            // Skip empty strings and very short words
            if (empty($word) || strlen($word) <= 2) {
                continue;
            }

            // Check if first character is uppercase (potential company name)
            if (ctype_upper($word[0])) {
                $potentialSupplier .= $word.' ';
            }
        }

        return trim($potentialSupplier) ?: 'Unknown Supplier';
    }

    private function detectCreditCategory()
    {
        if (! $this->transaction || ! $this->transaction->description) {
            return;
        }

        // Get credit prediction from the service
        $this->creditPrediction = $this->reconciliationService->predictCreditCategory($this->transaction);

        if ($this->creditPrediction && $this->creditPrediction['confidence'] >= 60) {
            // Auto-select high confidence predictions
            $this->selectedCreditCategory = $this->creditPrediction['category'];
        }
    }

    public function updatedSelectedCreditCategory()
    {
        // This method ensures Livewire properly reacts to dropdown changes
        // The button state should automatically update via the reactive property
    }

    public function categorizeCredit()
    {
        if (! $this->selectedCreditCategory) {
            session()->flash('error', 'Please select a credit category.');

            return;
        }

        if ($this->reconciliationService->categorizeCreditTransaction($this->transaction, $this->selectedCreditCategory, $this->notes)) {
            session()->flash('success', "Transaction categorized as {$this->getCreditCategoryDisplayName($this->selectedCreditCategory)}.");
            $this->closePanel();
            $this->dispatch('reconciliationUpdated');
        } else {
            session()->flash('error', 'Failed to categorize credit transaction.');
        }
    }

    public function getCreditCategories()
    {
        return BankTransaction::getCreditCategories();
    }

    private function getCreditCategoryDisplayName(string $category): string
    {
        $categories = $this->getCreditCategories();

        return $categories[$category] ?? 'Unknown Category';
    }

    public function getTransactionAmountProperty()
    {
        return $this->transaction->debit_amount > 0 ? $this->transaction->debit_amount : $this->transaction->credit_amount;
    }

    public function getTransactionTypeProperty()
    {
        return $this->transaction->debit_amount > 0 ? 'expense' : 'income';
    }

    public function getAvailableInvoicesForAllocation()
    {
        if (! $this->transaction->exists) {
            return collect();
        }

        // Get candidate invoices using the same logic as the service
        return $this->reconciliationService->findPotentialMatches($this->transaction)
            ->merge($this->searchResults)
            ->pluck('invoice')
            ->unique('id')
            ->sortBy('invoice_date');
    }

    public function getAvailableInvoicesForMultiSelect()
    {
        if (! $this->transaction->exists) {
            return collect();
        }

        // Use the filtered matches that respect supplier filtering
        $allMatches = $this->suggestedMatches;

        // Add search results if available
        if ($this->searchResults->count() > 0) {
            $allMatches = $allMatches->merge($this->searchResults);
        }

        // Return unique invoices sorted by confidence, then by date
        return $allMatches->unique(function ($match) {
            return $match['invoice']->id;
        })->sortByDesc(function ($match) {
            return $match['confidence'] ?? 0;
        })->values();
    }

    public function render()
    {
        return view('livewire.bank-reconciliation-panel');
    }
}
