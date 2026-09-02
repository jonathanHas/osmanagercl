<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $recipe->name }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('kitchen.edit', $recipe) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    Edit Recipe
                </a>
                <a href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900">
                    &larr; Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (! empty($organicWarnings))
                <div class="mb-6 bg-amber-50 border border-amber-300 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-amber-900">Organic registration form is incomplete</h3>
                    <p class="mt-1 text-sm text-amber-800">
                        The form still downloads, but these gaps are left blank on it:
                    </p>
                    <ul class="mt-2 list-disc list-inside space-y-1 text-sm text-amber-800">
                        @foreach ($organicWarnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Recipe Info -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-900">{{ $recipe->name }}</h3>
                                    @if($recipe->description)
                                        <p class="mt-2 text-gray-600">{{ $recipe->description }}</p>
                                    @endif
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $recipe->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $recipe->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm text-gray-500">Prep Time</p>
                                    <p class="text-lg font-semibold">{{ $recipe->prep_time ?? '-' }} min</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm text-gray-500">Cook Time</p>
                                    <p class="text-lg font-semibold">{{ $recipe->cook_time ?? '-' }} min</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm text-gray-500">Total Time</p>
                                    <p class="text-lg font-semibold">{{ $recipe->formatted_total_time }}</p>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="text-sm text-gray-500">Portions</p>
                                    <p class="text-lg font-semibold">{{ $recipe->portions_produced }}</p>
                                </div>
                            </div>

                            @if($recipe->product)
                                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                                    <p class="text-sm text-blue-600 font-medium">Linked Product</p>
                                    <p class="text-lg font-semibold text-blue-900">{{ $recipe->product->NAME }}</p>
                                    <p class="text-sm text-blue-700">Code: {{ $recipe->product->CODE }} | Sell Price: {{ number_format($recipe->product->PRICESELL, 2) }}</p>
                                </div>
                            @endif

                            @if($recipe->notes)
                                <div class="mt-6">
                                    <h4 class="text-sm font-medium text-gray-500 uppercase">Notes</h4>
                                    <p class="mt-1 text-gray-700">{{ $recipe->notes }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Ingredients -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Ingredients</h3>

                            @if($recipe->ingredients->isEmpty())
                                <p class="text-gray-500">No ingredients added yet.</p>
                            @else
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingredient</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Waste</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Line Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach($costs['ingredient_costs'] as $ingredient)
                                            <tr>
                                                <td class="px-4 py-3 font-medium text-gray-900">{{ $ingredient['product_name'] }}</td>
                                                <td class="px-4 py-3 text-gray-600">{{ number_format($ingredient['quantity'], 2) }} {{ $ingredient['unit_type'] }}</td>
                                                <td class="px-4 py-3 text-right text-gray-600">{{ number_format($ingredient['unit_cost'], 2) }}</td>
                                                <td class="px-4 py-3 text-right text-gray-600">{{ $ingredient['waste_factor'] > 0 ? $ingredient['waste_factor'] . '%' : '-' }}</td>
                                                <td class="px-4 py-3 text-right font-medium text-gray-900">{{ number_format($ingredient['line_cost'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="4" class="px-4 py-3 text-right font-bold text-gray-900">Ingredients Total:</td>
                                            <td class="px-4 py-3 text-right font-bold text-gray-900 text-lg">€{{ number_format($costs['ingredient_cost'], 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Cost Analysis -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Cost Analysis</h3>

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

                                <div class="flex justify-between items-center py-3 border-b bg-gray-50 -mx-6 px-6">
                                    <span class="text-gray-900 font-medium">Total Recipe Cost</span>
                                    <span class="font-bold text-lg">€{{ number_format($costs['total_cost'], 2) }}</span>
                                </div>

                                <div class="flex justify-between items-center py-2 border-b">
                                    <span class="text-gray-600">Portions Produced</span>
                                    <span class="font-medium">{{ $costs['portions_produced'] }}</span>
                                </div>

                                <div class="flex justify-between items-center py-3 border-b bg-indigo-50 -mx-6 px-6">
                                    <span class="text-indigo-800 font-medium">Cost per Portion</span>
                                    <span class="font-bold text-xl text-indigo-900">€{{ number_format($costs['cost_per_portion'], 2) }}</span>
                                </div>

                                @if($costs['has_linked_product'])
                                    <div class="flex justify-between items-center py-3 border-b">
                                        <span class="text-gray-600">Sell Price</span>
                                        <span class="font-medium">€{{ number_format($costs['sell_price'], 2) }}</span>
                                    </div>

                                    <div class="flex justify-between items-center py-3 border-b">
                                        <span class="text-gray-600">Profit per Portion</span>
                                        <span class="font-bold {{ $costs['profit_per_portion'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            €{{ number_format($costs['profit_per_portion'], 2) }}
                                        </span>
                                    </div>

                                    <div class="py-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-gray-600">Profit Margin</span>
                                            <span class="font-bold text-2xl
                                                @if($costs['margin_status'] === 'excellent') text-green-600
                                                @elseif($costs['margin_status'] === 'good') text-yellow-600
                                                @elseif($costs['margin_status'] === 'low') text-orange-600
                                                @else text-red-600
                                                @endif">
                                                {{ number_format($costs['margin_percentage'], 1) }}%
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-3">
                                            <div class="h-3 rounded-full transition-all
                                                @if($costs['margin_status'] === 'excellent') bg-green-500
                                                @elseif($costs['margin_status'] === 'good') bg-yellow-500
                                                @elseif($costs['margin_status'] === 'low') bg-orange-500
                                                @else bg-red-500
                                                @endif"
                                                style="width: {{ min(100, max(0, $costs['margin_percentage'])) }}%">
                                            </div>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-500">
                                            @if($costs['margin_status'] === 'excellent')
                                                Excellent margin (40%+)
                                            @elseif($costs['margin_status'] === 'good')
                                                Good margin (20-40%)
                                            @elseif($costs['margin_status'] === 'low')
                                                Low margin (10-20%)
                                            @else
                                                Critical margin (&lt;10%)
                                            @endif
                                        </p>
                                    </div>
                                @else
                                    <div class="bg-yellow-50 p-4 rounded-md">
                                        <p class="text-sm text-yellow-800">
                                            <strong>No linked product.</strong> Link this recipe to a POS product to see margin analysis.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                            <div class="space-y-3">
                                <a href="{{ route('kitchen.edit', $recipe) }}" class="block w-full text-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    Edit Recipe
                                </a>
                                <a href="{{ route('kitchen.organic-form', $recipe) }}" class="block w-full text-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    Organic Registration Form
                                </a>
                                <form action="{{ route('kitchen.destroy', $recipe) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this recipe?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">
                                        Delete Recipe
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
