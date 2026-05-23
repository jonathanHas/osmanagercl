<li class="item" data-item-id="{{ $item->id }}" onclick="toggleItem({{ $orderId }}, {{ $item->id }})">
    <button type="button" class="item__check" tabindex="-1" aria-label="Check"></button>
    <div class="item__body">
        <div class="item__main">
            <span class="item__qty">{{ $item->formatted_quantity }}×</span>
            <span class="item__name">{{ $item->display_name }}</span>
        </div>
        @if($item->modifiers)
            <div class="item__mods">
                @if(is_array($item->modifiers) && array_is_list($item->modifiers))
                    @foreach($item->modifiers as $m)
                        <span class="item__mod">{{ $m }}</span>
                    @endforeach
                @else
                    @foreach($item->modifiers as $k => $v)
                        <span class="item__mod">{{ $k }}: {{ $v }}</span>
                    @endforeach
                @endif
            </div>
        @endif
        @if($item->notes)
            <div class="item__notes">Note: {{ $item->notes }}</div>
        @endif
    </div>
</li>
