{{-- Deliveries this order counted as incoming stock (see OrderService::markDeliveriesCounted) --}}
@if(! empty($order->incoming_deliveries))
    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        <div class="font-semibold">Incoming stock counted in this order</div>
        <ul class="mt-1 space-y-0.5">
            @foreach($order->incoming_deliveries as $incoming)
                <li>
                    <a href="{{ route('deliveries.show', $incoming['id']) }}" class="font-medium underline hover:text-blue-700">{{ $incoming['delivery_number'] }}</a>
                    @if(! empty($incoming['delivery_date']))
                        · dated {{ \Carbon\Carbon::parse($incoming['delivery_date'])->format('j M Y') }}
                    @endif
                    · {{ number_format($incoming['units'] ?? 0, 0) }} units still to arrive
                </li>
            @endforeach
        </ul>
        <div class="mt-1 text-xs text-blue-700">
            Suggestions are reduced by these quantities (shown as "+N incoming" on each line). The deliveries have been unticked from "Include in Orders", so the next order will not count them again.
        </div>
    </div>
@endif
