<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Zebra Labels</h2>
            <a href="{{ route('zebra-labels.create') }}" class="px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition">Upload Label</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @include('labels._nav', ['current' => 'zebra'])

            {{-- Search --}}
            <form method="GET" class="mb-4">
                <div class="flex gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or barcode..."
                        class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-md text-sm hover:bg-gray-500 transition">Search</button>
                    @if ($search)
                        <a href="{{ route('zebra-labels.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md text-sm hover:bg-gray-300 transition">Clear</a>
                    @endif
                </div>
            </form>

            @if (session('success'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md text-green-700 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($labels->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center text-gray-400">
                    No labels stored yet. <a href="{{ route('zebra-labels.create') }}" class="text-indigo-600 hover:underline">Upload one</a> to get started.
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Barcode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($labels as $label)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $label->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ $label->product_code ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $label->original_filename ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $label->created_at->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('zebra-labels.show', $label) }}"
                                                class="px-2 py-1 bg-gray-100 border border-gray-300 rounded text-xs font-medium text-gray-700 hover:bg-gray-200 transition">View</a>
                                            <form method="POST" action="{{ route('zebra-labels.destroy', $label) }}" onsubmit="return confirm('Delete this label?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="px-2 py-1 bg-red-50 border border-red-200 rounded text-xs font-medium text-red-600 hover:bg-red-100 transition">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $labels->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
