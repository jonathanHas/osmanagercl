{{--
    One small form per allowed next status for a line. Expects $item.
    Styles follow the target status so "Put aside" is always amber, "Collected" always green, etc.
--}}
@php
    $buttonStyles = [
        \App\Models\CustomerRequestItem::STATUS_ORDERED => 'bg-blue-600 hover:bg-blue-700 text-white',
        \App\Models\CustomerRequestItem::STATUS_PUT_ASIDE => 'bg-amber-500 hover:bg-amber-600 text-white',
        \App\Models\CustomerRequestItem::STATUS_COLLECTED => 'bg-green-600 hover:bg-green-700 text-white',
        \App\Models\CustomerRequestItem::STATUS_NOT_AVAILABLE => 'bg-white border border-red-300 text-red-700 hover:bg-red-50',
        \App\Models\CustomerRequestItem::STATUS_CANCELLED => 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50',
        \App\Models\CustomerRequestItem::STATUS_PENDING => 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50',
    ];
    $buttonLabels = [
        \App\Models\CustomerRequestItem::STATUS_ORDERED => 'Ordered',
        \App\Models\CustomerRequestItem::STATUS_PUT_ASIDE => 'Put aside',
        \App\Models\CustomerRequestItem::STATUS_COLLECTED => 'Collected',
        \App\Models\CustomerRequestItem::STATUS_NOT_AVAILABLE => 'Not available',
        \App\Models\CustomerRequestItem::STATUS_CANCELLED => 'Cancel',
        \App\Models\CustomerRequestItem::STATUS_PENDING => 'Back to pending',
    ];
@endphp
<div class="flex flex-wrap gap-1.5">
    @foreach($item->nextStatuses() as $next)
        <form method="POST" action="{{ route('customer-requests.items.status', $item) }}" class="inline">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $next }}">
            <button type="submit"
                    class="inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-semibold touch-manipulation {{ $buttonStyles[$next] ?? 'bg-gray-200 text-gray-800' }}">
                {{ $buttonLabels[$next] ?? \App\Models\CustomerRequestItem::labelFor($next) }}
            </button>
        </form>
    @endforeach
</div>
