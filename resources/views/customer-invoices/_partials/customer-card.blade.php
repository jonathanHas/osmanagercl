<div class="card">
    <div class="card-head">
        <h3>Customer</h3>
        <button type="button" class="link-btn" @click="showCustomerModal = true">+ New customer</button>
    </div>
    <div class="field full">
        <div class="search-field">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="text" placeholder="Search saved customers…"
                   x-model="customerSearchTerm"
                   @input.debounce.250ms="runCustomerSearch()">
            <span class="chip-saved" x-show="customer.id" x-cloak>saved</span>
        </div>
        <div x-show="customerResults.length > 0" x-cloak class="search-results">
            <template x-for="c in customerResults" :key="`cs-${c.id}`">
                <button type="button" @click="pickCustomer(c)">
                    <span class="sr-name">
                        <span x-text="c.name"></span>
                        <span class="sr-code" x-text="[c.email, c.phone, c.city].filter(Boolean).join(' · ')"></span>
                    </span>
                </button>
            </template>
        </div>
    </div>
    <div class="grid-2">
        <div class="field">
            <label>Name <span class="req">*</span></label>
            <input type="text" required x-model="customer.name">
        </div>
        <div class="field">
            <label>Email</label>
            <input type="email" inputmode="email" x-model="customer.email">
        </div>
    </div>
    <div class="field full">
        <label>Address</label>
        <textarea rows="2" x-model="customer.address"></textarea>
    </div>
    <div class="grid-2">
        <div class="field">
            <label>VAT Number</label>
            <input type="text" x-model="customer.vat_number">
        </div>
        <div class="field">
            <label>&nbsp;</label>
        </div>
    </div>
</div>
