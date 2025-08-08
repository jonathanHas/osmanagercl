<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit User: ') . $user->name }}
            </h2>
            <a href="{{ route('users.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                Back to Users
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @csrf
                        @method('PATCH')

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $user->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Email -->
                        <div class="mb-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $user->email)" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <!-- Username -->
                        <div class="mb-4">
                            <x-input-label for="username" :value="__('Username (Optional)')" />
                            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username', $user->username)" />
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>

                        <!-- Current Role Info -->
                        @if($user->role)
                        <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <h4 class="font-medium text-blue-900 mb-2">Current Role Information</h4>
                            <p class="text-sm text-blue-800">
                                <strong>Role:</strong> {{ $user->role->display_name }}
                            </p>
                            <p class="text-sm text-blue-700 mt-1">
                                {{ $user->role->description }}
                            </p>
                            <p class="text-xs text-blue-600 mt-2">
                                <strong>Permissions:</strong> {{ $user->role->permissions->count() }} permissions
                            </p>
                        </div>
                        @endif

                        <!-- Role Selection -->
                        <div class="mb-4">
                            <x-input-label for="role_id" :value="__('Role')" />
                            @if($user->id === Auth::id() && $user->role && $user->role->name === 'admin')
                                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                    <p class="text-sm text-yellow-800">
                                        <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <strong>Warning:</strong> You cannot change your own admin role for security reasons.
                                    </p>
                                </div>
                                <input type="hidden" name="role_id" value="{{ $user->role_id }}">
                                <select disabled class="block mt-2 w-full border-gray-300 bg-gray-50 rounded-md shadow-sm">
                                    <option selected>{{ $user->role->display_name }} (Admin - Cannot Change)</option>
                                </select>
                            @else
                                <select id="role_id" name="role_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">No Role Assigned</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" 
                                                {{ (old('role_id', $user->role_id) == $role->id) ? 'selected' : '' }}
                                                data-description="{{ $role->description }}">
                                            {{ $role->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p id="role-description" class="mt-1 text-sm text-gray-600">
                                    @if($user->role)
                                        {{ $user->role->description }}
                                    @else
                                        No role currently assigned.
                                    @endif
                                </p>
                            @endif
                            <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                        </div>

                        @if(!($user->id === Auth::id() && $user->role && $user->role->name === 'admin'))
                        <script>
                            document.getElementById('role_id').addEventListener('change', function() {
                                const selectedOption = this.options[this.selectedIndex];
                                const description = selectedOption.getAttribute('data-description') || 'No role selected.';
                                document.getElementById('role-description').textContent = description;
                            });
                        </script>
                        @endif

                        <!-- Password -->
                        <div class="mb-4">
                            <x-input-label for="password" :value="__('New Password (Leave blank to keep current)')" />
                            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-6">
                            <x-input-label for="password_confirmation" :value="__('Confirm New Password')" />
                            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="{{ route('users.index') }}" 
                               class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:border-blue-300 focus:ring focus:ring-blue-200 active:bg-gray-200 disabled:opacity-25 transition ease-in-out duration-150 mr-3">
                                Cancel
                            </a>
                            <x-primary-button>
                                {{ __('Update User') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>