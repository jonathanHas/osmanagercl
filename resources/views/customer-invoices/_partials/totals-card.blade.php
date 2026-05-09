@php($variant = $variant ?? 'desktop')

@if ($variant === 'desktop')
    <div class="card totals">
        <div class="totals-row">
            <span>Subtotal (net)</span>
            <span class="mono">€<span x-text="totalNet().toFixed(2)"></span></span>
        </div>
        <div class="totals-row">
            <span>VAT</span>
            <span class="mono">€<span x-text="totalVat().toFixed(2)"></span></span>
        </div>
        <div class="totals-row total">
            <span>Total</span>
            <span class="mono">€<span x-text="totalGross().toFixed(2)"></span></span>
        </div>
        <div class="totals-actions">
            <button type="button" class="btn ghost" @click="submitForm('0')">Save draft</button>
            <button type="button" class="btn primary" @click="submitForm('1')">
                Issue &amp; save
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
@else
    <div class="m-actions">
        <button type="button" class="btn ghost" @click="submitForm('0')">Save draft</button>
        <button type="button" class="btn primary" @click="submitForm('1')">Issue &amp; save</button>
    </div>
@endif
