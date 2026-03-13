<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Test Pages Hub</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <p class="text-sm text-gray-500 mb-6">Central index of all test/debug pages. These can be removed when no longer needed. See <code>docs/test.md</code> for cleanup instructions.</p>

            {{-- Labels --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Labels & Printing</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('labels.barcode-scan-test') }}" class="text-sm text-indigo-600 hover:underline font-medium">Barcode Scanner Test</a>
                            <p class="text-xs text-gray-400">Live camera barcode scanning via html5-qrcode</p>
                        </div>
                        <code class="text-xs text-gray-400">/labels/barcode-scan-test</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('labels.camera-test') }}" class="text-sm text-indigo-600 hover:underline font-medium">Camera Test v1</a>
                            <p class="text-xs text-gray-400">Product label photo capture (raw ZPL via Gemini)</p>
                        </div>
                        <code class="text-xs text-gray-400">/labels/camera-test</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('labels.camera-test2') }}" class="text-sm text-indigo-600 hover:underline font-medium">Camera Test v2</a>
                            <p class="text-xs text-gray-400">JSON-based Gemini translation</p>
                        </div>
                        <code class="text-xs text-gray-400">/labels/camera-test2</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('labels.zpl-debug') }}" class="text-sm text-indigo-600 hover:underline font-medium">ZPL Debug</a>
                            <p class="text-xs text-gray-400">ZPL renderer diagnostics</p>
                        </div>
                        <code class="text-xs text-gray-400">/labels/zpl-debug</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('zebra-labels.create') }}" class="text-sm text-indigo-600 hover:underline font-medium">Zebra Label Upload</a>
                            <p class="text-xs text-gray-400">Upload & print ZebraDesigner .prn exports</p>
                        </div>
                        <code class="text-xs text-gray-400">/labels/zebra/manage/create</code>
                    </div>
                </div>
            </div>

            {{-- Roles & Auth --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Roles & Authentication</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('roles.test') }}" class="text-sm text-indigo-600 hover:underline font-medium">Roles & Permissions Test</a>
                            <p class="text-xs text-gray-400">Role-based access control testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/roles-test</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.authentication-test') }}" class="text-sm text-indigo-600 hover:underline font-medium">Authentication Test</a>
                            <p class="text-xs text-gray-400">Auth system testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/authentication-test</code>
                    </div>
                </div>
            </div>

            {{-- Products & Suppliers --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Products & Suppliers</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('products.independent-test') }}" class="text-sm text-indigo-600 hover:underline font-medium">Independent Supplier Test</a>
                            <p class="text-xs text-gray-400">Independent delivery integration testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/products/independent-test</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.specific-product-test') }}" class="text-sm text-indigo-600 hover:underline font-medium">Specific Product Test</a>
                            <p class="text-xs text-gray-400">Product lookup debugging</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/specific-product-test</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.customer-price', ['productCode' => '0']) }}" class="text-sm text-indigo-600 hover:underline font-medium">Customer Price Debug</a>
                            <p class="text-xs text-gray-400">Scraper price comparison testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/customer-price/{code}</code>
                    </div>
                </div>
            </div>

            {{-- Language --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Language & Search</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.language-debug') }}" class="text-sm text-indigo-600 hover:underline font-medium">Language Debug</a>
                            <p class="text-xs text-gray-400">Language control testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/language-debug</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.language-flag-test') }}" class="text-sm text-indigo-600 hover:underline font-medium">Language Flag Test</a>
                            <p class="text-xs text-gray-400">Language flag display testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/language-flag-test</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.english-search-test') }}" class="text-sm text-indigo-600 hover:underline font-medium">English Search Test</a>
                            <p class="text-xs text-gray-400">English search functionality testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/english-search-test</code>
                    </div>
                </div>
            </div>

            {{-- Scraper & Integrations --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Scraper & Integrations</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.guzzle') }}" class="text-sm text-indigo-600 hover:underline font-medium">Guzzle Login Test</a>
                            <p class="text-xs text-gray-400">Server-side Udea login + Guzzle HTTP</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/guzzle</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.client') }}" class="text-sm text-indigo-600 hover:underline font-medium">Client Fetch Test</a>
                            <p class="text-xs text-gray-400">Client-side fetch testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/client</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.dashboard') }}" class="text-sm text-indigo-600 hover:underline font-medium">Scraper Dashboard</a>
                            <p class="text-xs text-gray-400">Udea scraper test dashboard</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/dashboard</code>
                    </div>
                </div>
            </div>

            {{-- UI Components --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">UI Components</h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.phase2-components') }}" class="text-sm text-indigo-600 hover:underline font-medium">Phase 2 Components</a>
                            <p class="text-xs text-gray-400">Phase 2 UI component testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/phase2-components</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <a href="{{ route('tests.tab-group') }}" class="text-sm text-indigo-600 hover:underline font-medium">Tab Group</a>
                            <p class="text-xs text-gray-400">Tab group component testing</p>
                        </div>
                        <code class="text-xs text-gray-400">/tests/tab-group</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
