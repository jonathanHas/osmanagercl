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

        {{-- Success Banner for Invoice Created --}}
        @if(session('invoice_created'))
            @php
                $invoice = session('invoice_created');
                $supplierName = $invoice['supplier_name'] ?? 'No supplier selected';
            @endphp
            <div class="mb-6" data-invoice-banner>
                <div class="relative overflow-hidden rounded-2xl border border-emerald-500/40 bg-gradient-to-r from-emerald-950 via-emerald-900/95 to-emerald-800/90 p-5 shadow-xl">
                    <div class="pointer-events-none absolute -top-16 -right-8 h-40 w-40 rounded-full bg-emerald-500/20 blur-3xl"></div>
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-300 ring-1 ring-inset ring-emerald-400/40">
                                <svg class="h-7 w-7" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300/80">Invoice saved</p>
                                <h3 class="text-lg font-semibold text-emerald-100">{{ $invoice['invoice_number'] }} created</h3>
                                <p class="mt-1 text-sm text-emerald-100/80">
                                    Total <span class="font-semibold text-white">€{{ number_format($invoice['total_amount'], 2) }}</span>
                                    for <span class="font-medium text-white/90">{{ $supplierName }}</span> on
                                    <span class="text-white/80">{{ $invoice['invoice_date'] }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <div class="grid w-full grid-cols-1 gap-3 text-sm text-emerald-200 sm:w-auto sm:grid-cols-2">
                                <div class="rounded-xl border border-emerald-400/20 bg-emerald-950/60 px-4 py-3 shadow-inner">
                                    <p class="text-xs uppercase tracking-wide text-emerald-300/70">Supplier</p>
                                    <p class="mt-1 font-medium text-white/90">{{ $supplierName }}</p>
                                </div>
                                <div class="rounded-xl border border-emerald-400/20 bg-emerald-950/60 px-4 py-3 shadow-inner">
                                    <p class="text-xs uppercase tracking-wide text-emerald-300/70">Invoice Date</p>
                                    <p class="mt-1 font-medium text-white/90">{{ $invoice['invoice_date'] }}</p>
                                </div>
                            </div>

                            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                                <a href="{{ route('invoices.show', $invoice['id']) }}"
                                   class="inline-flex items-center justify-center rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/20">
                                    View Invoice
                                </a>
                                <a href="{{ route('invoices.create-simple') }}"
                                   class="inline-flex items-center justify-center rounded-lg border border-emerald-400/40 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:border-emerald-300 hover:text-white">
                                    Start Fresh
                                </a>
                            </div>
                        </div>
                    </div>

                    <button type="button"
                            onclick="this.closest('[data-invoice-banner]').remove()"
                            class="absolute right-3 top-3 rounded-full p-2 text-emerald-200 transition hover:bg-white/10 hover:text-white focus:outline-none">
                        <span class="sr-only">Dismiss</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        {{-- Duplicate Warning Banner --}}
        @if(session('duplicate_found'))
            @php
                $duplicates = session('duplicate_found')['invoices'];
            @endphp
            <div class="mb-6 bg-yellow-900/30 border border-yellow-700 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-yellow-400">Possible Duplicate Invoice Detected!</h3>
                        <div class="mt-2 text-sm text-yellow-300">
                            <p class="mb-2">The following similar invoice(s) already exist:</p>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($duplicates as $dup)
                                    <li>
                                        <strong>{{ $dup['invoice_number'] }}</strong> -
                                        {{ $dup['supplier_name'] }} -
                                        {{ $dup['invoice_date'] }} -
                                        €{{ number_format($dup['total_amount'], 2) }}
                                        <a href="{{ route('invoices.show', $dup['id']) }}"
                                           class="text-yellow-200 hover:text-yellow-100 underline ml-2"
                                           target="_blank">
                                            View →
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-3 text-xs text-yellow-400">
                                <strong>Detection criteria:</strong> Same supplier, within ±1 day, within ±€0.50
                            </p>
                        </div>
                        <div class="mt-4 flex space-x-3">
                            <button type="button"
                                    onclick="document.getElementById('force-create-form').submit()"
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Create Anyway
                            </button>
                            <button type="button"
                                    onclick="window.location.href='{{ route('invoices.create-simple') }}'"
                                    class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Start Fresh
                            </button>
                        </div>
                    </div>
                    <div class="ml-auto pl-3">
                        <div class="flex items-center">
                            <button type="button"
                                    onclick="this.closest('.bg-yellow-900\\/30').remove()"
                                    class="inline-flex rounded-md text-yellow-400 hover:text-yellow-300 focus:outline-none">
                                <span class="sr-only">Dismiss</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Hidden form for force creation --}}
            <form id="force-create-form" method="POST" action="{{ route('invoices.store-simple') }}" style="display: none;">
                @csrf
                <input type="hidden" name="force" value="1">
                @foreach(old() as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $k => $v)
                            <input type="hidden" name="{{ $key }}[{{ $k }}]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
            </form>
        @endif

        <form method="POST" action="{{ route('invoices.store-simple') }}" @submit.prevent="submitForm" enctype="multipart/form-data">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Invoice Details --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- Essential Fields --}}
                    <div class="bg-gray-800 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">Essential Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Invoice Date - FIRST field for keyboard entry --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Invoice Date *</label>
                                <input type="date" name="invoice_date" x-model="invoiceDate" required autofocus
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('invoice_date') border-red-500 @enderror"
                                       value="{{ old('invoice_date', date('Y-m-d')) }}">
                                @error('invoice_date')
                                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Supplier dropdown - commonly used --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Supplier</label>
                                <select name="supplier_id" x-model="supplierId"
                                        class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                                    <option value="">Select Supplier (Optional)</option>
                                    @foreach($suppliers as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Auto-populates Supplier Name below</p>
                            </div>
                        </div>
                    </div>

                    {{-- VAT Breakdown - Reordered: 0% first (most common) --}}
                    <div class="bg-gray-800 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">VAT Breakdown</h3>
                        <p class="text-sm text-gray-400 mb-4">Enter the net amounts for each VAT rate. VAT will be calculated automatically.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Zero Rate 0% - FIRST (most common for food) --}}
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

                            {{-- Standard Rate 23% - LAST --}}
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

                    {{-- Optional Fields Section --}}
                    <div class="bg-gray-800 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-100 mb-4">Additional Details (Optional)</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Supplier Invoice Reference --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">
                                    Supplier Invoice Reference
                                    <span class="text-xs text-gray-500">(optional)</span>
                                </label>
                                <input type="text" name="supplier_invoice_reference" x-model="supplierInvoiceReference"
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md"
                                       placeholder="e.g., INV-2024-001234"
                                       value="{{ old('supplier_invoice_reference') }}">
                                <p class="text-xs text-gray-400 mt-1">The supplier's original invoice number (if available)</p>
                            </div>

                            {{-- Supplier Name - Auto-populated --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Supplier Name *</label>
                                <input type="text" name="supplier_name" x-model="supplierName" required
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('supplier_name') border-red-500 @enderror"
                                       value="{{ old('supplier_name') }}">
                                <p class="text-xs text-gray-400 mt-1">Auto-populated from Supplier dropdown above</p>
                                @error('supplier_name')
                                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Due Date --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Due Date</label>
                                <input type="date" name="due_date" x-model="dueDate"
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('due_date') border-red-500 @enderror"
                                       value="{{ old('due_date') }}">
                                @error('due_date')
                                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Expense Category --}}
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

                        {{-- Notes --}}
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Notes</label>
                            <textarea name="notes" rows="2" x-model="notes"
                                      class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">{{ old('notes') }}</textarea>
                        </div>

                        {{-- File Upload --}}
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-400 mb-2">
                                Invoice Document
                                <span class="text-xs text-gray-500">- PDF, DOC, XLS, Images (max 25MB)</span>
                            </label>
                            <div class="relative">
                                <input type="file"
                                       name="invoice_document"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.tiff,.tif"
                                       x-ref="fileInput"
                                       x-on:change="handleFileChange($event)"
                                       class="hidden">

                                <div x-show="!selectedFile"
                                     @click="$refs.fileInput.click()"
                                     class="border-2 border-dashed border-gray-600 hover:border-gray-500 bg-gray-700 hover:bg-gray-650 rounded-lg p-6 text-center cursor-pointer transition-colors">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-400">
                                        <span class="font-medium text-blue-400 hover:text-blue-300">Click to upload</span> or drag and drop
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, TIFF up to 25MB</p>
                                </div>

                                <div x-show="selectedFile" class="bg-gray-700 rounded-lg p-4 border border-gray-600">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <svg class="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-100" x-text="selectedFile?.name"></p>
                                                <p class="text-xs text-gray-400" x-text="formatFileSize(selectedFile?.size)"></p>
                                            </div>
                                        </div>
                                        <button type="button"
                                                @click="clearFile()"
                                                class="ml-4 text-red-400 hover:text-red-300">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @error('invoice_document')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
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
                invoiceDate: '{{ old('invoice_date', date('Y-m-d')) }}',
                supplierId: '{{ old('supplier_id') }}',
                supplierName: '{{ old('supplier_name') }}',
                supplierInvoiceReference: '{{ old('supplier_invoice_reference') }}',
                dueDate: '{{ old('due_date') }}',
                expenseCategory: '{{ old('expense_category') }}',
                notes: '{{ old('notes') }}',

                // Supplier data for auto-population
                suppliers: @json($suppliers),
                
                // File upload
                selectedFile: null,
                
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
                
                // Watch for supplier changes and auto-populate supplier name
                init() {
                    // Check for supplier_id in query parameter (from previous submission)
                    const urlParams = new URLSearchParams(window.location.search);
                    const supplierIdFromUrl = urlParams.get('supplier_id');

                    if (supplierIdFromUrl && this.suppliers[supplierIdFromUrl]) {
                        // Pre-select the supplier from previous invoice
                        this.supplierId = supplierIdFromUrl;
                        this.supplierName = this.suppliers[supplierIdFromUrl];
                    }

                    this.$watch('supplierId', (value) => {
                        if (value && this.suppliers[value]) {
                            this.supplierName = this.suppliers[value];
                        } else if (!value) {
                            // Only clear if user manually clears dropdown, not if they're typing in supplier name
                            this.supplierName = '';
                        }
                    });
                },
                
                // File handling methods
                handleFileChange(event) {
                    const file = event.target.files[0];
                    if (file) {
                        // Check file size (25MB limit)
                        if (file.size > 25 * 1024 * 1024) {
                            alert('File size must be less than 25MB');
                            this.clearFile();
                            return;
                        }
                        this.selectedFile = file;
                    }
                },
                
                clearFile() {
                    this.selectedFile = null;
                    this.$refs.fileInput.value = '';
                },
                
                formatFileSize(bytes) {
                    if (!bytes) return '';
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(1024));
                    return Math.round(bytes / Math.pow(1024, i) * 10) / 10 + ' ' + sizes[i];
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
