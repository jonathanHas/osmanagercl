<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Application Settings') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-alert type="success" :message="session('success')" />
            <x-alert type="error" :message="session('error')" />

            <!-- System Status -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-4">System Status</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Cache Status -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($settings['cache_status'] === 'Working')
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                    @else
                                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">Cache System</p>
                                    <p class="text-sm text-gray-600">{{ $settings['cache_status'] }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Database Status -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($settings['database_status'] === 'Connected')
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                    @else
                                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">Database</p>
                                    <p class="text-sm text-gray-600">{{ $settings['database_status'] }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Storage Status -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    @if($settings['storage_status'] === 'Writable')
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                    @else
                                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                    @endif
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">Storage</p>
                                    <p class="text-sm text-gray-600">{{ $settings['storage_status'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Information -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-4">Application Information</h3>
                    
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Application Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $settings['app_name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Debug Mode</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($settings['app_debug'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Enabled
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Disabled
                                    </span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Cache Management -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-4">Cache Management</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Clear application caches to resolve issues or apply configuration changes.
                    </p>
                    
                    <form method="POST" action="{{ route('settings.clear-cache') }}" class="inline">
                        @csrf
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring focus:ring-red-300 disabled:opacity-25 transition ease-in-out duration-150"
                                onclick="return confirm('Are you sure you want to clear all caches?')">
                            Clear All Caches
                        </button>
                    </form>
                </div>
            </div>

            <!-- System Information Link -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-4">System Information</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        View detailed system information including PHP version, Laravel version, and server details.
                    </p>
                    
                    <a href="{{ route('settings.system-info') }}" 
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                        View System Info
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>