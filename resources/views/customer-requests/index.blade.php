<x-board-layout title="Customer Requests">
    <x-slot name="actions">
        @if($canManage)
            <a href="{{ route('customer-requests.create') }}"
               class="inline-flex items-center px-3 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-500 text-sm font-semibold text-white">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New request
            </a>
        @endif
    </x-slot>

    @if(session('status'))
        <div class="mb-4 rounded bg-green-700 text-white px-4 py-2 text-sm">{{ session('status') }}</div>
    @endif
    @if($errors->has('status') || $errors->has('items'))
        <div class="mb-4 rounded bg-red-700 text-white px-4 py-2 text-sm">{{ $errors->first('status') ?: $errors->first('items') }}</div>
    @endif

    @unless($canManage)
        <div class="mb-4 rounded-md bg-gray-200 text-gray-700 px-4 py-2 text-sm flex items-center justify-between gap-3">
            <span>This board is read-only. <a href="{{ route('login') }}" class="underline font-medium">Sign in</a> to add a request or mark items ordered, put aside or collected.</span>
        </div>
    @endunless

    {{-- Due today / overdue --}}
    <section class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-red-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Due today / overdue
                <span class="text-sm font-semibold text-red-700 bg-red-100 rounded-full px-2 py-0.5">{{ $due->count() }}</span>
            </h2>
        </div>
        @if($due->isEmpty())
            <p class="text-sm text-gray-500 bg-white rounded-lg shadow px-4 py-3">Nothing due today.</p>
        @else
            <div class="space-y-3">
                @foreach($due as $request)
                    @include('customer-requests.partials.request-card', ['request' => $request, 'variant' => 'due'])
                @endforeach
            </div>
        @endif
    </section>

    {{-- Everything else that is still open --}}
    <section class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                Open requests
                <span class="text-sm font-semibold text-gray-700 bg-gray-200 rounded-full px-2 py-0.5">{{ $open->count() }}</span>
            </h2>
            @if($canManage)
                <a href="{{ $showClosed ? route('customer-requests.index') : route('customer-requests.index', ['closed' => 1]) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                    {{ $showClosed ? 'Hide closed' : 'Show closed (last 30 days)' }}
                </a>
            @endif
        </div>
        @if($open->isEmpty())
            <p class="text-sm text-gray-500 bg-white rounded-lg shadow px-4 py-3">No other open requests.</p>
        @else
            <div class="space-y-3">
                @foreach($open as $request)
                    @include('customer-requests.partials.request-card', ['request' => $request, 'variant' => 'open'])
                @endforeach
            </div>
        @endif
    </section>

    @if($showClosed)
        <section class="mb-8">
            <h2 class="text-lg font-bold text-gray-600 flex items-center gap-2 mb-3">
                Closed in the last 30 days
                <span class="text-sm font-semibold text-gray-600 bg-gray-200 rounded-full px-2 py-0.5">{{ $closed->count() }}</span>
            </h2>
            @if($closed->isEmpty())
                <p class="text-sm text-gray-500 bg-white rounded-lg shadow px-4 py-3">No requests closed recently.</p>
            @else
                <div class="space-y-3">
                    @foreach($closed as $request)
                        @include('customer-requests.partials.request-card', ['request' => $request, 'variant' => 'closed'])
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</x-board-layout>
