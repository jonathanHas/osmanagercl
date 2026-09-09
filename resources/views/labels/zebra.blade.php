<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Zebra Labels</h2>
    </x-slot>

    <style>[x-cloak] { display: none !important; }</style>

    <div class="py-6" x-data="zebraLabels()" x-cloak>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @include('labels._nav', ['current' => 'zebra'])

            {{-- Sub-tabs: Zebra Labels / Translated Labels --}}
            <div class="flex items-center gap-1 mb-5 bg-gray-100 rounded-lg p-1 w-fit">
                <a href="{{ route('labels.zebra') }}"
                   class="px-4 py-2 rounded-md text-sm font-medium transition {{ $view === 'zebra' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                    Zebra Labels
                </a>
                <a href="{{ route('labels.zebra', ['view' => 'translations']) }}"
                   class="px-4 py-2 rounded-md text-sm font-medium transition {{ $view === 'translations' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                    Translated Labels
                </a>
            </div>

            {{-- Printer spool. This queue lives on the printer host, not on this machine,
                 so clearing the local CUPS queue never affected stuck label jobs. --}}
            <div class="mb-5 bg-white rounded-lg shadow-sm border border-gray-200" x-data="printerQueue()" x-init="load()">
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">Printer Queue</h3>
                        <span class="text-xs text-gray-500" x-text="host ? host + ' · ' + printer : ''"></span>
                        <span x-show="!loading && jobs.length > 0" class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800" x-text="jobs.length + ' queued'"></span>
                        <span x-show="!loading && jobs.length === 0 && loaded" class="text-xs text-green-600">Empty</span>
                        <span x-show="loading" class="text-xs text-gray-400">Checking…</span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="load()" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Refresh</button>
                        <button type="button" x-show="jobs.length > 0" @click="cancelAll()" class="rounded-md bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-red-700">Cancel all</button>
                    </div>
                </div>
                <div x-show="jobs.length > 0" class="border-t border-gray-200 px-5 py-2">
                    <template x-for="job in jobs" :key="job.job_id">
                        <div class="flex items-center justify-between gap-3 py-1.5 text-xs border-b border-gray-100 last:border-0">
                            <span class="font-mono text-gray-700" x-text="job.job_id"></span>
                            <span class="text-gray-500" x-text="job.user"></span>
                            <span class="flex-1 text-gray-400 truncate" x-text="job.submitted_at"></span>
                            <button type="button" @click="cancelJob(job.job_id)" class="rounded border border-red-200 px-2 py-0.5 font-medium text-red-700 hover:bg-red-50">Cancel</button>
                        </div>
                    </template>
                </div>
                <div x-show="error" class="border-t border-gray-200 px-5 py-2">
                    <p class="text-xs text-red-600" x-text="error"></p>
                </div>
            </div>

            @if ($view === 'zebra')
                {{-- ==================== ZEBRA LABELS VIEW ==================== --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-5 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Products on Till with Labels</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Labels linked to products currently visible on the till</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <form method="GET" action="{{ route('labels.zebra') }}" class="relative">
                                    <input type="text" name="search" value="{{ $search }}" placeholder="Search products..."
                                           class="w-56 pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                    <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </form>
                                <a href="{{ route('zebra-labels.index') }}" class="px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-200 transition whitespace-nowrap">
                                    Manage Labels
                                </a>
                            </div>
                        </div>
                    </div>

                    @if (count($zebraLabels) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                        <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="w-20 px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Print</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <template x-for="label in labels" :key="label.id">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-medium text-gray-900" x-text="label.product_name"></div>
                                                <div class="text-xs text-gray-400 font-mono" x-text="label.product_code"></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <a :href="'/labels/zebra/manage/' + label.id" class="text-sm text-blue-600 hover:text-blue-800" x-text="label.name"></a>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                <span x-text="(label.width_mm || '?') + ' × ' + (label.height_mm || '?') + 'mm'"></span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span x-show="!label.mismatches" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">OK</span>
                                                <span x-show="label.mismatches" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 cursor-pointer" @click="openPrintModal(label)">
                                                    <span x-show="label.mismatches?.price && label.mismatches?.country">Price + Country</span>
                                                    <span x-show="label.mismatches?.price && !label.mismatches?.country">Price</span>
                                                    <span x-show="!label.mismatches?.price && label.mismatches?.country">Country</span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button @click="openPrintModal(label)"
                                                        class="relative p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                    </svg>
                                                    <span x-show="label.mismatches" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-8 text-center text-gray-400 text-sm">
                            @if ($search)
                                No till labels found matching "{{ $search }}".
                            @else
                                No zebra labels linked to products currently on till.
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Other Labels (not on till) --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
                    <div class="p-5 border-b border-gray-200">
                        <h3 class="text-base font-semibold text-gray-900">Other Labels</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Labels linked to products not currently on the till</p>
                    </div>

                    <template x-if="otherLabels.length > 0">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                        <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="w-20 px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Print</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <template x-for="label in otherLabels" :key="label.id">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-medium text-gray-900" x-text="label.product_name"></div>
                                                <div class="text-xs text-gray-400 font-mono" x-text="label.product_code"></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <a :href="'/labels/zebra/manage/' + label.id" class="text-sm text-blue-600 hover:text-blue-800" x-text="label.name"></a>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                <span x-text="(label.width_mm || '?') + ' × ' + (label.height_mm || '?') + 'mm'"></span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span x-show="!label.mismatches" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">OK</span>
                                                <span x-show="label.mismatches" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 cursor-pointer" @click="openPrintModal(label)">
                                                    <span x-show="label.mismatches?.price && label.mismatches?.country">Price + Country</span>
                                                    <span x-show="label.mismatches?.price && !label.mismatches?.country">Price</span>
                                                    <span x-show="!label.mismatches?.price && label.mismatches?.country">Country</span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button @click="openPrintModal(label)"
                                                        class="relative p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                    </svg>
                                                    <span x-show="label.mismatches" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                    <template x-if="otherLabels.length === 0">
                        <div class="p-8 text-center text-gray-400 text-sm">
                            @if ($search)
                                No other labels found matching "{{ $search }}".
                            @else
                                All labels are linked to products on the till.
                            @endif
                        </div>
                    </template>
                </div>

                {{-- Standalone Labels (no barcode) --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
                    <div class="p-5 border-b border-gray-200">
                        <h3 class="text-base font-semibold text-gray-900">Standalone Labels</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Labels not linked to a product barcode</p>
                    </div>

                    <template x-if="standAloneLabels.length > 0">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                        <th class="w-20 px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Print</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <template x-for="label in standAloneLabels" :key="label.id">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <a :href="'/labels/zebra/manage/' + label.id" class="text-sm font-medium text-blue-600 hover:text-blue-800" x-text="label.name"></a>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-600">
                                                <span x-text="(label.width_mm || '?') + ' × ' + (label.height_mm || '?') + 'mm'"></span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <button @click="openPrintModal(label)"
                                                        class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                    <template x-if="standAloneLabels.length === 0">
                        <div class="p-8 text-center text-gray-400 text-sm">
                            @if ($search)
                                No standalone labels found matching "{{ $search }}".
                            @else
                                No standalone labels uploaded.
                            @endif
                        </div>
                    </template>
                </div>

            @else
                {{-- ==================== TRANSLATED LABELS VIEW ==================== --}}
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="p-5 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">Translated Labels</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Product translations grouped by category</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <form method="GET" action="{{ route('labels.zebra') }}" class="relative">
                                    <input type="hidden" name="view" value="translations">
                                    <input type="text" name="search" value="{{ $search }}" placeholder="Search translations..."
                                           class="w-56 pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                    <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </form>
                                <a href="{{ route('labels.translate') }}" class="px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-indigo-500 transition whitespace-nowrap">
                                    New Translation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                @if (count($translationsByCategory) > 0)
                    @foreach ($translationsByCategory as $category => $translations)
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4">
                            <div class="p-4 border-b border-gray-200">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900">{{ $category }}</h4>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ count($translations) }}</span>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Label Size</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                                            <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Auto-print</th>
                                            <th class="w-28 px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach ($translations as $t)
                                            <tr x-data="{ autoPrint: {{ $t['auto_print'] ? 'true' : 'false' }} }"
                                                class="hover:bg-gray-50" :class="{ 'opacity-50': !autoPrint }">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-3">
                                                        @if ($t['product_code'] && isset($translationProducts[$t['product_code']]))
                                                            <x-product-image
                                                                :product="$translationProducts[$t['product_code']]"
                                                                :supplier-service="$supplierService"
                                                                size="sm"
                                                                :hover="true"
                                                                :fallback="false" />
                                                        @endif
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900">{{ $t['db_product_name'] ?? $t['product_name'] }}</div>
                                                            @if ($t['db_product_name'] && $t['db_product_name'] !== $t['product_name'])
                                                                <div class="text-xs text-gray-400">{{ $t['product_name'] }}</div>
                                                            @endif
                                                            @if ($t['product_code'])
                                                                <div class="text-xs text-gray-400 font-mono">{{ $t['product_code'] }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600">{{ $t['label_size'] }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-500">{{ $t['created_at'] }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-500">{{ $t['user_name'] }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    <button type="button"
                                                            title="Auto-print this label with deliveries"
                                                            @click="const next = !autoPrint; autoPrint = next; toggleAutoPrint({{ $t['id'] }}, next).catch(() => autoPrint = !next)"
                                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                                            :class="autoPrint ? 'bg-green-600' : 'bg-gray-300'">
                                                        <span class="sr-only">Toggle auto-print</span>
                                                        <span :class="autoPrint ? 'translate-x-6' : 'translate-x-1'"
                                                              class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                                    </button>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <div class="flex items-center justify-center gap-1">
                                                        <a href="{{ route('labels.translate', ['edit' => $t['id'], 'from' => 'zebra']) }}"
                                                           class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition" title="Edit">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </a>
                                                        <button @click="openTranslationPrintModal({{ $t['id'] }}, {{ json_encode($t['product_name']) }}, {{ json_encode($t['label_size']) }})"
                                                                class="p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition" title="Print">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center text-gray-400 text-sm">
                        @if ($search)
                            No translated labels found matching "{{ $search }}".
                        @else
                            No translated labels yet. <a href="{{ route('labels.translate') }}" class="text-indigo-600 hover:underline">Translate a label</a> to get started.
                        @endif
                    </div>
                @endif
            @endif

            {{-- Print Modal --}}
            <div x-show="printModal.open" x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 @keydown.escape.window="printModal.open = false">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="printModal.open = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full p-6" @click.stop>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Print Label</h3>

                        <div class="space-y-3 text-sm">
                            <div x-show="printModal.productName">
                                <span class="text-gray-500">Product:</span>
                                <span class="font-medium text-gray-900 ml-1" x-text="printModal.productName"></span>
                            </div>
                            <div x-show="printModal.labelName">
                                <span class="text-gray-500">Label:</span>
                                <span class="text-gray-900 ml-1" x-text="printModal.labelName"></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Size:</span>
                                <span class="text-gray-900 ml-1" x-text="printModal.labelSize"></span>
                            </div>

                            {{-- Mismatches (zebra labels only) --}}
                            <template x-if="!printModal.isTranslation && printModal.mismatches?.price">
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-amber-800">Price mismatch</div>
                                            <div class="text-xs text-amber-700 mt-0.5">
                                                Label: <span class="font-mono font-medium" x-text="'€' + printModal.mismatches.price.label_value"></span>
                                                &rarr; DB: <span class="font-mono font-medium" x-text="'€' + printModal.mismatches.price.db_value"></span>
                                            </div>
                                        </div>
                                        <button @click="fixMismatch('price')" :disabled="printModal.fixing"
                                                class="px-2 py-1 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-500 transition disabled:opacity-50">
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!printModal.isTranslation && printModal.mismatches?.country">
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-amber-800">Country mismatch</div>
                                            <div class="text-xs text-amber-700 mt-0.5">
                                                Label: <span class="font-medium" x-text="printModal.mismatches.country.label_value"></span>
                                                &rarr; DB: <span class="font-medium" x-text="printModal.mismatches.country.db_value"></span>
                                            </div>
                                        </div>
                                        <button @click="fixMismatch('country')" :disabled="printModal.fixing"
                                                class="px-2 py-1 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-500 transition disabled:opacity-50">
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div class="pt-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Number of labels</label>
                                <input type="number" x-model.number="printModal.copies" min="1" max="99"
                                       class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-center">
                            </div>
                        </div>

                        <div class="mt-5 flex items-center gap-3">
                            <button @click="sendPrint()" :disabled="printModal.printing"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-500 transition disabled:opacity-50">
                                <span x-text="printModal.printing ? 'Printing...' : 'Print'"></span>
                            </button>
                            <button @click="printModal.open = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 transition">
                                Cancel
                            </button>
                            <p x-show="printModal.message" :class="printModal.success ? 'text-green-600' : 'text-red-600'" class="text-xs" x-text="printModal.message"></p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Live view of the CUPS spool on the printer host, with cancel.
        function printerQueue() {
            return {
                jobs: [],
                host: '',
                printer: '',
                loading: false,
                loaded: false,
                error: '',

                load() {
                    this.loading = true;
                    this.error = '';
                    fetch('{{ route('labels.printer-queue') }}', { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(data => {
                            this.jobs = data.jobs || [];
                            this.host = data.host || '';
                            this.printer = data.printer || '';
                            this.loaded = true;
                            if (!data.success) {
                                this.error = data.output || 'Could not read the printer queue.';
                            }
                        })
                        .catch(e => { this.error = 'Could not reach the printer: ' + e; })
                        .finally(() => { this.loading = false; });
                },

                cancelJob(jobId) {
                    this.send({ job_id: jobId });
                },

                cancelAll() {
                    if (!confirm('Cancel every queued job for this printer?')) return;
                    this.send({ all: true });
                },

                send(payload) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    this.loading = true;
                    fetch('{{ route('labels.printer-cancel') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(payload),
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (!data.success) this.error = data.output || 'Cancel failed.';
                        })
                        .catch(e => { this.error = 'Cancel failed: ' + e; })
                        .finally(() => { this.load(); });
                },
            };
        }

        function zebraLabels() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            return {
                labels: @json($zebraLabels),
                otherLabels: @json($otherLabels),
                standAloneLabels: @json($standAloneLabels),
                printModal: {
                    open: false,
                    labelId: null,
                    labelName: '',
                    labelSize: '',
                    productName: '',
                    copies: 1,
                    printing: false,
                    fixing: false,
                    message: '',
                    success: false,
                    mismatches: null,
                    fields: [],
                    isTranslation: false,
                },

                openPrintModal(label) {
                    this.printModal = {
                        open: true,
                        labelId: label.id,
                        labelName: label.name,
                        labelSize: (label.width_mm || '?') + 'mm × ' + (label.height_mm || '?') + 'mm',
                        productName: label.product_name,
                        copies: label.default_copies || 1,
                        printing: false,
                        fixing: false,
                        message: '',
                        success: false,
                        mismatches: label.mismatches ? JSON.parse(JSON.stringify(label.mismatches)) : null,
                        fields: label.fields ? [...label.fields] : [],
                        isTranslation: false,
                    };
                },

                openTranslationPrintModal(id, productName, labelSize) {
                    this.printModal = {
                        open: true,
                        labelId: id,
                        labelName: '',
                        labelSize: labelSize,
                        productName: productName,
                        copies: 1,
                        printing: false,
                        fixing: false,
                        message: '',
                        success: false,
                        mismatches: null,
                        fields: [],
                        isTranslation: true,
                    };
                },

                async sendPrint() {
                    if (this.printModal.printing) return;
                    this.printModal.printing = true;
                    this.printModal.message = '';

                    const url = this.printModal.isTranslation
                        ? '/labels/translate/' + this.printModal.labelId + '/print'
                        : '/labels/zebra/manage/' + this.printModal.labelId + '/print';

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ copies: this.printModal.copies }),
                        });
                        const data = await res.json();
                        this.printModal.message = data.success ? data.message : ('Failed: ' + (data.output || data.message));
                        this.printModal.success = data.success;
                        if (data.success) {
                            setTimeout(() => { this.printModal.open = false; }, 1500);
                        }
                    } catch (err) {
                        this.printModal.message = 'Failed: ' + err.message;
                        this.printModal.success = false;
                    }
                    this.printModal.printing = false;
                },

                // Toggle whether a translation is included in delivery auto-printing.
                // Throws on failure so the caller can revert the optimistic switch state.
                async toggleAutoPrint(id, autoPrint) {
                    const res = await fetch('/labels/translate/' + id + '/auto-print', {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({ auto_print: autoPrint }),
                    });
                    if (!res.ok) throw new Error('toggle failed');
                },

                async fixMismatch(type) {
                    const mismatch = this.printModal.mismatches?.[type];
                    if (!mismatch || this.printModal.fixing) return;
                    this.printModal.fixing = true;

                    const fields = [...this.printModal.fields];
                    fields[mismatch.field_index] = mismatch.new_field;

                    try {
                        const res = await fetch('/labels/zebra/manage/' + this.printModal.labelId + '/fields', {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify({ fields }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            delete this.printModal.mismatches[type];
                            if (Object.keys(this.printModal.mismatches).length === 0) {
                                this.printModal.mismatches = null;
                            }
                            this.printModal.fields = data.fields;

                            // Update the label in whichever list it belongs to
                            const label = this.labels.find(l => l.id === this.printModal.labelId)
                                || this.otherLabels.find(l => l.id === this.printModal.labelId)
                                || this.standAloneLabels.find(l => l.id === this.printModal.labelId);
                            if (label) {
                                label.fields = data.fields;
                                label.mismatches = this.printModal.mismatches
                                    ? JSON.parse(JSON.stringify(this.printModal.mismatches))
                                    : null;
                            }
                        }
                    } catch (err) {
                        // silently fail
                    }
                    this.printModal.fixing = false;
                },
            };
        }
    </script>
</x-admin-layout>
