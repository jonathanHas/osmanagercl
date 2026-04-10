<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Cash Reconciliation</h2>
            <div class="flex items-center space-x-3">
                @if($reconciliation)
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $tillName }} &mdash; {{ $selectedDate->format('l, F j, Y') }}</span>
                @endif
                <a href="{{ route('management.cash-lodgements.index') }}"
                   class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Lodgements
                </a>
            </div>
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

            <!-- Date and Till Selector -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                <form method="GET" action="{{ route('cash-reconciliation.index') }}" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="till_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Till</label>
                        <select name="till_id" id="till_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach($tills as $id => $name)
                            <option value="{{ $id }}" {{ $tillId == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date</label>
                        <input type="date" name="date" id="date" value="{{ $selectedDate->format('Y-m-d') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">
                            Load
                        </button>
                    </div>
                </form>
            </div>

            @if($reconciliation)
            <!-- POS Summary + Lodgement Status -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Cash Total</div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">€{{ number_format($reconciliation->pos_cash_total, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Card Total</div>
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">€{{ number_format($reconciliation->pos_card_total, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Sales</div>
                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">€{{ number_format($reconciliation->pos_cash_total + $reconciliation->pos_card_total, 2) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">Variance</div>
                    <div class="text-2xl font-bold {{ $reconciliation->variance > 0 ? 'text-green-600 dark:text-green-400' : ($reconciliation->variance < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400') }}">
                        €{{ number_format(abs($reconciliation->variance), 2) }}
                        @if($reconciliation->variance != 0)
                            {{ $reconciliation->variance > 0 ? '↑' : '↓' }}
                        @endif
                    </div>
                </div>
            </div>

            <!-- Lodgement Status Banner -->
            @if($reconciliation->has_lodgement)
            <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-300 dark:border-green-700 rounded-lg p-3 flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm text-green-700 dark:text-green-300">Lodgement recorded for this date</span>
                    @if($reconciliation->legacyCashLodgement)
                    <span class="ml-2 text-sm text-green-600 dark:text-green-400 font-medium">€{{ number_format($reconciliation->legacy_lodged_amount, 2) }}</span>
                    @endif
                </div>
                @if($reconciliation->legacyCashLodgement)
                <a href="{{ route('management.cash-lodgements.show', $reconciliation->legacyCashLodgement) }}"
                   class="text-sm text-green-700 dark:text-green-300 hover:underline font-medium">View Lodgement &rarr;</a>
                @endif
            </div>
            @else
            <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg p-3 flex items-center">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                <span class="text-sm text-amber-700 dark:text-amber-300">No lodgement recorded for this date</span>
            </div>
            @endif

            <!-- Main Reconciliation Form -->
            <form method="POST" action="{{ route('cash-reconciliation.store') }}" x-data="cashReconciliation()">
                @csrf
                <input type="hidden" name="reconciliation_id" value="{{ $reconciliation->id }}">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Column 1: Cash Counting -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Cash Count</h3>

                        <div class="space-y-2">
                            <!-- Notes -->
                            <div class="border-b dark:border-gray-700 pb-2">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</h4>
                                @foreach([50, 20, 10, 5] as $denomination)
                                <div class="flex items-center justify-between mb-1">
                                    <label for="cash_{{ $denomination }}" class="text-sm text-gray-700 dark:text-gray-300">€{{ $denomination }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}"
                                               value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                               x-model.number="denominations.cash_{{ $denomination }}"
                                               @input="calculateTotals()"
                                               min="0"
                                               class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 w-20 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $denomination }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Coins -->
                            <div class="pt-2">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Coins</h4>
                                @foreach([2, 1] as $denomination)
                                <div class="flex items-center justify-between mb-1">
                                    <label for="cash_{{ $denomination }}" class="text-sm text-gray-700 dark:text-gray-300">€{{ $denomination }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}"
                                               value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                               x-model.number="denominations.cash_{{ $denomination }}"
                                               @input="calculateTotals()"
                                               min="0"
                                               class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 w-20 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $denomination }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach

                                @foreach(['50c', '20c', '10c'] as $denomination)
                                @php $multiplier = $denomination == '50c' ? 0.5 : ($denomination == '20c' ? 0.2 : 0.1); @endphp
                                <div class="flex items-center justify-between mb-1">
                                    <label for="cash_{{ $denomination }}" class="text-sm text-gray-700 dark:text-gray-300">{{ $denomination }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}"
                                               value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                               x-model.number="denominations.cash_{{ $denomination }}"
                                               @input="calculateTotals()"
                                               min="0"
                                               class="w-20 text-right rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 w-20 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $multiplier }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Cash Totals -->
                            <div class="border-t dark:border-gray-700 pt-2 space-y-1">
                                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                                    <span>Notes subtotal</span>
                                    <span>€<span x-text="totalNotes.toFixed(2)"></span></span>
                                </div>
                                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                                    <span>Coins subtotal</span>
                                    <span>€<span x-text="totalCoins.toFixed(2)"></span></span>
                                </div>
                                <div class="flex items-center justify-between font-bold text-gray-800 dark:text-gray-200">
                                    <span>Total Cash</span>
                                    <span class="text-lg">€<span x-text="totalCash.toFixed(2)"></span></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Float, Card, Other Payments -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Float & Payments</h3>

                        <div class="space-y-4">
                            <!-- Float -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Float</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="note_float" class="block text-xs text-gray-600 dark:text-gray-400">Note Float</label>
                                        <input type="number" step="0.01" name="note_float" id="note_float"
                                               value="{{ old('note_float', $reconciliation->note_float) }}"
                                               x-model.number="noteFloat"
                                               @input="calculateTotals()"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="coin_float" class="block text-xs text-gray-600 dark:text-gray-400">
                                            Coin Float
                                            <span x-show="!coinFloatManual" class="text-indigo-500 dark:text-indigo-400 text-xs">(auto)</span>
                                        </label>
                                        <input type="number" step="0.01" name="coin_float" id="coin_float"
                                               value="{{ old('coin_float', $reconciliation->coin_float) }}"
                                               x-model.number="coinFloat"
                                               @input="coinFloatManual = true; calculateTotals()"
                                               @dblclick="coinFloatManual = false; calculateTotals()"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                               :class="{ 'ring-1 ring-indigo-300 dark:ring-indigo-600': !coinFloatManual }">
                                    </div>
                                </div>
                            </div>

                            <!-- Card & Cashback -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Card & Cashback</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="card" class="block text-xs text-gray-600 dark:text-gray-400">Card (inc cashback)</label>
                                        <input type="number" step="0.01" name="card" id="card"
                                               value="{{ old('card', $reconciliation->card) }}"
                                               x-model.number="card"
                                               @input="calculateTotals()"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="cash_back" class="block text-xs text-gray-600 dark:text-gray-400">Cashback</label>
                                        <input type="number" step="0.01" name="cash_back" id="cash_back"
                                               value="{{ old('cash_back', $reconciliation->cash_back) }}"
                                               x-model.number="cashBack"
                                               @input="calculateTotals()"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Other Payments -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Other</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="cheque" class="block text-xs text-gray-600 dark:text-gray-400">Cheque</label>
                                        <input type="number" step="0.01" name="cheque" id="cheque"
                                               value="{{ old('cheque', $reconciliation->cheque) }}"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="money_added" class="block text-xs text-gray-600 dark:text-gray-400">Money Added to Till</label>
                                        <input type="number" step="0.01" name="money_added" id="money_added"
                                               value="{{ old('money_added', $reconciliation->money_added) }}"
                                               x-model.number="moneyAdded"
                                               @input="calculateTotals()"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="free" class="block text-xs text-gray-600 dark:text-gray-400">Free</label>
                                        <input type="number" step="0.01" name="free" id="free"
                                               value="{{ old('free', $reconciliation->free) }}"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="voucher_used" class="block text-xs text-gray-600 dark:text-gray-400">Voucher Used</label>
                                        <input type="number" step="0.01" name="voucher_used" id="voucher_used"
                                               value="{{ old('voucher_used', $reconciliation->voucher_used) }}"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Debt Tracking -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Debt</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="debt" class="block text-xs text-gray-600 dark:text-gray-400">Debt</label>
                                        <input type="number" step="0.01" name="debt" id="debt"
                                               value="{{ old('debt', $reconciliation->debt) }}"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt_paid_cash" class="block text-xs text-gray-600 dark:text-gray-400">Debt Paid Cash</label>
                                        <input type="number" step="0.01" name="debt_paid_cash" id="debt_paid_cash"
                                               value="{{ old('debt_paid_cash', $reconciliation->debt_paid_cash) }}"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt_paid_cheque" class="block text-xs text-gray-600 dark:text-gray-400">Debt Paid Cheque</label>
                                        <input type="number" step="0.01" name="debt_paid_cheque" id="debt_paid_cheque"
                                               value="{{ old('debt_paid_cheque', $reconciliation->debt_paid_cheque) }}"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt_paid_card" class="block text-xs text-gray-600 dark:text-gray-400">Debt Paid Card</label>
                                        <input type="number" step="0.01" name="debt_paid_card" id="debt_paid_card"
                                               value="{{ old('debt_paid_card', $reconciliation->debt_paid_card) }}"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: Supplier Payments, Notes, Summary -->
                    <div class="space-y-6">
                        <!-- Supplier Payments -->
                        @php
                            $existingPayments = $reconciliation->payments->map(function($p) {
                                return ['supplier_id' => $p->supplier_id, 'payee_name' => $p->payee_name, 'amount' => $p->amount, 'description' => $p->description];
                            })->toArray();

                            $defaultPayments = [
                                ['supplier_id' => '', 'payee_name' => '', 'amount' => '', 'description' => ''],
                                ['supplier_id' => '', 'payee_name' => '', 'amount' => '', 'description' => ''],
                                ['supplier_id' => '', 'payee_name' => '', 'amount' => '', 'description' => ''],
                                ['supplier_id' => '', 'payee_name' => '', 'amount' => '', 'description' => '']
                            ];

                            $paymentsData = old('payments', count($existingPayments) > 0 ? $existingPayments : $defaultPayments);
                        @endphp
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Supplier Payments</h3>

                            <div class="space-y-2" x-data="{ payments: {{ json_encode($paymentsData) }} }">
                                <template x-for="(payment, index) in payments" :key="index">
                                    <div class="border dark:border-gray-700 rounded p-2">
                                        <div class="grid grid-cols-2 gap-2">
                                            <select :name="'payments[' + index + '][supplier_id]'"
                                                    x-model="payment.supplier_id"
                                                    class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">-- Select Supplier --</option>
                                                @foreach($suppliers as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" step="0.01"
                                                   :name="'payments[' + index + '][amount]'"
                                                   x-model="payment.amount"
                                                   placeholder="Amount"
                                                   @input="calculatePayments()"
                                                   min="0"
                                                   class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        </div>
                                        <input type="text"
                                               :name="'payments[' + index + '][description]'"
                                               x-model="payment.description"
                                               placeholder="Description (optional)"
                                               class="mt-2 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </template>

                                <div class="border-t dark:border-gray-700 pt-2">
                                    <div class="flex items-center justify-between font-bold text-gray-800 dark:text-gray-200">
                                        <span>Total Payments</span>
                                        <span>€<span x-text="totalPayments.toFixed(2)"></span></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Notes</h3>
                            <textarea name="notes" rows="3"
                                      placeholder="Add any notes about today's reconciliation..."
                                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('notes', $reconciliation->latestNote?->message) }}</textarea>
                        </div>

                        <!-- Summary -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 lg:sticky lg:top-4">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Summary</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                    <span>Total Cash Counted:</span>
                                    <span class="font-medium">€<span x-text="totalCash.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                    <span>Previous Float:</span>
                                    <span class="font-medium">€<span x-text="previousFloat.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                    <span>Supplier Payments:</span>
                                    <span class="font-medium">€<span x-text="totalPayments.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                    <span>Money Added:</span>
                                    <span class="font-medium">€<span x-text="parseFloat(moneyAdded || 0).toFixed(2)"></span></span>
                                </div>
                                <div class="border-t dark:border-gray-700 pt-2 flex justify-between font-bold text-gray-800 dark:text-gray-200">
                                    <span>Day's Cash Taking:</span>
                                    <span>€<span x-text="daysCashTaking.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                    <span>POS Cash Total:</span>
                                    <span class="font-medium">€{{ number_format($reconciliation->pos_cash_total, 2) }}</span>
                                </div>
                                <div class="border-t dark:border-gray-700 pt-2 flex justify-between font-bold text-lg">
                                    <span class="text-gray-800 dark:text-gray-200">Variance:</span>
                                    <span :class="variance > 0 ? 'text-green-600 dark:text-green-400' : (variance < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400')">
                                        €<span x-text="Math.abs(variance).toFixed(2)"></span>
                                        <span x-show="variance != 0" x-text="variance > 0 ? '↑' : '↓'"></span>
                                    </span>
                                </div>

                                <!-- Available to Lodge -->
                                <div class="border-t dark:border-gray-700 pt-2 mt-2">
                                    <div class="flex justify-between font-bold text-indigo-700 dark:text-indigo-400">
                                        <span>Available to Lodge:</span>
                                        <span>€<span x-text="availableToLodge.toFixed(2)"></span></span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">Total cash - float - supplier payments</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex space-x-4">
                            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-md">
                                Save Reconciliation
                            </button>
                            <a href="{{ route('till-review.index', ['date' => $selectedDate->format('Y-m-d')]) }}"
                               class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2.5 px-4 rounded-md text-center">
                                View Receipts
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Recent History -->
            @if($history->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mt-6 p-4">
                <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Recent History</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Cash</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">POS Cash</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Variance</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avail. to Lodge</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
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
                                $availableToLodge = $item->calculateAvailableToLodge();
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">{{ $item->date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">€{{ number_format($item->total_cash_counted, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">€{{ number_format($item->pos_cash_total, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <span class="{{ $varianceClass }}">
                                        €{{ number_format($absVariance, 2) }}
                                        @if($item->variance != 0)
                                            {{ $item->variance > 0 ? '↑' : '↓' }}
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-indigo-600 dark:text-indigo-400">€{{ number_format($availableToLodge, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $item->creator?->name ?? '-' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <a href="{{ route('cash-reconciliation.index', ['date' => $item->date->format('Y-m-d'), 'till_id' => $item->till_id]) }}"
                                       class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">View</a>
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
                availableToLodge: 0,
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

                    // Auto-set coin float to total coins (all coins stay in till)
                    if (!this.coinFloatManual) {
                        this.coinFloat = Math.round(this.totalCoins * 100) / 100;
                    }

                    this.totalCash = this.totalNotes + this.totalCoins;

                    this.daysCashTaking = this.totalCash + parseFloat(this.cashBack || 0) - this.previousFloat - parseFloat(this.moneyAdded || 0);
                    this.variance = this.daysCashTaking - this.posCashTotal;

                    this.availableToLodge = Math.max(0,
                        this.totalCash - parseFloat(this.noteFloat || 0) - parseFloat(this.coinFloat || 0) - this.totalPayments
                    );
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
