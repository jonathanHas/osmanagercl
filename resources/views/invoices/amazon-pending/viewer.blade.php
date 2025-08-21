<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">View Amazon Invoice</h2>
            <div class="flex space-x-2">
                <a href="{{ $downloadUrl }}" 
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download
                </a>
                <a href="{{ route('amazon-pending.show', $pending) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Payment Entry
                </a>
            </div>
        </div>

        {{-- File Info --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-100">{{ $pending->uploadFile->original_filename }}</h3>
                    <p class="text-sm text-gray-400">
                        Amazon Invoice • {{ $pending->uploadFile->formatted_file_size }} • 
                        Uploaded {{ $pending->uploadFile->created_at->format('d/m/Y H:i') }}
                    </p>
                    @if($pending->invoice_number)
                        <p class="text-sm text-gray-300 mt-1">Invoice #{{ $pending->invoice_number }}</p>
                    @endif
                    @if($pending->invoice_date)
                        <p class="text-sm text-gray-300">Date: {{ $pending->invoice_date->format('d/m/Y') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- PDF Viewer --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <div class="relative" style="height: 80vh;">
                <embed src="{{ $viewUrl }}" 
                       type="application/pdf" 
                       class="w-full h-full"
                       title="{{ $pending->uploadFile->original_filename }}">
                
                {{-- Fallback if embed doesn't work --}}
                <div class="absolute inset-0 flex items-center justify-center bg-gray-900 text-center" id="pdf-fallback" style="display: none;">
                    <div>
                        <svg class="w-16 h-16 mx-auto text-gray-500 mb-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-300 mb-2">PDF Preview Unavailable</h3>
                        <p class="text-gray-400 mb-4">Your browser cannot display this PDF inline.</p>
                        <a href="{{ $viewUrl }}" target="_blank" 
                           class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">
                            Open in New Tab
                        </a>
                        <a href="{{ $downloadUrl }}" 
                           class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Download PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Check if PDF loaded successfully
        setTimeout(function() {
            const embed = document.querySelector('embed');
            if (!embed || embed.offsetHeight === 0) {
                document.getElementById('pdf-fallback').style.display = 'flex';
                embed.style.display = 'none';
            }
        }, 3000);
    </script>
</x-admin-layout>