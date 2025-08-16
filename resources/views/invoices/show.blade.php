<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Invoice #{{ $invoice->invoice_number }}</h2>
            <div class="flex space-x-2">
                <a href="{{ route('invoices.edit', $invoice) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Edit Invoice
                </a>
                <a href="{{ route('invoices.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to List
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Invoice Content --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Invoice Header --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-100 mb-3">Invoice Details</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex">
                                    <dt class="text-gray-400 w-32">Invoice Number:</dt>
                                    <dd class="text-gray-200 font-semibold">{{ $invoice->invoice_number }}</dd>
                                </div>
                                <div class="flex">
                                    <dt class="text-gray-400 w-32">Invoice Date:</dt>
                                    <dd class="text-gray-200">{{ $invoice->invoice_date->format('d/m/Y') }}</dd>
                                </div>
                                <div class="flex">
                                    <dt class="text-gray-400 w-32">Due Date:</dt>
                                    <dd class="text-gray-200">
                                        {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'Not set' }}
                                        @if($invoice->isOverdue())
                                            <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-900 text-red-300">
                                                Overdue
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="flex">
                                    <dt class="text-gray-400 w-32">Category:</dt>
                                    <dd class="text-gray-200">{{ $invoice->expense_category ?: 'Not categorized' }}</dd>
                                </div>
                            </dl>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-100 mb-3">Supplier</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex">
                                    <dt class="text-gray-400 w-32">Name:</dt>
                                    <dd class="text-gray-200 font-semibold">{{ $invoice->supplier_name }}</dd>
                                </div>
                                @if($invoice->supplier)
                                    <div class="flex">
                                        <dt class="text-gray-400 w-32">VAT Number:</dt>
                                        <dd class="text-gray-200">{{ $invoice->supplier->vat_number ?: 'Not provided' }}</dd>
                                    </div>
                                    <div class="flex">
                                        <dt class="text-gray-400 w-32">Email:</dt>
                                        <dd class="text-gray-200">{{ $invoice->supplier->email ?: 'Not provided' }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    @if($invoice->notes)
                        <div class="mt-4 pt-4 border-t border-gray-700">
                            <h4 class="text-sm font-medium text-gray-400 mb-1">Notes</h4>
                            <p class="text-gray-300 text-sm">{{ $invoice->notes }}</p>
                        </div>
                    @endif
                </div>

                {{-- VAT Lines --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">VAT Lines</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="text-left text-xs font-medium text-gray-400 uppercase pb-2">VAT Category</th>
                                    <th class="text-right text-xs font-medium text-gray-400 uppercase pb-2 w-32">Net Amount</th>
                                    <th class="text-center text-xs font-medium text-gray-400 uppercase pb-2 w-24">VAT %</th>
                                    <th class="text-right text-xs font-medium text-gray-400 uppercase pb-2 w-32">VAT Amount</th>
                                    <th class="text-right text-xs font-medium text-gray-400 uppercase pb-2 w-32">Gross Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->vatLines as $line)
                                    <tr class="border-b border-gray-700">
                                        <td class="py-3 text-gray-300">
                                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-gray-700 text-gray-300">
                                                {{ $line->vat_category_label }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-right text-gray-300">€{{ number_format($line->net_amount, 2) }}</td>
                                        <td class="py-3 text-center text-gray-300">{{ $line->formatted_vat_rate }}</td>
                                        <td class="py-3 text-right text-gray-300">€{{ number_format($line->vat_amount, 2) }}</td>
                                        <td class="py-3 text-right font-semibold text-white">€{{ number_format($line->gross_amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-gray-600">
                                    <td class="py-3 text-right font-semibold text-gray-400">Totals:</td>
                                    <td class="py-3 text-right font-semibold text-gray-300">€{{ number_format($invoice->subtotal, 2) }}</td>
                                    <td class="py-3"></td>
                                    <td class="py-3 text-right font-semibold text-gray-300">€{{ number_format($invoice->vat_amount, 2) }}</td>
                                    <td class="py-3 text-right font-bold text-white text-lg">€{{ number_format($invoice->total_amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Inline Document Viewer --}}
                <div class="bg-gray-800 rounded-lg" id="inline-viewer-section" style="display: none;">
                    <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-100">Document Viewer</h3>
                        <div class="flex space-x-2">
                            <button onclick="toggleViewer()" class="text-gray-400 hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="relative">
                        <iframe id="document-iframe" 
                                src="" 
                                class="w-full border-0 bg-white" 
                                style="height: 600px;"
                                sandbox="allow-same-origin"
                                title="Document Viewer">
                        </iframe>
                        <div id="viewer-loading" class="absolute inset-0 flex items-center justify-center bg-gray-900 bg-opacity-75" style="display: none;">
                            <div class="text-center">
                                <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <div class="text-gray-300">Loading document...</div>
                            </div>
                        </div>
                        <div id="viewer-error" class="absolute inset-0 flex items-center justify-center bg-gray-900 bg-opacity-75 text-center" style="display: none;">
                            <div>
                                <svg class="w-12 h-12 mx-auto text-red-500 mb-3" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                </svg>
                                <div class="text-gray-300 mb-2">Could not load document</div>
                                <button onclick="window.open(document.getElementById('document-iframe').src, '_blank')" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                                    Open in New Tab
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- Payment Status --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Payment Status</h3>
                    
                    @php
                        $statusColors = [
                            'pending' => 'bg-yellow-900 text-yellow-300',
                            'overdue' => 'bg-red-900 text-red-300',
                            'paid' => 'bg-green-900 text-green-300',
                            'partial' => 'bg-orange-900 text-orange-300',
                            'cancelled' => 'bg-gray-700 text-gray-400',
                        ];
                        $statusColor = $statusColors[$invoice->payment_status] ?? 'bg-gray-700 text-gray-400';
                    @endphp
                    
                    <div class="text-center mb-4">
                        <span class="px-4 py-2 inline-flex text-lg font-semibold rounded-full {{ $statusColor }}">
                            {{ ucfirst($invoice->payment_status) }}
                        </span>
                    </div>
                    
                    @if($invoice->payment_status === 'paid')
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-400">Payment Date:</dt>
                                <dd class="text-gray-200">{{ $invoice->payment_date ? $invoice->payment_date->format('d/m/Y') : '-' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-400">Payment Method:</dt>
                                <dd class="text-gray-200">{{ $invoice->payment_method ?: '-' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-400">Reference:</dt>
                                <dd class="text-gray-200">{{ $invoice->payment_reference ?: '-' }}</dd>
                            </div>
                        </dl>
                    @elseif($invoice->payment_status !== 'cancelled')
                        <form action="{{ route('invoices.mark-paid', $invoice) }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Payment Date</label>
                                <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}"
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Payment Method</label>
                                <select name="payment_method" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md text-sm">
                                    <option value="">Select method</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="card">Card</option>
                                    <option value="cash">Cash</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-1">Reference</label>
                                <input type="text" name="payment_reference" 
                                       class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md text-sm"
                                       placeholder="Transaction ref...">
                            </div>
                            <button type="submit" 
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Mark as Paid
                            </button>
                        </form>
                    @endif
                </div>

                {{-- VAT Breakdown --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">VAT Breakdown</h3>
                    <div class="space-y-2">
                        @foreach($vatBreakdown as $vat)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-400">
                                    {{ $vat['code'] }} @ {{ number_format($vat['rate'] * 100, 1) }}%
                                </span>
                                <div class="text-right">
                                    <div class="text-gray-300">€{{ number_format($vat['vat_amount'], 2) }}</div>
                                    <div class="text-gray-500 text-xs">on €{{ number_format($vat['net_amount'], 2) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-700">
                        <div class="flex justify-between font-semibold">
                            <span class="text-gray-300">Total VAT</span>
                            <span class="text-white">€{{ number_format($invoice->vat_amount, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Audit Information --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Audit Information</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Created By:</dt>
                            <dd class="text-gray-200">{{ $invoice->creator->name ?? 'System' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Created At:</dt>
                            <dd class="text-gray-200">{{ $invoice->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                        @if($invoice->updated_by && $invoice->updated_at != $invoice->created_at)
                            <div class="flex justify-between">
                                <dt class="text-gray-400">Updated By:</dt>
                                <dd class="text-gray-200">{{ $invoice->updater->name ?? 'System' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-400">Updated At:</dt>
                                <dd class="text-gray-200">{{ $invoice->updated_at->format('d/m/Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Attachments --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-100">Attachments</h3>
                        <button type="button" 
                                onclick="openUploadModal()" 
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded text-sm">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Upload
                        </button>
                    </div>
                    
                    <div id="attachments-list" class="space-y-2">
                        {{-- Attachments will be loaded via JavaScript --}}
                        <div id="attachments-loading" class="text-center py-4">
                            <div class="text-gray-500 text-sm">Loading attachments...</div>
                        </div>
                        <div id="no-attachments" class="text-center py-4 hidden">
                            <div class="text-gray-500 text-sm">No attachments uploaded</div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Actions</h3>
                    <div class="space-y-2">
                        <a href="{{ route('invoices.edit', $invoice) }}" 
                           class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center">
                            Edit Invoice
                        </a>
                        <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this invoice?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Delete Invoice
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- File Upload Modal --}}
    <div id="upload-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-100">Upload Attachments</h3>
                <button type="button" onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form id="upload-form" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Select Files</label>
                    <input type="file" 
                           name="attachments[]" 
                           id="file-input"
                           multiple 
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.txt,.doc,.docx,.xls,.xlsx"
                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                    <div class="text-xs text-gray-500 mt-1">
                        Max 5 files, 10MB each. PDF, images, and documents allowed.
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Attachment Type</label>
                    <select name="attachment_type" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                        <option value="invoice_scan">Invoice Scan</option>
                        <option value="receipt">Receipt</option>
                        <option value="delivery_note">Delivery Note</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-400 mb-2">Description (Optional)</label>
                    <input type="text" 
                           name="description" 
                           placeholder="Brief description of the files..."
                           class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" 
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        <span id="upload-text">Upload Files</span>
                        <span id="upload-loading" class="hidden">Uploading...</span>
                    </button>
                    <button type="button" 
                            onclick="closeUploadModal()" 
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const invoiceId = {{ $invoice->id }};
        let attachments = [];

        // Load attachments when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadAttachments();
        });

        function openUploadModal() {
            document.getElementById('upload-modal').classList.remove('hidden');
            document.getElementById('upload-modal').classList.add('flex');
        }

        function closeUploadModal() {
            document.getElementById('upload-modal').classList.add('hidden');
            document.getElementById('upload-modal').classList.remove('flex');
            document.getElementById('upload-form').reset();
        }

        function loadAttachments() {
            fetch(`/invoices/${invoiceId}/attachments`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        attachments = data.attachments;
                        renderAttachments();
                    }
                })
                .catch(error => {
                    console.error('Error loading attachments:', error);
                    document.getElementById('attachments-loading').innerHTML = 
                        '<div class="text-red-400 text-sm">Error loading attachments</div>';
                });
        }

        function renderAttachments() {
            const loadingElement = document.getElementById('attachments-loading');
            const noAttachmentsElement = document.getElementById('no-attachments');
            const listElement = document.getElementById('attachments-list');

            console.log('Rendering attachments, count:', attachments.length);
            
            loadingElement.classList.add('hidden');

            // Clear existing attachment elements first (but keep loading and no-attachments divs)
            Array.from(listElement.children).forEach(child => {
                if (child.id !== 'attachments-loading' && child.id !== 'no-attachments') {
                    child.remove();
                }
            });

            if (attachments.length === 0) {
                console.log('No attachments, showing empty state');
                noAttachmentsElement.classList.remove('hidden');
                return;
            }

            console.log('Showing attachments, hiding empty state');
            noAttachmentsElement.classList.add('hidden');

            attachments.forEach(attachment => {
                const attachmentElement = createAttachmentElement(attachment);
                listElement.appendChild(attachmentElement);
            });
        }

        function createAttachmentElement(attachment) {
            const div = document.createElement('div');
            div.className = 'p-3 bg-gray-700 rounded-lg';
            
            const primaryBadge = attachment.is_primary ? 
                '<span class="px-2 py-1 text-xs bg-blue-600 text-blue-100 rounded-full ml-2">Primary</span>' : '';
            
            div.innerHTML = `
                <div class="space-y-3">
                    <!-- File Info Row -->
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            ${getFileIcon(attachment)}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-gray-200 font-medium break-words" title="${attachment.original_filename}">
                                ${attachment.original_filename}${primaryBadge}
                            </div>
                            <div class="text-gray-400 text-xs">
                                ${attachment.attachment_type_label} • ${attachment.formatted_file_size} • ${attachment.uploaded_at}
                            </div>
                            ${attachment.description ? `<div class="text-gray-500 text-xs mt-1 break-words" title="${attachment.description}">${attachment.description}</div>` : ''}
                        </div>
                    </div>
                    
                    <!-- Action Buttons Row -->
                    <div class="flex justify-center space-x-2 pt-2 border-t border-gray-600">
                        ${attachment.is_viewable ? 
                            `<button onclick="showInlineViewer('${attachment.view_url}', '${attachment.original_filename}')" class="inline-flex items-center px-2 py-1 text-xs font-medium text-orange-400 hover:text-orange-300 hover:bg-gray-600 rounded-md transition-colors" title="View inline on this page">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                </svg>
                                Inline
                            </button>
                            <a href="${attachment.viewer_url}" class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-400 hover:text-blue-300 hover:bg-gray-600 rounded-md transition-colors" title="View in dedicated viewer page">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Page
                            </a>
                            <a href="${attachment.view_url}" target="_blank" class="inline-flex items-center px-2 py-1 text-xs font-medium text-purple-400 hover:text-purple-300 hover:bg-gray-600 rounded-md transition-colors" title="Open directly (may download depending on browser settings)">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                Direct
                            </a>` : ''
                        }
                        <a href="${attachment.download_url}" class="inline-flex items-center px-3 py-1 text-xs font-medium text-green-400 hover:text-green-300 hover:bg-gray-600 rounded-md transition-colors" title="Download">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Download
                        </a>
                        <button onclick="deleteAttachment(${attachment.id})" class="inline-flex items-center px-3 py-1 text-xs font-medium text-red-400 hover:text-red-300 hover:bg-gray-600 rounded-md transition-colors" title="Delete">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </div>
                </div>
            `;
            
            return div;
        }

        function getFileIcon(attachment) {
            if (attachment.original_filename.toLowerCase().endsWith('.pdf')) {
                return '<svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" /></svg>';
            } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(attachment.original_filename.split('.').pop().toLowerCase())) {
                return '<svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z" /></svg>';
            } else {
                return '<svg class="w-8 h-8 text-gray-500" fill="currentColor" viewBox="0 0 24 24"><path d="M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z" /></svg>';
            }
        }

        function deleteAttachment(attachmentId) {
            if (!confirm('Are you sure you want to delete this attachment?')) {
                return;
            }

            // Find and disable the delete button during request
            const deleteButton = document.querySelector(`button[onclick="deleteAttachment(${attachmentId})"]`);
            if (deleteButton) {
                deleteButton.disabled = true;
                deleteButton.innerHTML = 'Deleting...';
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                console.error('CSRF token not found');
                alert('Security token not found. Please refresh the page.');
                return;
            }

            fetch(`/invoice-attachments/${attachmentId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            })
            .then(response => {
                console.log('Delete response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Delete response data:', data);
                if (data.success) {
                    // Remove from local attachments array (ensure type matching)
                    const idToDelete = parseInt(attachmentId);
                    console.log('Deleting attachment ID:', idToDelete);
                    console.log('Attachments before filter:', attachments.map(a => a.id));
                    attachments = attachments.filter(att => parseInt(att.id) !== idToDelete);
                    console.log('Attachments after filter:', attachments.map(a => a.id));
                    // Re-render the list
                    renderAttachments();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    // Re-enable button on error
                    if (deleteButton) {
                        deleteButton.disabled = false;
                        deleteButton.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>Delete';
                    }
                }
            })
            .catch(error => {
                console.error('Error deleting attachment:', error);
                alert('Failed to delete attachment: ' + error.message);
                // Re-enable button on error
                if (deleteButton) {
                    deleteButton.disabled = false;
                    deleteButton.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>Delete';
                }
            });
        }

        // Handle file upload form submission
        document.getElementById('upload-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const uploadText = document.getElementById('upload-text');
            const uploadLoading = document.getElementById('upload-loading');

            uploadText.classList.add('hidden');
            uploadLoading.classList.remove('hidden');

            fetch(`/invoices/${invoiceId}/attachments`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                uploadText.classList.remove('hidden');
                uploadLoading.classList.add('hidden');

                if (data.success) {
                    closeUploadModal();
                    loadAttachments(); // Reload the list
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                uploadText.classList.remove('hidden');
                uploadLoading.classList.add('hidden');
                alert('Upload failed');
            });
        });

        // Inline Viewer Functions
        function showInlineViewer(viewUrl, filename) {
            const section = document.getElementById('inline-viewer-section');
            const iframe = document.getElementById('document-iframe');
            const loading = document.getElementById('viewer-loading');
            const error = document.getElementById('viewer-error');
            
            // Show the viewer section
            section.style.display = 'block';
            
            // Show loading state
            loading.style.display = 'flex';
            error.style.display = 'none';
            
            // Update document title
            iframe.title = filename;
            
            // Set iframe source
            iframe.src = viewUrl;
            
            // Handle iframe load
            iframe.onload = function() {
                loading.style.display = 'none';
            };
            
            // Handle iframe error (timeout after 10 seconds)
            setTimeout(function() {
                if (loading.style.display !== 'none') {
                    loading.style.display = 'none';
                    error.style.display = 'flex';
                }
            }, 10000);
            
            // Scroll to viewer
            section.scrollIntoView({ behavior: 'smooth' });
        }

        function toggleViewer() {
            const section = document.getElementById('inline-viewer-section');
            const iframe = document.getElementById('document-iframe');
            
            section.style.display = 'none';
            iframe.src = ''; // Clear src to stop loading
        }

        // Auto-show primary attachment on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for attachments to load, then check for primary attachment
            setTimeout(function() {
                const primaryAttachment = attachments.find(att => att.is_primary && att.is_viewable);
                if (primaryAttachment) {
                    showInlineViewer(primaryAttachment.view_url, primaryAttachment.original_filename);
                }
            }, 1000);
        });
    </script>
</x-admin-layout>