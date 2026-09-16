<x-board-layout title="Customer Requests">
    <x-slot name="actions">
        @if($canManage)
            <button type="button" x-data @click="$dispatch('open-new-request')"
               class="inline-flex items-center px-3 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-500 text-sm font-semibold text-white">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New request
            </button>
        @endif
    </x-slot>

    @if(session('status'))
        <div class="mb-4 rounded bg-green-700 text-white px-4 py-2 text-sm">{{ session('status') }}</div>
    @endif
    @if(! $openNew && ($errors->has('status') || $errors->has('items')))
        <div class="mb-4 rounded bg-red-700 text-white px-4 py-2 text-sm">{{ $errors->first('status') ?: $errors->first('items') }}</div>
    @endif

    @unless($canManage)
        <div class="mb-4 rounded-md bg-gray-200 text-gray-700 px-4 py-2 text-sm flex items-center justify-between gap-3">
            <span>This board is read-only. <a href="{{ route('login', ['redirect' => request()->getRequestUri()]) }}" class="underline font-medium">Sign in</a> to add a request or mark items ordered, put aside or collected.</span>
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

    @if($canManage)
        {{-- New request modal. Lives on the board so staff never leave the board's look and feel. --}}
        <div x-data="{ open: @js($openNew) }"
             x-on:open-new-request.window="open = true; $nextTick(() => $el.querySelector('#customer_name')?.focus())"
             x-on:close-new-request.window="open = false"
             x-on:keydown.escape.window="open = false"
             x-init="$watch('open', v => document.body.classList.toggle('overflow-y-hidden', v)); if (open) document.body.classList.add('overflow-y-hidden')"
             x-show="open" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             role="dialog" aria-modal="true" aria-labelledby="new-request-title">
            <div class="fixed inset-0 bg-gray-500 opacity-75" @click="open = false"></div>

            <div class="relative min-h-full flex items-start justify-center p-4 sm:p-6">
                <div class="relative w-full max-w-4xl bg-gray-100 rounded-lg shadow-xl"
                     x-show="open"
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="bg-gray-900 text-white rounded-t-lg px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
                        <div>
                            <h2 id="new-request-title" class="text-lg font-semibold leading-tight">New customer request</h2>
                            <p class="text-xs text-gray-400">Taken by {{ auth()->user()->name }}</p>
                        </div>
                        <button type="button" @click="open = false" class="text-gray-400 hover:text-white p-1" title="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-4 sm:px-6 py-4">
                        @include('customer-requests._form', ['customerRequest' => null, 'seedItems' => $seedItems, 'inModal' => true])
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-board-layout>
