<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">RTD VAT Fallbacks</h2>
                <p class="text-gray-400 text-sm mt-1">Manual VAT rate assignments for article codes without product links</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('rtd-fallbacks.unresolved') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    View Unresolved Items
                </a>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="bg-green-600 text-white px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-600 text-white px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        @if($fallbacks->isEmpty())
            <div class="bg-gray-800 rounded-lg p-8 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3 class="text-xl font-semibold text-gray-300 mb-2">No Fallback Entries</h3>
                <p class="text-gray-400 mb-4">You haven't created any manual VAT rate assignments yet.</p>
                <a href="{{ route('rtd-fallbacks.unresolved') }}"
                   class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Assign Unresolved Items
                </a>
            </div>
        @else
            {{-- Table --}}
            <div class="bg-gray-800 rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Article Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Supplier</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase">VAT Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase">Created</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-300 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($fallbacks as $fallback)
                            <tr class="hover:bg-gray-700/50">
                                <td class="px-4 py-3 font-mono text-gray-200">
                                    {{ $fallback->article_code }}
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-sm">
                                    {{ $fallback->supplier->name ?? 'Unknown' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($fallback->vat_rate == 0) bg-purple-600 text-white
                                        @elseif($fallback->vat_rate == 9) bg-blue-600 text-white
                                        @elseif($fallback->vat_rate == 13.5) bg-yellow-600 text-white
                                        @else bg-green-600 text-white
                                        @endif">
                                        {{ number_format($fallback->vat_rate, 1) }}%
                                    </span>
                                    @if($fallback->is_non_retail)
                                        <span class="ml-1 px-2 py-1 rounded text-xs font-medium bg-yellow-600 text-white">
                                            Non-retail
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-300 text-sm">
                                    {{ $fallback->description ?: '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-sm">
                                    {{ $fallback->created_at->format('d M Y H:i') }}
                                    @if($fallback->creator)
                                        <br><span class="text-xs">by {{ $fallback->creator->name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center space-x-2">
                                        {{-- Edit Button (opens modal) --}}
                                        <button type="button"
                                                onclick="openEditModal({{ json_encode($fallback) }})"
                                                class="text-blue-400 hover:text-blue-300 text-sm">
                                            Edit
                                        </button>

                                        {{-- Delete Button --}}
                                        <form action="{{ route('rtd-fallbacks.destroy', $fallback) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('Delete fallback for {{ $fallback->article_code }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-300 text-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $fallbacks->links() }}
            </div>
        @endif
    </div>

    {{-- Edit Modal --}}
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg max-w-md w-full">
            <form id="editForm" method="POST">
                @csrf
                @method('PUT')
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-100">Edit Fallback Entry</h3>
                        <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Article Code</label>
                            <input type="text" id="edit_article_code" disabled
                                   class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-gray-400">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">VAT Rate</label>
                            <select name="vat_rate" id="edit_vat_rate" required
                                    class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white">
                                <option value="0">0% (Zero rated)</option>
                                <option value="9">9% (Reduced)</option>
                                <option value="13.5">13.5% (Second reduced)</option>
                                <option value="23">23% (Standard)</option>
                            </select>
                        </div>

                        <div>
                            <label class="flex items-center text-gray-300 text-sm">
                                <input type="hidden" name="is_non_retail" value="0">
                                <input type="checkbox" name="is_non_retail" value="1" id="edit_is_non_retail"
                                       class="mr-2 rounded bg-gray-700 border-gray-600">
                                Non-retail (exclude from goods for resale)
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                            <input type="text" name="description" id="edit_description" maxlength="100"
                                   class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Notes</label>
                            <textarea name="notes" id="edit_notes" rows="2" maxlength="500"
                                      class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white"></textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-2">
                        <button type="button" onclick="closeEditModal()"
                                class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function openEditModal(fallback) {
            document.getElementById('editForm').action = '/rtd-fallbacks/' + fallback.id;
            document.getElementById('edit_article_code').value = fallback.article_code;
            document.getElementById('edit_vat_rate').value = fallback.vat_rate;
            document.getElementById('edit_is_non_retail').checked = !!fallback.is_non_retail;
            document.getElementById('edit_description').value = fallback.description || '';
            document.getElementById('edit_notes').value = fallback.notes || '';
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });

        // Close modal on background click
        document.getElementById('editModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
    @endpush
</x-admin-layout>
