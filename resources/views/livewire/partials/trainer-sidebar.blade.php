@php
    $trainerSidebarTabs = ['sections' => __('My Sections'), 'attendance' => __('Attendance'), 'assignments' => __('Assignments'), 'transactions' => __('Transactions'), 'complaints' => __('Complaints'), 'profile' => __('Edit Profile')];
    $trainerSidebarCurrentTab = $activeTab ?? null;
    $trainerSidebarUseWireClick = $useWireClick ?? false;
@endphp
<aside :class="sidebarOpen ? 'translate-x-0' : '{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }} md:translate-x-0'"
       class="fixed md:static top-0 bottom-0 start-0 z-50 w-60 md:w-64 bg-white dark:bg-gray-800 border-{{ app()->getLocale() === 'ar' ? 'l' : 'r' }} border-gray-200 dark:border-gray-700 transition-transform duration-300 ease-in-out shrink-0">
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
            @php $avatar = $trainer->getFirstMediaUrl('main'); @endphp
            @if ($avatar)
                <img src="{{ $avatar }}" class="w-12 h-12 rounded-full object-cover ring-2 ring-emerald-500" alt="">
            @else
                <div class="w-12 h-12 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold">
                    {{ mb_substr($trainer->getTranslation('name', app()->getLocale(), false) ?? 'T', 0, 1) }}
                </div>
            @endif
            <div class="min-w-0">
                <p class="font-semibold truncate">{{ $trainer->getTranslation('name', app()->getLocale(), false) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $trainer->trainer_number }}</p>
            </div>
        </div>
    </div>
    <nav class="p-4 space-y-1">
        @foreach ($trainerSidebarTabs as $tab => $label)
            @if ($trainerSidebarUseWireClick)
                <button wire:click="setActiveTab('{{ $tab }}')" @click="sidebarOpen = false"
                        class="w-full text-start px-4 py-2.5 rounded-lg transition {{ $trainerSidebarCurrentTab === $tab ? 'bg-emerald-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    {{ $label }}
                </button>
            @else
                <a href="{{ route('trainer.dashboard') }}?tab={{ $tab }}" wire:navigate @click="sidebarOpen = false"
                   class="block w-full text-start px-4 py-2.5 rounded-lg transition hover:bg-gray-100 dark:hover:bg-gray-700">
                    {{ $label }}
                </a>
            @endif
        @endforeach
        <a href="{{ route('trainer.login-activities') }}" wire:navigate @click="sidebarOpen = false"
           class="block w-full text-start px-4 py-2.5 rounded-lg transition {{ $trainerSidebarCurrentTab === 'login-activities' ? 'bg-emerald-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            {{ __('Login History') }}
        </a>
    </nav>
    <div class="p-4 border-t border-gray-200 dark:border-gray-700">
        <button type="button" @click="confirmBox = { open: true, message: '{{ __('Are you sure you want to logout?') }}', action: () => $wire.logout() }"
                class="w-full text-start px-4 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
            {{ __('Logout') }}
        </button>
    </div>
</aside>
