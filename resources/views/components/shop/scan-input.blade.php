@props([
    'placeholder' => 'Scan or type a barcode',
    'hint' => 'Ready — scanner listening',
    'camera' => true,
])
<div class="shop-scan" x-data="shopScanInput()" :class="{ 'is-camera': cameraOpen, 'is-error': error !== null }"
     @shop-scan-done.window="done()" @shop-scan-error.window="fail($event.detail)"
     @shop-scan-saved.window="restartCameraIfWanted()">
    <label class="shop-scan__field">
        <x-shop.icon name="scan" size="lg" />
        <span class="shop-sr-only">Barcode</span>
        <input class="shop-scan__input" x-ref="input" x-model="value" :inputmode="inputMode" autocomplete="off" enterkeyhint="go"
               placeholder="{{ $placeholder }}" @keydown.enter.prevent="submit()" @focusout="refocus($event)">
    </label>
    @if ($camera)
        <div class="shop-scan__tools">
            <button class="shop-iconbtn shop-touch-only" type="button" :aria-pressed="cameraOpen" aria-label="Camera" @click="toggleCamera()"><x-shop.icon name="camera" /></button>
            <button class="shop-iconbtn shop-touch-only" type="button" :aria-pressed="keyboard" aria-label="Show keyboard" @click="toggleKeyboard()"><x-shop.icon name="keyboard" /></button>
        </div>
    @endif
    <p class="shop-scan__hint">{{ $hint }}</p>
    <p class="shop-scan__msg"><x-shop.icon name="alert" size="sm" /><span x-text="error"></span></p>
    {{-- html5-qrcode replaces the children of the element it mounts on, so it gets
         its own empty div; the reticle and label must survive. --}}
    <div class="shop-scan__camera"><div :id="cameraId"></div><div class="shop-scan__reticle"></div><span class="shop-scan__camlabel">Point at the barcode</span></div>
</div>
