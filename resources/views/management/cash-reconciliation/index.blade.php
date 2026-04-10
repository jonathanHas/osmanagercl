<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Cash Reconciliation</h2>
            <a href="{{ route('management.cash-lodgements.index') }}"
               class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Lodgements
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(session('success'))
            <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
            @endif

            @if(session('error') || isset($error))
            <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded">
                {{ session('error') ?? $error }}
            </div>
            @endif

            <!-- Navigation Bar -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex-shrink-0">
                        @if($adjacentDates['prev'])
                        <a href="{{ route('cash-reconciliation.index', ['date' => $adjacentDates['prev'], 'till_id' => $tillId]) }}"
                           class="inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md transition"
                           title="Previous: {{ \Carbon\Carbon::parse($adjacentDates['prev'])->format('D, M j') }}">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                            <span class="hidden sm:inline text-sm">{{ \Carbon\Carbon::parse($adjacentDates['prev'])->format('D j') }}</span>
                        </a>
                        @else
                        <span class="inline-flex items-center px-3 py-2 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </span>
                        @endif
                    </div>

                    <form method="GET" action="{{ route('cash-reconciliation.index') }}" class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="till_id" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Till</label>
                            <select name="till_id" id="till_id" onchange="this.form.submit()"
                                    class="mt-1 block rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach($tills as $id => $name)
                                <option value="{{ $id }}" {{ $tillId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="date" class="block text-xs font-medium text-gray-500 dark:text-gray-400">Date</label>
                            <input type="date" name="date" id="date" value="{{ $selectedDate->format('Y-m-d') }}"
                                   class="mt-1 block rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md text-sm">Go</button>
                    </form>

                    <div class="flex-shrink-0">
                        @if($adjacentDates['next'])
                        <a href="{{ route('cash-reconciliation.index', ['date' => $adjacentDates['next'], 'till_id' => $tillId]) }}"
                           class="inline-flex items-center px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md transition"
                           title="Next: {{ \Carbon\Carbon::parse($adjacentDates['next'])->format('D, M j') }}">
                            <span class="hidden sm:inline text-sm">{{ \Carbon\Carbon::parse($adjacentDates['next'])->format('D j') }}</span>
                            <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                        @else
                        <span class="inline-flex items-center px-3 py-2 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </span>
                        @endif
                    </div>
                </div>
                <div class="mt-2 text-center">
                    <span class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $selectedDate->format('l, F j, Y') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ $tillName }}</span>
                </div>
            </div>

            @if($reconciliation)
            <!-- POS Summary -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">POS Cash</div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">€{{ number_format($reconciliation->pos_cash_total, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">POS Card</div>
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">€{{ number_format($reconciliation->pos_card_total, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Sales</div>
                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-200 mt-1">€{{ number_format($reconciliation->pos_cash_total + $reconciliation->pos_card_total, 2) }}</div>
                </div>
            </div>

            <!-- Main Form -->
            <form method="POST" action="{{ route('cash-reconciliation.store') }}" x-data="cashReconciliation()">
                @csrf
                <input type="hidden" name="reconciliation_id" value="{{ $reconciliation->id }}">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Column 1: Cash Count -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-200">Cash Count</h3>

                        <!-- Notes -->
                        <div class="border-b dark:border-gray-700 pb-2 mb-2">
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Notes</h4>
                            @foreach([50, 20, 10, 5] as $denomination)
                            <div class="flex items-center justify-between mb-1">
                                <label for="cash_{{ $denomination }}" class="text-sm text-gray-700 dark:text-gray-300 w-10">€{{ $denomination }}</label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}"
                                           value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                           x-model.number="denominations.cash_{{ $denomination }}"
                                           @input="calculateTotals()" min="0"
                                           class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <span class="text-sm text-gray-400 dark:text-gray-500 w-16 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $denomination }}).toFixed(2)"></span></span>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <!-- Coins -->
                        <div class="pb-2 mb-2">
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Coins</h4>
                            @foreach([2, 1] as $denomination)
                            <div class="flex items-center justify-between mb-1">
                                <label for="cash_{{ $denomination }}" class="text-sm text-gray-700 dark:text-gray-300 w-10">€{{ $denomination }}</label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}"
                                           value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                           x-model.number="denominations.cash_{{ $denomination }}"
                                           @input="calculateTotals()" min="0"
                                           class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <span class="text-sm text-gray-400 dark:text-gray-500 w-16 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $denomination }}).toFixed(2)"></span></span>
                                </div>
                            </div>
                            @endforeach
                            @foreach(['50c', '20c', '10c'] as $denomination)
                            @php $multiplier = $denomination == '50c' ? 0.5 : ($denomination == '20c' ? 0.2 : 0.1); @endphp
                            <div class="flex items-center justify-between mb-1">
                                <label for="cash_{{ $denomination }}" class="text-sm text-gray-700 dark:text-gray-300 w-10">{{ $denomination }}</label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}"
                                           value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                           x-model.number="denominations.cash_{{ $denomination }}"
                                           @input="calculateTotals()" min="0"
                                           class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <span class="text-sm text-gray-400 dark:text-gray-500 w-16 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $multiplier }}).toFixed(2)"></span></span>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <!-- Totals -->
                        <div class="border-t dark:border-gray-700 pt-2 space-y-1">
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Notes</span>
                                <span>€<span x-text="totalNotes.toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Coins</span>
                                <span>€<span x-text="totalCoins.toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between font-bold text-gray-800 dark:text-gray-200 text-lg pt-1">
                                <span>Total</span>
                                <span>€<span x-text="totalCash.toFixed(2)"></span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Float, Card & Extras -->
                    <div class="space-y-4">
                        <!-- Always visible: Note Float + Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="note_float" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Note Float</label>
                                    <input type="number" step="0.01" name="note_float" id="note_float"
                                           value="{{ old('note_float', $reconciliation->note_float) }}"
                                           x-model.number="noteFloat" @input="calculateTotals()" min="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg font-medium">
                                </div>
                                <div>
                                    <label for="coin_float" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Coin Float
                                        <span x-show="!coinFloatManual" class="text-indigo-500 dark:text-indigo-400 text-xs ml-1">(auto)</span>
                                    </label>
                                    <input type="number" step="0.01" name="coin_float" id="coin_float"
                                           value="{{ old('coin_float', $reconciliation->coin_float) }}"
                                           x-model.number="coinFloat"
                                           @input="coinFloatManual = true; calculateTotals()"
                                           @dblclick="coinFloatManual = false; calculateTotals()" min="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg font-medium"
                                           :class="{ 'ring-1 ring-indigo-300 dark:ring-indigo-600': !coinFloatManual }">
                                </div>
                            </div>

                            <div class="mt-4">
                                <label for="card" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Card (inc cashback)</label>
                                <input type="number" step="0.01" name="card" id="card"
                                       value="{{ old('card', $reconciliation->card) }}"
                                       x-model.number="card" @input="calculateTotals()" min="0"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg font-medium">
                            </div>
                        </div>

                        <!-- Collapsible: Other entries -->
                        @php
                            $hasExtras = ($reconciliation->cash_back > 0 || $reconciliation->cheque > 0 ||
                                         $reconciliation->money_added > 0 || $reconciliation->free > 0 ||
                                         $reconciliation->voucher_used > 0 || $reconciliation->debt > 0 ||
                                         $reconciliation->debt_paid_cash > 0 || $reconciliation->debt_paid_cheque > 0 ||
                                         $reconciliation->debt_paid_card > 0);
                        @endphp
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow" x-data="{ showExtras: {{ $hasExtras ? 'true' : 'false' }} }">
                            <button type="button" @click="showExtras = !showExtras"
                                    class="w-full flex items-center justify-between p-4 text-left">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Cashback, Cheques, Debt & Other</span>
                                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': showExtras }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div x-show="showExtras" x-collapse class="px-4 pb-4 space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="cash_back" class="block text-xs text-gray-600 dark:text-gray-400">Cashback</label>
                                        <input type="number" step="0.01" name="cash_back" id="cash_back"
                                               value="{{ old('cash_back', $reconciliation->cash_back) }}"
                                               x-model.number="cashBack" @input="calculateTotals()" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="cheque" class="block text-xs text-gray-600 dark:text-gray-400">Cheque</label>
                                        <input type="number" step="0.01" name="cheque" id="cheque"
                                               value="{{ old('cheque', $reconciliation->cheque) }}" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="money_added" class="block text-xs text-gray-600 dark:text-gray-400">Money Added</label>
                                        <input type="number" step="0.01" name="money_added" id="money_added"
                                               value="{{ old('money_added', $reconciliation->money_added) }}"
                                               x-model.number="moneyAdded" @input="calculateTotals()" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="free" class="block text-xs text-gray-600 dark:text-gray-400">Free</label>
                                        <input type="number" step="0.01" name="free" id="free"
                                               value="{{ old('free', $reconciliation->free) }}" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="voucher_used" class="block text-xs text-gray-600 dark:text-gray-400">Voucher Used</label>
                                        <input type="number" step="0.01" name="voucher_used" id="voucher_used"
                                               value="{{ old('voucher_used', $reconciliation->voucher_used) }}" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt" class="block text-xs text-gray-600 dark:text-gray-400">Debt</label>
                                        <input type="number" step="0.01" name="debt" id="debt"
                                               value="{{ old('debt', $reconciliation->debt) }}" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt_paid_cash" class="block text-xs text-gray-600 dark:text-gray-400">Debt Paid Cash</label>
                                        <input type="number" step="0.01" name="debt_paid_cash" id="debt_paid_cash"
                                               value="{{ old('debt_paid_cash', $reconciliation->debt_paid_cash) }}" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt_paid_cheque" class="block text-xs text-gray-600 dark:text-gray-400">Debt Paid Cheque</label>
                                        <input type="number" step="0.01" name="debt_paid_cheque" id="debt_paid_cheque"
                                               value="{{ old('debt_paid_cheque', $reconciliation->debt_paid_cheque) }}" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt_paid_card" class="block text-xs text-gray-600 dark:text-gray-400">Debt Paid Card</label>
                                        <input type="number" step="0.01" name="debt_paid_card" id="debt_paid_card"
                                               value="{{ old('debt_paid_card', $reconciliation->debt_paid_card) }}" min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Collapsible: Supplier Payments -->
                        @php
                            $existingPayments = $reconciliation->payments->map(function($p) {
                                return ['supplier_id' => $p->supplier_id, 'payee_name' => $p->payee_name, 'amount' => $p->amount, 'description' => $p->description];
                            })->toArray();
                            $hasPayments = count($existingPayments) > 0;
                            $initialPayments = $hasPayments
                                ? $existingPayments
                                : [['supplier_id' => '', 'payee_name' => '', 'amount' => '', 'description' => '']];
                            $paymentsData = old('payments', $initialPayments);
                        @endphp
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow"
                             x-data="{ showPayments: {{ $hasPayments ? 'true' : 'false' }}, payments: {{ json_encode($paymentsData) }} }">
                            <button type="button" @click="showPayments = !showPayments"
                                    class="w-full flex items-center justify-between p-4 text-left">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Supplier Payments</span>
                                    <span x-show="totalPayments > 0" class="ml-2 text-xs font-medium text-indigo-600 dark:text-indigo-400">€<span x-text="totalPayments.toFixed(2)"></span></span>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': showPayments }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div x-show="showPayments" x-collapse class="px-4 pb-4">
                                <div class="space-y-2">
                                    <template x-for="(payment, index) in payments" :key="index">
                                        <div class="flex items-center gap-2">
                                            <select :name="'payments[' + index + '][supplier_id]'" x-model="payment.supplier_id"
                                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Supplier...</option>
                                                @foreach($suppliers as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" step="0.01"
                                                   :name="'payments[' + index + '][amount]'" x-model="payment.amount"
                                                   placeholder="€" @input="calculatePayments()" min="0"
                                                   class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <input type="hidden" :name="'payments[' + index + '][description]'" x-model="payment.description">
                                        </div>
                                    </template>
                                </div>
                                <button type="button"
                                        @click="payments.push({supplier_id: '', payee_name: '', amount: '', description: ''})"
                                        class="mt-2 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                    + Add another
                                </button>
                            </div>
                        </div>

                        <!-- Collapsible: Notes -->
                        @php $hasNotes = !empty($reconciliation->latestNote?->message); @endphp
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow" x-data="{ showNotes: {{ $hasNotes ? 'true' : 'false' }} }">
                            <button type="button" @click="showNotes = !showNotes"
                                    class="w-full flex items-center justify-between p-4 text-left">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Notes</span>
                                    @if($hasNotes)
                                    <span class="ml-2 w-2 h-2 bg-blue-500 rounded-full"></span>
                                    @endif
                                </div>
                                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': showNotes }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div x-show="showNotes" x-collapse class="px-4 pb-4">
                                <textarea name="notes" rows="3"
                                          placeholder="Add any notes about today's reconciliation..."
                                          class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('notes', $reconciliation->latestNote?->message) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: Summary + Save -->
                    <div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 lg:sticky lg:top-4">
                            <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-200">Summary</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>Total Cash Counted</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-200">€<span x-text="totalCash.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>Previous Float</span>
                                    <span>-€<span x-text="previousFloat.toFixed(2)"></span></span>
                                </div>
                                <div x-show="parseFloat(cashBack || 0) > 0" class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>Cashback</span>
                                    <span>+€<span x-text="parseFloat(cashBack || 0).toFixed(2)"></span></span>
                                </div>
                                <div x-show="parseFloat(moneyAdded || 0) > 0" class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>Money Added</span>
                                    <span>-€<span x-text="parseFloat(moneyAdded || 0).toFixed(2)"></span></span>
                                </div>
                                <div class="border-t dark:border-gray-700 pt-2 flex justify-between font-semibold text-gray-800 dark:text-gray-200">
                                    <span>Day's Cash Taking</span>
                                    <span>€<span x-text="daysCashTaking.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span>POS Cash Total</span>
                                    <span>€{{ number_format($reconciliation->pos_cash_total, 2) }}</span>
                                </div>

                                <!-- Variance - the key number -->
                                <div class="border-t dark:border-gray-700 pt-3 mt-1">
                                    <div class="flex justify-between items-baseline">
                                        <span class="text-base font-bold text-gray-800 dark:text-gray-200">Variance</span>
                                        <span class="text-2xl font-bold"
                                              :class="variance > 0 ? 'text-green-600 dark:text-green-400' : (variance < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400')">
                                            €<span x-text="Math.abs(variance).toFixed(2)"></span>
                                            <span class="text-base" x-show="variance != 0" x-text="variance > 0 ? '↑' : '↓'"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Save + View Receipts -->
                            <div class="mt-6 space-y-2">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md text-base">
                                    Save
                                </button>
                                <a href="{{ route('till-review.index', ['date' => $selectedDate->format('Y-m-d')]) }}"
                                   class="block w-full text-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 py-1">
                                    View Receipts
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Recent History -->
            @if($history->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mt-6 p-4">
                <h3 class="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-200">Recent History</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cash</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">POS</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Variance</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">By</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($history as $item)
                            @php
                                $absVariance = abs($item->variance);
                                $varianceClass = $item->variance == 0
                                    ? 'text-gray-600 dark:text-gray-400'
                                    : ($absVariance > 20
                                        ? 'text-red-600 dark:text-red-400 font-semibold'
                                        : ($absVariance > 5
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : ($item->variance > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')));
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">{{ $item->date->format('D d/m') }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200 text-right">€{{ number_format($item->total_cash_counted, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200 text-right">€{{ number_format($item->pos_cash_total, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-right">
                                    <span class="{{ $varianceClass }}">
                                        €{{ number_format($absVariance, 2) }}
                                        @if($item->variance != 0) {{ $item->variance > 0 ? '↑' : '↓' }} @endif
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $item->creator?->name ?? '-' }}</td>
                                <td class="px-4 py-2 text-sm text-right">
                                    <a href="{{ route('cash-reconciliation.index', ['date' => $item->date->format('Y-m-d'), 'till_id' => $item->till_id]) }}"
                                       class="text-indigo-600 dark:text-indigo-400 hover:underline">View</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            @else
            <div class="bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-400 dark:border-yellow-600 text-yellow-700 dark:text-yellow-300 px-4 py-3 rounded">
                No closed cash record found for the selected date and till. Please ensure the till was closed on this date.
            </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function cashReconciliation() {
            return {
                denominations: {
                    cash_50: {{ $reconciliation->cash_50 ?? 0 }},
                    cash_20: {{ $reconciliation->cash_20 ?? 0 }},
                    cash_10: {{ $reconciliation->cash_10 ?? 0 }},
                    cash_5: {{ $reconciliation->cash_5 ?? 0 }},
                    cash_2: {{ $reconciliation->cash_2 ?? 0 }},
                    cash_1: {{ $reconciliation->cash_1 ?? 0 }},
                    cash_50c: {{ $reconciliation->cash_50c ?? 0 }},
                    cash_20c: {{ $reconciliation->cash_20c ?? 0 }},
                    cash_10c: {{ $reconciliation->cash_10c ?? 0 }},
                },
                noteFloat: {{ $reconciliation->note_float ?? 0 }},
                coinFloat: {{ $reconciliation->coin_float ?? 0 }},
                coinFloatManual: false,
                card: {{ $reconciliation->card ?? 0 }},
                cashBack: {{ $reconciliation->cash_back ?? 0 }},
                moneyAdded: {{ $reconciliation->money_added ?? 0 }},
                totalCash: 0,
                totalNotes: 0,
                totalCoins: 0,
                totalPayments: 0,
                previousFloat: 0,
                daysCashTaking: 0,
                variance: 0,
                posCashTotal: {{ $reconciliation->pos_cash_total ?? 0 }},

                init() {
                    this.calculateTotals();
                    this.calculatePayments();
                    @if($reconciliation)
                    fetch('{{ route("cash-reconciliation.previous-float") }}?date={{ $selectedDate->format("Y-m-d") }}&till_id={{ $tillId }}')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.date) {
                                this.previousFloat = parseFloat(data.note_float) + parseFloat(data.coin_float);
                                this.calculateTotals();
                            }
                        });
                    @endif
                },

                calculateTotals() {
                    this.totalNotes =
                        (this.denominations.cash_50 * 50) +
                        (this.denominations.cash_20 * 20) +
                        (this.denominations.cash_10 * 10) +
                        (this.denominations.cash_5 * 5);

                    this.totalCoins =
                        (this.denominations.cash_2 * 2) +
                        (this.denominations.cash_1 * 1) +
                        (this.denominations.cash_50c * 0.5) +
                        (this.denominations.cash_20c * 0.2) +
                        (this.denominations.cash_10c * 0.1);

                    if (!this.coinFloatManual) {
                        this.coinFloat = Math.round(this.totalCoins * 100) / 100;
                    }

                    this.totalCash = this.totalNotes + this.totalCoins;
                    this.daysCashTaking = this.totalCash + parseFloat(this.cashBack || 0) - this.previousFloat - parseFloat(this.moneyAdded || 0);
                    this.variance = this.daysCashTaking - this.posCashTotal;
                },

                calculatePayments() {
                    const paymentElements = document.querySelectorAll('[name*="payments"][name*="[amount]"]');
                    this.totalPayments = 0;
                    paymentElements.forEach(el => {
                        this.totalPayments += parseFloat(el.value || 0);
                    });
                    this.calculateTotals();
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>
