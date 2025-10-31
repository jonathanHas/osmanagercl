<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Welcome Section -->
            <div class="mb-8">
                <h3 class="text-2xl font-bold text-gray-900">Welcome back, {{ Auth::user()->name }}!</h3>
                <p class="mt-1 text-gray-600">Here's what's happening with your store today.</p>
            </div>

            <!-- Statistics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Products Card -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Products</dt>
                                    <dd class="text-3xl font-semibold text-gray-900">{{ $statistics['total_products'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <a href="{{ route('products.index') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                                View all products
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Active Products Card -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Active Products</dt>
                                    <dd class="text-3xl font-semibold text-gray-900">{{ $statistics['active_products'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-green-600">{{ round(($statistics['active_products'] ?? 0) / max(($statistics['total_products'] ?? 1), 1) * 100) }}%</span>
                            <span class="text-gray-500">of total</span>
                        </div>
                    </div>
                </div>

                <!-- In Stock Card -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">In Stock</dt>
                                    <dd class="text-3xl font-semibold text-gray-900">{{ $statistics['in_stock'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <span class="text-blue-600">{{ $statistics['out_of_stock'] ?? 0 }}</span>
                            <span class="text-gray-500">out of stock</span>
                        </div>
                    </div>
                </div>

                <!-- Service Products Card -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Service Products</dt>
                                    <dd class="text-3xl font-semibold text-gray-900">{{ $statistics['service_products'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm text-gray-500">
                            Non-physical items
                        </div>
                    </div>
                </div>
            </div>

            <!-- Amazon Pending Invoices Alert (if any) -->
            @if(($amazonPendingCount ?? 0) > 0)
            <div class="mb-8">
                <div class="bg-gradient-to-r from-orange-500 to-red-600 overflow-hidden shadow rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-orange-100 truncate">Amazon Invoices Pending</dt>
                                    <dd class="text-3xl font-bold text-white">{{ $amazonPendingCount ?? 0 }}</dd>
                                </dl>
                            </div>
                            <div class="ml-5 flex-shrink-0">
                                <a href="{{ route('amazon-pending.index') }}" 
                                   class="bg-white bg-opacity-20 backdrop-blur-sm hover:bg-opacity-30 text-white font-bold py-3 px-6 rounded-lg transition-all duration-200 inline-flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    Enter EUR Payments
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="bg-orange-600 bg-opacity-50 px-6 py-3">
                        <div class="text-sm">
                            <p class="text-orange-100">
                                {{ ($amazonPendingCount ?? 0) == 1 ? 'This invoice needs' : 'These invoices need' }} EUR payment amounts entered before they can be processed.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Quick Links -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Links</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <a href="{{ route('fruit-veg.manage') }}" class="group relative flex items-center justify-between rounded-xl border border-green-100 bg-white p-5 shadow-sm transition hover:shadow-md focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-green-500">
                            <div class="flex items-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-600">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.5Q13.3 3 16 2.5" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.5Q10.7 3 8 2.5" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.5C8.75 6.5 6.25 9.17 6.25 12.63 6.25 16.33 8.75 19.5 12 19.5 15.25 19.5 17.75 16.33 17.75 12.63 17.75 9.17 15.25 6.5 12 6.5Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.25 12.25c.6.4 1.55.87 2.75.87s2.15-.47 2.75-.87" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-base font-semibold text-gray-900">Manage Fruit &amp; Veg</p>
                                    <p class="mt-1 text-sm text-gray-500">Update availability, pricing, and labels.</p>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-gray-300 transition group-hover:text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>

                        <a href="{{ route('invoices.bulk-upload.index') }}" class="group relative flex items-center justify-between rounded-xl border border-indigo-100 bg-white p-5 shadow-sm transition hover:shadow-md focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                            <div class="flex items-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-base font-semibold text-gray-900">Upload Invoices</p>
                                    <p class="mt-1 text-sm text-gray-500">Start a new bulk upload batch.</p>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-gray-300 transition group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>

                        <a href="{{ route('suppliers.outstanding-report') }}" class="group relative flex items-center justify-between rounded-xl border border-amber-100 bg-white p-5 shadow-sm transition hover:shadow-md focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-amber-500">
                            <div class="flex items-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="8" stroke-width="2" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .794-3 2s1.343 2 3 2 3 .794 3 2-1.343 2-3 2m0-8c1.657 0 3 .794 3 2m-3-6v12m0-12V4m0 12v2" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-base font-semibold text-gray-900">Outstanding Suppliers</p>
                                    <p class="mt-1 text-sm text-gray-500">Review balances and follow-ups.</p>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-gray-300 transition group-hover:text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
