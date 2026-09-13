{{--
    One request on the board. Expects $request (CustomerRequest with items + creator loaded)
    and $canManage (bool). $variant is 'due' | 'open' | 'closed' and only affects the accent.
--}}
@php
    $variant = $variant ?? 'open';
    $accent = match(true) {
        $variant === 'closed' => 'border-gray-300',
        $request->isOverdue() => 'border-red-500',
        $request->isDueToday() => 'border-amber-500',
        default => 'border-indigo-400',
    };
@endphp
<div class="bg-white shadow rounded-lg border-l-4 {{ $accent }} {{ $variant === 'closed' ? 'opacity-75' : '' }}">
    <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-base font-semibold text-gray-900">{{ $request->customer_name }}</h3>
                @if($request->customer_phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $request->customer_phone) }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ $request->customer_phone }}</a>
                @endif
                @if($request->isOverdue())
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase bg-red-600 text-white">
                        Overdue {{ $request->wanted_on->diffInDays(today()) }}d
                    </span>
                @elseif($request->isDueToday())
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase bg-amber-500 text-white">Due today</span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                @if($request->wanted_on)
                    Wanted <span class="font-medium text-gray-700">{{ $request->wanted_on->format('D j M') }}</span>
                @else
                    No date given
                @endif
                &middot; taken by {{ $request->creator?->name ?? 'unknown' }} {{ $request->created_at->diffForHumans() }}
                @if($variant === 'closed' && $request->closed_at)
                    &middot; closed {{ $request->closed_at->diffForHumans() }}
                @endif
            </p>
            @if($request->notes)
                <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $request->notes }}</p>
            @endif
        </div>
        @if($canManage)
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('customer-requests.show', $request) }}" class="text-xs text-gray-500 hover:text-gray-800">Details</a>
                <a href="{{ route('customer-requests.edit', $request) }}" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Edit</a>
                @if($request->isOpen())
                    <form method="POST" action="{{ route('customer-requests.cancel', $request) }}" onsubmit="return confirm('Cancel every open item on this request?');" class="inline">
                        @csrf
                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">Cancel request</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <ul class="divide-y divide-gray-100">
        @foreach($request->items as $item)
            <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-3 {{ $item->isOpen() ? '' : 'bg-gray-50' }}">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-gray-900 {{ $item->status === \App\Models\CustomerRequestItem::STATUS_CANCELLED ? 'line-through text-gray-500' : '' }}">{{ $item->description }}</span>
                        <span class="text-sm text-gray-600">&times; {{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</span>
                        @include('customer-requests.partials.status-pill', ['status' => $item->status])
                        @unless($item->isLinkedToProduct())
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-purple-100 text-purple-700">To source</span>
                        @endunless
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap gap-x-3">
                        @if($item->product_code)
                            <span class="font-mono">{{ $item->product_code }}</span>
                        @endif
                        @if($item->notes)
                            <span>{{ $item->notes }}</span>
                        @endif
                        @if($item->status !== \App\Models\CustomerRequestItem::STATUS_PENDING && $item->status_changed_at)
                            <span>{{ $item->statusLabel() }} by {{ $item->statusChanger?->name ?? 'unknown' }} {{ $item->status_changed_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
                @if($canManage)
                    <div class="flex-shrink-0">
                        @include('customer-requests.partials.status-buttons', ['item' => $item])
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</div>
