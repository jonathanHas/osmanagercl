<?php

namespace App\Livewire;

use App\Models\BankTransaction;
use App\Models\ReconciliationRule;
use App\Services\BankReconciliationService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class BankReconciliationTable extends Component
{
    use WithPagination;

    // Search and filter properties
    public string $searchQuery = '';

    public string $statusFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $amountFrom = '';

    public string $amountTo = '';

    public string $transactionType = ''; // all, debit, credit

    public string $creditCategoryFilter = ''; // all, card_lodgement, cash_lodgement, other_credit

    // Bulk category assignment
    public string $bulkCreditCategory = ''; // for user-controlled bulk categorization

    public string $bulkDebitCategory = ''; // for user-controlled bulk debit categorization

    // Bulk selection properties
    public array $selectedTransactions = [];

    public bool $selectAll = false;

    public bool $showBulkActions = false;

    public bool $showPreviewModal = false;

    public array $previewData = [];

    protected BankReconciliationService $reconciliationService;

    public function boot(BankReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    public function mount()
    {
        // Check if there are transactions in the current month, otherwise default to last 3 months
        $currentMonthTransactions = BankTransaction::where('transaction_date', '>=', now()->startOfMonth())
            ->where('transaction_date', '<=', now()->endOfMonth())
            ->count();

        if ($currentMonthTransactions > 0) {
            // Default to current month for better performance
            $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
            $this->dateTo = now()->endOfMonth()->format('Y-m-d');
        } else {
            // If no transactions in current month, default to last 3 months
            $this->dateFrom = now()->subMonths(3)->startOfMonth()->format('Y-m-d');
            $this->dateTo = now()->endOfMonth()->format('Y-m-d');
        }
    }

    #[On('reconciliationUpdated')]
    public function refreshData()
    {
        // Reset pagination to first page and refresh
        $this->resetPage();

        // Dispatch event to show success message if needed
        $this->dispatch('dataRefreshed');
    }

    // Reset pagination when search/filter changes
    public function updatedSearchQuery()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function updatedAmountFrom()
    {
        $this->resetPage();
    }

    public function updatedAmountTo()
    {
        $this->resetPage();
    }

    public function updatedTransactionType()
    {
        $this->resetPage();
    }

    public function updatedCreditCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatedSelectedTransactions()
    {
        $this->updateBulkActionsVisibility();
    }

    public function clearFilters()
    {
        $this->searchQuery = '';
        $this->statusFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->amountFrom = '';
        $this->amountTo = '';
        $this->transactionType = '';
        $this->creditCategoryFilter = '';
        $this->resetPage();
    }

    public function setStatusFilter($status)
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    public function render()
    {
        $query = BankTransaction::query();

        // Apply text search filter
        if (! empty($this->searchQuery)) {
            $searchTerm = '%'.$this->searchQuery.'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', $searchTerm)
                    ->orWhere('source_filename', 'like', $searchTerm)
                    ->orWhere('notes', 'like', $searchTerm)
                    ->orWhere('debit_amount', 'like', $searchTerm)
                    ->orWhere('credit_amount', 'like', $searchTerm);
            });
        }

        // Apply status filter
        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        // Apply date range filter
        if (! empty($this->dateFrom)) {
            $query->where('transaction_date', '>=', $this->dateFrom);
        }
        if (! empty($this->dateTo)) {
            $query->where('transaction_date', '<=', $this->dateTo);
        }

        // Apply amount range filters
        if (! empty($this->amountFrom)) {
            $query->where(function ($q) {
                $q->where('debit_amount', '>=', $this->amountFrom)
                    ->orWhere('credit_amount', '>=', $this->amountFrom);
            });
        }
        if (! empty($this->amountTo)) {
            $query->where(function ($q) {
                $q->where('debit_amount', '<=', $this->amountTo)
                    ->orWhere('credit_amount', '<=', $this->amountTo);
            });
        }

        // Apply transaction type filter
        if (! empty($this->transactionType)) {
            if ($this->transactionType === 'debit') {
                $query->where('debit_amount', '>', 0);
            } elseif ($this->transactionType === 'credit') {
                $query->where('credit_amount', '>', 0);
            }
        }

        // Apply credit category filter
        if (! empty($this->creditCategoryFilter)) {
            $query->where('credit_category', $this->creditCategoryFilter);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->paginate(50);

        return view('livewire.bank-reconciliation-table', compact('transactions'));
    }

    public function getFilteredCountProperty()
    {
        return $this->getFilteredQuery()->count();
    }

    // Computed properties for full dataset statistics
    public function getFullDatasetStatisticsProperty()
    {
        $query = $this->getFilteredQuery();

        return [
            'total_count' => $query->count(),
            'unmatched_count' => (clone $query)->whereIn('status', ['pending', 'unmatched'])->count(),
            'total_credits' => $query->sum('credit_amount'),
            'total_debits' => $query->sum('debit_amount'),
        ];
    }

    public function hasActiveFilters()
    {
        return ! empty($this->searchQuery) || ! empty($this->statusFilter) ||
               ! empty($this->dateFrom) || ! empty($this->dateTo) ||
               ! empty($this->amountFrom) || ! empty($this->amountTo) ||
               ! empty($this->transactionType) || ! empty($this->creditCategoryFilter);
    }

    // Bulk selection methods
    public function toggleTransactionSelection($transactionId)
    {
        if (in_array($transactionId, $this->selectedTransactions)) {
            $this->selectedTransactions = array_diff($this->selectedTransactions, [$transactionId]);
        } else {
            $this->selectedTransactions[] = $transactionId;
        }

        $this->updateBulkActionsVisibility();
    }

    public function selectAllVisible()
    {
        $query = $this->getFilteredQuery();
        $visibleTransactionIds = $query->where(function ($q) {
            $q->where('status', 'unmatched')
                ->orWhere('status', 'pending');
        })->pluck('id')->toArray();

        if (empty(array_diff($visibleTransactionIds, $this->selectedTransactions))) {
            // All visible are selected, so deselect all
            $this->selectedTransactions = array_diff($this->selectedTransactions, $visibleTransactionIds);
            $this->selectAll = false;
        } else {
            // Select all visible
            $this->selectedTransactions = array_unique(array_merge($this->selectedTransactions, $visibleTransactionIds));
            $this->selectAll = true;
        }

        $this->updateBulkActionsVisibility();
    }

    public function clearSelection()
    {
        $this->selectedTransactions = [];
        $this->selectAll = false;
        $this->updateBulkActionsVisibility();
    }

    private function updateBulkActionsVisibility()
    {
        $this->showBulkActions = count($this->selectedTransactions) > 0;
    }

    // Prediction methods
    public function getPredictedExpense($transaction)
    {
        if (! $transaction || $transaction->status === 'matched') {
            return null;
        }

        // If this is a credit transaction, predict credit category instead
        if ($transaction->isCreditTransaction()) {
            return $this->reconciliationService->predictCreditCategory($transaction);
        }

        // Check for non-supplier expense patterns
        $rule = ReconciliationRule::findNonSupplierExpenseByDescription($transaction->description);
        if ($rule && $rule->confidence_score >= 50) {
            return [
                'type' => 'non_supplier',
                'category' => $rule->expense_category,
                'description' => $rule->expense_description,
                'confidence' => $rule->confidence_score,
                'icon' => $this->getExpenseIcon($rule->expense_category),
                'color' => $this->getConfidenceColor($rule->confidence_score),
            ];
        }

        // Check for existing wage rules (fallback)
        $fingerprint = ReconciliationRule::createDescriptionFingerprint($transaction->description);
        $wageRule = ReconciliationRule::where('description_fingerprint', $fingerprint)
            ->whereNull('supplier_id')
            ->where('expense_category', 'WAGES')
            ->where('match_count', '>=', 1)
            ->first();

        if ($wageRule) {
            return [
                'type' => 'wages',
                'category' => 'WAGES',
                'description' => 'Wages & Salaries',
                'confidence' => $wageRule->confidence_score,
                'icon' => '💰',
                'color' => $this->getConfidenceColor($wageRule->confidence_score),
            ];
        }

        // Check for supplier matches
        $supplier = ReconciliationRule::findSupplierByDescription($transaction->description);
        if ($supplier) {
            return [
                'type' => 'supplier',
                'category' => 'SUPPLIER',
                'description' => $supplier->SupplierName,
                'confidence' => 70, // Default confidence for supplier matches
                'icon' => '🏢',
                'color' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            ];
        }

        return null;
    }

    private function getExpenseIcon($category)
    {
        return match ($category) {
            'WAGES' => '💰',
            'TAX' => '🏛️',
            'INSURANCE' => '🛡️',
            'BANK_FEES' => '🏦',
            'UTILITIES' => '⚡',
            'RENT' => '🏠',
            default => '📋',
        };
    }

    private function getConfidenceColor($confidence)
    {
        if ($confidence >= 80) {
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        } elseif ($confidence >= 60) {
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
        } else {
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
        }
    }

    // Bulk actions
    public function previewBulkReconciliation()
    {
        $selectedTransactionObjects = BankTransaction::whereIn('id', $this->selectedTransactions)->get();
        $this->previewData = [];

        foreach ($selectedTransactionObjects as $transaction) {
            $prediction = $this->getPredictedExpense($transaction);
            $this->previewData[] = [
                'transaction' => $transaction,
                'prediction' => $prediction,
                'amount' => $transaction->debit_amount > 0 ? $transaction->debit_amount : $transaction->credit_amount,
            ];
        }

        $this->showPreviewModal = true;
    }

    public function closePreviewModal()
    {
        $this->showPreviewModal = false;
        $this->previewData = [];
    }

    public function processBulkReconciliation()
    {
        $processedCount = 0;
        $errors = [];
        $skippedLowConfidence = 0;

        foreach ($this->previewData as $item) {
            $transaction = $item['transaction'];
            $prediction = $item['prediction'];

            // Skip items with very low confidence unless it's a credit category prediction
            if (! $prediction) {
                continue;
            }

            // For credit transactions, even lower confidence predictions can be useful
            $minConfidence = $transaction->isCreditTransaction() ? 30 : 50;

            if ($prediction['confidence'] < $minConfidence) {
                $skippedLowConfidence++;

                continue;
            }

            try {
                // Handle credit transactions differently
                if ($transaction->isCreditTransaction()) {
                    // Use manual category selection if available, otherwise use prediction
                    $category = $this->bulkCreditCategory ?? $prediction['category'] ?? 'other_credit';

                    if ($this->reconciliationService->categorizeCreditTransaction($transaction, $category)) {
                        $processedCount++;
                    } else {
                        $errors[] = "Failed to categorize credit transaction: {$transaction->description}";
                    }
                } else {
                    // Handle expense transactions (existing logic)
                    $expenseData = [
                        'supplier_name' => $prediction['description'] ?? 'Unknown',
                        'expense_description' => $prediction['description'] ?? $transaction->description,
                        'category' => $prediction['category'] ?? 'OTHER',
                        'subtotal' => $item['amount'],
                        'vat_amount' => 0, // Most non-supplier expenses have no VAT
                        'is_non_supplier' => ($prediction['type'] ?? 'other') !== 'supplier',
                    ];

                    if ($this->reconciliationService->createExpenseFromTransaction($transaction, $expenseData)) {
                        $processedCount++;
                    } else {
                        $errors[] = "Failed to process transaction: {$transaction->description}";
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Error processing {$transaction->description}: {$e->getMessage()}";
            }
        }

        $this->closePreviewModal();
        $this->clearSelection();

        if ($processedCount > 0) {
            session()->flash('success', "Successfully processed {$processedCount} transactions.");
            $this->dispatch('reconciliationUpdated');
        }

        if (! empty($errors)) {
            session()->flash('error', 'Some transactions could not be processed: '.implode(', ', $errors));
        }
    }

    public function bulkCategorizeSelected()
    {
        if (empty($this->selectedTransactions) || empty($this->bulkCreditCategory)) {
            session()->flash('error', 'Please select transactions and a category.');

            return;
        }

        try {
            $result = $this->reconciliationService->bulkCategorizeCreditTransactions(
                $this->selectedTransactions,
                $this->bulkCreditCategory
            );

            $this->clearSelection();
            $this->bulkCreditCategory = ''; // Reset the dropdown

            if ($result['success']) {
                session()->flash('success', "Successfully categorized {$result['processed']} transactions as {$result['category_display']}.");

                if ($result['skipped'] > 0) {
                    session()->flash('warning', "Skipped {$result['skipped']} transactions (not credit transactions or already processed).");
                }

                $this->dispatch('reconciliationUpdated');
            } else {
                session()->flash('error', $result['message'] ?? 'Failed to process bulk categorization.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error during bulk categorization: '.$e->getMessage());
        }
    }

    public function bulkCategorizeDebitsSelected()
    {
        \Log::info('bulkCategorizeDebitsSelected called', [
            'selectedTransactions' => $this->selectedTransactions,
            'bulkDebitCategory' => $this->bulkDebitCategory,
            'selectedCount' => count($this->selectedTransactions),
        ]);

        if (empty($this->selectedTransactions) || empty($this->bulkDebitCategory)) {
            session()->flash('error', 'Please select transactions and a category.');

            return;
        }

        try {
            $result = $this->reconciliationService->bulkCategorizeDebitTransactions(
                $this->selectedTransactions,
                $this->bulkDebitCategory
            );

            $this->clearSelection();
            $this->bulkDebitCategory = ''; // Reset the dropdown

            if ($result['success']) {
                session()->flash('success', "Successfully categorized {$result['processed']} transactions as {$result['category_display']}.");

                if ($result['skipped'] > 0) {
                    session()->flash('warning', "Skipped {$result['skipped']} transactions (not debit transactions or already processed).");
                }

                $this->dispatch('reconciliationUpdated');
            } else {
                session()->flash('error', $result['message'] ?? 'Failed to process bulk categorization.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error during bulk categorization: '.$e->getMessage());
        }
    }

    private function getFilteredQuery()
    {
        $query = BankTransaction::query();

        // Apply same filters as render method
        if (! empty($this->searchQuery)) {
            $searchTerm = '%'.$this->searchQuery.'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', $searchTerm)
                    ->orWhere('source_filename', 'like', $searchTerm)
                    ->orWhere('notes', 'like', $searchTerm)
                    ->orWhere('debit_amount', 'like', $searchTerm)
                    ->orWhere('credit_amount', 'like', $searchTerm);
            });
        }

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (! empty($this->dateFrom)) {
            $query->where('transaction_date', '>=', $this->dateFrom);
        }
        if (! empty($this->dateTo)) {
            $query->where('transaction_date', '<=', $this->dateTo);
        }

        if (! empty($this->amountFrom)) {
            $query->where(function ($q) {
                $q->where('debit_amount', '>=', $this->amountFrom)
                    ->orWhere('credit_amount', '>=', $this->amountFrom);
            });
        }
        if (! empty($this->amountTo)) {
            $query->where(function ($q) {
                $q->where('debit_amount', '<=', $this->amountTo)
                    ->orWhere('credit_amount', '<=', $this->amountTo);
            });
        }

        if (! empty($this->transactionType)) {
            if ($this->transactionType === 'debit') {
                $query->where('debit_amount', '>', 0);
            } elseif ($this->transactionType === 'credit') {
                $query->where('credit_amount', '>', 0);
            }
        }

        if (! empty($this->creditCategoryFilter)) {
            $query->where('credit_category', $this->creditCategoryFilter);
        }

        return $query->orderBy('transaction_date', 'desc');
    }

    // Date navigation methods
    public function setCurrentMonth()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function setPreviousMonth()
    {
        $this->dateFrom = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->subMonth()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function setLastThreeMonths()
    {
        $this->dateFrom = now()->subMonths(3)->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function setThisYear()
    {
        $this->dateFrom = now()->startOfYear()->format('Y-m-d');
        $this->dateTo = now()->endOfYear()->format('Y-m-d');
        $this->resetPage();
    }

    public function navigateToPreviousMonth()
    {
        $currentFrom = \Carbon\Carbon::parse($this->dateFrom ?? now());
        $currentTo = \Carbon\Carbon::parse($this->dateTo ?? now());

        // If current range spans multiple months, move both dates back by one month
        // Otherwise, move to the complete previous month
        $this->dateFrom = $currentFrom->subMonth()->startOfMonth()->format('Y-m-d');
        $this->dateTo = $currentFrom->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function navigateToNextMonth()
    {
        $currentFrom = \Carbon\Carbon::parse($this->dateFrom ?? now());
        $currentTo = \Carbon\Carbon::parse($this->dateTo ?? now());

        // If current range spans multiple months, move both dates forward by one month  
        // Otherwise, move to the complete next month
        $this->dateFrom = $currentFrom->addMonth()->startOfMonth()->format('Y-m-d');
        $this->dateTo = $currentFrom->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function getCurrentMonthDisplayProperty()
    {
        if (empty($this->dateFrom) || empty($this->dateTo)) {
            return now()->format('F Y');
        }

        $from = \Carbon\Carbon::parse($this->dateFrom);
        $to = \Carbon\Carbon::parse($this->dateTo);

        // If it's a single month, show just the month
        if ($from->isSameMonth($to)) {
            return $from->format('F Y');
        }

        // If it spans multiple months, show range
        return $from->format('M Y').' - '.$to->format('M Y');
    }
}
