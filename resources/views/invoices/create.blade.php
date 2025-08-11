<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" x-data="invoiceForm()">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Create Invoice</h2>
            <a href="{{ route('invoices.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Invoices
            </a>
        </div>

        <form method="POST" action="{{ route('invoices.store') }}" @submit.prevent="submitForm">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Invoice Details --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- Invoice Header --}}
                    <div class="bg-gray-800 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">Invoice Details</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Invoice Number *</label>
                                <input type="text" name="invoice_number" x-model="invoiceNumber" required
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('invoice_number') border-red-500 @enderror"
                                       value="{{ old('invoice_number') }}">
                                @error('invoice_number')
                                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Invoice Date *</label>
                                <input type="date" name="invoice_date" x-model="invoiceDate" required
                                       @change="updateVatRates()"
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('invoice_date') border-red-500 @enderror"
                                       value="{{ old('invoice_date', date('Y-m-d')) }}">
                                @error('invoice_date')
                                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Supplier</label>
                                <select name="supplier_id" x-model="supplierId" @change="updateSupplierDetails()"
                                        class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                                    <option value="">Select Supplier (Optional)</option>
                                    @foreach($suppliers as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Supplier Name *</label>
                                <input type="text" name="supplier_name" x-model="supplierName" required
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('supplier_name') border-red-500 @enderror"
                                       value="{{ old('supplier_name') }}">
                                @error('supplier_name')
                                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Due Date</label>
                                <input type="date" name="due_date" x-model="dueDate"
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('due_date') border-red-500 @enderror"
                                       value="{{ old('due_date') }}">
                                @error('due_date')
                                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Expense Category</label>
                                <select name="expense_category" x-model="expenseCategory"
                                        class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Notes</label>
                            <textarea name="notes" rows="2" x-model="notes"
                                      class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    {{-- VAT Lines --}}
                    <div class="bg-gray-800 rounded-lg p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-100">VAT Lines</h3>
                            <button type="button" @click="addVatLine()" 
                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded text-sm">
                                + Add VAT Line
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-700">
                                        <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2 w-40">VAT Category</th>
                                        <th class="text-right text-xs font-medium text-gray-400 uppercase pb-2 w-32">Net Amount</th>
                                        <th class="text-right text-xs font-medium text-gray-400 uppercase pb-2 w-24">VAT %</th>
                                        <th class="text-right text-xs font-medium text-gray-400 uppercase pb-2 w-32">VAT Amount</th>
                                        <th class="text-right text-xs font-medium text-gray-400 uppercase pb-2 w-32">Gross Amount</th>
                                        <th class="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(vatLine, index) in vatLines" :key="index">
                                        <tr class="border-b border-gray-700">
                                            <td class="py-2 px-1">
                                                <select :name="'vat_lines[' + index + '][vat_category]'" 
                                                        x-model="vatLine.vat_category" required
                                                        @change="updateVatLineRate(index)"
                                                        class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded text-sm">
                                                    <option value="">Select Category</option>
                                                    <option value="STANDARD">Standard Rate (23%)</option>
                                                    <option value="REDUCED">Reduced Rate (13.5%)</option>
                                                    <option value="SECOND_REDUCED">Second Reduced Rate (9%)</option>
                                                    <option value="ZERO">Zero Rate (0%)</option>
                                                </select>
                                            </td>
                                            <td class="py-2 px-1">
                                                <input type="number" :name="'vat_lines[' + index + '][net_amount]'" 
                                                       x-model.number="vatLine.net_amount" required step="0.01" min="0"
                                                       @input="calculateVatLine(index)"
                                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded text-sm text-right"
                                                       placeholder="0.00">
                                            </td>
                                            <td class="py-2 px-1 text-right text-gray-400 text-sm">
                                                <span x-text="(vatLine.vat_rate * 100).toFixed(1) + '%'"></span>
                                            </td>
                                            <td class="py-2 px-1 text-right text-gray-300 text-sm">
                                                €<span x-text="vatLine.vat_amount.toFixed(2)"></span>
                                            </td>
                                            <td class="py-2 px-1 text-right text-white font-semibold text-sm">
                                                €<span x-text="vatLine.gross_amount.toFixed(2)"></span>
                                            </td>
                                            <td class="py-2 pl-1">
                                                <button type="button" @click="removeVatLine(index)" 
                                                        class="text-red-400 hover:text-red-300">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        @error('vat_lines')
                            <p class="text-red-400 text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Summary Sidebar --}}
                <div class="lg:col-span-1">
                    <div class="bg-gray-800 rounded-lg p-6 sticky top-6">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">Summary</h3>
                        
                        {{-- VAT Breakdown --}}
                        <div class="space-y-2 mb-4 pb-4 border-b border-gray-700">
                            <h4 class="text-sm font-medium text-gray-400">VAT Breakdown</h4>
                            <template x-for="(vat, category) in vatBreakdown" :key="category">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-400">
                                        <span x-text="getCategoryLabel(category)"></span> @ <span x-text="(vat.rate * 100).toFixed(1) + '%'"></span>
                                    </span>
                                    <span class="text-gray-300">€<span x-text="vat.amount.toFixed(2)"></span></span>
                                </div>
                            </template>
                        </div>
                        
                        {{-- Totals --}}
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Subtotal (Net)</span>
                                <span class="text-gray-300">€<span x-text="totals.net.toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Total VAT</span>
                                <span class="text-gray-300">€<span x-text="totals.vat.toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between text-lg font-semibold pt-2 border-t border-gray-700">
                                <span class="text-gray-100">Total (Gross)</span>
                                <span class="text-white">€<span x-text="totals.gross.toFixed(2)"></span></span>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="mt-6 space-y-2">
                            <button type="submit" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Create Invoice
                            </button>
                            <a href="{{ route('invoices.index') }}" 
                               class="block text-center bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function invoiceForm() {
            return {
                invoiceNumber: '{{ old('invoice_number') }}',
                invoiceDate: '{{ old('invoice_date', date('Y-m-d')) }}',
                supplierId: '{{ old('supplier_id') }}',
                supplierName: '{{ old('supplier_name') }}',
                dueDate: '{{ old('due_date') }}',
                expenseCategory: '{{ old('expense_category') }}',
                notes: '{{ old('notes') }}',
                vatLines: [{
                    vat_category: 'STANDARD',
                    net_amount: 0,
                    vat_rate: 0.23,
                    vat_amount: 0,
                    gross_amount: 0,
                    line_number: 1
                }],
                vatRates: {
                    'STANDARD': 0.23,
                    'REDUCED': 0.135,
                    'SECOND_REDUCED': 0.09,
                    'ZERO': 0.00
                },
                suppliers: @json($suppliers->toArray()),
                
                get totals() {
                    const net = this.vatLines.reduce((sum, line) => sum + line.net_amount, 0);
                    const vat = this.vatLines.reduce((sum, line) => sum + line.vat_amount, 0);
                    const gross = this.vatLines.reduce((sum, line) => sum + line.gross_amount, 0);
                    return { net, vat, gross };
                },
                
                get vatBreakdown() {
                    const breakdown = {};
                    this.vatLines.forEach(line => {
                        if (line.vat_category && line.net_amount > 0) {
                            if (!breakdown[line.vat_category]) {
                                breakdown[line.vat_category] = {
                                    rate: line.vat_rate,
                                    net: 0,
                                    amount: 0
                                };
                            }
                            breakdown[line.vat_category].net += line.net_amount;
                            breakdown[line.vat_category].amount += line.vat_amount;
                        }
                    });
                    return breakdown;
                },
                
                addVatLine() {
                    this.vatLines.push({
                        vat_category: 'STANDARD',
                        net_amount: 0,
                        vat_rate: 0.23,
                        vat_amount: 0,
                        gross_amount: 0,
                        line_number: this.vatLines.length + 1
                    });
                },
                
                removeVatLine(index) {
                    if (this.vatLines.length > 1) {
                        this.vatLines.splice(index, 1);
                        // Update line numbers
                        this.vatLines.forEach((line, i) => {
                            line.line_number = i + 1;
                        });
                    }
                },
                
                calculateVatLine(index) {
                    const line = this.vatLines[index];
                    line.vat_amount = Math.round(line.net_amount * line.vat_rate * 100) / 100;
                    line.gross_amount = Math.round((line.net_amount + line.vat_amount) * 100) / 100;
                },
                
                updateVatLineRate(index) {
                    const line = this.vatLines[index];
                    if (!line.vat_category) return;
                    
                    // Set the VAT rate based on category
                    line.vat_rate = this.vatRates[line.vat_category] || 0;
                    
                    // Recalculate amounts
                    this.calculateVatLine(index);
                },
                
                updateVatRates() {
                    for (let i = 0; i < this.vatLines.length; i++) {
                        this.updateVatLineRate(i);
                    }
                },
                
                updateSupplierDetails() {
                    if (this.supplierId && this.suppliers[this.supplierId]) {
                        this.supplierName = this.suppliers[this.supplierId];
                    }
                },
                
                getCategoryLabel(category) {
                    const labels = {
                        'STANDARD': 'Standard Rate',
                        'REDUCED': 'Reduced Rate',
                        'SECOND_REDUCED': 'Second Reduced Rate',
                        'ZERO': 'Zero Rate'
                    };
                    return labels[category] || category;
                },
                
                submitForm(event) {
                    // Let the form submit normally
                    event.target.submit();
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>