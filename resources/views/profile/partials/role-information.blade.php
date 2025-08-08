<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Role Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Your current role and access level within the system.') }}
        </p>
    </header>

    <div class="mt-6 space-y-4">
        @if($user->role)
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                        @if($user->role->name === 'admin') bg-red-100 text-red-800
                        @elseif($user->role->name === 'manager') bg-yellow-100 text-yellow-800
                        @elseif($user->role->name === 'employee') bg-blue-100 text-blue-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        @if($user->role->name === 'admin')
                            <svg class="mr-2 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        @elseif($user->role->name === 'manager')
                            <svg class="mr-2 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                            </svg>
                        @elseif($user->role->name === 'employee')
                            <svg class="mr-2 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                            </svg>
                        @endif
                        {{ $user->role->display_name }}
                    </span>
                </div>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-2">Role Description</h3>
                <p class="text-sm text-gray-600">
                    {{ $user->role->description }}
                </p>
            </div>

            <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="font-medium text-blue-900 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    Access Summary
                </h3>
                <div class="text-sm text-blue-800">
                    <p class="mb-2">
                        <strong>Permissions:</strong> {{ $user->getPermissions()->count() }} total permissions
                    </p>
                    @if($user->role->name === 'admin')
                        <p class="text-sm">
                            ✅ <strong>Full System Access</strong> - You have administrative privileges and can access all features.
                        </p>
                    @elseif($user->role->name === 'manager')
                        <p class="text-sm">
                            ✅ <strong>Management Access</strong> - You can access sales data, manage products, and view reports.
                        </p>
                    @elseif($user->role->name === 'employee')
                        <p class="text-sm">
                            ✅ <strong>Operational Access</strong> - You have access to daily operational tasks and basic functions.
                        </p>
                    @endif

                    @if($user->can('users.view'))
                        <p class="mt-2 text-xs">🔑 You can view and manage users</p>
                    @endif
                    @if($user->can('sales.view_reports'))
                        <p class="text-xs">📊 You can view sales reports and analytics</p>
                    @endif
                    @if($user->can('deliveries.manage'))
                        <p class="text-xs">📦 You can create and manage deliveries</p>
                    @endif
                </div>
            </div>

            @if(!$user->isAdmin())
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h3 class="font-medium text-yellow-900 mb-2 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Need Different Access?
                    </h3>
                    <p class="text-sm text-yellow-800">
                        If you need access to additional features or functions, please contact your system administrator to review your role permissions.
                    </p>
                </div>
            @endif

        @else
            <div class="bg-red-50 p-4 rounded-lg">
                <h3 class="font-medium text-red-900 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    No Role Assigned
                </h3>
                <p class="text-sm text-red-800">
                    You currently do not have a role assigned. Please contact your system administrator to assign you an appropriate role for system access.
                </p>
            </div>
        @endif
    </div>
</section>