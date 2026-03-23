<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Organic Trust Report
            </h2>
            @if(isset($suppliers) && $suppliers->count() > 0)
                <div class="flex space-x-2">
                    <a href="{{ route('suppliers.organic-trust-report.export', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-download mr-2"></i>Export All
                    </a>
                    <a href="{{ route('suppliers.organic-trust-report.export', ['start_date' => $startDate, 'end_date' => $endDate, 'organic_only' => 1]) }}"
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-leaf mr-2"></i>Export Organic Only
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Back Navigation -->
            <div class="mb-4">
                <a href="{{ route('suppliers.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Suppliers
                </a>
            </div>

            <!-- Date Range Form -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Organic Trust Return - Field 13: Bought In Organic Ingredients/Products</h3>
                    <form method="GET" action="{{ route('suppliers.organic-trust-report') }}" class="space-y-4">
                        <div class="flex flex-wrap items-end gap-4">
                            <div class="flex-1 min-w-[140px] max-w-[180px]">
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Start Date
                                </label>
                                <input type="date"
                                       id="start_date"
                                       name="start_date"
                                       value="{{ $startDate }}"
                                       max="{{ now()->format('Y-m-d') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div class="flex-1 min-w-[140px] max-w-[180px]">
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date
                                </label>
                                <input type="date"
                                       id="end_date"
                                       name="end_date"
                                       value="{{ $endDate }}"
                                       max="{{ now()->format('Y-m-d') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <button type="submit"
                                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    Generate Report
                                </button>
                            </div>
                        </div>
                    </form>
                    <p class="mt-2 text-sm text-gray-600">
                        Lists all product suppliers with spend in the selected period. Toggle organic status, then export organic-only for the Organic Trust return.
                    </p>
                </div>
            </div>

            {{-- Options Management --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6" x-data="{
                open: false,
                productTypes: @js($productTypes).sort((a, b) => a.localeCompare(b)),
                certBodies: @js($certBodies).sort((a, b) => a.localeCompare(b)),
                newProductType: '',
                newCertBody: '',
                saving: false,
                saved: false,
                async save() {
                    this.saving = true;
                    await fetch('{{ route('suppliers.organic-trust-report.options') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({
                            organic_product_types: this.productTypes,
                            organic_certification_bodies: this.certBodies
                        })
                    });
                    this.saving = false;
                    this.saved = true;
                    setTimeout(() => this.saved = false, 2000);
                },
                addProductType() {
                    if (this.newProductType.trim() && !this.productTypes.includes(this.newProductType.trim())) {
                        this.productTypes.push(this.newProductType.trim());
                        this.productTypes.sort((a, b) => a.localeCompare(b));
                        this.newProductType = '';
                        this.save();
                    }
                },
                addCertBody() {
                    if (this.newCertBody.trim() && !this.certBodies.includes(this.newCertBody.trim())) {
                        this.certBodies.push(this.newCertBody.trim());
                        this.certBodies.sort((a, b) => a.localeCompare(b));
                        this.newCertBody = '';
                        this.save();
                    }
                },
                removeProductType(index) {
                    this.productTypes.splice(index, 1);
                    this.save();
                },
                removeCertBody(index) {
                    this.certBodies.splice(index, 1);
                    this.save();
                }
            }">
                <div class="p-4">
                    <button @click="open = !open" class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                        <i class="fas fa-cog mr-2"></i>
                        Manage Dropdown Options
                        <i class="fas fa-chevron-down ml-2 transition-transform duration-200" :class="open && 'rotate-180'"></i>
                    </button>

                    <div x-show="open" x-transition class="mt-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Product Types --}}
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">Product Types</h4>
                                <div class="space-y-1 mb-2">
                                    <template x-for="(type, index) in productTypes" :key="index">
                                        <div class="flex items-center justify-between bg-gray-50 rounded px-3 py-1.5 text-sm group">
                                            <span x-text="type"></span>
                                            <button type="button" @click="removeProductType(index)" class="text-red-500 hover:text-red-700 hover:bg-red-100 rounded px-2 py-0.5 font-bold text-xs">&times;</button>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex gap-2">
                                    <input type="text" x-model="newProductType" @keydown.enter="addProductType()"
                                        placeholder="Add product type..."
                                        class="text-sm border-gray-300 rounded-md py-1.5 px-3 flex-1">
                                    <button @click="addProductType()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-sm">
                                        Add
                                    </button>
                                </div>
                            </div>

                            {{-- Certification Bodies --}}
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">Certification Bodies</h4>
                                <div class="space-y-1 mb-2">
                                    <template x-for="(body, index) in certBodies" :key="index">
                                        <div class="flex items-center justify-between bg-gray-50 rounded px-3 py-1.5 text-sm group">
                                            <span x-text="body"></span>
                                            <button type="button" @click="removeCertBody(index)" class="text-red-500 hover:text-red-700 hover:bg-red-100 rounded px-2 py-0.5 font-bold text-xs">&times;</button>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex gap-2">
                                    <input type="text" x-model="newCertBody" @keydown.enter="addCertBody()"
                                        placeholder="Add certification body..."
                                        class="text-sm border-gray-300 rounded-md py-1.5 px-3 flex-1">
                                    <button @click="addCertBody()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-sm">
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 text-sm">
                            <span x-show="saving" class="text-gray-500"><i class="fas fa-spinner fa-spin mr-1"></i>Saving...</span>
                            <span x-show="saved" x-transition class="text-green-600"><i class="fas fa-check mr-1"></i>Saved</span>
                        </div>
                    </div>
                </div>
            </div>

            @if(isset($suppliers))
                <!-- Summary Stats -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Report: {{ Carbon\Carbon::parse($startDate)->format('F j, Y') }} to {{ Carbon\Carbon::parse($endDate)->format('F j, Y') }}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">{{ $supplierCount }}</div>
                                <div class="text-sm text-gray-600">Total Suppliers</div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-gray-700">&euro;{{ number_format($totalSpend, 2) }}</div>
                                <div class="text-sm text-gray-600">Total Spend</div>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-purple-600">&euro;{{ number_format($totalSales, 2) }}</div>
                                <div class="text-sm text-gray-600">Total Sales</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">{{ $organicCount }}</div>
                                <div class="text-sm text-gray-600">Organic Suppliers</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">&euro;{{ number_format($organicSpend, 2) }}</div>
                                <div class="text-sm text-gray-600">Organic Spend</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">&euro;{{ number_format($organicSales, 2) }}</div>
                                <div class="text-sm text-gray-600">Organic Sales</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($suppliers->count() > 0)
                    <!-- Supplier Table -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Organic
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Supplier
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Product Type
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Certification Body
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Invoices
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Total Amount (ex. VAT)
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Sales Revenue
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($suppliers as $supplier)
                                            <tr class="hover:bg-gray-50" x-data="{
                                                organic: {{ $supplier->is_organic ? 'true' : 'false' }},
                                                productType: '{{ $supplier->organic_product_type ?? '' }}',
                                                certBody: '{{ $supplier->organic_certification_body ?? '' }}',
                                                customProductType: false,
                                                customCertBody: false,
                                                customProductTypeValue: '',
                                                customCertBodyValue: '',
                                                saveField(field, value) {
                                                    fetch('{{ route('suppliers.update-organic-fields', $supplier) }}', {
                                                        method: 'POST',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                        },
                                                        body: JSON.stringify({ [field]: value })
                                                    })
                                                }
                                            }">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <button @click="
                                                        fetch('{{ route('suppliers.toggle-organic', $supplier) }}', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                            }
                                                        })
                                                        .then(r => r.json())
                                                        .then(d => { organic = d.is_organic })
                                                    "
                                                    :class="organic ? 'bg-green-500' : 'bg-gray-300'"
                                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                                        <span :class="organic ? 'translate-x-5' : 'translate-x-0'"
                                                              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                                    </button>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $supplier->name }}
                                                </td>
                                                {{-- Product Type --}}
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                                    <template x-if="organic">
                                                        <div>
                                                            <template x-if="!customProductType">
                                                                <select x-model="productType" @change="
                                                                    if (productType === '__custom__') { customProductType = true; productType = ''; return; }
                                                                    saveField('organic_product_type', productType)
                                                                " class="text-sm border-gray-300 rounded-md py-1 px-2 w-44">
                                                                    <option value="">--</option>
                                                                    @foreach($productTypes as $type)
                                                                        <option value="{{ $type }}">{{ $type }}</option>
                                                                    @endforeach
                                                                    <option value="__custom__">Other...</option>
                                                                </select>
                                                            </template>
                                                            <template x-if="customProductType">
                                                                <input type="text" x-model="customProductTypeValue"
                                                                    @keydown.enter="productType = customProductTypeValue; customProductType = false; saveField('organic_product_type', customProductTypeValue)"
                                                                    @keydown.escape="customProductType = false; productType = '{{ $supplier->organic_product_type ?? '' }}'"
                                                                    placeholder="Type & press Enter"
                                                                    class="text-sm border-gray-300 rounded-md py-1 px-2 w-44" x-ref="customPt" x-init="$nextTick(() => $refs.customPt?.focus())" />
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="!organic">
                                                        <span class="text-gray-400">--</span>
                                                    </template>
                                                </td>
                                                {{-- Certification Body --}}
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                                    <template x-if="organic">
                                                        <div>
                                                            <template x-if="!customCertBody">
                                                                <select x-model="certBody" @change="
                                                                    if (certBody === '__custom__') { customCertBody = true; certBody = ''; return; }
                                                                    saveField('organic_certification_body', certBody)
                                                                " class="text-sm border-gray-300 rounded-md py-1 px-2 w-44">
                                                                    <option value="">--</option>
                                                                    @foreach($certBodies as $body)
                                                                        <option value="{{ $body }}">{{ $body }}</option>
                                                                    @endforeach
                                                                    <option value="__custom__">Other...</option>
                                                                </select>
                                                            </template>
                                                            <template x-if="customCertBody">
                                                                <input type="text" x-model="customCertBodyValue"
                                                                    @keydown.enter="certBody = customCertBodyValue; customCertBody = false; saveField('organic_certification_body', customCertBodyValue)"
                                                                    @keydown.escape="customCertBody = false; certBody = '{{ $supplier->organic_certification_body ?? '' }}'"
                                                                    placeholder="Type & press Enter"
                                                                    class="text-sm border-gray-300 rounded-md py-1 px-2 w-44" x-ref="customCb" x-init="$nextTick(() => $refs.customCb?.focus())" />
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="!organic">
                                                        <span class="text-gray-400">--</span>
                                                    </template>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-right">
                                                    {{ $supplier->period_invoice_count }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                                    &euro;{{ number_format($supplier->period_total, 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right {{ $supplier->period_sales > 0 ? 'text-purple-700' : 'text-gray-400' }}">
                                                    &euro;{{ number_format($supplier->period_sales, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Grand Total -->
                    <div class="bg-gray-900 text-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-medium">Totals (All Suppliers)</h3>
                                    <p class="text-sm text-gray-300">
                                        {{ $supplierCount }} suppliers | {{ $organicCount }} marked organic (&euro;{{ number_format($organicSpend, 2) }} spend, &euro;{{ number_format($organicSales, 2) }} sales)
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold">&euro;{{ number_format($totalSpend, 2) }} <span class="text-lg text-gray-400">spend</span></div>
                                    <div class="text-xl font-bold text-purple-300">&euro;{{ number_format($totalSales, 2) }} <span class="text-sm text-gray-400">sales</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-search text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium mb-2">No Suppliers Found</h3>
                                <p>No product suppliers had invoices between {{ Carbon\Carbon::parse($startDate)->format('F j, Y') }} and {{ Carbon\Carbon::parse($endDate)->format('F j, Y') }}.</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>
