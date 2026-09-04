<div class="card line-card">
    <div class="card-head">
        <h3>Line items</h3>
        <span class="count">
            <span x-text="items.length"></span> <span x-text="items.length === 1 ? 'item' : 'items'"></span>
        </span>
    </div>

    <template x-if="items.length === 0">
        <div class="empty">
            <div class="empty-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8v8M11 8v8M15 8v8M19 8v8"/></svg>
            </div>
            <div class="empty-title">No items yet</div>
            <div class="empty-sub">Scan a barcode, search by name, or pick a category to add lines.</div>
        </div>
    </template>

    <template x-if="items.length > 0">
        <div class="line-table">
            <div class="line-head">
                <div>Description</div>
                <div class="num">Qty</div>
                <div class="num">Unit net</div>
                <div class="num">VAT %</div>
                <div class="num">Net</div>
                <div class="num">Gross</div>
                <div></div>
            </div>
            <template x-for="(item, idx) in items" :key="`d-${idx}`">
                <div class="line-row" :class="{ 'line-invalid': serverItemErrors.has(idx) || clientItemErrors.has(idx) }">
                    <div class="line-desc">
                        <input type="text" class="line-name-input" :data-item-idx="idx" data-field="description" x-model="item.description">
                        <div class="line-sku" x-show="item.pos_product_code" x-cloak>SKU · <span x-text="item.pos_product_code"></span></div>
                    </div>
                    <div class="num">
                        <div class="qty-stepper">
                            <button type="button" @click="item.quantity = Math.max(0, (parseFloat(item.quantity) || 0) - 1)">−</button>
                            <input type="number" step="0.001" min="0" inputmode="decimal" :data-item-idx="idx" data-field="quantity" x-model.number="item.quantity">
                            <button type="button" @click="item.quantity = (parseFloat(item.quantity) || 0) + 1">+</button>
                        </div>
                    </div>
                    <div class="num">
                        <input type="number" step="0.01" min="0" inputmode="decimal" class="num-input mono" :data-item-idx="idx" data-field="unit_price" x-model.number="item.unit_price">
                    </div>
                    <div class="num">
                        <select class="vat-select" :data-item-idx="idx" data-field="vat_rate" x-model.number="item.vat_rate">
                            <option value="0">0.0%</option>
                            <option value="0.09">9.0%</option>
                            <option value="0.135">13.5%</option>
                            <option value="0.23">23.0%</option>
                        </select>
                    </div>
                    <div class="num mono" x-text="'€' + lineNet(item).toFixed(2)"></div>
                    <div class="num mono strong">
                        {{-- Ad-hoc lines can be priced gross-first; the net is back-calculated. --}}
                        <template x-if="isAdHoc(item)">
                            <input type="number" step="0.01" min="0" inputmode="decimal" class="num-input mono strong"
                                   :value="item._grossFocused ? item._grossDraft : lineGross(item).toFixed(2)"
                                   @focus="item._grossFocused = true; item._grossDraft = lineGross(item).toFixed(2)"
                                   @input="item._grossDraft = $event.target.value; applyLineGross(item, $event.target.value)"
                                   @blur="item._grossFocused = false">
                        </template>
                        <template x-if="! isAdHoc(item)">
                            <span>€<span x-text="lineGross(item).toFixed(2)"></span></span>
                        </template>
                    </div>
                    <button type="button" class="row-x" aria-label="Remove" @click="removeItem(idx)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
    </template>

    <div class="line-foot">
        <button type="button" class="link-btn" @click="addBlankItem()">+ Add ad-hoc line</button>
        <button type="button" class="link-btn subtle" x-show="items.length > 0" x-cloak @click="clearAllItems()">Clear all</button>
    </div>
</div>
