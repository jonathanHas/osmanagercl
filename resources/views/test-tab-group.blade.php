<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Tab Group Component Test
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Basic Tab Example -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Basic Tabs</h3>
                    
                    <x-tab-group :tabs="[
                        ['id' => 'overview', 'label' => 'Overview'],
                        ['id' => 'details', 'label' => 'Details'],
                        ['id' => 'settings', 'label' => 'Settings']
                    ]">
                        <x-slot name="overview">
                            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">Overview Content</h4>
                                <p class="text-blue-800 dark:text-blue-200">This is the overview tab content. It contains general information and summary data.</p>
                                <div class="mt-4 grid grid-cols-3 gap-4">
                                    <div class="bg-white dark:bg-gray-800 p-3 rounded shadow">
                                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">24</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Total Items</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 p-3 rounded shadow">
                                        <div class="text-2xl font-bold text-green-600">18</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Completed</div>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 p-3 rounded shadow">
                                        <div class="text-2xl font-bold text-red-600">6</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Pending</div>
                                    </div>
                                </div>
                            </div>
                        </x-slot>
                        
                        <x-slot name="details">
                            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <h4 class="font-medium text-green-900 dark:text-green-100 mb-2">Detailed Information</h4>
                                <p class="text-green-800 dark:text-green-200 mb-4">This tab contains detailed information with forms and data tables.</p>
                                
                                <div class="bg-white dark:bg-gray-800 p-4 rounded border">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                                <th class="text-left py-2">Property</th>
                                                <th class="text-left py-2">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                                <td class="py-2 text-gray-600 dark:text-gray-400">Created Date</td>
                                                <td class="py-2 text-gray-900 dark:text-gray-100">{{ now()->format('Y-m-d H:i:s') }}</td>
                                            </tr>
                                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                                <td class="py-2 text-gray-600 dark:text-gray-400">Status</td>
                                                <td class="py-2">
                                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Active</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="py-2 text-gray-600 dark:text-gray-400">Last Updated</td>
                                                <td class="py-2 text-gray-900 dark:text-gray-100">{{ now()->subMinutes(15)->format('Y-m-d H:i:s') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </x-slot>
                        
                        <x-slot name="settings">
                            <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                <h4 class="font-medium text-purple-900 dark:text-purple-100 mb-2">Configuration Settings</h4>
                                <p class="text-purple-800 dark:text-purple-200 mb-4">This tab contains configuration options and preferences.</p>
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Enable notifications</label>
                                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" checked>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Auto-save changes</label>
                                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Dark mode</label>
                                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" checked>
                                    </div>
                                </div>
                            </div>
                        </x-slot>
                    </x-tab-group>
                </div>
            </div>

            <!-- Tabs with Badges -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Tabs with Badges</h3>
                    
                    <x-tab-group :tabs="[
                        ['id' => 'orders', 'label' => 'Orders', 'badge' => 12],
                        ['id' => 'issues', 'label' => 'Issues', 'badge' => 3],
                        ['id' => 'completed', 'label' => 'Completed', 'badge' => 28]
                    ]" :activeTab="1">
                        <x-slot name="orders">
                            <div class="p-4">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Orders (12)</h4>
                                <p class="text-gray-600 dark:text-gray-400">Current orders requiring attention.</p>
                                <div class="mt-4 space-y-2">
                                    <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded border-l-4 border-blue-500">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Order #1001</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Pending review</div>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded border-l-4 border-blue-500">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Order #1002</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">In progress</div>
                                    </div>
                                </div>
                            </div>
                        </x-slot>
                        
                        <x-slot name="issues">
                            <div class="p-4">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Issues (3)</h4>
                                <p class="text-gray-600 dark:text-gray-400">Issues that need immediate attention.</p>
                                <div class="mt-4 space-y-2">
                                    <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded border-l-4 border-red-500">
                                        <div class="text-sm font-medium text-red-900 dark:text-red-100">Critical Error</div>
                                        <div class="text-xs text-red-600 dark:text-red-400">System connection failed</div>
                                    </div>
                                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded border-l-4 border-yellow-500">
                                        <div class="text-sm font-medium text-yellow-900 dark:text-yellow-100">Warning</div>
                                        <div class="text-xs text-yellow-600 dark:text-yellow-400">Low inventory levels</div>
                                    </div>
                                </div>
                            </div>
                        </x-slot>
                        
                        <x-slot name="completed">
                            <div class="p-4">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Completed (28)</h4>
                                <p class="text-gray-600 dark:text-gray-400">Successfully completed items.</p>
                                <div class="mt-4 grid grid-cols-2 gap-4">
                                    <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded">
                                        <div class="text-lg font-bold text-green-600">28</div>
                                        <div class="text-sm text-green-800 dark:text-green-200">Total Completed</div>
                                    </div>
                                    <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded">
                                        <div class="text-lg font-bold text-green-600">100%</div>
                                        <div class="text-sm text-green-800 dark:text-green-200">Success Rate</div>
                                    </div>
                                </div>
                            </div>
                        </x-slot>
                    </x-tab-group>
                </div>
            </div>

            <!-- Bottom Position Tabs -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Bottom Position Tabs</h3>
                    
                    <x-tab-group :tabs="[
                        ['id' => 'content', 'label' => 'Content'],
                        ['id' => 'metadata', 'label' => 'Metadata'],
                        ['id' => 'actions', 'label' => 'Actions']
                    ]" position="bottom">
                        <x-slot name="content">
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg min-h-[200px]">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Main Content</h4>
                                <p class="text-gray-600 dark:text-gray-400">This is the primary content area. The tabs appear at the bottom of this content.</p>
                                <div class="mt-4 p-4 bg-white dark:bg-gray-800 rounded border">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                                </div>
                            </div>
                        </x-slot>
                        
                        <x-slot name="metadata">
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg min-h-[200px]">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Metadata Information</h4>
                                <p class="text-gray-600 dark:text-gray-400">Additional information and metadata for this item.</p>
                                <div class="mt-4 space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Created:</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ now()->format('Y-m-d') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Modified:</span>
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ now()->format('Y-m-d') }}</span>
                                    </div>
                                </div>
                            </div>
                        </x-slot>
                        
                        <x-slot name="actions">
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg min-h-[200px]">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Available Actions</h4>
                                <p class="text-gray-600 dark:text-gray-400">Actions you can perform on this item.</p>
                                <div class="mt-4 space-x-2">
                                    <button class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Edit</button>
                                    <button class="px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700">Save</button>
                                    <button class="px-3 py-2 bg-red-600 text-white rounded text-sm hover:bg-red-700">Delete</button>
                                </div>
                            </div>
                        </x-slot>
                    </x-tab-group>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>