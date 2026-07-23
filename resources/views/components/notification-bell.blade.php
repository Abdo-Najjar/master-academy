@props(['notifications', 'unreadCount'])

<div x-data="{ open: false }" @click.outside="open = false" class="fixed top-4 end-4 z-50">
    <button
        type="button"
        @click="open = !open"
        aria-label="{{ __('Notifications') }}"
        class="relative flex h-11 w-11 items-center justify-center rounded-full bg-white text-gray-700 shadow-lg ring-1 ring-gray-200 transition hover:scale-105 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700"
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -top-1 -end-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-transition
        style="display: none;"
        class="absolute top-14 end-0 w-80 max-w-[90vw] max-h-96 overflow-y-auto rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl"
    >
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h4 class="font-semibold text-sm">{{ __('Notifications') }}</h4>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllNotificationsRead" class="text-xs text-purple-600 hover:underline">
                    {{ __('Mark all as read') }}
                </button>
            @endif
        </div>

        @forelse ($notifications as $n)
            <button
                type="button"
                wire:click="markNotificationRead('{{ $n->id }}')"
                wire:key="notification-{{ $n->id }}"
                class="w-full text-start px-4 py-3 border-b border-gray-100 dark:border-gray-700/60 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition {{ $n->read_at ? 'opacity-60' : '' }}"
            >
                <div class="flex items-start gap-2">
                    @if (! $n->read_at)
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-purple-600"></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium truncate">{{ $n->data['title'] ?? __('Notification') }}</p>
                        @if (! empty($n->data['reply']))
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $n->data['reply'] }}</p>
                        @endif
                        <p class="mt-1 text-[11px] text-gray-400">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            </button>
        @empty
            <p class="px-4 py-6 text-center text-sm text-gray-400">{{ __('No notifications yet') }}</p>
        @endforelse
    </div>
</div>
