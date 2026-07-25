@php
    /**
     * Shared top bar for the student/trainer portals.
     *
     * Expects: $portalTitle, $portalUser, $portalSubtitle, $portalAvatarClass,
     *          $portalFallbackClass, plus $notifications / $unreadNotificationsCount
     *          for the bell. Must live inside the Alpine scope that owns `sidebarOpen`.
     */
    $portalName = $portalUser->getTranslation('name', app()->getLocale(), false) ?: '';
    $portalAvatar = $portalUser->getFirstMediaUrl('main');
@endphp
<header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
    <h1 class="truncate text-lg font-semibold">{{ $portalTitle }}</h1>

    <div class="flex shrink-0 items-center gap-1 sm:gap-3">
        <x-notification-bell
            wrapper-class="relative"
            button-class="h-10 w-10 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
            :notifications="$notifications"
            :unread-count="$unreadNotificationsCount" />

        <div class="flex items-center gap-2">
            @if ($portalAvatar)
                <img src="{{ $portalAvatar }}" alt=""
                     class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 {{ $portalAvatarClass }}">
            @else
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white {{ $portalFallbackClass }}">
                    {{ mb_substr($portalName ?: 'U', 0, 1) }}
                </div>
            @endif

            <div class="hidden min-w-0 sm:block">
                <p class="truncate text-sm font-medium leading-tight">{{ $portalName }}</p>
                <p class="truncate text-xs leading-tight text-gray-500 dark:text-gray-400">{{ $portalSubtitle }}</p>
            </div>
        </div>

        <button type="button" @click="sidebarOpen = !sidebarOpen" aria-label="{{ __('Menu') }}"
                class="rounded-lg p-2 hover:bg-gray-100 md:hidden dark:hover:bg-gray-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>
</header>
