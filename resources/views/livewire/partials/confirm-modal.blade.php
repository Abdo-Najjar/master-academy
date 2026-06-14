{{-- Styled confirmation modal. Requires `confirmBox: { open, message, action }`
     in the root Alpine x-data. Open it with:
     @click="confirmBox = { open: true, message: '...', action: () => $wire.method() }" --}}
<style>[x-cloak] { display: none !important; }</style>

<div x-show="confirmBox.open" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div x-show="confirmBox.open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="confirmBox.open = false"
         class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

    <div x-show="confirmBox.open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @keydown.escape.window="confirmBox.open = false"
         class="relative w-full max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 p-6 text-center">

        <div class="mx-auto mb-4 w-14 h-14 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
            <svg class="w-7 h-7 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
        </div>

        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('Confirm Action') }}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 leading-relaxed" x-text="confirmBox.message"></p>

        <div class="flex gap-3">
            <button type="button" @click="confirmBox.open = false"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                {{ __('Cancel') }}
            </button>
            <button type="button" @click="confirmBox.action && confirmBox.action(); confirmBox.open = false"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold shadow-lg shadow-red-600/30 transition">
                {{ __('Confirm') }}
            </button>
        </div>
    </div>
</div>
