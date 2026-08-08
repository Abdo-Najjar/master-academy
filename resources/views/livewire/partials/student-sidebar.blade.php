@php
    $studentSidebarTabs = ['registrations' => __('My Sections'), 'schedule' => __('Schedule'), 'attendance' => __('Attendance'), 'materials' => __('Materials'), 'assignments' => __('Assignments'), 'grades' => __('Grades'), 'transactions' => __('Transactions'), 'certificates' => __('Certificates'), 'complaints' => __('Complaints'), 'profile' => __('Edit Profile')];
    $studentSidebarCurrentTab = $activeTab ?? null;
    $studentSidebarUseWireClick = $useWireClick ?? false;
@endphp
{{-- The closed (off-canvas) transform is static and mobile-scoped (max-md:) so it applies
     on the very first paint — binding it through :class alone makes the sidebar flash open
     until Alpine boots. Scoping to max-md: leaves the desktop sidebar untransformed, so
     there is no cascade fight; the open state uses `!` to beat the static transform. --}}
<aside :class="sidebarOpen ? 'translate-x-0!' : ''"
       class="fixed md:static top-0 bottom-0 start-0 z-50 w-60 md:w-64 bg-white dark:bg-gray-800 border-{{ app()->getLocale() === 'ar' ? 'l' : 'r' }} border-gray-200 dark:border-gray-700 {{ app()->getLocale() === 'ar' ? 'max-md:translate-x-full' : 'max-md:-translate-x-full' }} transition-transform duration-300 ease-in-out shrink-0">
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
            @php $avatar = $student->getFirstMediaUrl('main'); @endphp
            @if ($avatar)
                <img src="{{ $avatar }}" class="w-12 h-12 rounded-full object-cover ring-2 ring-purple-500" alt="">
            @else
                <div class="w-12 h-12 rounded-full bg-purple-500 text-white flex items-center justify-center font-bold">
                    {{ mb_substr($student->getTranslation('name', app()->getLocale(), false) ?? 'U', 0, 1) }}
                </div>
            @endif
            <div class="min-w-0">
                <p class="font-semibold truncate">{{ $student->getTranslation('name', app()->getLocale(), false) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->student_number }}</p>
            </div>
        </div>
    </div>
    <nav class="p-4 space-y-1">
        @foreach ($studentSidebarTabs as $tab => $label)
            @if ($studentSidebarUseWireClick)
                <button wire:click="setActiveTab('{{ $tab }}')" @click="sidebarOpen = false"
                        class="w-full text-start px-4 py-2.5 rounded-lg transition {{ $studentSidebarCurrentTab === $tab ? 'bg-purple-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    {{ $label }}
                </button>
            @else
                <a href="{{ route('student.dashboard') }}?tab={{ $tab }}" wire:navigate @click="sidebarOpen = false"
                   class="block w-full text-start px-4 py-2.5 rounded-lg transition hover:bg-gray-100 dark:hover:bg-gray-700">
                    {{ $label }}
                </a>
            @endif
        @endforeach
        <a href="{{ route('student.login-activities') }}" wire:navigate @click="sidebarOpen = false"
           class="block w-full text-start px-4 py-2.5 rounded-lg transition {{ $studentSidebarCurrentTab === 'login-activities' ? 'bg-purple-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
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
