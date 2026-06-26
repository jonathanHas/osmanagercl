<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Admin</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak] { display: none !important; }</style>
        
        <!-- Livewire Styles are auto-injected when inject_assets is true in config/livewire.php -->
    </head>
    <body class="font-sans antialiased">
        <div x-data="{
            sidebarOpen: false,
            sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
            operationsOpen: localStorage.getItem('nav_operationsOpen') === 'true',
            kitchenOpen: localStorage.getItem('nav_kitchenOpen') === 'true',
            ordersOpen: localStorage.getItem('nav_ordersOpen') === 'true',
            stockMonitoringOpen: localStorage.getItem('nav_stockMonitoringOpen') === 'true',
            financialOpen: localStorage.getItem('nav_financialOpen') === 'true',
            revenueOpen: localStorage.getItem('nav_revenueOpen') === 'true',
            stockOpen: localStorage.getItem('nav_stockOpen') === 'true',
            systemToolsOpen: localStorage.getItem('nav_systemToolsOpen') === 'true',
            adminOpen: localStorage.getItem('nav_adminOpen') === 'true',
            customerOpen: localStorage.getItem('nav_customerOpen') === 'true',
            voucherOpen: localStorage.getItem('nav_voucherOpen') === 'true'
        }"
        x-init="
            $watch('sidebarCollapsed', val => localStorage.setItem('sidebarCollapsed', val));
            $watch('operationsOpen', val => localStorage.setItem('nav_operationsOpen', val));
            $watch('kitchenOpen', val => localStorage.setItem('nav_kitchenOpen', val));
            $watch('ordersOpen', val => localStorage.setItem('nav_ordersOpen', val));
            $watch('stockMonitoringOpen', val => localStorage.setItem('nav_stockMonitoringOpen', val));
            $watch('financialOpen', val => localStorage.setItem('nav_financialOpen', val));
            $watch('revenueOpen', val => localStorage.setItem('nav_revenueOpen', val));
            $watch('stockOpen', val => localStorage.setItem('nav_stockOpen', val));
            $watch('systemToolsOpen', val => localStorage.setItem('nav_systemToolsOpen', val));
            $watch('adminOpen', val => localStorage.setItem('nav_adminOpen', val));
            $watch('customerOpen', val => localStorage.setItem('nav_customerOpen', val));
            $watch('voucherOpen', val => localStorage.setItem('nav_voucherOpen', val));
        "
        class="flex h-screen bg-gray-100">
            <!-- Sidebar -->
            <div :class="{
                    'translate-x-0': sidebarOpen,
                    '-translate-x-full': !sidebarOpen,
                    'lg:translate-x-0': !sidebarCollapsed,
                    'lg:-translate-x-full lg:w-0 lg:overflow-hidden': sidebarCollapsed
                 }"
                 class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 transition-all duration-300 ease-in-out lg:static lg:block lg:flex-shrink-0">
                <div class="flex h-full min-h-full flex-col bg-gray-900">
                    <!-- Logo -->
                    <div class="flex h-16 items-center justify-between px-4 bg-gray-800">
                        <a href="{{ route('dashboard') }}" class="flex items-center">
                            <x-application-logo class="h-8 w-auto fill-current text-white" />
                            <span class="ml-2 text-xl font-semibold text-white">Admin</span>
                        </a>
                        <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 hover:text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    @if(auth()->user()->hasRole('admin'))
                    <div class="border-b border-gray-800 px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('fruit-veg.manage') }}"
                               title="Manage Fruit &amp; Veg"
                               aria-label="Manage Fruit &amp; Veg"
                               class="group flex h-10 w-10 items-center justify-center rounded-lg bg-gray-800 text-gray-400 transition hover:bg-green-600/20 hover:text-green-400 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.5Q13.3 3 16 2.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5.5Q10.7 3 8 2.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.5C8.75 6.5 6.25 9.17 6.25 12.63 6.25 16.33 8.75 19.5 12 19.5 15.25 19.5 17.75 16.33 17.75 12.63 17.75 9.17 15.25 6.5 12 6.5Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.25 12.25c.6.4 1.55.87 2.75.87s2.15-.47 2.75-.87" />
                                </svg>
                                <span class="sr-only">Manage Fruit &amp; Veg</span>
                            </a>

                            <a href="{{ route('invoices.index') }}"
                               title="Invoices"
                               aria-label="Invoices"
                               class="group flex h-10 w-10 items-center justify-center rounded-lg bg-gray-800 text-gray-400 transition hover:bg-purple-600/20 hover:text-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="sr-only">Invoices</span>
                            </a>

                            <a href="{{ route('invoices.bulk-upload.index') }}"
                               title="Upload Invoices"
                               aria-label="Upload Invoices"
                               class="group flex h-10 w-10 items-center justify-center rounded-lg bg-gray-800 text-gray-400 transition hover:bg-indigo-600/20 hover:text-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <span class="sr-only">Upload Invoices</span>
                            </a>

                            @if(auth()->user()->can('customer-invoices.manage'))
                            <a href="{{ route('customer-invoices.index') }}"
                               title="Customer Invoices"
                               aria-label="Customer Invoices"
                               class="group flex h-10 w-10 items-center justify-center rounded-lg bg-gray-800 text-gray-400 transition hover:bg-blue-600/20 hover:text-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                <span class="sr-only">Customer Invoices</span>
                            </a>
                            @endif

                            <a href="{{ route('suppliers.outstanding-report') }}"
                               title="Suppliers Outstanding Report"
                               aria-label="Suppliers Outstanding Report"
                               class="group flex h-10 w-10 items-center justify-center rounded-lg bg-gray-800 text-gray-400 transition hover:bg-amber-600/20 hover:text-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-gray-900">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="8" stroke-width="2" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .794-3 2s1.343 2 3 2 3 .794 3 2-1.343 2-3 2m0-8c1.657 0 3 .794 3 2m-3-6v12m0-12V4m0 12v2" />
                                </svg>
                                <span class="sr-only">Suppliers Outstanding Report</span>
                            </a>
                        </div>
                    </div>
                    @endif

                    <!-- Navigation -->
                    <nav class="flex-1 overflow-y-auto space-y-1 px-2 py-4">
                        <!-- OPERATIONS SECTION -->
                        @unless(auth()->user()->hasRole('barista'))
                        <div class="px-2">
                            <button @click="operationsOpen = !operationsOpen" 
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Operations</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="operationsOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div x-show="operationsOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('dashboard') }}" 
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('dashboard') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Dashboard
                            </a>
                            
                            <a href="{{ route('products.index') }}" 
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('products.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                Products
                            </a>
                            
                            <a href="{{ route('categories.index') }}" 
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('categories.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                                Categories
                            </a>
                            
                            <a href="{{ route('fruit-veg.index') }}" 
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('fruit-veg.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Fruit & Veg
                            </a>
                            
                            <a href="{{ route('coffee.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('coffee.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                                Coffee Fresh
                            </a>
                        </div>
                        @endunless

                        <!-- CUSTOMER SECTION -->
                        @if(auth()->user()->can('customer-invoices.manage'))
                        <div class="px-2 pt-4">
                            <button @click="customerOpen = !customerOpen"
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Customer</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="customerOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <div x-show="customerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('customers.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('customers.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 4a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Customers
                            </a>

                            <a href="{{ route('customer-invoices.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('customer-invoices.index') || request()->routeIs('customer-invoices.show') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Customer Invoices
                            </a>

                            <a href="{{ route('customer-invoices.create') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('customer-invoices.create') || request()->routeIs('customer-invoices.edit') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                New Invoice
                            </a>

                            <a href="{{ route('customer-payments.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('customer-payments.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2m6 5H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z"/>
                                </svg>
                                Customer Payments
                            </a>
                        </div>
                        @endif

                        <!-- VOUCHER SECTION -->
                        @if(auth()->user()->can('vouchers.redeem'))
                        <div class="px-2 pt-4">
                            <button @click="voucherOpen = !voucherOpen"
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Vouchers</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="voucherOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <div x-show="voucherOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('vouchers.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('vouchers.index') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4"/>
                                </svg>
                                Scan / Redeem
                            </a>

                            @if(auth()->user()->can('vouchers.manage'))
                            <a href="{{ route('vouchers.list') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('vouchers.list') || request()->routeIs('vouchers.transactions') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                                All Vouchers
                            </a>

                            <a href="{{ route('vouchers.generate') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('vouchers.generate') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Generate
                            </a>
                            @endif
                        </div>
                        @endif

                        <!-- KITCHEN SECTION -->
                        @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                        <div class="px-2 pt-4">
                            <button @click="kitchenOpen = !kitchenOpen"
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Kitchen</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="kitchenOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <div x-show="kitchenOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('kitchen.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('kitchen.index') || request()->routeIs('kitchen.create') || request()->routeIs('kitchen.edit') || request()->routeIs('kitchen.show') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                Recipes
                            </a>

                            <a href="{{ route('kitchen.profiles.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('kitchen.profiles.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Ingredient Profiles
                            </a>

                            <a href="{{ route('kitchen.products.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('kitchen.products.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Kitchen Products
                            </a>
                        </div>
                        @endif

                        <!-- ORDER MANAGEMENT SECTION -->
                        @unless(auth()->user()->hasRole('barista'))
                        <div class="px-2 pt-4">
                            <button @click="ordersOpen = !ordersOpen" 
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Order Management</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="ordersOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div x-show="ordersOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('orders.index') }}" 
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('orders.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                Orders
                            </a>
                            
                            <a href="{{ route('deliveries.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('deliveries.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 11-2 0 1 1 0 012 0z" />
                                </svg>
                                Deliveries
                            </a>

                            <a href="{{ route('delivery-legacy.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('delivery-legacy.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                </svg>
                                Delivery Legacy
                            </a>
                        </div>
                        @endunless

                        <!-- STOCK MONITORING SECTION -->
                        @if(auth()->user()->hasRole('admin'))
                        <div class="px-2 pt-4">
                            <button @click="stockMonitoringOpen = !stockMonitoringOpen"
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Stock Monitoring</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="stockMonitoringOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <div x-show="stockMonitoringOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('order-manager.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('order-manager.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                Order Manager
                            </a>
                        </div>
                        @endif

                        <!-- FINANCIAL MANAGEMENT SECTION -->
                        @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                        <div class="px-2 pt-4">
                            <button @click="financialOpen = !financialOpen" 
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Financial</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="financialOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div x-show="financialOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('management.financial.dashboard') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.financial.dashboard') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Financial Dashboard
                            </a>

                            <a href="{{ route('management.sales-review.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.sales-review.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Sales Review
                            </a>

                            <a href="{{ route('management.profit-loss.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.profit-loss.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Profit & Loss
                            </a>

                            <a href="{{ route('management.wages.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.wages.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Wages
                            </a>

                            <a href="{{ route('management.sales-accounting.index') }}" 
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.sales-accounting.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Sales Accounting
                            </a>
                            
                            <a href="{{ route('cash-reconciliation.index') }}" 
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('cash-reconciliation.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Cash Reconciliation
                        </a>
                        
                        <a href="{{ route('management.bank-statements.index') }}" 
                           class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.bank-statements.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            Bank Statements
                        </a>
                        
                        <a href="{{ route('management.cash-lodgements.index') }}" 
                           class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.cash-lodgements.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                            </svg>
                            Cash Lodgements
                        </a>
                        
                        <a href="{{ route('till-review.index') }}" 
                           class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('till-review.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            Till Review
                        </a>
                        
                        <a href="{{ route('invoices.index') }}"
                           class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('invoices.*') && !request()->routeIs('amazon-pending.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Invoices
                        </a>

                        @php
                            $pendingCount = \App\Models\AmazonInvoicePending::pending()->count();
                        @endphp
                        @if($pendingCount > 0)
                        <a href="{{ route('amazon-pending.index') }}" 
                           class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('amazon-pending.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div class="flex items-center">
                                <span class="mr-2">Amazon Pending</span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-orange-600 text-white">
                                    {{ $pendingCount }}
                                </span>
                            </div>
                        </a>
                        @endif
                        
                        <a href="{{ route('management.osaccounts-import.index') }}" 
                           class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.osaccounts-import.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                            OSAccounts Import
                        </a>
                        
                        <a href="{{ route('suppliers.index') }}" 
                           class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('suppliers.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                            <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            Suppliers
                        </a>
                        </div>
                        @endif

                        <!-- REVENUE SECTION -->
                        @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                        <div class="px-2 pt-4">
                            <button @click="revenueOpen = !revenueOpen"
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Revenue</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="revenueOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <div x-show="revenueOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('management.vat-dashboard.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.vat-dashboard.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                VAT Dashboard
                            </a>

                            <a href="{{ route('management.vat-returns.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.vat-returns.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                                </svg>
                                VAT Returns
                            </a>

                            <a href="{{ route('management.vat-purchases.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('management.vat-purchases.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                                </svg>
                                VAT on Purchases
                            </a>

                            <a href="{{ route('rtd.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('rtd.*') || request()->routeIs('rtd-fallbacks.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                RTD Management
                            </a>
                        </div>
                        @endif

                        <!-- STOCK SECTION -->
                        @unless(auth()->user()->hasRole('barista'))
                        <div class="px-2 pt-4">
                            <button @click="stockOpen = !stockOpen"
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Stock</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="stockOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <div x-show="stockOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('stocking.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('stocking.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                                Stocking
                            </a>

                            <a href="{{ route('stock-review.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('stock-review.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                Stock Review
                            </a>

                            <a href="{{ route('destock-review.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('destock-review.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                Destock Review
                            </a>

                            <a href="{{ route('labels.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('labels.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Labels & Printing
                            </a>
                        </div>
                        @endunless

                        <!-- SYSTEM TOOLS SECTION -->
                        @unless(auth()->user()->hasRole('barista'))
                        <div class="px-2 pt-4">
                            <button @click="systemToolsOpen = !systemToolsOpen" 
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>System Tools</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="systemToolsOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div x-show="systemToolsOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            <a href="{{ route('sales-import.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('sales-import.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Sales Import
                            </a>

                            @if(auth()->user()->can('kds.access'))
                            <a href="{{ route('kds.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('kds.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                </svg>
                                Coffee KDS
                            </a>
                            @endif

                            @if(auth()->user()->isAdmin())
                            <a href="{{ route('tools.ai-diagnostics') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('tools.ai-diagnostics*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                AI Diagnostics
                            </a>
                            @endif
                        </div>
                        @endunless

                        <!-- ADMINISTRATION SECTION -->
                        @if(auth()->user()->can('users.view') || !auth()->user()->hasRole('barista'))
                        <div class="px-2 pt-4">
                            <button @click="adminOpen = !adminOpen" 
                                    class="w-full flex items-center justify-between text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-300 py-2">
                                <span>Administration</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="adminOpen ? 'rotate-90' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div x-show="adminOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1">
                            @if(auth()->user()->can('users.view'))
                            <a href="{{ route('users.index') }}" 
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('users.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                Users
                            </a>
                            @endif

                            @unless(auth()->user()->hasRole('barista'))
                            <a href="{{ route('settings.index') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('settings.*') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Settings
                            </a>
                            @endunless

                            @if(auth()->user()->hasRole('admin'))
                            <a href="{{ route('stocking.logs') }}"
                               class="group flex items-center px-2 py-2 text-sm font-medium rounded-md {{ request()->routeIs('stocking.logs') ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                                <svg class="mr-3 h-6 w-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Stock Logs
                            </a>
                            @endif
                        </div>
                        @endif
                    </nav>

                    <!-- User menu -->
                    <div class="flex flex-shrink-0 border-t border-gray-800 p-4">
                        <div class="flex items-center">
                            <div>
                                <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7C3AED&background=EDE9FE" alt="{{ Auth::user()->name }}">
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-white">{{ Auth::user()->name }}</p>
                                <p class="text-xs font-medium text-gray-400">{{ Auth::user()->email }}</p>
                            </div>
                        </div>
                        <div class="ml-auto">
                            <!-- Custom upward dropdown -->
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                                <div @click="open = ! open">
                                    <button class="text-gray-400 hover:text-white">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                        </svg>
                                    </button>
                                </div>

                                <div x-show="open"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="opacity-100 scale-100"
                                        x-transition:leave-end="opacity-0 scale-95"
                                        class="absolute z-50 bottom-full mb-2 w-48 rounded-md shadow-lg ltr:origin-bottom-right rtl:origin-bottom-left end-0"
                                        style="display: none;"
                                        @click="open = false">
                                    <div class="rounded-md ring-1 ring-black ring-opacity-5 py-1 bg-white dark:bg-gray-800">
                                        <x-dropdown-link :href="route('profile.edit')">
                                            {{ __('Profile') }}
                                        </x-dropdown-link>

                                        <!-- Authentication -->
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <x-dropdown-link :href="route('logout')"
                                                    onclick="event.preventDefault();
                                                                this.closest('form').submit();">
                                                {{ __('Log Out') }}
                                            </x-dropdown-link>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto border-t border-gray-800 px-4 py-3 text-xs text-gray-500">
                        @php($buildVersion = \App\Support\AppVersion::current())
                        <div class="flex items-center justify-between">
                            <span class="uppercase tracking-wide text-[10px] text-gray-400">Build</span>
                            <span class="font-mono text-sm text-gray-300">{{ $buildVersion }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-gray-400">
                            <span>Env</span>
                            <span class="uppercase tracking-wide">{{ config('app.env') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top bar -->
                <header class="bg-white shadow-sm">
                    <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <button @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-600 lg:hidden">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <!-- Desktop sidebar toggle -->
                        <button @click="sidebarCollapsed = !sidebarCollapsed"
                                class="hidden lg:block text-gray-400 hover:text-gray-600 p-1 rounded transition-colors"
                                :title="sidebarCollapsed ? 'Show sidebar' : 'Hide sidebar'">
                            <svg class="h-5 w-5 transition-transform duration-200"
                                 :class="sidebarCollapsed ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                            </svg>
                        </button>

                        <!-- Page Header -->
                        @isset($header)
                            {{ $header }}
                        @endisset
                    </div>
                </header>

                <!-- Main content area -->
                <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <!-- Mobile sidebar backdrop -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"
             class="fixed inset-0 bg-gray-600 bg-opacity-75 z-40 lg:hidden"
             style="display: none;">
        </div>
        
        @stack('scripts')

        <!-- Livewire Scripts are auto-injected when inject_assets is true in config/livewire.php -->

        {{-- Stale page detector: checks session on page visibility resume --}}
        <script>
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    fetch('{{ route("auth.check") }}', {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.authenticated) {
                            window.location.href = '{{ route("login") }}';
                        }
                    })
                    .catch(() => {}); // Network error - don't redirect
                }
            });
        </script>
    </body>
</html>
