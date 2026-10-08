{{--
    The correction card: tap a row, step or type its quantity, saved 400 ms after
    the last tap. Shared by the delivery scan and summary screens (deliveries
    cycle 3); the logic is resources/js/shop/delivery-correction.js, composed into
    both pages. `$show` is the x-show expression (default `editing`); the scan
    page hides the card while its quantity prompt is open. One card per page:
    the input id is fixed.
--}}
{{-- A row is a button, so the stepper cannot live inside one. --}}
<section class="shop-card" x-show="{{ $show ?? 'editing' }}" x-cloak x-ref="correct">
    <div class="shop-between">
        <h2 class="shop-subtitle">Correct quantity</h2>
        <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" @click="editing = null">
            <x-shop.icon name="x" />
        </button>
    </div>

    <div class="shop-inline">
        <x-shop.product-thumb expr="editingRow" />
        <div class="shop-stack shop-stack--tight">
            <span class="shop-row__title" x-text="editingRow?.name"></span>
            <span class="shop-row__meta shop-code" x-text="editingRow?.code || editingRow?.barcode"></span>
            <span class="shop-meta" x-show="editingRow?.stock !== null && editingRow" x-cloak
                  x-text="editingRow ? 'Stock ' + stockText(editingRow.stock) : ''"></span>
        </div>
    </div>

    {{-- The number is local and steps at once; the save follows 400 ms
         after the last tap (deliveries cycle 2). Never disabled: a tap
         must not be swallowed while a save is in flight. --}}
    <div class="shop-stepper" x-show="editTyped === null">
        <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One fewer" @click="adjust(editingRow, -1)"><x-shop.icon name="minus" size="lg" /></button>
        <button class="shop-stepper__value" type="button" aria-label="Type the quantity" x-on:click="typeCorrection()" x-text="stockText(editValue)"></button>
        <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One more" @click="adjust(editingRow, 1)"><x-shop.icon name="plus" size="lg" /></button>
    </div>

    <div class="shop-field" x-show="editTyped !== null" x-cloak>
        <label class="shop-field__label" for="delivery-edit-qty">Quantity or weight</label>
        <input class="shop-input" id="delivery-edit-qty" type="text" inputmode="decimal" autocomplete="off" enterkeyhint="done"
               x-ref="editQty" x-model="editTyped" x-on:keydown.enter.prevent="setCorrection()">
    </div>
    <button class="shop-btn shop-btn--primary shop-btn--block" type="button" x-show="editTyped !== null" x-cloak
            :disabled="busy" x-on:click="setCorrection()">Set</button>

    {{-- Steps back while a correction is typed, so Set is the one prominent button. --}}
    <button class="shop-btn shop-btn--block" type="button" :class="editTyped !== null ? 'shop-btn--ghost' : 'shop-btn--primary'" @click="editing = null">Done</button>
</section>
