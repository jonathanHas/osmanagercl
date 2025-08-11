<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-start mb-6">
            <div class="flex items-center">
                <a href="{{ route('suppliers.index') }}" 
                   class="text-gray-400 hover:text-gray-300 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-gray-100 flex items-center">
                        {{ $supplier->name }}
                        @if($supplier->is_pos_linked)
                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-800 text-purple-100">
                                <i class="fas fa-link mr-1"></i>POS Linked
                            </span>
                        @endif
                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($supplier->status === 'active') bg-green-800 text-green-100
                            @elseif($supplier->status === 'inactive') bg-gray-600 text-gray-100
                            @elseif($supplier->status === 'suspended') bg-red-800 text-red-100
                            @else bg-yellow-800 text-yellow-100 @endif">
                            {{ ucfirst($supplier->status) }}
                        </span>
                    </h2>
                    <p class="text-gray-400 mt-1">Code: {{ $supplier->code }}</p>
                </div>
            </div>
            <div class="flex space-x-2">
                <form class="toggle-status-form inline" data-supplier-id="{{ $supplier->id }}">
                    @csrf
                    <button type="submit" 
                            class="status-toggle-btn px-3 py-2 rounded font-bold transition-colors {{ $supplier->status === 'active' ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-600 hover:bg-gray-700 text-white' }}"
                            data-status="{{ $supplier->status }}">
                        <i class="fas fa-toggle-{{ $supplier->status === 'active' ? 'on' : 'off' }} mr-2"></i>
                        <span class="status-text">{{ $supplier->status === 'active' ? 'Active' : 'Activate' }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('suppliers.refresh-analytics', $supplier) }}" class="inline">
                    @csrf
                    <button type="submit" 
                            class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded text-sm">
                        <i class="fas fa-sync mr-2"></i>Refresh Analytics
                    </button>
                </form>
                <a href="{{ route('suppliers.edit', $supplier) }}" 
                   class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded text-sm">
                    <i class="fas fa-edit mr-2"></i>Edit Supplier
                </a>
                <a href="{{ route('invoices.create', ['supplier' => $supplier->id]) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                    <i class="fas fa-plus mr-2"></i>New Invoice
                </a>
            </div>
        </div>

        {{-- Success/Error Messages --}}
        @if(session('success'))
            <div class="bg-green-800 border border-green-600 text-green-100 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-800 border border-red-600 text-red-100 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Details --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Basic Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Basic Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-400">Supplier Name</label>
                            <div class="text-gray-100">{{ $supplier->name }}</div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-400">Supplier Code</label>
                            <div class="text-gray-100">{{ $supplier->code }}</div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-400">Type</label>
                            <div class="text-gray-100">{{ ucfirst($supplier->supplier_type) }}</div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-400">Status</label>
                            <div class="text-gray-100">{{ ucfirst($supplier->status) }}</div>
                        </div>
                        @if($supplier->vat_number)
                        <div>
                            <label class="text-sm font-medium text-gray-400">VAT Number</label>
                            <div class="text-gray-100">{{ $supplier->vat_number }}</div>
                        </div>
                        @endif
                        @if($supplier->company_registration)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Company Registration</label>
                            <div class="text-gray-100">{{ $supplier->company_registration }}</div>
                        </div>
                        @endif
                    </div>

                    @if($supplier->address)
                    <div class="mt-4">
                        <label class="text-sm font-medium text-gray-400">Address</label>
                        <div class="text-gray-100 whitespace-pre-line">{{ $supplier->address }}</div>
                    </div>
                    @endif
                </div>

                {{-- Contact Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Contact Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($supplier->contact_person)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Contact Person</label>
                            <div class="text-gray-100">{{ $supplier->contact_person }}</div>
                        </div>
                        @endif
                        @if($supplier->phone)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Phone</label>
                            <div class="text-gray-100">
                                <a href="tel:{{ $supplier->phone }}" class="text-blue-400 hover:text-blue-300">
                                    {{ $supplier->phone }}
                                </a>
                            </div>
                        </div>
                        @endif
                        @if($supplier->phone_secondary)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Secondary Phone</label>
                            <div class="text-gray-100">
                                <a href="tel:{{ $supplier->phone_secondary }}" class="text-blue-400 hover:text-blue-300">
                                    {{ $supplier->phone_secondary }}
                                </a>
                            </div>
                        </div>
                        @endif
                        @if($supplier->email)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Email</label>
                            <div class="text-gray-100">
                                <a href="mailto:{{ $supplier->email }}" class="text-blue-400 hover:text-blue-300">
                                    {{ $supplier->email }}
                                </a>
                            </div>
                        </div>
                        @endif
                        @if($supplier->website)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Website</label>
                            <div class="text-gray-100">
                                <a href="{{ $supplier->website }}" target="_blank" class="text-blue-400 hover:text-blue-300">
                                    {{ $supplier->website }} <i class="fas fa-external-link-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                        @endif
                        @if($supplier->fax)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Fax</label>
                            <div class="text-gray-100">{{ $supplier->fax }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Financial Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Financial Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($supplier->payment_terms_days)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Payment Terms</label>
                            <div class="text-gray-100">{{ $supplier->payment_terms_days }} days</div>
                        </div>
                        @endif
                        @if($supplier->preferred_payment_method)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Preferred Payment Method</label>
                            <div class="text-gray-100">{{ strtoupper($supplier->preferred_payment_method) }}</div>
                        </div>
                        @endif
                        @if($supplier->bank_account)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Bank Account</label>
                            <div class="text-gray-100">{{ $supplier->bank_account }}</div>
                        </div>
                        @endif
                        @if($supplier->sort_code)
                        <div>
                            <label class="text-sm font-medium text-gray-400">Sort Code</label>
                            <div class="text-gray-100">{{ $supplier->sort_code }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Recent Invoices --}}
                @if($supplier->invoices->count() > 0)
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-100">Recent Invoices</h3>
                        <a href="{{ route('invoices.index', ['supplier' => $supplier->id]) }}" 
                           class="text-blue-400 hover:text-blue-300 text-sm">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <div class="space-y-3">
                        @foreach($supplier->invoices->take(5) as $invoice)
                            <div class="flex justify-between items-center p-3 bg-gray-700 rounded">
                                <div>
                                    <div class="text-gray-100 font-medium">{{ $invoice->invoice_number }}</div>
                                    <div class="text-xs text-gray-400">{{ $invoice->invoice_date->format('M j, Y') }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-gray-100 font-medium">€{{ number_format($invoice->total_amount, 2) }}</div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        @if($invoice->payment_status === 'paid') bg-green-800 text-green-100
                                        @elseif($invoice->payment_status === 'pending') bg-yellow-800 text-yellow-100
                                        @elseif($invoice->payment_status === 'overdue') bg-red-800 text-red-100
                                        @else bg-gray-600 text-gray-100 @endif">
                                        {{ ucfirst($invoice->payment_status) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Additional Information --}}
                @if($supplier->delivery_instructions || $supplier->notes || $supplier->tags)
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Additional Information</h3>
                    
                    @if($supplier->delivery_instructions)
                    <div class="mb-4">
                        <label class="text-sm font-medium text-gray-400">Delivery Instructions</label>
                        <div class="text-gray-100 whitespace-pre-line">{{ $supplier->delivery_instructions }}</div>
                    </div>
                    @endif
                    
                    @if($supplier->notes)
                    <div class="mb-4">
                        <label class="text-sm font-medium text-gray-400">Notes</label>
                        <div class="text-gray-100 whitespace-pre-line">{{ $supplier->notes }}</div>
                    </div>
                    @endif
                    
                    @if($supplier->tags && count($supplier->tags) > 0)
                    <div>
                        <label class="text-sm font-medium text-gray-400">Tags</label>
                        <div class="flex flex-wrap gap-2 mt-1">
                            @foreach($supplier->tags as $tag)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-800 text-blue-100">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Sidebar Stats --}}
            <div class="space-y-6">
                {{-- Financial Stats --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Financial Summary</h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Total Spent</span>
                            <span class="text-gray-100 font-medium">€{{ number_format($invoiceStats->total_spent ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Total Owed</span>
                            <span class="text-red-400 font-medium">€{{ number_format($invoiceStats->total_owed ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Total Invoices</span>
                            <span class="text-gray-100 font-medium">{{ $invoiceStats->total_count ?? 0 }}</span>
                        </div>
                        @if($supplier->average_invoice_value > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-400">Average Invoice</span>
                            <span class="text-gray-100 font-medium">€{{ number_format($supplier->average_invoice_value, 2) }}</span>
                        </div>
                        @endif
                        @if($invoiceStats->last_invoice_date)
                        <div class="flex justify-between">
                            <span class="text-gray-400">Last Invoice</span>
                            <span class="text-gray-100 font-medium">{{ \Carbon\Carbon::parse($invoiceStats->last_invoice_date)->format('M j, Y') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Performance Rating --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Performance</h3>
                    
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-2
                            @if($supplier->performance_rating === 'premium') text-purple-400
                            @elseif($supplier->performance_rating === 'regular') text-blue-400
                            @elseif($supplier->performance_rating === 'occasional') text-green-400
                            @else text-gray-400 @endif">
                            {{ ucfirst($supplier->performance_rating) }}
                        </div>
                        <div class="text-sm text-gray-400">Supplier Rating</div>
                    </div>
                    
                    @if($supplier->is_overdue_for_contact)
                    <div class="mt-4 p-3 bg-yellow-800 rounded">
                        <div class="text-yellow-100 text-sm">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            No recent activity - consider reaching out
                        </div>
                    </div>
                    @endif
                </div>

                {{-- System Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">System Information</h3>
                    
                    <div class="space-y-3 text-sm">
                        @if($supplier->is_pos_linked)
                        <div class="flex justify-between">
                            <span class="text-gray-400">POS ID</span>
                            <span class="text-purple-400">{{ $supplier->external_pos_id }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-400">Created</span>
                            <span class="text-gray-100">{{ $supplier->created_at->format('M j, Y') }}</span>
                        </div>
                        @if($supplier->creator)
                        <div class="flex justify-between">
                            <span class="text-gray-400">Created By</span>
                            <span class="text-gray-100">{{ $supplier->creator->name }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-400">Updated</span>
                            <span class="text-gray-100">{{ $supplier->updated_at->format('M j, Y') }}</span>
                        </div>
                        @if($supplier->updater)
                        <div class="flex justify-between">
                            <span class="text-gray-400">Updated By</span>
                            <span class="text-gray-100">{{ $supplier->updater->name }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle status toggle AJAX
            document.querySelectorAll('.toggle-status-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const supplierId = this.dataset.supplierId;
                    const button = this.querySelector('.status-toggle-btn');
                    const statusText = this.querySelector('.status-text');
                    const icon = this.querySelector('i');
                    const originalText = statusText.textContent;
                    
                    // Show loading state
                    button.disabled = true;
                    statusText.textContent = 'Loading...';
                    
                    // Send AJAX request
                    fetch(`/suppliers/${supplierId}/toggle-status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.querySelector('input[name="_token"]').value
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update button appearance
                            const newStatus = data.status;
                            const isActive = newStatus === 'active';
                            
                            // Update classes
                            button.className = `status-toggle-btn px-3 py-2 rounded font-bold transition-colors ${
                                isActive ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-gray-600 hover:bg-gray-700 text-white'
                            }`;
                            
                            // Update icon
                            icon.className = `fas fa-toggle-${isActive ? 'on' : 'off'} mr-2`;
                            
                            // Update text
                            statusText.textContent = isActive ? 'Active' : 'Activate';
                            
                            // Update data attribute
                            button.dataset.status = newStatus;
                            
                            // Show success message
                            showNotification(data.message, 'success');
                        } else {
                            throw new Error(data.message || 'Failed to update status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        statusText.textContent = originalText;
                        showNotification(error.message || 'Failed to update supplier status', 'error');
                    })
                    .finally(() => {
                        button.disabled = false;
                    });
                });
            });
        });

        // Simple notification function
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md max-w-md ${
                type === 'success' ? 'bg-green-800 text-green-100' : 
                type === 'error' ? 'bg-red-800 text-red-100' : 
                'bg-blue-800 text-blue-100'
            }`;
            notification.textContent = message;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
    @endpush
</x-admin-layout>