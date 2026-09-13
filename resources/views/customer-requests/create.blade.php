<x-admin-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">New customer request</h2>
                <p class="text-sm text-gray-600">Taken by {{ auth()->user()->name }}</p>
            </div>
            <a href="{{ route('customer-requests.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Board</a>
        </div>

        @include('customer-requests._form', ['customerRequest' => null, 'seedItems' => $seedItems])
    </div>
</x-admin-layout>
