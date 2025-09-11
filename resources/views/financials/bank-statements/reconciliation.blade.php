<x-admin-layout>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Bank Transaction Reconciliation</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Review and match imported bank transactions with invoices or categorize them.
                </p>
            </div>
            <a href="{{ route('management.bank-statements.index') }}" 
               class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                ← Back to Upload
            </a>
        </div>

        <!-- Bank Reconciliation Table (Livewire Component) -->
        @livewire('bank-reconciliation-table')

        <!-- Livewire Reconciliation Panel -->
        @livewire('bank-reconciliation-panel')

    </div>
</x-admin-layout>