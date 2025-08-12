<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">Cash Reconciliation</h2>
            @if($reconciliation)
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600">Till: {{ $tillName }}</span>
                <span class="text-sm text-gray-600">{{ $selectedDate->format('l, F j, Y') }}</span>
            </div>
            @endif
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
            @endif

            @if(session('error') || isset($error))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ session('error') ?? $error }}
            </div>
            @endif

            <!-- Date and Till Selector -->
            <div class="bg-white rounded-lg shadow mb-6 p-4">
                <form method="GET" action="{{ route('cash-reconciliation.index') }}" class="flex items-center space-x-4">
                    <div>
                        <label for="till_id" class="block text-sm font-medium text-gray-700">Till</label>
                        <select name="till_id" id="till_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach($tills as $id => $name)
                            <option value="{{ $id }}" {{ $tillId == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" name="date" id="date" value="{{ $selectedDate->format('Y-m-d') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div class="pt-6">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Load
                        </button>
                    </div>
                </form>
            </div>

            @if($reconciliation)
            <!-- POS Summary -->
            <div class="bg-white rounded-lg shadow mb-6 p-4">
                <h3 class="text-lg font-semibold mb-4">POS Summary</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="text-sm text-gray-600">Cash Total</div>
                        <div class="text-xl font-bold text-green-600">€{{ number_format($reconciliation->pos_cash_total, 2) }}</div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="text-sm text-gray-600">Card Total</div>
                        <div class="text-xl font-bold text-purple-600">€{{ number_format($reconciliation->pos_card_total, 2) }}</div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="text-sm text-gray-600">Total Sales</div>
                        <div class="text-xl font-bold">€{{ number_format($reconciliation->pos_cash_total + $reconciliation->pos_card_total, 2) }}</div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded">
                        <div class="text-sm text-gray-600">Variance</div>
                        <div class="text-xl font-bold {{ $reconciliation->variance > 0 ? 'text-green-600' : ($reconciliation->variance < 0 ? 'text-red-600' : 'text-gray-600') }}">
                            €{{ number_format(abs($reconciliation->variance), 2) }}
                            @if($reconciliation->variance != 0)
                                {{ $reconciliation->variance > 0 ? '↑' : '↓' }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Reconciliation Form -->
            <form method="POST" action="{{ route('cash-reconciliation.store') }}" x-data="cashReconciliation()">
                @csrf
                <input type="hidden" name="reconciliation_id" value="{{ $reconciliation->id }}">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Cash Counting Section -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 class="text-lg font-semibold mb-4">Cash Count</h3>
                        
                        <div class="space-y-2">
                            <!-- Notes -->
                            <div class="border-b pb-2">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Notes</h4>
                                @foreach([50, 20, 10, 5] as $denomination)
                                <div class="flex items-center justify-between mb-1">
                                    <label for="cash_{{ $denomination }}" class="text-sm">€{{ $denomination }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}" 
                                               value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                               x-model="denominations.cash_{{ $denomination }}"
                                               @change="calculateTotals()"
                                               class="w-20 text-right rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-sm text-gray-500 w-20 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $denomination }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Coins -->
                            <div class="pt-2">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Coins</h4>
                                @foreach([2, 1] as $denomination)
                                <div class="flex items-center justify-between mb-1">
                                    <label for="cash_{{ $denomination }}" class="text-sm">€{{ $denomination }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}" 
                                               value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                               x-model="denominations.cash_{{ $denomination }}"
                                               @change="calculateTotals()"
                                               class="w-20 text-right rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-sm text-gray-500 w-20 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $denomination }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach
                                
                                @foreach(['50c', '20c', '10c'] as $denomination)
                                @php $multiplier = $denomination == '50c' ? 0.5 : ($denomination == '20c' ? 0.2 : 0.1); @endphp
                                <div class="flex items-center justify-between mb-1">
                                    <label for="cash_{{ $denomination }}" class="text-sm">{{ $denomination }}</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" name="cash_{{ $denomination }}" id="cash_{{ $denomination }}" 
                                               value="{{ old('cash_' . $denomination, $reconciliation->{'cash_' . $denomination}) }}"
                                               x-model="denominations.cash_{{ $denomination }}"
                                               @change="calculateTotals()"
                                               class="w-20 text-right rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <span class="text-sm text-gray-500 w-20 text-right">€<span x-text="(denominations.cash_{{ $denomination }} * {{ $multiplier }}).toFixed(2)"></span></span>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Total -->
                            <div class="border-t pt-2">
                                <div class="flex items-center justify-between font-bold">
                                    <span>Total Cash</span>
                                    <span class="text-lg">€<span x-text="totalCash.toFixed(2)"></span></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Float and Other Payments -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 class="text-lg font-semibold mb-4">Float & Payments</h3>
                        
                        <div class="space-y-3">
                            <!-- Float -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Float</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="note_float" class="block text-xs text-gray-600">Note Float</label>
                                        <input type="number" step="0.01" name="note_float" id="note_float" 
                                               value="{{ old('note_float', $reconciliation->note_float) }}"
                                               x-model="noteFloat"
                                               @change="calculateTotals()"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="coin_float" class="block text-xs text-gray-600">Coin Float</label>
                                        <input type="number" step="0.01" name="coin_float" id="coin_float" 
                                               value="{{ old('coin_float', $reconciliation->coin_float) }}"
                                               x-model="coinFloat"
                                               @change="calculateTotals()"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Card & Cashback -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Card & Cashback</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="card" class="block text-xs text-gray-600">Card (inc cashback)</label>
                                        <input type="number" step="0.01" name="card" id="card" 
                                               value="{{ old('card', $reconciliation->card) }}"
                                               x-model="card"
                                               @change="calculateTotals()"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="cash_back" class="block text-xs text-gray-600">Cashback</label>
                                        <input type="number" step="0.01" name="cash_back" id="cash_back" 
                                               value="{{ old('cash_back', $reconciliation->cash_back) }}"
                                               x-model="cashBack"
                                               @change="calculateTotals()"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Other Payments -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Other Payments</h4>
                                <div class="space-y-2">
                                    <div>
                                        <label for="cheque" class="block text-xs text-gray-600">Cheque</label>
                                        <input type="number" step="0.01" name="cheque" id="cheque" 
                                               value="{{ old('cheque', $reconciliation->cheque) }}"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="money_added" class="block text-xs text-gray-600">Money Added to Till</label>
                                        <input type="number" step="0.01" name="money_added" id="money_added" 
                                               value="{{ old('money_added', $reconciliation->money_added) }}"
                                               x-model="moneyAdded"
                                               @change="calculateTotals()"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Debt Tracking -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Debt</h4>
                                <div class="space-y-2">
                                    <div>
                                        <label for="debt" class="block text-xs text-gray-600">Debt</label>
                                        <input type="number" step="0.01" name="debt" id="debt" 
                                               value="{{ old('debt', $reconciliation->debt) }}"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt_paid_cash" class="block text-xs text-gray-600">Debt Paid Cash</label>
                                        <input type="number" step="0.01" name="debt_paid_cash" id="debt_paid_cash" 
                                               value="{{ old('debt_paid_cash', $reconciliation->debt_paid_cash) }}"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="debt_paid_card" class="block text-xs text-gray-600">Debt Paid Card</label>
                                        <input type="number" step="0.01" name="debt_paid_card" id="debt_paid_card" 
                                               value="{{ old('debt_paid_card', $reconciliation->debt_paid_card) }}"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Payments and Notes -->
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
                        <div class="bg-white rounded-lg shadow p-4">
                            <h3 class="text-lg font-semibold mb-4">Supplier Payments</h3>
                            
                            <div class="space-y-2" x-data="{ payments: {{ json_encode($paymentsData) }} }">
                                <template x-for="(payment, index) in payments" :key="index">
                                    <div class="border rounded p-2">
                                        <div class="grid grid-cols-2 gap-2">
                                            <select :name="'payments[' + index + '][supplier_id]'" 
                                                    x-model="payment.supplier_id"
                                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">-- Select Supplier --</option>
                                                @foreach($suppliers as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" step="0.01" 
                                                   :name="'payments[' + index + '][amount]'" 
                                                   x-model="payment.amount"
                                                   placeholder="Amount"
                                                   @change="calculatePayments()"
                                                   class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        </div>
                                        <input type="text" 
                                               :name="'payments[' + index + '][description]'" 
                                               x-model="payment.description"
                                               placeholder="Description (optional)"
                                               class="mt-2 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </template>
                                
                                <div class="border-t pt-2">
                                    <div class="flex items-center justify-between font-bold">
                                        <span>Total Payments</span>
                                        <span>€<span x-text="totalPayments.toFixed(2)"></span></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="bg-white rounded-lg shadow p-4">
                            <h3 class="text-lg font-semibold mb-4">Notes</h3>
                            <textarea name="notes" rows="4" 
                                      placeholder="Add any notes about today's reconciliation..."
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('notes', $reconciliation->latestNote?->message) }}</textarea>
                        </div>

                        <!-- Summary -->
                        <div class="bg-white rounded-lg shadow p-4">
                            <h3 class="text-lg font-semibold mb-4">Summary</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span>Total Cash Counted:</span>
                                    <span class="font-medium">€<span x-text="totalCash.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Previous Float:</span>
                                    <span class="font-medium">€<span x-text="previousFloat.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Supplier Payments:</span>
                                    <span class="font-medium">€<span x-text="totalPayments.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Money Added:</span>
                                    <span class="font-medium">€<span x-text="moneyAdded.toFixed(2)"></span></span>
                                </div>
                                <div class="border-t pt-2 flex justify-between font-bold">
                                    <span>Day's Cash Taking:</span>
                                    <span>€<span x-text="daysCashTaking.toFixed(2)"></span></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>POS Cash Total:</span>
                                    <span class="font-medium">€{{ number_format($reconciliation->pos_cash_total, 2) }}</span>
                                </div>
                                <div class="border-t pt-2 flex justify-between font-bold text-lg">
                                    <span>Variance:</span>
                                    <span :class="variance > 0 ? 'text-green-600' : (variance < 0 ? 'text-red-600' : 'text-gray-600')">
                                        €<span x-text="Math.abs(variance).toFixed(2)"></span>
                                        <span x-show="variance != 0" x-text="variance > 0 ? '↑' : '↓'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex space-x-4">
                            <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Save Reconciliation
                            </button>
                            <a href="{{ route('till-review.index', ['date' => $selectedDate->format('Y-m-d')]) }}" 
                               class="flex-1 bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-center">
                                View Receipts
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Recent History -->
            @if($history->count() > 0)
            <div class="bg-white rounded-lg shadow mt-6 p-4">
                <h3 class="text-lg font-semibold mb-4">Recent History</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cash</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">POS Cash</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($history as $item)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $item->date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">€{{ number_format($item->total_cash_counted, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">€{{ number_format($item->pos_cash_total, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="{{ $item->variance > 0 ? 'text-green-600' : ($item->variance < 0 ? 'text-red-600' : 'text-gray-600') }}">
                                        €{{ number_format(abs($item->variance), 2) }}
                                        @if($item->variance != 0)
                                            {{ $item->variance > 0 ? '↑' : '↓' }}
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $item->creator->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('cash-reconciliation.index', ['date' => $item->date->format('Y-m-d'), 'till_id' => $item->till_id]) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            @else
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
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
                card: {{ $reconciliation->card ?? 0 }},
                cashBack: {{ $reconciliation->cash_back ?? 0 }},
                moneyAdded: {{ $reconciliation->money_added ?? 0 }},
                totalCash: 0,
                totalPayments: 0,
                previousFloat: 0,
                daysCashTaking: 0,
                variance: 0,
                posCashTotal: {{ $reconciliation->pos_cash_total ?? 0 }},
                
                init() {
                    this.calculateTotals();
                    this.calculatePayments();
                    // Get previous float
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
                    this.totalCash = 
                        (this.denominations.cash_50 * 50) +
                        (this.denominations.cash_20 * 20) +
                        (this.denominations.cash_10 * 10) +
                        (this.denominations.cash_5 * 5) +
                        (this.denominations.cash_2 * 2) +
                        (this.denominations.cash_1 * 1) +
                        (this.denominations.cash_50c * 0.5) +
                        (this.denominations.cash_20c * 0.2) +
                        (this.denominations.cash_10c * 0.1);
                    
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