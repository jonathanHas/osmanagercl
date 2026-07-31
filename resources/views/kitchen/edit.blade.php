<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Recipe') }}: {{ $recipe->name }}
            </h2>
            <a href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900">
                &larr; Back to Recipes
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recipe Details Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Recipe Details</h3>
                            <form method="POST" action="{{ route('kitchen.update', $recipe) }}">
                                @csrf
                                @method('PUT')

                                <div class="space-y-4">
                                    <!-- Name -->
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700">Recipe Name *</label>
                                        <input type="text" name="name" id="name" value="{{ old('name', $recipe->name) }}" required
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>

                                    <!-- Description -->
                                    <div>
                                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                        <textarea name="description" id="description" rows="2"
                                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $recipe->description) }}</textarea>
                                    </div>

                                    <!-- Time Fields -->
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <label for="prep_time" class="block text-sm font-medium text-gray-700">Prep (min)</label>
                                            <input type="number" name="prep_time" id="prep_time" value="{{ old('prep_time', $recipe->prep_time) }}" min="0"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div>
                                            <label for="cook_time" class="block text-sm font-medium text-gray-700">Cook (min)</label>
                                            <input type="number" name="cook_time" id="cook_time" value="{{ old('cook_time', $recipe->cook_time) }}" min="0"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div>
                                            <label for="portions_produced" class="block text-sm font-medium text-gray-700">Portions *</label>
                                            <input type="number" name="portions_produced" id="portions_produced" value="{{ old('portions_produced', $recipe->portions_produced) }}" min="1" required
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>

                                    <!-- Linked Product -->
                                    <div x-data="productSearch('{{ $recipe->pos_product_id }}', '{{ $recipe->product?->NAME ?? '' }}', '{{ $recipe->product?->CODE ?? '' }}')">
                                        <label class="block text-sm font-medium text-gray-700">Linked Product</label>
                                        <div class="mt-1 relative">
                                            <input type="text" x-model="searchQuery" @input.debounce.300ms="search()"
                                                   placeholder="Search for a product..."
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <input type="hidden" name="pos_product_id" x-model="selectedId">

                                            <div x-show="showResults && results.length > 0" x-cloak
                                                 class="absolute z-10 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 max-h-60 overflow-auto">
                                                <template x-for="product in results" :key="product.id">
                                                    <div @click.stop="selectProduct(product)"
                                                         class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                                        <div class="font-medium" x-text="product.name"></div>
                                                        <div class="text-sm text-gray-500">
                                                            Code: <span x-text="product.code"></span> |
                                                            Sell: €<span x-text="parseFloat(product.sell_price || 0).toFixed(2)"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>

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
                                    </div>

                                    <!-- Active Status -->
                                    <div class="flex items-center">
                                        <input type="checkbox" name="is_active" id="is_active" value="1"
                                               {{ old('is_active', $recipe->is_active) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <label for="is_active" class="ml-2 text-sm text-gray-700">Active Recipe</label>
                                    </div>

                                    <!-- Notes -->
                                    <div>
                                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                        <textarea name="notes" id="notes" rows="2"
                                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $recipe->notes) }}</textarea>
                                    </div>

                                    <!-- Rate Overrides (Collapsible) -->
                                    <div x-data="{ showOverrides: {{ $recipe->hasRateOverrides() ? 'true' : 'false' }} }" class="pt-4 border-t">
                                        <button type="button" @click="showOverrides = !showOverrides"
                                                class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="showOverrides ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                            <span x-show="!showOverrides">Override cost rates</span>
                                            <span x-show="showOverrides">Hide rate overrides</span>
                                        </button>

                                        <div x-show="showOverrides" x-transition class="mt-4 p-4 bg-gray-50 rounded-lg">
                                            <p class="text-xs text-gray-500 mb-4">
                                                Leave blank to use defaults: Labour €{{ config('kitchen.labour_rate', 15) }}/hr, Electricity €{{ config('kitchen.electricity_rate', 0.25) }}/kWh, Power {{ config('kitchen.avg_cooking_power', 2.0) }}kW.
                                                Labour charges all prep time plus {{ round(config('kitchen.cook_supervision_factor', 0.10) * 100) }}% of cook time (cooking is largely unattended); electricity charges the full cook time.
                                            </p>

                                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                                <div>
                                                    <label for="labour_rate_override" class="block text-sm font-medium text-gray-700">Labour Rate</label>
                                                    <div class="mt-1 relative">
                                                        <span class="absolute left-3 top-2 text-gray-500">€</span>
                                                        <input type="number" name="labour_rate_override" id="labour_rate_override"
                                                               step="0.01" min="0"
                                                               value="{{ old('labour_rate_override', $recipe->labour_rate_override) }}"
                                                               placeholder="{{ config('kitchen.labour_rate', 15) }}"
                                                               class="w-full pl-8 pr-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <span class="absolute right-3 top-2 text-gray-500 text-sm">/hr</span>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label for="electricity_rate_override" class="block text-sm font-medium text-gray-700">Electricity</label>
                                                    <div class="mt-1 relative">
                                                        <span class="absolute left-3 top-2 text-gray-500">€</span>
                                                        <input type="number" name="electricity_rate_override" id="electricity_rate_override"
                                                               step="0.01" min="0"
                                                               value="{{ old('electricity_rate_override', $recipe->electricity_rate_override) }}"
                                                               placeholder="{{ config('kitchen.electricity_rate', 0.25) }}"
                                                               class="w-full pl-8 pr-12 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <span class="absolute right-3 top-2 text-gray-500 text-sm">/kWh</span>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label for="cooking_power_override" class="block text-sm font-medium text-gray-700">Cook Power</label>
                                                    <div class="mt-1 relative">
                                                        <input type="number" name="cooking_power_override" id="cooking_power_override"
                                                               step="0.1" min="0"
                                                               value="{{ old('cooking_power_override', $recipe->cooking_power_override) }}"
                                                               placeholder="{{ config('kitchen.avg_cooking_power', 2.0) }}"
                                                               class="w-full pr-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <span class="absolute right-3 top-2 text-gray-500 text-sm">kW</span>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label for="packaging_cost_per_portion" class="block text-sm font-medium text-gray-700">Packaging</label>
                                                    <div class="mt-1 relative">
                                                        <span class="absolute left-3 top-2 text-gray-500">€</span>
                                                        <input type="number" name="packaging_cost_per_portion" id="packaging_cost_per_portion"
                                                               step="0.01" min="0"
                                                               value="{{ old('packaging_cost_per_portion', $recipe->packaging_cost_per_portion) }}"
                                                               placeholder="0.00"
                                                               class="w-full pl-8 pr-16 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <span class="absolute right-3 top-2 text-gray-500 text-sm">/portion</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 flex justify-end">
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                        Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Ingredients Section -->
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Ingredients</h3>

                            @php
                                $hasLegacyIngredients = $recipe->ingredients->contains(fn($i) => !$i->hasProfile());
                            @endphp

                            @if($hasLegacyIngredients)
                                <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <div class="text-sm">
                                            <p class="font-medium text-yellow-800">Some ingredients use estimated costing</p>
                                            <p class="text-yellow-700">Create ingredient profiles for accurate unit-based costs.
                                                <a href="{{ route('kitchen.profiles.index') }}" class="underline hover:text-yellow-900">Manage Profiles</a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($recipe->ingredients->isEmpty())
                                <p class="text-gray-500 mb-4">No ingredients added yet.</p>
                            @else
                                <table class="min-w-full divide-y divide-gray-200 mb-6">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ingredient</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Line Cost</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach($recipe->ingredients as $ingredient)
                                            <tr class="{{ $ingredient->hasProfile() ? '' : 'bg-yellow-50' }}">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center">
                                                        @if($ingredient->hasProfile())
                                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20" title="Has profile - accurate costing">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                            </svg>
                                                        @else
                                                            <svg class="w-4 h-4 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20" title="No profile - estimated cost">
                                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                            </svg>
                                                        @endif
                                                        <div>
                                                            <div class="font-medium text-gray-900">{{ $ingredient->product_name }}</div>
                                                            @if($ingredient->hasProfile())
                                                                <div class="text-xs text-green-600">{{ $ingredient->profile->formatted_purchase_size }}</div>
                                                            @endif
                                                            @if($ingredient->waste_factor > 0)
                                                                <div class="text-xs text-gray-500">+{{ $ingredient->waste_factor }}% waste</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ $ingredient->formatted_quantity }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    @if($ingredient->hasProfile())
                                                        <span class="text-green-600">{{ number_format($ingredient->profile->cost_per_base_unit, 4) }}/{{ $ingredient->profile->base_unit }}</span>
                                                    @else
                                                        <span class="text-yellow-600">{{ number_format($ingredient->getUnitCost(), 2) }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                                    @php
                                                        $lineCost = $ingredient->getLineCost();
                                                        $needsDensity = false;
                                                        if ($ingredient->hasProfile() && $ingredient->profile && $lineCost == 0) {
                                                            $profileCat = \App\Models\KitchenIngredientProfile::getUnitCategory($ingredient->profile->purchase_unit);
                                                            $recipeCat = \App\Models\KitchenIngredientProfile::getUnitCategory($ingredient->unit_type);
                                                            $needsDensity = $profileCat !== $recipeCat && !$ingredient->profile->hasDensity();
                                                        }
                                                    @endphp
                                                    {{ number_format($lineCost, 2) }}
                                                    @if($needsDensity)
                                                        <div class="text-xs text-red-500 mt-1">
                                                            <a href="{{ route('kitchen.profiles.edit', $ingredient->profile) }}" class="hover:underline">
                                                                ⚠ Add density
                                                            </a>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm">
                                                    <form action="{{ route('kitchen.ingredients.remove', $ingredient) }}" method="POST" class="inline" onsubmit="return confirm('Remove this ingredient?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900">Remove</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="3" class="px-4 py-3 text-sm font-medium text-gray-900 text-right">Total:</td>
                                            <td class="px-4 py-3 text-sm font-bold text-gray-900">{{ number_format($costs['total_cost'], 2) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            @endif

                            <!-- Add Ingredient Form -->
                            <div class="border-t pt-4" x-data="ingredientSearch()">
                                <h4 class="font-medium text-gray-900 mb-3">Add Ingredient</h4>

                                <!-- Search Mode Toggle -->
                                <div class="mb-4 flex items-center space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" x-model="searchMode" value="profile" class="form-radio text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-700">Search Profiles (recommended)</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" x-model="searchMode" value="product" class="form-radio text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-700">Search All Products</span>
                                    </label>
                                </div>

                                <form method="POST" action="{{ route('kitchen.ingredients.add', $recipe) }}" class="space-y-4">
                                    @csrf

                                    <!-- Ingredient Search -->
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700">
                                            <span x-show="searchMode === 'profile'">Search Ingredient Profiles</span>
                                            <span x-show="searchMode === 'product'">Search Products (no profile)</span>
                                        </label>
                                        <input type="text" x-model="searchQuery" @input.debounce.300ms="search()"
                                               :placeholder="searchMode === 'profile' ? 'Search profiles...' : 'Search products...'"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <input type="hidden" name="pos_product_id" x-model="selectedProductId">
                                        <input type="hidden" name="ingredient_profile_id" x-model="selectedProfileId">

                                        <div x-show="showResults && results.length > 0" x-cloak
                                             class="absolute z-50 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 max-h-80 overflow-y-auto">
                                            <!-- Profile Results -->
                                            <template x-if="searchMode === 'profile'">
                                                <div>
                                                    <template x-for="profile in results" :key="profile.id">
                                                        <div @click.stop="selectProfile(profile)"
                                                             class="px-4 py-2 hover:bg-green-50 cursor-pointer border-l-4 border-green-500">
                                                            <div class="flex items-center">
                                                                <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                                </svg>
                                                                <span class="font-medium" x-text="profile.name"></span>
                                                            </div>
                                                            <div class="text-sm text-gray-500 ml-6">
                                                                <span x-text="profile.purchase_size"></span> ·
                                                                €<span x-text="parseFloat(profile.cost_per_base_unit || 0).toFixed(4)"></span>/<span x-text="profile.base_unit"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                            <!-- Product Results -->
                                            <template x-if="searchMode === 'product'">
                                                <div>
                                                    <template x-for="product in results" :key="product.id">
                                                        <div @click.stop="selectProduct(product)"
                                                             class="px-4 py-2 hover:bg-yellow-50 cursor-pointer border-l-4 border-yellow-400">
                                                            <div class="flex items-center">
                                                                <svg class="w-4 h-4 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                                </svg>
                                                                <span class="font-medium" x-text="product.name"></span>
                                                            </div>
                                                            <div class="text-sm text-gray-500 ml-6">
                                                                <span x-text="product.code"></span> · Cost: €<span x-text="parseFloat(product.cost || 0).toFixed(2)"></span>
                                                                <span class="text-yellow-600">(no profile)</span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Selected Item Display -->
                                        <div x-show="selectedName" class="mt-2 p-3 rounded-md"
                                             :class="selectedProfileId ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center">
                                                    <template x-if="selectedProfileId">
                                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </template>
                                                    <template x-if="!selectedProfileId && selectedProductId">
                                                        <svg class="w-5 h-5 text-yellow-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </template>
                                                    <div>
                                                        <span class="font-medium" :class="selectedProfileId ? 'text-green-800' : 'text-yellow-800'" x-text="selectedName"></span>
                                                        <p x-show="selectedInfo" class="text-sm" :class="selectedProfileId ? 'text-green-600' : 'text-yellow-600'" x-text="selectedInfo"></p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <!-- Create Profile button (only show when product selected without profile) -->
                                                    <template x-if="!selectedProfileId && selectedProductId">
                                                        <button type="button" @click="openProfileModal()"
                                                                class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                                            + Create Profile
                                                        </button>
                                                    </template>
                                                    <button type="button" @click="clearSelection()" class="text-red-600 hover:text-red-800 text-sm">Clear</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity *</label>
                                            <input type="number" name="quantity" id="quantity" step="0.0001" min="0.0001" required
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                        <div>
                                            <label for="unit_type" class="block text-sm font-medium text-gray-700">Unit *</label>
                                            <select name="unit_type" id="unit_type" required
                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach($unitTypes as $key => $label)
                                                    <option value="{{ $key }}" {{ $key === 'g' ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="waste_factor" class="block text-sm font-medium text-gray-700">Waste %</label>
                                            <input type="number" name="waste_factor" id="waste_factor" step="0.01" min="0" max="100" value="0"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" x-bind:disabled="!selectedProductId && !selectedProfileId"
                                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                            Add Ingredient
                                        </button>
                                    </div>
                                </form>

                                <!-- Quick Create Profile Modal (inline) -->
                                <div x-show="showProfileModal" x-cloak
                                     class="fixed inset-0 z-50 overflow-y-auto"
                                     style="display: none;">
                                    <div class="flex items-center justify-center min-h-screen px-4">
                                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeProfileModal()"></div>

                                        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" @click.stop>
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-medium text-gray-900">Quick Create Profile</h3>
                                                <button type="button" @click="closeProfileModal()" class="text-gray-400 hover:text-gray-600">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>

                                            <p class="text-sm text-gray-500 mb-4">
                                                Define how <strong x-text="selectedName"></strong> is purchased to enable accurate cost calculations.
                                            </p>

                                            <!-- Error Message -->
                                            <div x-show="profileError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                                                <p class="text-sm text-red-600" x-text="profileError"></p>
                                            </div>

                                            <!-- Profile Form -->
                                            <div class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Profile Name</label>
                                                    <input type="text" x-model="profileForm.name"
                                                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Size</label>
                                                    <p class="text-xs text-gray-500 mb-2">How is this product purchased from supplier?</p>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <input type="number" x-model="profileForm.purchase_quantity"
                                                               step="0.0001" min="0.0001"
                                                               placeholder="Quantity"
                                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <select x-model="profileForm.purchase_unit"
                                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            <optgroup label="Weight">
                                                                <option value="kg">Kilograms (kg)</option>
                                                                <option value="g">Grams (g)</option>
                                                            </optgroup>
                                                            <optgroup label="Volume">
                                                                <option value="L">Litres (L)</option>
                                                                <option value="ml">Millilitres (ml)</option>
                                                                <option value="tbsp">Tablespoons (tbsp)</option>
                                                                <option value="tsp">Teaspoons (tsp)</option>
                                                            </optgroup>
                                                            <optgroup label="Count">
                                                                <option value="unit">Units</option>
                                                                <option value="dozen">Dozen (12)</option>
                                                                <option value="pack">Pack</option>
                                                            </optgroup>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Cost Preview -->
                                                <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-md">
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-sm text-indigo-700">Supplier Cost:</span>
                                                        <span class="font-medium text-indigo-900">€<span x-text="selectedProductCost.toFixed(2)"></span></span>
                                                    </div>
                                                    <div class="flex justify-between items-center mt-1">
                                                        <span class="text-sm text-indigo-700">Cost per base unit:</span>
                                                        <span class="font-bold text-indigo-900" x-text="getProfileCostPreview()"></span>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                                                    <input type="text" x-model="profileForm.notes"
                                                           placeholder="e.g., Purchased from Musgrave"
                                                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                            </div>

                                            <!-- Actions -->
                                            <div class="mt-6 flex justify-end space-x-3">
                                                <button type="button" @click="closeProfileModal()"
                                                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                                    Cancel
                                                </button>
                                                <button type="button" @click="saveProfile()"
                                                        :disabled="profileSaving"
                                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                                    <span x-show="!profileSaving">Create Profile</span>
                                                    <span x-show="profileSaving">Creating...</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cost Summary Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg sticky top-6">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Cost Summary</h3>

                            <div class="space-y-4">
                                <!-- Cost Breakdown -->
                                <div class="flex justify-between items-center py-2 border-b">
                                    <span class="text-gray-600">Ingredients</span>
                                    <span class="font-medium">€{{ number_format($costs['ingredient_cost'], 2) }}</span>
                                </div>

                                @if($costs['labour_cost'] > 0)
                                <div class="py-2 border-b">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Labour ({{ $costs['labour_minutes'] }} min)</span>
                                        <span class="font-medium">€{{ number_format($costs['labour_cost'], 2) }}</span>
                                    </div>
                                    @if($recipe->cook_time > 0)
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ $recipe->prep_time ?? 0 }} min prep + {{ round($recipe->getCookSupervisionFactor() * 100) }}% of {{ $recipe->cook_time }} min cook
                                    </p>
                                    @endif
                                </div>
                                @endif

                                @if($costs['electricity_cost'] > 0)
                                <div class="flex justify-between items-center py-2 border-b">
                                    <span class="text-gray-600">Electricity ({{ $recipe->cook_time }} min)</span>
                                    <span class="font-medium">€{{ number_format($costs['electricity_cost'], 2) }}</span>
                                </div>
                                @endif

                                @if($costs['packaging_cost'] > 0)
                                <div class="flex justify-between items-center py-2 border-b">
                                    <span class="text-gray-600">Packaging</span>
                                    <span class="font-medium">€{{ number_format($costs['packaging_cost'], 2) }}</span>
                                </div>
                                @endif

                                <div class="flex justify-between items-center py-2 border-b bg-gray-50 -mx-6 px-6">
                                    <span class="text-gray-900 font-medium">Total Cost</span>
                                    <span class="font-bold">€{{ number_format($costs['total_cost'], 2) }}</span>
                                </div>

                                <div class="flex justify-between items-center py-2 border-b">
                                    <span class="text-gray-600">Portions</span>
                                    <span class="font-medium">{{ $costs['portions_produced'] }}</span>
                                </div>

                                <div class="flex justify-between items-center py-2 border-b">
                                    <span class="text-gray-600">Cost / Portion</span>
                                    <span class="font-bold text-lg">€{{ number_format($costs['cost_per_portion'], 2) }}</span>
                                </div>

                                @if($costs['has_linked_product'])
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-gray-600">Sell Price</span>
                                        <span class="font-medium">{{ number_format($costs['sell_price'], 2) }}</span>
                                    </div>

                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-gray-600">Profit / Portion</span>
                                        <span class="font-medium {{ $costs['profit_per_portion'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ number_format($costs['profit_per_portion'], 2) }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between items-center py-2">
                                        <span class="text-gray-600">Margin</span>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                            @if($costs['margin_status'] === 'excellent') bg-green-100 text-green-800
                                            @elseif($costs['margin_status'] === 'good') bg-yellow-100 text-yellow-800
                                            @elseif($costs['margin_status'] === 'low') bg-orange-100 text-orange-800
                                            @else bg-red-100 text-red-800
                                            @endif">
                                            {{ number_format($costs['margin_percentage'], 1) }}%
                                        </span>
                                    </div>
                                @else
                                    <div class="bg-yellow-50 p-3 rounded-md">
                                        <p class="text-sm text-yellow-800">
                                            Link a product to see margin calculations.
                                        </p>
                                    </div>
                                @endif
                            </div>

                            <!-- Batch Scaling Calculator -->
                            <div class="mt-6 pt-4 border-t" x-data="scalingCalculator(@js($costs))">
                                <button type="button" @click="showScaling = !showScaling"
                                        class="w-full text-left text-sm text-indigo-600 hover:text-indigo-800 flex items-center justify-between">
                                    <span class="font-medium">Batch Scaling Calculator</span>
                                    <svg class="w-4 h-4 transition-transform" :class="showScaling ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <div x-show="showScaling" x-transition x-cloak class="mt-4 space-y-4">
                                    <!-- Scaling Inputs -->
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Recipe Multiplier</label>
                                            <select x-model="recipeMultiplier" @change="updateSuggestedFactors()"
                                                    class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="2">2x (Double batch)</option>
                                                <option value="3">3x (Triple batch)</option>
                                                <option value="5">5x batch</option>
                                                <option value="10">10x batch</option>
                                                <option value="custom">Custom...</option>
                                            </select>
                                            <input x-show="recipeMultiplier === 'custom'" x-model.number="customMultiplier" type="number"
                                                   min="1" step="0.5" placeholder="Enter multiplier"
                                                   class="mt-2 w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Labour Factor</label>
                                                <div class="relative">
                                                    <input type="number" x-model.number="labourFactor" min="0.1" step="0.1"
                                                           class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-6">
                                                    <span class="absolute right-2 top-2 text-xs text-gray-400">x</span>
                                                </div>
                                                <p class="text-xs text-gray-400 mt-1">Prep <span x-text="baseCosts.prep_time"></span> → <span x-text="scaledPrepTime"></span> min</p>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Electricity Factor</label>
                                                <div class="relative">
                                                    <input type="number" x-model.number="electricityFactor" min="0.1" step="0.1"
                                                           class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-6">
                                                    <span class="absolute right-2 top-2 text-xs text-gray-400">x</span>
                                                </div>
                                                <p class="text-xs text-gray-400 mt-1">Cook <span x-text="baseCosts.cook_time"></span> → <span x-text="scaledCookTime"></span> min</p>
                                            </div>
                                        </div>

                                        <p class="text-xs text-gray-400">
                                            The factors scale the recipe's prep and cook times, which is what the saved recipe stores — so the figures below are exactly what you'll get.
                                        </p>
                                    </div>

                                    <!-- Comparison Table -->
                                    <div class="bg-gray-50 rounded-lg p-3 -mx-2">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="text-xs text-gray-500">
                                                    <th class="text-left pb-2"></th>
                                                    <th class="text-right pb-2">Original</th>
                                                    <th class="text-right pb-2">Scaled</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr>
                                                    <td class="py-1.5 text-gray-600">Portions</td>
                                                    <td class="py-1.5 text-right" x-text="baseCosts.portions_produced"></td>
                                                    <td class="py-1.5 text-right font-medium" x-text="scaledPortions"></td>
                                                </tr>
                                                <tr x-show="baseCosts.prep_time > 0">
                                                    <td class="py-1.5 text-gray-600">Prep time</td>
                                                    <td class="py-1.5 text-right"><span x-text="baseCosts.prep_time"></span> min</td>
                                                    <td class="py-1.5 text-right font-medium"><span x-text="scaledPrepTime"></span> min</td>
                                                </tr>
                                                <tr x-show="baseCosts.cook_time > 0">
                                                    <td class="py-1.5 text-gray-600">Cook time</td>
                                                    <td class="py-1.5 text-right"><span x-text="baseCosts.cook_time"></span> min</td>
                                                    <td class="py-1.5 text-right font-medium"><span x-text="scaledCookTime"></span> min</td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1.5 text-gray-600">Ingredients</td>
                                                    <td class="py-1.5 text-right">€<span x-text="baseCosts.ingredient_cost.toFixed(2)"></span></td>
                                                    <td class="py-1.5 text-right">€<span x-text="scaledIngredientCost.toFixed(2)"></span></td>
                                                </tr>
                                                <tr x-show="baseCosts.labour_cost > 0">
                                                    <td class="py-1.5 text-gray-600">Labour</td>
                                                    <td class="py-1.5 text-right">€<span x-text="baseCosts.labour_cost.toFixed(2)"></span></td>
                                                    <td class="py-1.5 text-right">€<span x-text="scaledLabourCost.toFixed(2)"></span></td>
                                                </tr>
                                                <tr x-show="baseCosts.electricity_cost > 0">
                                                    <td class="py-1.5 text-gray-600">Electricity</td>
                                                    <td class="py-1.5 text-right">€<span x-text="baseCosts.electricity_cost.toFixed(2)"></span></td>
                                                    <td class="py-1.5 text-right">€<span x-text="scaledElectricityCost.toFixed(2)"></span></td>
                                                </tr>
                                                <tr x-show="baseCosts.packaging_cost > 0">
                                                    <td class="py-1.5 text-gray-600">Packaging</td>
                                                    <td class="py-1.5 text-right">€<span x-text="baseCosts.packaging_cost.toFixed(2)"></span></td>
                                                    <td class="py-1.5 text-right">€<span x-text="scaledPackagingCost.toFixed(2)"></span></td>
                                                </tr>
                                                <tr class="font-medium">
                                                    <td class="py-1.5 text-gray-900">Total</td>
                                                    <td class="py-1.5 text-right">€<span x-text="baseCosts.total_cost.toFixed(2)"></span></td>
                                                    <td class="py-1.5 text-right">€<span x-text="scaledTotalCost.toFixed(2)"></span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Per Portion Comparison -->
                                    <div class="bg-indigo-50 rounded-lg p-3 -mx-2">
                                        <div class="text-xs text-indigo-600 font-medium mb-2">Cost per Portion</div>
                                        <div class="flex items-center justify-between">
                                            <div class="text-center">
                                                <div class="text-lg font-bold text-gray-700">€<span x-text="baseCosts.cost_per_portion.toFixed(2)"></span></div>
                                                <div class="text-xs text-gray-500">Original</div>
                                            </div>
                                            <div class="text-2xl text-gray-400">→</div>
                                            <div class="text-center">
                                                <div class="text-lg font-bold text-indigo-700">€<span x-text="scaledCostPerPortion.toFixed(2)"></span></div>
                                                <div class="text-xs text-gray-500">Scaled</div>
                                            </div>
                                        </div>
                                        <div class="mt-2 text-center" x-show="savingsPercent > 0">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Save <span x-text="savingsPercent.toFixed(1)"></span>% per portion
                                            </span>
                                        </div>
                                        <div class="mt-2 text-center" x-show="savingsPercent <= 0">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                No savings at current factors
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Save as New Recipe Button -->
                                    <button type="button" @click="openSaveModal()"
                                            class="w-full px-3 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                        </svg>
                                        Save as New Recipe
                                    </button>
                                </div>

                                <!-- Save as New Recipe Modal -->
                                <div x-show="showSaveModal" x-cloak
                                     class="fixed inset-0 z-50 overflow-y-auto"
                                     style="display: none;">
                                    <div class="flex items-center justify-center min-h-screen px-4">
                                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeSaveModal()"></div>

                                        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6" @click.stop>
                                            <div class="flex justify-between items-center mb-4">
                                                <h3 class="text-lg font-medium text-gray-900">Save Scaled Recipe</h3>
                                                <button type="button" @click="closeSaveModal()" class="text-gray-400 hover:text-gray-600">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>

                                            <p class="text-sm text-gray-500 mb-4">
                                                Create a new recipe with scaled quantities and adjusted overhead costs.
                                            </p>

                                            <!-- Error Message -->
                                            <div x-show="saveError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                                                <p class="text-sm text-red-600" x-text="saveError"></p>
                                            </div>

                                            <!-- Summary -->
                                            <div class="mb-4 p-3 bg-gray-50 rounded-lg text-sm space-y-1">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Portions:</span>
                                                    <span class="font-medium" x-text="baseCosts.portions_produced + ' → ' + scaledPortions"></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Ingredients scaled:</span>
                                                    <span class="font-medium" x-text="getMultiplierValue() + 'x'"></span>
                                                </div>
                                                <div class="flex justify-between" x-show="baseCosts.packaging_cost_per_portion > 0">
                                                    <span class="text-gray-600">Packaging:</span>
                                                    <span class="font-medium">€<span x-text="baseCosts.packaging_cost_per_portion.toFixed(2)"></span>/portion</span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Cost per portion:</span>
                                                    <span class="font-medium text-green-600">€<span x-text="scaledCostPerPortion.toFixed(2)"></span></span>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">New Recipe Name</label>
                                                <input type="text" x-model="newRecipeName"
                                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>

                                            <div class="flex justify-end space-x-3">
                                                <button type="button" @click="closeSaveModal()"
                                                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                                    Cancel
                                                </button>
                                                <button type="button" @click="saveAsNewRecipe()"
                                                        :disabled="saving || !newRecipeName.trim()"
                                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                                    <span x-show="!saving">Save Recipe</span>
                                                    <span x-show="saving">Saving...</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t">
                                <a href="{{ route('kitchen.show', $recipe) }}" class="block text-center px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                    View Full Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function scalingCalculator(baseCosts) {
            return {
                baseCosts: baseCosts,
                showScaling: false,
                recipeMultiplier: '2',
                customMultiplier: 2,
                labourFactor: 1.5,
                electricityFactor: 1.0,

                // Save modal state
                showSaveModal: false,
                newRecipeName: '',
                saving: false,
                saveError: '',

                getMultiplierValue() {
                    return this.recipeMultiplier === 'custom' ? this.customMultiplier : parseFloat(this.recipeMultiplier);
                },

                updateSuggestedFactors() {
                    const mult = this.getMultiplierValue();
                    // Smart defaults based on multiplier
                    if (mult <= 2) {
                        this.labourFactor = 1.5;
                        this.electricityFactor = 1.0;
                    } else if (mult <= 5) {
                        this.labourFactor = 2.0;
                        this.electricityFactor = 1.5;
                    } else {
                        this.labourFactor = 3.0;
                        this.electricityFactor = 2.0;
                    }
                },

                get scaledIngredientCost() {
                    return this.baseCosts.ingredient_cost * this.getMultiplierValue();
                },

                // The factors are applied to the times, matching exactly what
                // saveScaledRecipe() persists - so this preview is what you get.
                get scaledPrepTime() {
                    return Math.round(this.baseCosts.prep_time * this.labourFactor);
                },

                get scaledCookTime() {
                    return Math.round(this.baseCosts.cook_time * this.electricityFactor);
                },

                get scaledLabourMinutes() {
                    return this.scaledPrepTime + this.scaledCookTime * this.baseCosts.cook_supervision_factor;
                },

                get scaledLabourCost() {
                    return (this.scaledLabourMinutes / 60) * this.baseCosts.labour_rate;
                },

                get scaledElectricityCost() {
                    return (this.scaledCookTime / 60) * this.baseCosts.cooking_power * this.baseCosts.electricity_rate;
                },

                get scaledPackagingCost() {
                    return (this.baseCosts.packaging_cost_per_portion || 0) * this.scaledPortions;
                },

                get scaledTotalCost() {
                    return this.scaledIngredientCost + this.scaledLabourCost + this.scaledElectricityCost + this.scaledPackagingCost;
                },

                get scaledPortions() {
                    return Math.round(this.baseCosts.portions_produced * this.getMultiplierValue());
                },

                get scaledCostPerPortion() {
                    return this.scaledPortions > 0 ? this.scaledTotalCost / this.scaledPortions : 0;
                },

                get savingsPercent() {
                    if (this.baseCosts.cost_per_portion <= 0) return 0;
                    return ((this.baseCosts.cost_per_portion - this.scaledCostPerPortion) / this.baseCosts.cost_per_portion) * 100;
                },

                openSaveModal() {
                    const mult = this.getMultiplierValue();
                    this.newRecipeName = `{{ $recipe->name }} (${mult}x batch)`;
                    this.saveError = '';
                    this.showSaveModal = true;
                },

                closeSaveModal() {
                    this.showSaveModal = false;
                    this.saveError = '';
                },

                async saveAsNewRecipe() {
                    if (!this.newRecipeName.trim()) return;

                    this.saving = true;
                    this.saveError = '';

                    try {
                        const response = await fetch('{{ route('kitchen.scale', $recipe) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                name: this.newRecipeName,
                                recipe_multiplier: this.getMultiplierValue(),
                                labour_factor: this.labourFactor,
                                electricity_factor: this.electricityFactor
                            })
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to save recipe');
                        }

                        // Redirect to the new recipe
                        window.location.href = data.redirect_url;

                    } catch (error) {
                        this.saveError = error.message;
                    } finally {
                        this.saving = false;
                    }
                }
            };
        }

        function productSearch(initialId = '', initialName = '', initialCode = '') {
            return {
                searchQuery: '',
                results: [],
                showResults: false,
                selectedId: initialId,
                selectedName: initialName,
                selectedCode: initialCode,

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

        function ingredientSearch() {
            return {
                searchQuery: '',
                results: [],
                showResults: false,
                searchMode: 'profile',
                selectedProductId: '',
                selectedProfileId: '',
                selectedName: '',
                selectedInfo: '',
                selectedProductCost: 0,

                // Profile modal state
                showProfileModal: false,
                profileForm: {
                    name: '',
                    purchase_quantity: 1,
                    purchase_unit: 'g',
                    notes: ''
                },
                profileSaving: false,
                profileError: '',

                async search() {
                    if (this.searchQuery.length < 2) {
                        this.results = [];
                        this.showResults = false;
                        return;
                    }

                    try {
                        let url;
                        if (this.searchMode === 'profile') {
                            url = `{{ route('kitchen.api.profiles.search') }}?q=${encodeURIComponent(this.searchQuery)}`;
                        } else {
                            url = `{{ route('kitchen.api.products.search') }}?q=${encodeURIComponent(this.searchQuery)}`;
                        }
                        const response = await fetch(url);
                        this.results = await response.json();
                        this.showResults = true;
                    } catch (error) {
                        console.error('Search failed:', error);
                    }
                },

                selectProfile(profile) {
                    this.selectedProfileId = profile.id;
                    this.selectedProductId = profile.pos_product_id;
                    this.selectedName = profile.name;
                    const costPerUnit = parseFloat(profile.cost_per_base_unit) || 0;
                    this.selectedInfo = `${profile.purchase_size} · €${costPerUnit.toFixed(4)}/${profile.base_unit}`;
                    this.selectedProductCost = parseFloat(profile.supplier_cost) || 0;
                    this.searchQuery = '';
                    this.showResults = false;
                    this.results = [];
                },

                selectProduct(product) {
                    this.selectedProfileId = '';
                    this.selectedProductId = product.id;
                    this.selectedName = product.name;
                    const cost = parseFloat(product.cost) || 0;
                    this.selectedProductCost = cost;
                    this.selectedInfo = `No profile - estimated cost: €${cost.toFixed(2)}`;
                    this.searchQuery = '';
                    this.showResults = false;
                    this.results = [];
                },

                clearSelection() {
                    this.selectedProductId = '';
                    this.selectedProfileId = '';
                    this.selectedName = '';
                    this.selectedInfo = '';
                    this.selectedProductCost = 0;
                },

                openProfileModal() {
                    this.profileForm.name = this.selectedName;
                    this.profileForm.purchase_quantity = 1;
                    this.profileForm.purchase_unit = 'g';
                    this.profileForm.notes = '';
                    this.profileError = '';
                    this.showProfileModal = true;
                },

                closeProfileModal() {
                    this.showProfileModal = false;
                    this.profileError = '';
                },

                getProfileCostPreview() {
                    const unitConversions = {
                        'kg': { base: 'g', multiplier: 1000 },
                        'g': { base: 'g', multiplier: 1 },
                        'L': { base: 'ml', multiplier: 1000 },
                        'ml': { base: 'ml', multiplier: 1 },
                        'tbsp': { base: 'ml', multiplier: 15 },
                        'tsp': { base: 'ml', multiplier: 5 },
                        'unit': { base: 'unit', multiplier: 1 },
                        'dozen': { base: 'unit', multiplier: 12 },
                        'pack': { base: 'unit', multiplier: 1 }
                    };
                    const conv = unitConversions[this.profileForm.purchase_unit];
                    if (!conv || this.profileForm.purchase_quantity <= 0) return '-';
                    const baseUnits = this.profileForm.purchase_quantity * conv.multiplier;
                    const costPerUnit = this.selectedProductCost / baseUnits;
                    return `€${costPerUnit.toFixed(6)}/${conv.base}`;
                },

                async saveProfile() {
                    this.profileSaving = true;
                    this.profileError = '';

                    try {
                        const response = await fetch('{{ route('kitchen.profiles.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                pos_product_id: this.selectedProductId,
                                name: this.profileForm.name,
                                purchase_quantity: this.profileForm.purchase_quantity,
                                purchase_unit: this.profileForm.purchase_unit,
                                notes: this.profileForm.notes
                            })
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to create profile');
                        }

                        // Update selection to use the new profile
                        this.selectedProfileId = data.id;
                        this.selectedInfo = `${data.purchase_size} · €${parseFloat(data.cost_per_base_unit).toFixed(4)}/${data.base_unit}`;
                        this.closeProfileModal();

                    } catch (error) {
                        this.profileError = error.message;
                    } finally {
                        this.profileSaving = false;
                    }
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>
