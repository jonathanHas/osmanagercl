<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Role & Permission Test') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Current User Info -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Current User Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p><strong>Name:</strong> {{ $user->name }}</p>
                            <p><strong>Email:</strong> {{ $user->email }}</p>
                            <p><strong>Username:</strong> {{ $user->username ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p><strong>Role:</strong> 
                                @if($user->role)
                                    <span class="px-2 py-1 bg-blue-500 text-white rounded">
                                        {{ $user->role->display_name }}
                                    </span>
                                @else
                                    <span class="px-2 py-1 bg-gray-500 text-white rounded">No Role</span>
                                @endif
                            </p>
                            <p class="mt-2"><strong>Permissions Count:</strong> {{ $user->getPermissions()->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Links -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Test Protected Routes</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="{{ route('roles.admin-only') }}" 
                           class="block p-4 bg-red-100 dark:bg-red-900 rounded hover:bg-red-200 dark:hover:bg-red-800 transition">
                            <h4 class="font-semibold">Admin Only Page</h4>
                            <p class="text-sm">Requires admin role</p>
                        </a>
                        <a href="{{ route('roles.manager-only') }}" 
                           class="block p-4 bg-yellow-100 dark:bg-yellow-900 rounded hover:bg-yellow-200 dark:hover:bg-yellow-800 transition">
                            <h4 class="font-semibold">Manager Only Page</h4>
                            <p class="text-sm">Requires manager role</p>
                        </a>
                        <a href="{{ route('roles.sales-reports') }}" 
                           class="block p-4 bg-green-100 dark:bg-green-900 rounded hover:bg-green-200 dark:hover:bg-green-800 transition">
                            <h4 class="font-semibold">Sales Reports</h4>
                            <p class="text-sm">Requires sales.view_reports permission</p>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Roles Overview -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">System Roles</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Role
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Users Count
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Permissions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($roles as $role)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded 
                                            @if($role->name === 'admin') bg-red-500
                                            @elseif($role->name === 'manager') bg-yellow-500
                                            @else bg-blue-500
                                            @endif text-white">
                                            {{ $role->display_name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        {{ $role->description }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        {{ $role->users_count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        {{ $role->permissions->count() }} permissions
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Your Permissions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Your Permissions</h3>
                    @if($user->role)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($user->getPermissions()->groupBy('module') as $module => $modulePermissions)
                            <div class="border dark:border-gray-700 rounded p-4">
                                <h4 class="font-semibold mb-2">{{ $module }}</h4>
                                <ul class="text-sm space-y-1">
                                    @foreach($modulePermissions as $permission)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $permission->display_name }}
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">You have no assigned role.</p>
                    @endif
                </div>
            </div>

            <!-- All Users -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">System Users</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        User
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Email
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Role
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($users as $userItem)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $userItem->name }}
                                        @if($userItem->id === $user->id)
                                            <span class="text-xs text-gray-500">(You)</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        {{ $userItem->email }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($userItem->role)
                                            <span class="px-2 py-1 text-xs rounded 
                                                @if($userItem->role->name === 'admin') bg-red-500
                                                @elseif($userItem->role->name === 'manager') bg-yellow-500
                                                @else bg-blue-500
                                                @endif text-white">
                                                {{ $userItem->role->display_name }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs bg-gray-500 text-white rounded">No Role</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>