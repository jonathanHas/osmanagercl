<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-form-group 
                name="current_password" 
                label="Current Password" 
                type="password" 
                id="update_password_current_password"
                autocomplete="current-password" 
                containerClass="" />
            <!-- Manual error display for custom error bag -->
            @if($errors->updatePassword->has('current_password'))
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->updatePassword->first('current_password') }}</p>
            @endif
        </div>

        <div>
            <x-form-group 
                name="password" 
                label="New Password" 
                type="password" 
                id="update_password_password"
                autocomplete="new-password" 
                containerClass="" />
            <!-- Manual error display for custom error bag -->
            @if($errors->updatePassword->has('password'))
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->updatePassword->first('password') }}</p>
            @endif
        </div>

        <div>
            <x-form-group 
                name="password_confirmation" 
                label="Confirm Password" 
                type="password" 
                id="update_password_password_confirmation"
                autocomplete="new-password" 
                containerClass="" />
            <!-- Manual error display for custom error bag -->
            @if($errors->updatePassword->has('password_confirmation'))
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $errors->updatePassword->first('password_confirmation') }}</p>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
