<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Create Recipe') }}
            </h2>
            <a href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900">
                &larr; Back to Recipes
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('kitchen.store') }}">
                        @csrf

                        <div class="space-y-6">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Recipe Name *</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Description -->
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" id="description" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Time Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="prep_time" class="block text-sm font-medium text-gray-700">Prep Time (min)</label>
                                    <input type="number" name="prep_time" id="prep_time" value="{{ old('prep_time') }}" min="0"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('prep_time')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="cook_time" class="block text-sm font-medium text-gray-700">Cook Time (min)</label>
                                    <input type="number" name="cook_time" id="cook_time" value="{{ old('cook_time') }}" min="0"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('cook_time')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="portions_produced" class="block text-sm font-medium text-gray-700">Portions Produced *</label>
                                    <input type="number" name="portions_produced" id="portions_produced" value="{{ old('portions_produced', 1) }}" min="1" required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('portions_produced')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Linked Product -->
                            <div x-data="productSearch()">
                                <label class="block text-sm font-medium text-gray-700">Linked Product (Finished Item)</label>
                                <p class="mt-1 text-sm text-gray-500">Link this recipe to the POS product it creates for margin calculations.</p>
                                <div class="mt-2 relative">
                                    <input type="text" x-model="searchQuery" @input.debounce.300ms="search()"
                                           placeholder="Search for a product..."
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <input type="hidden" name="pos_product_id" x-model="selectedId">

                                    <!-- Search Results -->
                                    <div x-show="showResults && results.length > 0" x-cloak
                                         class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 max-h-60 overflow-auto">
                                        <template x-for="product in results" :key="product.id">
                                            <div @click="selectProduct(product)"
                                                 class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                                <div class="font-medium" x-text="product.name"></div>
                                                <div class="text-sm text-gray-500">
                                                    Code: <span x-text="product.code"></span> |
                                                    Sell: <span x-text="product.sell_price.toFixed(2)"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Selected Product -->
                                    <div x-show="selectedName" class="mt-2 flex items-center justify-between bg-green-50 p-3 rounded-md">
                                        <div>
                                            <span class="font-medium text-green-800" x-text="selectedName"></span>
                                            <span class="text-sm text-green-600 ml-2" x-text="selectedCode ? '(' + selectedCode + ')' : ''"></span>
                                        </div>
                                        <button type="button" @click="clearSelection()" class="text-red-600 hover:text-red-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                @error('pos_product_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Notes -->
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea name="notes" id="notes" rows="2"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-4">
                            <a href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Create Recipe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function productSearch() {
            return {
                searchQuery: '',
                results: [],
                showResults: false,
                selectedId: '',
                selectedName: '',
                selectedCode: '',

                async search() {
                    if (this.searchQuery.length < 2) {
                        this.results = [];
                        this.showResults = false;
                        return;
                    }

                    try {
                        const response = await fetch(`{{ route('kitchen.api.products.search') }}?q=${encodeURIComponent(this.searchQuery)}`);
                        this.results = await response.json();
                        this.showResults = true;
                    } catch (error) {
                        console.error('Search failed:', error);
                    }
                },

                selectProduct(product) {
                    this.selectedId = product.id;
                    this.selectedName = product.name;
                    this.selectedCode = product.code;
                    this.searchQuery = '';
                    this.showResults = false;
                    this.results = [];
                },

                clearSelection() {
                    this.selectedId = '';
                    this.selectedName = '';
                    this.selectedCode = '';
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>
