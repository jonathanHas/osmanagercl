<x-admin-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-start mb-6">
            <div class="flex items-center">
                <a href="{{ route('suppliers.show', $supplier) }}" 
                   class="text-gray-400 hover:text-gray-300 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-gray-100">Edit Supplier</h2>
                    <p class="text-gray-400 mt-1">{{ $supplier->name }} - {{ $supplier->code }}</p>
                    @if($supplier->is_pos_linked)
                        <p class="text-purple-400 text-sm mt-1">
                            <i class="fas fa-link mr-1"></i>POS Linked (ID: {{ $supplier->external_pos_id }})
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Error Messages --}}
        @if($errors->any())
            <div class="bg-red-800 border border-red-600 text-red-100 px-4 py-3 rounded mb-6">
                <h4 class="font-medium mb-2">Please fix the following errors:</h4>
                <ul class="list-disc list-inside text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                {{-- Basic Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Basic Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">
                                Supplier Code *
                                @if($supplier->is_pos_linked)
                                    <span class="text-purple-400 text-xs">(POS Managed)</span>
                                @endif
                            </label>
                            <input type="text" name="code" value="{{ old('code', $supplier->code) }}" required
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('code') border-red-500 @enderror {{ $supplier->is_pos_linked ? 'opacity-75' : '' }}"
                                   placeholder="e.g. SUP-001"
                                   @if($supplier->is_pos_linked) readonly @endif>
                            @if($supplier->is_pos_linked)
                                <p class="text-xs text-gray-500 mt-1">Code cannot be changed for POS-linked suppliers</p>
                            @endif
                            @error('code')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">
                                Supplier Name *
                                @if($supplier->is_pos_linked)
                                    <span class="text-purple-400 text-xs">(POS Managed)</span>
                                @endif
                            </label>
                            <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('name') border-red-500 @enderror {{ $supplier->is_pos_linked ? 'opacity-75' : '' }}"
                                   placeholder="Company or supplier name"
                                   @if($supplier->is_pos_linked) readonly @endif>
                            @if($supplier->is_pos_linked)
                                <p class="text-xs text-gray-500 mt-1">Name synced from POS system</p>
                            @endif
                            @error('name')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Supplier Type *</label>
                            <select name="supplier_type" required
                                    class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('supplier_type') border-red-500 @enderror">
                                <option value="">Select Type</option>
                                @foreach($supplierTypes as $type)
                                    <option value="{{ $type }}" {{ old('supplier_type', $supplier->supplier_type) === $type ? 'selected' : '' }}>
                                        {{ ucfirst($type) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_type')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Status *</label>
                            <select name="status" required
                                    class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('status') border-red-500 @enderror">
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" {{ old('status', $supplier->status) === $status ? 'selected' : '' }}>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Address</label>
                        <textarea name="address" rows="3"
                                  class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('address') border-red-500 @enderror"
                                  placeholder="Full business address">{{ old('address', $supplier->address) }}</textarea>
                        @error('address')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Contact Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Contact Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Contact Person</label>
                            <input type="text" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('contact_person') border-red-500 @enderror"
                                   placeholder="Primary contact name">
                            @error('contact_person')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email', $supplier->email) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('email') border-red-500 @enderror"
                                   placeholder="contact@supplier.com">
                            @error('email')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('phone') border-red-500 @enderror"
                                   placeholder="+353 1 234 5678">
                            @error('phone')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Secondary Phone</label>
                            <input type="text" name="phone_secondary" value="{{ old('phone_secondary', $supplier->phone_secondary) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('phone_secondary') border-red-500 @enderror"
                                   placeholder="Alternative phone number">
                            @error('phone_secondary')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Website</label>
                            <input type="url" name="website" value="{{ old('website', $supplier->website) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('website') border-red-500 @enderror"
                                   placeholder="https://www.supplier.com">
                            @error('website')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Fax</label>
                            <input type="text" name="fax" value="{{ old('fax', $supplier->fax) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('fax') border-red-500 @enderror"
                                   placeholder="Fax number">
                            @error('fax')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Financial Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Financial Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">VAT Number</label>
                            <input type="text" name="vat_number" value="{{ old('vat_number', $supplier->vat_number) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('vat_number') border-red-500 @enderror"
                                   placeholder="IE1234567T">
                            @error('vat_number')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Company Registration</label>
                            <input type="text" name="company_registration" value="{{ old('company_registration', $supplier->company_registration) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('company_registration') border-red-500 @enderror"
                                   placeholder="Company registration number">
                            @error('company_registration')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Tax Reference</label>
                            <input type="text" name="tax_reference" value="{{ old('tax_reference', $supplier->tax_reference) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('tax_reference') border-red-500 @enderror"
                                   placeholder="Tax reference number">
                            @error('tax_reference')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Payment Terms (Days)</label>
                            <input type="number" name="payment_terms_days" value="{{ old('payment_terms_days', $supplier->payment_terms_days) }}" min="0" max="365"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('payment_terms_days') border-red-500 @enderror"
                                   placeholder="30">
                            @error('payment_terms_days')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Preferred Payment Method</label>
                            <select name="preferred_payment_method"
                                    class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('preferred_payment_method') border-red-500 @enderror">
                                <option value="">Select Method</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method }}" {{ old('preferred_payment_method', $supplier->preferred_payment_method) === $method ? 'selected' : '' }}>
                                        {{ strtoupper($method) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('preferred_payment_method')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Bank Account</label>
                            <input type="text" name="bank_account" value="{{ old('bank_account', $supplier->bank_account) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('bank_account') border-red-500 @enderror"
                                   placeholder="Account number">
                            @error('bank_account')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Sort Code</label>
                            <input type="text" name="sort_code" value="{{ old('sort_code', $supplier->sort_code) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('sort_code') border-red-500 @enderror"
                                   placeholder="12-34-56">
                            @error('sort_code')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Default VAT Code</label>
                            <input type="text" name="default_vat_code" value="{{ old('default_vat_code', $supplier->default_vat_code) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('default_vat_code') border-red-500 @enderror"
                                   placeholder="e.g. STANDARD">
                            @error('default_vat_code')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Default Expense Category</label>
                            <input type="text" name="default_expense_category" value="{{ old('default_expense_category', $supplier->default_expense_category) }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('default_expense_category') border-red-500 @enderror"
                                   placeholder="e.g. office_supplies">
                            @error('default_expense_category')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Additional Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Additional Information</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Delivery Instructions</label>
                            <textarea name="delivery_instructions" rows="3"
                                      class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('delivery_instructions') border-red-500 @enderror"
                                      placeholder="Special delivery instructions...">{{ old('delivery_instructions', $supplier->delivery_instructions) }}</textarea>
                            @error('delivery_instructions')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('notes') border-red-500 @enderror"
                                      placeholder="Additional notes about this supplier...">{{ old('notes', $supplier->notes) }}</textarea>
                            @error('notes')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Tags</label>
                            <input type="text" name="tags" value="{{ old('tags', $supplier->tags ? implode(', ', $supplier->tags) : '') }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('tags') border-red-500 @enderror"
                                   placeholder="tag1, tag2, tag3">
                            <p class="text-xs text-gray-500 mt-1">Separate multiple tags with commas</p>
                            @error('tags')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Analytics (Read-only) --}}
                @if($supplier->invoice_count > 0)
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Analytics (Read-Only)</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Total Spent</label>
                            <div class="text-gray-100 font-medium">€{{ number_format($supplier->total_spent, 2) }}</div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Invoice Count</label>
                            <div class="text-gray-100 font-medium">{{ $supplier->invoice_count }}</div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Average Invoice</label>
                            <div class="text-gray-100 font-medium">€{{ number_format($supplier->average_invoice_value, 2) }}</div>
                        </div>
                        
                        @if($supplier->last_invoice_date)
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Last Invoice</label>
                            <div class="text-gray-100 font-medium">{{ $supplier->last_invoice_date->format('M j, Y') }}</div>
                        </div>
                        @endif
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Performance Rating</label>
                            <div class="text-gray-100 font-medium">{{ ucfirst($supplier->performance_rating) }}</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-700">
                        <p class="text-xs text-gray-500">
                            Analytics are automatically calculated from invoices. Use the "Refresh Analytics" button to update these values.
                        </p>
                    </div>
                </div>
                @endif

                {{-- Form Actions --}}
                <div class="flex justify-between items-center pt-6">
                    <a href="{{ route('suppliers.show', $supplier) }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded">
                        Cancel
                    </a>
                    
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                        Update Supplier
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>