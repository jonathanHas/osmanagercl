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

        {{-- Success Message --}}
        @if(session('success'))
            <div class="bg-green-800 border border-green-600 text-green-100 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        {{-- Error Message --}}
        @if(session('error'))
            <div class="bg-red-800 border border-red-600 text-red-100 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            </div>
        @endif

        {{-- Validation Errors --}}
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
                    
                    {{-- POS Integration Section --}}
                    @if(!$supplier->is_pos_linked)
                        <div class="mt-4 p-4 bg-gray-750 rounded-md border border-blue-600">
                            <div class="flex items-start space-x-3">
                                <input type="checkbox" name="create_in_pos" id="create_in_pos" value="1" 
                                       {{ old('create_in_pos') ? 'checked' : '' }}
                                       class="mt-1 bg-gray-700 border-gray-600 text-blue-600 rounded focus:ring-blue-500 focus:ring-2">
                                <div>
                                    <label for="create_in_pos" class="block text-sm font-medium text-blue-300">
                                        Create in POS system
                                    </label>
                                    <p class="text-xs text-gray-400 mt-1">
                                        This supplier is not linked to the POS system. Check this to create a POS entry and link them.
                                    </p>
                                </div>
                            </div>
                            @error('create_in_pos')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <div class="mt-4 p-4 bg-gray-750 rounded-md border border-purple-600">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-check-circle text-purple-400"></i>
                                <div>
                                    <p class="text-sm font-medium text-purple-300">
                                        POS Integration Active
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        This supplier is linked to POS ID: <span class="font-mono text-purple-300">{{ $supplier->external_pos_id }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                    
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

                            <label class="flex items-center mt-2 text-sm text-gray-300">
                                <input type="checkbox" name="send_daily_sales_email" value="1"
                                       {{ old('send_daily_sales_email', $supplier->send_daily_sales_email) ? 'checked' : '' }}
                                       class="rounded bg-gray-700 border-gray-600 text-indigo-500 focus:ring-indigo-500 mr-2">
                                Email this supplier a same-day sales report each evening
                            </label>
                            <p class="text-gray-500 text-xs mt-1">
                                Requires a valid email address and a POS link. Sent at ~20:15 daily.
                                @if ($supplier->is_pos_linked)
                                    <a href="{{ route('suppliers.daily-sales-preview.show', $supplier) }}" target="_blank"
                                       class="text-indigo-400 hover:text-indigo-300 ml-1">Preview this email &rarr;</a>
                                @endif
                            </p>

                            <label class="flex items-center mt-3 ml-6 text-sm text-gray-300">
                                <input type="checkbox" name="include_sales_values" value="1"
                                       {{ old('include_sales_values', $supplier->include_sales_values) ? 'checked' : '' }}
                                       class="rounded bg-gray-700 border-gray-600 text-indigo-500 focus:ring-indigo-500 mr-2">
                                Include sales values (money) in their report
                            </label>
                            <p class="text-gray-500 text-xs mt-1 ml-6">Untick to send units and trends only, hiding all € figures.</p>

                            <label class="flex items-center mt-3 ml-6 text-sm text-gray-300">
                                <input type="checkbox" name="attach_sales_csv" value="1"
                                       {{ old('attach_sales_csv', $supplier->attach_sales_csv) ? 'checked' : '' }}
                                       class="rounded bg-gray-700 border-gray-600 text-indigo-500 focus:ring-indigo-500 mr-2">
                                Attach a CSV of the figures to their email
                            </label>
                            <p class="text-gray-500 text-xs mt-1 ml-6">Untick to send the summary in the email body only, with no attachment.</p>
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

                {{-- VAT Classification --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">
                        VAT Classification
                        <span class="text-sm font-normal text-gray-400 ml-2">(for VAT3 / RTD reporting)</span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Country</label>
                            <select name="country_code" id="country_code"
                                    class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('country_code') border-red-500 @enderror">
                                <option value="">Select Country</option>
                                @foreach($countryCodes as $code => $name)
                                    <option value="{{ $code }}" {{ old('country_code', $supplier->country_code) === $code ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Used to auto-suggest VAT treatment</p>
                            @error('country_code')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">VAT Treatment</label>
                            <select name="vat_treatment" id="vat_treatment"
                                    class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('vat_treatment') border-red-500 @enderror">
                                <option value="">Auto (based on country)</option>
                                @foreach($vatTreatments as $value => $label)
                                    <option value="{{ $value }}" {{ old('vat_treatment', $supplier->vat_treatment) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">How VAT is accounted for on purchases</p>
                            @error('vat_treatment')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">RTD Classification</label>
                            <select name="rtd_classification"
                                    class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md @error('rtd_classification') border-red-500 @enderror">
                                @foreach($rtdClassifications as $value => $label)
                                    <option value="{{ $value }}" {{ old('rtd_classification', $supplier->rtd_classification ?? 'not_applicable') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Include in RTD dashboard?</p>
                            @error('rtd_classification')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- VAT Treatment Helper Info --}}
                    <div class="mt-4 p-3 bg-gray-750 rounded-md border border-gray-600">
                        <p class="text-xs text-gray-400">
                            <i class="fas fa-info-circle text-blue-400 mr-1"></i>
                            <strong>VAT Treatment Guide:</strong>
                            <span class="text-green-400">Irish VAT</span> = Standard Irish suppliers |
                            <span class="text-blue-400">EU Goods Zero Rated</span> = EU product suppliers (Intrastat) |
                            <span class="text-purple-400">EU Reverse Charge</span> = EU service suppliers |
                            <span class="text-yellow-400">Postponed Import</span> = Non-EU imports |
                            <span class="text-gray-400">Outside Scope</span> = GB/Other
                        </p>
                    </div>

                    {{-- RTD Classification Helper Info --}}
                    <div class="mt-3 p-3 bg-gray-750 rounded-md border border-gray-600">
                        <p class="text-xs text-gray-400">
                            <i class="fas fa-info-circle text-purple-400 mr-1"></i>
                            <strong>RTD Classification Guide:</strong>
                            <span class="text-green-400">Goods - Simple VAT</span> = Uses invoice VAT breakdown fields (T1) |
                            <span class="text-blue-400">Goods - Dedicated Parser</span> = Has specialized parser (Udea, Dynamis, IIH) (T1) |
                            <span class="text-yellow-400">Service/Overhead</span> = Tracked as T2 overhead |
                            <span class="text-gray-400">Not Applicable</span> = Not tracked in RTD
                        </p>
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
                    
                    <button type="submit" id="submitBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                        Update Supplier
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
        });
    </script>
</x-admin-layout>