@php($variant = $variant ?? 'desktop')

@if ($variant === 'desktop')
    <div class="card scan">
        <label class="scan-label">Scan or search</label>
        <div class="scan-row">
            <div class="scan-input">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8v8M11 8v8M15 8v8M19 8v8"/></svg>
                <input type="text" placeholder="Scan barcode or type product name…"
                       x-model="searchTerm"
                       x-ref="searchBoxDesktop"
                       @input.debounce.250ms="runSearch()"
                       @keydown.enter.prevent="commitTopMatch()"
                       autocomplete="off">
                <kbd>⏎</kbd>
            </div>
            <button type="button" class="btn-camera" :class="{ active: scanner.cameraActive }"
                    @click="toggleCamera('invoice-scanner-desktop')"
                    :aria-label="scanner.cameraActive ? 'Stop camera' : 'Scan with camera'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
            </button>
        </div>
        <div class="scan-hint">Press <kbd>⏎</kbd> to commit top match</div>

        <div x-show="scanner.cameraVisible" x-transition x-cloak>
            <div id="invoice-scanner-desktop" class="camera-viewport"></div>
            <p class="camera-status" x-text="scanner.cameraStatus"></p>
        </div>

        <div x-show="searchResults.length > 0" x-cloak class="search-results">
            <template x-for="(p, idx) in searchResults" :key="`srd-${p.id}`">
                <button type="button"
                        @click="addProduct(p); searchResults = []; searchTerm = ''; $refs.searchBoxDesktop?.focus()">
                    <span class="sr-name">
                        <span x-text="p.name"></span>
                        <span class="sr-code" x-text="p.code"></span>
                    </span>
                    <span class="sr-price" x-text="'€' + p.gross_price.toFixed(2)"></span>
                </button>
            </template>
        </div>
    </div>
@else
    <div class="m-scan">
        <div class="m-scan-input">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8v8M11 8v8M15 8v8M19 8v8"/></svg>
            <input type="text" placeholder="Scan or type product…"
                   x-model="searchTerm"
                   x-ref="searchBoxMobile"
                   @input.debounce.250ms="runSearch()"
                   @keydown.enter.prevent="commitTopMatch(); mobileTab='items'"
                   autocomplete="off">
        </div>
        <button type="button" class="m-cam" :class="{ active: scanner.cameraActive }"
                @click="toggleCamera('invoice-scanner-mobile')"
                :aria-label="scanner.cameraActive ? 'Stop camera' : 'Camera'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
        </button>
    </div>

    <div x-show="scanner.cameraVisible" x-transition x-cloak style="padding: 12px 16px;">
        <div id="invoice-scanner-mobile" class="camera-viewport"></div>
        <p class="camera-status" x-text="scanner.cameraStatus"></p>
    </div>

    <div x-show="searchResults.length > 0" x-cloak class="search-results" style="margin: 0 16px 12px;">
        <template x-for="p in searchResults" :key="`srm-${p.id}`">
            <button type="button"
                    @click="addProduct(p); searchResults = []; searchTerm = ''; mobileTab='items'">
                <span class="sr-name">
                    <span x-text="p.name"></span>
                    <span class="sr-code" x-text="p.code"></span>
                </span>
                <span class="sr-price" x-text="'€' + p.gross_price.toFixed(2)"></span>
            </button>
        </template>
    </div>
@endif
