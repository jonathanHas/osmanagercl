<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <x-form-group 
            name="name" 
            label="Name" 
            type="text" 
            :value="old('name')" 
            required 
            autofocus 
            autocomplete="name" 
            containerClass="" />

        <!-- Email Address -->
        <x-form-group 
            name="email" 
            label="Email" 
            type="email" 
            :value="old('email')" 
            required 
            autocomplete="username" />

        <!-- Password -->
        <x-form-group 
            name="password" 
            label="Password" 
            type="password" 
            required 
            autocomplete="new-password" />

        <!-- Confirm Password -->
        <x-form-group 
            name="password_confirmation" 
            label="Confirm Password" 
            type="password" 
            required 
            autocomplete="new-password" />

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
