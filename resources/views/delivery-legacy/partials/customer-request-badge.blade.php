{{--
    Flags a delivery line that is on an open customer request so staff put it aside.
    Expects $lines (Collection<CustomerRequestItem> with ->request loaded) or null,
    and optional $arrived (bool, default true) — false for OOS / not-scanned sections
    where the item did not turn up.
--}}
@if(!empty($lines) && count($lines) > 0)
    @php($arrived = $arrived ?? true)
    <div class="mt-1 flex flex-wrap gap-1">
        @foreach($lines as $line)
            <a href="{{ route('customer-requests.show', $line->customer_request_id) }}" target="_blank"
               class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold {{ $arrived ? 'bg-pink-600 text-white hover:bg-pink-700' : 'bg-pink-100 text-pink-800 hover:bg-pink-200' }}"
               title="Customer request #{{ $line->customer_request_id }}{{ $line->request?->wanted_on ? ' - wanted '.$line->request->wanted_on->format('D j M') : '' }}">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 3a1 1 0 011-1h12a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm0 8a1 1 0 011-1h12a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6z"/></svg>
                {{ $arrived ? 'Put aside for' : 'Wanted by' }} {{ $line->request?->customer_name ?? 'customer' }}
                &times; {{ rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.') }}
                @if($line->request?->wanted_on)
                    <span class="font-normal opacity-80">({{ $line->request->wanted_on->format('j M') }})</span>
                @endif
            </a>
        @endforeach
    </div>
@endif
