<li class="item" data-item-id="{{ $item['id'] }}" onclick="toggleItem({{ $orderId }}, {{ $item['id'] }})">
    <button type="button" class="item__check" tabindex="-1" aria-label="Check"></button>
    <div class="item__body">
        <div class="item__main">
            <span class="item__qty">{{ $item['quantity'] }}×</span>
            <span class="item__name">{{ $item['product_name'] }}</span>
            @if(! empty($item['modifiers']))
                <span class="item__mods">
                    @foreach($item['modifiers'] as $m)
                        <span class="item__mod">{{ $m }}</span>
                    @endforeach
                </span>
            @endif
        </div>
        @if(! empty($item['notes']))
            <div class="item__notes">Note: {{ $item['notes'] }}</div>
        @endif
    </div>
</li>
