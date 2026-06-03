<div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-gray-900 dark:to-gray-800">
    <div class="max-w-md w-full bg-white dark:bg-gray-800 shadow-xl rounded-2xl p-8 border border-gray-200 dark:border-gray-700">
        <div class="text-center mb-6">
            <img src="{{ \App\Support\AppBranding::logoUrl() }}" alt="" class="mx-auto h-20 mb-3" onerror="this.style.display='none'">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Student Login') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Sign in to your student account') }}</p>
        </div>

        <form wire:submit="login" class="space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Username') }}</label>
                <input wire:model="username" type="text" id="username" autocomplete="username"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Password') }}</label>
                <input wire:model="password" type="password" id="password" autocomplete="current-password"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input wire:model="remember" type="checkbox" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                {{ __('Remember me') }}
            </label>
            <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-medium">
                <span wire:loading.remove wire:target="login">{{ __('Login') }}</span>
                <span wire:loading wire:target="login">{{ __('Signing in...') }}</span>
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('portal') }}" wire:navigate class="text-sm text-gray-600 dark:text-gray-400 hover:text-purple-600">
                ← {{ __('Back to Portal') }}
            </a>
        </div>
    </div>
</div>
