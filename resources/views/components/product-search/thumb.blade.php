{{--
    Result-row thumbnail for the x-product-search component, with a hover preview.

    Included from inside an Alpine `x-for`, so it reads `product` (a row of the
    /api/products/search JSON) from the surrounding scope rather than taking a
    prop. The equivalent for server-rendered Product models is the
    x-product-image component in hover mode, which this mirrors; the search rows
    are built in Alpine from JSON, so they cannot use it.

    Note: never write a component tag in a comment here — Blade compiles
    component tags before it strips comments, so it would render for real.

    @param bool $tap  also open the preview on click/tap (list mode). Leave
                      false where the row itself is clickable, e.g. the picker
                      dropdown, so a tap still selects the product.
--}}
@php($tap = $tap ?? false)

<span class="relative block w-10 h-10 flex-shrink-0" x-data="productSearchThumb()">
    <template x-if="product.image_url">
        <img :src="product.image_url" :alt="product.name" loading="lazy"
             x-show="!failed"
             x-on:error="failed = true"
             x-on:mouseenter="preview($el)"
             x-on:mouseleave="hide()"
             @if($tap) x-on:click.prevent.stop="pin($el)" @endif
             class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-gray-700 @if($tap) cursor-zoom-in @endif">
    </template>

    <span x-show="!product.image_url || failed"
          class="absolute inset-0 bg-gray-100 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-700 items-center justify-center flex">
        <svg class="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </span>

    {{-- Hover preview, teleported so table overflow and dropdown clipping cannot cut it off --}}
    <template x-teleport="body">
        <div x-show="open && !pinned" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed z-[99999] pointer-events-none w-64"
             :style="'left: ' + pos.x + 'px; top: ' + pos.y + 'px;'">
            <img :src="product.image_url" :alt="product.name"
                 class="w-64 h-auto max-h-80 object-contain rounded-lg border-2 border-white dark:border-gray-600 shadow-2xl bg-white">
            <div class="bg-black bg-opacity-75 text-white text-xs p-2 rounded-b-lg truncate w-64" x-text="product.name"></div>
        </div>
    </template>

    @if($tap)
        {{-- Tapped on a touch screen: centred overlay with a backdrop, since there is no hover --}}
        <template x-teleport="body">
            <div x-show="pinned" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 x-on:keydown.escape.window="pinned = false"
                 class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/50 p-4"
                 x-on:click.self="pinned = false">
                <div class="relative max-w-sm">
                    <button type="button" x-on:click.stop="pinned = false"
                            class="absolute -top-3 -right-3 z-10 w-8 h-8 bg-white rounded-full shadow-lg flex items-center justify-center text-gray-600 hover:text-gray-900 touch-manipulation">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <img :src="product.image_url" :alt="product.name"
                         class="w-full h-auto max-h-[70vh] object-contain rounded-lg border-2 border-white shadow-2xl bg-white">
                    <div class="bg-black bg-opacity-75 text-white text-sm p-2.5 rounded-b-lg truncate" x-text="product.name"></div>
                </div>
            </div>
        </template>
    @endif
</span>
