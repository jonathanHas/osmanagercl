<x-board-layout title="Edit request">
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Edit request for {{ $customerRequest->customer_name }}</h2>
                <p class="text-sm text-gray-600">
                    Taken by {{ $customerRequest->creator?->name ?? 'unknown' }} {{ $customerRequest->created_at->diffForHumans() }}.
                    Line statuses are changed from the board; editing here only changes the details.
                </p>
            </div>
            <a href="{{ route('customer-requests.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Board</a>
        </div>

        @include('customer-requests._form', ['customerRequest' => $customerRequest, 'seedItems' => $seedItems])
    </div>
</x-board-layout>
