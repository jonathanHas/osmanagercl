<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sales Reports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="text-center">
                        <svg class="mx-auto h-24 w-24 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <h3 class="mt-4 text-2xl font-semibold">Access Granted!</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            You have successfully accessed the sales reports page.
                        </p>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-500">
                            This page requires the <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">sales.view_reports</code> permission.
                        </p>
                        <a href="{{ route('roles.test') }}" class="mt-6 inline-block px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                            Back to Test Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>