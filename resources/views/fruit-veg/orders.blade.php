<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Generate F&V Order') }}
            </h2>
            <a href="{{ route('fruit-veg.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- Info Banner -->
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">
                                    This will generate order suggestions for <strong>all Fruit & Vegetable products</strong>
                                    based on sales history. Products are grouped by category (Fruits, Vegetables, Barcoded).
                                </p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('fruit-veg.orders.generate') }}" class="space-y-6">
                        @csrf

                        <!-- Sales Period -->
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700">
                                    Sales Period Start
                                </label>
                                <input type="date" name="start_date" id="start_date" required
                                       value="{{ old('start_date', $defaultStartDate) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                @error('start_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">
                                    Start of the period to analyze sales data.
                                </p>
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700">
                                    Sales Period End
                                </label>
                                <input type="date" name="end_date" id="end_date" required
                                       value="{{ old('end_date', $defaultEndDate) }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
                                @error('end_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-sm text-gray-500">
                                    End of the period to analyze sales data.
                                </p>
                            </div>
                        </div>

                        <!-- Coverage Days -->
                        <div>
                            <label for="coverage_days" class="block text-sm font-medium text-gray-700">
                                Coverage Days
                            </label>
                            <input type="number" name="coverage_days" id="coverage_days" required
                                   value="{{ old('coverage_days', 7) }}"
                                   min="1" max="30"
                                   class="mt-1 block w-32 border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm">
                            @error('coverage_days')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">
                                How many days should this order cover? Suggested quantities will be calculated as:
                                <code class="bg-gray-100 px-1 rounded">average daily sales × coverage days</code>
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                Generate Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <x-order-progress-overlay
        :stream-url="route('fruit-veg.orders.generate-stream')"
        form-selector="form[action='{{ route('fruit-veg.orders.generate') }}']"
    />
</x-admin-layout>
