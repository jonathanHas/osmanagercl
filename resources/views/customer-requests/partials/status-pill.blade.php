@php
    $pillClass = match($status) {
        \App\Models\CustomerRequestItem::STATUS_PENDING => 'bg-gray-200 text-gray-800',
        \App\Models\CustomerRequestItem::STATUS_ORDERED => 'bg-blue-100 text-blue-800',
        \App\Models\CustomerRequestItem::STATUS_PUT_ASIDE => 'bg-amber-100 text-amber-800',
        \App\Models\CustomerRequestItem::STATUS_COLLECTED => 'bg-green-100 text-green-800',
        \App\Models\CustomerRequestItem::STATUS_NOT_AVAILABLE => 'bg-red-100 text-red-800',
        \App\Models\CustomerRequestItem::STATUS_CANCELLED => 'bg-gray-100 text-gray-500 line-through',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase tracking-wide {{ $pillClass }}">
    {{ \App\Models\CustomerRequestItem::labelFor($status) }}
</span>
