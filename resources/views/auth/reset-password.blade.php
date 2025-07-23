<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <x-form-group 
            name="email" 
            label="Email" 
            type="email" 
            :value="old('email', $request->email)" 
            required 
            autofocus 
            autocomplete="username" 
            containerClass="" />

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
            <x-primary-button>
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
