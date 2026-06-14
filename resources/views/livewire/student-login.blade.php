<div class="pa-root" style="--pa-a1:#8b5cf6;--pa-a2:#6d28d9;">
    @include('livewire.partials.portal-auth-style')

    <span class="pa-blob pa-blob--1"></span>
    <span class="pa-blob pa-blob--2"></span>

    <div class="pa-card">
        <img src="{{ \App\Support\AppBranding::logoUrl() }}" alt="" class="pa-logo" onerror="this.style.display='none'">

        <div class="pa-icon">
            <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
            </svg>
        </div>

        <div class="pa-head">
            <h1 class="pa-title">{{ __('Student Login') }}</h1>
            <p class="pa-sub">{{ __('Sign in to your student account') }}</p>
        </div>

        <form wire:submit="login" class="pa-form">
            <div>
                <label for="username" class="pa-label">{{ __('Username') }}</label>
                <input wire:model="username" type="text" id="username" autocomplete="username" class="pa-input">
                @error('username') <p class="pa-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="pa-label">{{ __('Password') }}</label>
                <input wire:model="password" type="password" id="password" autocomplete="current-password" class="pa-input">
                @error('password') <p class="pa-error">{{ $message }}</p> @enderror
            </div>
            <label class="pa-remember">
                <input wire:model="remember" type="checkbox">
                {{ __('Remember me') }}
            </label>
            <button type="submit" class="pa-btn" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">{{ __('Login') }}</span>
                <span wire:loading wire:target="login">{{ __('Signing in...') }}</span>
            </button>
        </form>

        <a href="{{ route('portal') }}" wire:navigate class="pa-back">← {{ __('Back to Portal') }}</a>
    </div>

    <div class="pa-footer">© {{ now()->year }} {{ __('Manba Al-Tamayoz Center') }}</div>
</div>
