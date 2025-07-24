<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('System Information') }}
            </h2>
            <a href="{{ route('settings.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                Back to Settings
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Server Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Server Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">PHP Version</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $info['php_version'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Server Software</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $info['server_software'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Operating System</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ PHP_OS }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Application Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Application Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Laravel Version</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $info['laravel_version'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Database Connection</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $info['database_connection'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Timezone</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $info['timezone'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Locale</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $info['locale'] }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- PHP Extensions -->
                    <div class="mt-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Key PHP Extensions</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            @php
                                $extensions = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'curl', 'fileinfo', 'gd'];
                            @endphp
                            @foreach($extensions as $extension)
                                <div class="flex items-center">
                                    @if(extension_loaded($extension))
                                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                        </svg>
                                    @endif
                                    <span class="text-sm text-gray-900">{{ $extension }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Memory Information -->
                    <div class="mt-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Memory Information</h3>
                        <dl class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Memory Limit</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ ini_get('memory_limit') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Current Usage</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ round(memory_get_usage(true) / 1024 / 1024, 2) }} MB</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Peak Usage</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ round(memory_get_peak_usage(true) / 1024 / 1024, 2) }} MB</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>