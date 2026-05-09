@php($variant = $variant ?? 'desktop')

@if ($variant === 'desktop')
    <div class="card totals">
        {{-- Pre-discount subtotal shown only if a discount is applied --}}
        <div class="totals-row" x-show="hasDiscount()" x-cloak>
            <span>Subtotal (before discount)</span>
            <span class="mono">€<span x-text="preDiscountNet().toFixed(2)"></span></span>
        </div>

        {{-- Discount row + editable input --}}
        <div class="totals-row" x-show="hasDiscount()" x-cloak>
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                Discount
                <span class="mono" style="color: var(--text-mute); font-size: 11.5px;">(<span x-text="(parseFloat(invoice.discount_percent) || 0).toFixed(1)"></span>%)</span>
                <button type="button" class="link-btn subtle" @click="invoice.discount_percent = 0" style="font-size: 11.5px;">remove</button>
            </span>
            <span class="mono" style="color: var(--danger);">−€<span x-text="discountAmount().toFixed(2)"></span></span>
        </div>

        {{-- Toggle to add a discount when none is set --}}
        <div class="totals-row" x-show="!hasDiscount()" x-cloak>
            <button type="button" class="link-btn"
                    @click="invoice.discount_percent = 10; $nextTick(() => document.getElementById('discount-input-desktop')?.focus())">
                + Add wholesale discount
            </button>
            <span></span>
        </div>

        {{-- Discount editor (only when active) --}}
        <div class="totals-row" x-show="hasDiscount()" x-cloak style="padding-top: 4px;">
            <label for="discount-input-desktop" style="font-size: 11.5px; color: var(--text-mute);">Discount %</label>
            <span style="display: inline-flex; align-items: center; gap: 4px;">
                <input id="discount-input-desktop" type="number" step="0.01" min="0" max="100" inputmode="decimal"
                       x-model.number="invoice.discount_percent"
                       class="num-input mono" style="width: 80px; text-align: right;">
                <span class="mono" style="color: var(--text-mute);">%</span>
            </span>
        </div>

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
        <div class="totals-actions" x-show="!adminEdit">
            <button type="button" class="btn ghost" @click="submitForm('0')">Save draft</button>
            <button type="button" class="btn primary" @click="submitForm('1')">
                Issue &amp; save
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </button>
        </div>
        <div class="totals-actions" x-show="adminEdit" x-cloak style="grid-template-columns: 1fr;">
            <button type="button" class="btn primary" @click="submitForm('0')">
                Save changes
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
@else
    <div class="m-actions" x-show="!adminEdit">
        <button type="button" class="btn ghost" @click="submitForm('0')">Save draft</button>
        <button type="button" class="btn primary" @click="submitForm('1')">Issue &amp; save</button>
    </div>
    <div class="m-actions" x-show="adminEdit" x-cloak style="grid-template-columns: 1fr;">
        <button type="button" class="btn primary" @click="submitForm('0')">Save changes</button>
    </div>
@endif
