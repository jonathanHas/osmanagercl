<x-admin-layout>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Card Transactions</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    View and manage card transaction reconciliation records.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('management.card-reconciliation.index') }}"
                   class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                    Back to Overview
                </a>
                <a href="{{ route('management.card-reconciliation.export', request()->query()) }}"
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Export CSV
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                @if(request('batch_id'))
                <input type="hidden" name="batch_id" value="{{ request('batch_id') }}">
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select name="status" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                        <option value="">All</option>
                        <option value="matched" {{ request('status') == 'matched' ? 'selected' : '' }}>Matched</option>
                        <option value="mismatch" {{ request('status') == 'mismatch' ? 'selected' : '' }}>Mismatch</option>
                        <option value="declined" {{ request('status') == 'declined' ? 'selected' : '' }}>Declined</option>
                        <option value="orphan" {{ request('status') == 'orphan' ? 'selected' : '' }}>Orphan</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="discrepancies" {{ request('status') == 'discrepancies' ? 'selected' : '' }}>All Discrepancies</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                    Filter
                </button>

                <a href="{{ route('management.card-reconciliation.transactions') }}" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 text-sm">
                    Clear
                </a>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow" x-data="transactionsTable()">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date/Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Terminal</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Card</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Card Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reconciliation</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Variance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($transactions as $tx)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $tx->transaction_datetime->format('M j, Y H:i') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span class="text-gray-900 dark:text-white">{{ $tx->terminal_name ?: 'Unknown' }}</span>
                                @if($tx->terminal_id)
                                <span class="text-gray-500 dark:text-gray-400 text-xs block">TID: {{ $tx->terminal_id }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span class="text-gray-900 dark:text-white">{{ $tx->card_masked }}</span>
                                <span class="text-gray-500 dark:text-gray-400 text-xs block">{{ $tx->processor }} {{ $tx->card_type }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-medium text-gray-900 dark:text-white">
                                {{ $tx->currency }} {{ number_format($tx->amount, 2) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($tx->transaction_status === 'Approved')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                    Approved
                                </span>
                                @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                    {{ $tx->transaction_status }}
                                </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @switch($tx->reconciliation_status)
                                    @case('matched')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                            Matched
                                        </span>
                                        @if($tx->confidence_score)
                                        <span class="text-xs text-gray-500 ml-1">({{ $tx->confidence_score }}%)</span>
                                        @endif
                                        @break
                                    @case('mismatch')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                            Mismatch
                                        </span>
                                        @break
                                    @case('declined')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                            Declined
                                        </span>
                                        @break
                                    @case('orphan')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            No POS Match
                                        </span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                            Pending
                                        </span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                @if($tx->variance_amount)
                                <span class="text-red-600 dark:text-red-400 font-medium">
                                    {{ $tx->currency }} {{ number_format($tx->variance_amount, 2) }}
                                </span>
                                @else
                                <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    @if($tx->reconciliation_status === 'orphan' || $tx->reconciliation_status === 'pending')
                                    <button @click="openMatchModal('{{ $tx->id }}')"
                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        Find Match
                                    </button>
                                    @elseif($tx->pos_payment_id)
                                    <button @click="unmatch('{{ $tx->id }}')"
                                            class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300">
                                        Unmatch
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No transactions found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $transactions->withQueryString()->links() }}
            </div>

            <!-- Match Modal -->
            <div x-show="showMatchModal" x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 @keydown.escape.window="showMatchModal = false">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-black opacity-50" @click="showMatchModal = false"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Find POS Match</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Select a POS payment to match with this card transaction.</p>
                        </div>
                        <div class="p-6">
                            <!-- Terminal Mapping Info -->
                            <div x-show="mappedTill" class="mb-4 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm">
                                        <span class="text-blue-800 dark:text-blue-300">Mapped to till: </span>
                                        <span class="font-medium text-blue-900 dark:text-blue-200" x-text="mappedTill"></span>
                                    </div>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" x-model="showAllTills" @change="reloadMatches()"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-2">
                                        <span class="text-blue-800 dark:text-blue-300">Show all tills</span>
                                    </label>
                                </div>
                            </div>
                            <div x-show="!mappedTill && hasMapping === false" class="mb-4 p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                                <p class="text-sm text-yellow-800 dark:text-yellow-300">
                                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    No terminal mapping configured. Searching all tills.
                                </p>
                            </div>

                            <div x-show="loadingMatches" class="flex items-center justify-center py-8">
                                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <div x-show="!loadingMatches">
                                <template x-if="nearbyPayments.length === 0">
                                    <p class="text-center text-gray-500 dark:text-gray-400 py-8">No nearby POS payments found.</p>
                                </template>
                                <div class="space-y-2">
                                    <template x-for="payment in nearbyPayments" :key="payment.payment.ID">
                                        <div class="flex items-center justify-between p-3 border rounded-lg dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                             @click="matchWith(payment.payment.ID)">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="'€' + parseFloat(payment.payment.TOTAL).toFixed(2)"></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="payment.payment.receipt_datetime"></p>
                                                <p x-show="payment.till_name" class="text-xs text-blue-600 dark:text-blue-400 mt-0.5" x-text="'Till: ' + payment.till_name"></p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-medium" :class="payment.confidence >= 80 ? 'text-green-600' : 'text-yellow-600'" x-text="payment.confidence + '% match'"></p>
                                                <p class="text-xs text-gray-500" x-text="Math.round(payment.time_diff_seconds / 60) + ' min diff'"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                            <button @click="showMatchModal = false" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function transactionsTable() {
            return {
                showMatchModal: false,
                loadingMatches: false,
                nearbyPayments: [],
                selectedTransactionId: null,
                mappedTill: null,
                hasMapping: null,
                showAllTills: false,
                csrfToken: '{{ csrf_token() }}',

                openMatchModal(transactionId) {
                    this.selectedTransactionId = transactionId;
                    this.showMatchModal = true;
                    this.loadingMatches = true;
                    this.nearbyPayments = [];
                    this.mappedTill = null;
                    this.hasMapping = null;
                    this.showAllTills = false;

                    this.loadMatches();
                },

                loadMatches() {
                    this.loadingMatches = true;
                    const params = new URLSearchParams({
                        transaction_id: this.selectedTransactionId,
                        show_all_tills: this.showAllTills ? '1' : '0'
                    });

                    fetch('{{ route("management.card-reconciliation.nearby-payments") }}?' + params.toString(), {
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.nearbyPayments = data.payments || [];
                        this.mappedTill = data.mapped_till;
                        this.hasMapping = data.has_mapping;
                        this.loadingMatches = false;
                    })
                    .catch(error => {
                        console.error('Error loading nearby payments:', error);
                        this.loadingMatches = false;
                    });
                },

                reloadMatches() {
                    this.loadMatches();
                },

                matchWith(posPaymentId) {
                    fetch('{{ route("management.card-reconciliation.match") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            transaction_id: this.selectedTransactionId,
                            pos_payment_id: posPaymentId
                        }),
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.showMatchModal = false;
                            location.reload();
                        } else {
                            alert(data.message || 'Failed to match transaction');
                        }
                    })
                    .catch(error => {
                        console.error('Error matching:', error);
                        alert('Failed to match transaction');
                    });
                },

                unmatch(transactionId) {
                    if (!confirm('Remove this match?')) return;

                    fetch('{{ route("management.card-reconciliation.unmatch") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            transaction_id: transactionId
                        }),
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'Failed to unmatch');
                        }
                    })
                    .catch(error => {
                        console.error('Error unmatching:', error);
                        alert('Failed to unmatch transaction');
                    });
                }
            }
        }
    </script>
</x-admin-layout>
