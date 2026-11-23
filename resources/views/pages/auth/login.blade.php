<x-layouts::auth :title="__('Sign in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Sign in')" :description="__('For the Founder / Chairman only.')" />

        <!-- Session Status -->
        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
            />

            <div class="flex flex-col gap-2">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="inline-flex min-h-11 items-center self-start text-sm font-semibold text-blue hover:text-navy focus-visible:outline-2 focus-visible:outline-blue">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <flux:checkbox name="remember" :label="__('Keep me signed in on this device')" :checked="old('remember')" />

            <button type="submit" class="min-h-12 w-full rounded-[10px] bg-blue px-4 text-[15px] font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue" data-test="login-button">
                {{ __('Sign in') }}
            </button>
        </form>
    </div>
</x-layouts::auth>
