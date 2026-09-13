<x-admin-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $customerRequest->customer_name }}</h2>
                <p class="text-sm text-gray-600">
                    Request #{{ $customerRequest->id }} &middot;
                    taken by {{ $customerRequest->creator?->name ?? 'unknown' }} on {{ $customerRequest->created_at->format('D j M Y H:i') }}
                    @if($customerRequest->closed_at)
                        &middot; closed {{ $customerRequest->closed_at->format('D j M Y H:i') }}@if($customerRequest->closer) by {{ $customerRequest->closer->name }}@endif
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('customer-requests.edit', $customerRequest) }}" class="text-sm text-indigo-600 hover:text-indigo-900 font-medium">Edit</a>
                <a href="{{ route('customer-requests.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Board</a>
            </div>
        </div>

        @if(session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2 text-sm">{{ session('status') }}</div>
        @endif
        @if($errors->has('status'))
            <div class="mb-4 rounded bg-red-700 text-white px-4 py-2 text-sm">{{ $errors->first('status') }}</div>
        @endif

        @include('customer-requests.partials.request-card', [
            'request' => $customerRequest,
            'canManage' => true,
            'variant' => $customerRequest->isOpen() ? 'open' : 'closed',
        ])

        <div class="mt-8 bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900">Status history</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">When</th>
                            <th class="px-4 py-2">Item</th>
                            <th class="px-4 py-2">Change</th>
                            <th class="px-4 py-2">By</th>
                            <th class="px-4 py-2">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $logs = $customerRequest->items->flatMap(fn ($item) => $item->statusLogs->map(fn ($log) => ['item' => $item, 'log' => $log]))
                                ->sortBy(fn ($row) => [$row['log']->created_at->timestamp, $row['log']->id])->values();
                        @endphp
                        @forelse($logs as $row)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $row['log']->created_at->format('D j M H:i') }}</td>
                                <td class="px-4 py-2 text-gray-900">{{ $row['item']->description }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if($row['log']->from_status)
                                        <span class="text-gray-500">{{ \App\Models\CustomerRequestItem::labelFor($row['log']->from_status) }}</span>
                                        <span class="text-gray-400 mx-1">&rarr;</span>
                                    @endif
                                    @include('customer-requests.partials.status-pill', ['status' => $row['log']->to_status])
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $row['log']->user?->name ?? 'unknown' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $row['log']->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No status changes recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
