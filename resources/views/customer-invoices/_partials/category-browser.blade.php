<div class="card category-card">
    <div class="card-head">
        <h3>Browse</h3>
        <span class="count" x-text="categories.length"></span>
    </div>
    <div class="cat-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="text" placeholder="Filter categories" x-model="categoryFilter">
    </div>
    <div class="cat-list">
        <template x-for="[letter, items] in groupedCategories()" :key="`g-${letter}`">
            <div class="cat-group">
                <div class="cat-letter" x-text="letter"></div>
                <template x-for="cat in items" :key="`c-${cat.id}`">
                    <button type="button" class="cat-item"
                            :class="{ active: selectedCategoryId === cat.id }"
                            @click="selectCategory(cat)">
                        <span x-text="cat.name"></span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                </template>
            </div>
        </template>
    </div>

    <template x-if="selectedCategoryId && categoryProducts.length > 0">
        <div class="cat-products">
            <template x-for="p in categoryProducts" :key="`cp-${p.id}`">
                <button type="button" class="cat-product-item" @click="addProduct(p)">
                    <span class="name" x-text="p.name"></span>
                    <span class="price" x-text="'€' + p.gross_price.toFixed(2)"></span>
                </button>
            </template>
        </div>
    </template>
    <template x-if="selectedCategoryId && !loadingProducts && categoryProducts.length === 0">
        <div class="cat-products" style="text-align: center; color: var(--text-mute); font-size: 12px;">
            No till-visible products in this category.
        </div>
    </template>
</div>
