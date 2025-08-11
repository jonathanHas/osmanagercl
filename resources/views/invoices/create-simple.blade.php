<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" x-data="simpleInvoiceForm()">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Create Invoice (Simple)</h2>
            <div class="flex space-x-2">
                <a href="{{ route('invoices.create') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm">
                    Detailed Invoice
                </a>
                <a href="{{ route('invoices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Invoices
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('invoices.store-simple') }}" @submit.prevent="submitForm">
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
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('invoice_date') border-red-500 @enderror"
                                       value="{{ old('invoice_date', date('Y-m-d')) }}">
                                @error('invoice_date')
                                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Supplier</label>
                                <select name="supplier_id" x-model="supplierId"
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

                    {{-- VAT Breakdown --}}
                    <div class="bg-gray-800 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">VAT Breakdown</h3>
                        <p class="text-sm text-gray-400 mb-4">Enter the net amounts for each VAT rate. VAT will be calculated automatically.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Standard Rate 23% --}}
                            <div class="bg-gray-900 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-gray-300 mb-2">Standard Rate (23%)</h4>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Net Amount</label>
                                    <input type="number" name="standard_net" x-model.number="standardNet" step="0.01" min="0"
                                           @input="calculateVat()"
                                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded text-sm">
                                </div>
                                <div class="mt-2 text-xs text-gray-400">
                                    VAT: €<span x-text="standardVat.toFixed(2)"></span> | 
                                    Total: €<span x-text="(standardNet + standardVat).toFixed(2)"></span>
                                </div>
                            </div>

                            {{-- Reduced Rate 13.5% --}}
                            <div class="bg-gray-900 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-gray-300 mb-2">Reduced Rate (13.5%)</h4>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Net Amount</label>
                                    <input type="number" name="reduced_net" x-model.number="reducedNet" step="0.01" min="0"
                                           @input="calculateVat()"
                                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded text-sm">
                                </div>
                                <div class="mt-2 text-xs text-gray-400">
                                    VAT: €<span x-text="reducedVat.toFixed(2)"></span> | 
                                    Total: €<span x-text="(reducedNet + reducedVat).toFixed(2)"></span>
                                </div>
                            </div>

                            {{-- Second Reduced Rate 9% --}}
                            <div class="bg-gray-900 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-gray-300 mb-2">Second Reduced Rate (9%)</h4>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Net Amount</label>
                                    <input type="number" name="second_reduced_net" x-model.number="secondReducedNet" step="0.01" min="0"
                                           @input="calculateVat()"
                                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded text-sm">
                                </div>
                                <div class="mt-2 text-xs text-gray-400">
                                    VAT: €<span x-text="secondReducedVat.toFixed(2)"></span> | 
                                    Total: €<span x-text="(secondReducedNet + secondReducedVat).toFixed(2)"></span>
                                </div>
                            </div>

                            {{-- Zero Rate 0% --}}
                            <div class="bg-gray-900 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-gray-300 mb-2">Zero Rate (0%)</h4>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1">Net Amount</label>
                                    <input type="number" name="zero_net" x-model.number="zeroNet" step="0.01" min="0"
                                           @input="calculateVat()"
                                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded text-sm">
                                </div>
                                <div class="mt-2 text-xs text-gray-400">
                                    VAT: €<span x-text="zeroVat.toFixed(2)"></span> | 
                                    Total: €<span x-text="(zeroNet + zeroVat).toFixed(2)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Hidden VAT amount fields --}}
                        <input type="hidden" name="standard_vat" :value="standardVat.toFixed(2)">
                        <input type="hidden" name="reduced_vat" :value="reducedVat.toFixed(2)">
                        <input type="hidden" name="second_reduced_vat" :value="secondReducedVat.toFixed(2)">
                        <input type="hidden" name="zero_vat" :value="zeroVat.toFixed(2)">
                        <input type="hidden" name="subtotal" :value="totalNet.toFixed(2)">
                        <input type="hidden" name="vat_amount" :value="totalVat.toFixed(2)">
                        <input type="hidden" name="total_amount" :value="grandTotal.toFixed(2)">
                    </div>
                </div>

                {{-- Summary Sidebar --}}
                <div class="lg:col-span-1">
                    <div class="bg-gray-800 rounded-lg p-6 sticky top-6">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">Summary</h3>
                        
                        {{-- Totals --}}
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Total Net</span>
                                <span class="text-gray-300">€<span x-text="totalNet.toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Total VAT</span>
                                <span class="text-gray-300">€<span x-text="totalVat.toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between text-lg font-semibold pt-2 border-gray-700">
                                <span class="text-gray-100">Grand Total</span>
                                <span class="text-white">€<span x-text="grandTotal.toFixed(2)"></span></span>
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
        function simpleInvoiceForm() {
            return {
                invoiceNumber: '{{ old('invoice_number') }}',
                invoiceDate: '{{ old('invoice_date', date('Y-m-d')) }}',
                supplierId: '{{ old('supplier_id') }}',
                supplierName: '{{ old('supplier_name') }}',
                dueDate: '{{ old('due_date') }}',
                expenseCategory: '{{ old('expense_category') }}',
                notes: '{{ old('notes') }}',
                
                // VAT amounts
                standardNet: 0,
                standardVat: 0,
                reducedNet: 0,
                reducedVat: 0,
                secondReducedNet: 0,
                secondReducedVat: 0,
                zeroNet: 0,
                zeroVat: 0,
                
                get totalNet() {
                    return this.standardNet + this.reducedNet + this.secondReducedNet + this.zeroNet;
                },
                
                get totalVat() {
                    return this.standardVat + this.reducedVat + this.secondReducedVat + this.zeroVat;
                },
                
                get grandTotal() {
                    return this.totalNet + this.totalVat;
                },
                
                calculateVat() {
                    this.standardVat = Math.round(this.standardNet * 0.23 * 100) / 100;
                    this.reducedVat = Math.round(this.reducedNet * 0.135 * 100) / 100;
                    this.secondReducedVat = Math.round(this.secondReducedNet * 0.09 * 100) / 100;
                    this.zeroVat = 0; // Always 0 for zero rate
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