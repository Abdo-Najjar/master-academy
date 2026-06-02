<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="{ sidebarOpen: false }">
    <div class="md:hidden sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">{{ __('Student Portal') }}</h1>
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <div class="flex min-h-screen">
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
               class="fixed md:static top-0 bottom-0 z-50 w-60 md:w-64 bg-white dark:bg-gray-800 border-{{ app()->getLocale() === 'ar' ? 'l' : 'r' }} border-gray-200 dark:border-gray-700 transition-transform duration-300 ease-in-out shrink-0">
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
                @foreach (['registrations' => __('My Sections'), 'schedule' => __('Schedule'), 'transactions' => __('Transactions'), 'complaints' => __('Complaints'), 'profile' => __('Edit Profile')] as $tab => $label)
                    <button wire:click="setActiveTab('{{ $tab }}')" @click="sidebarOpen = false"
                            class="w-full text-start px-4 py-2.5 rounded-lg transition {{ $activeTab === $tab ? 'bg-purple-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <button wire:click="logout" wire:confirm="{{ __('Are you sure you want to logout?') }}"
                        class="w-full text-start px-4 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                    {{ __('Logout') }}
                </button>
            </div>
        </aside>

        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="md:hidden fixed inset-0 bg-black/50 z-40" style="display: none;"></div>

        <main class="flex-1 p-4 md:p-6">
            @if (session('message'))
                <div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('message') }}</div>
            @endif

            {{-- Announcements --}}
            @if ($announcements->isNotEmpty())
                <div class="mb-6 space-y-3">
                    @foreach ($announcements as $a)
                        <div wire:key="announcement-{{ $a->id }}"
                             class="relative overflow-hidden rounded-2xl p-5 border border-purple-200 dark:border-purple-800
                                    bg-gradient-to-r from-purple-50 via-white to-purple-50
                                    dark:from-purple-900/30 dark:via-gray-800 dark:to-purple-900/30 shadow-sm">
                            <div class="flex items-start gap-3 pe-10">
                                <div class="shrink-0 w-10 h-10 rounded-full bg-purple-600 text-white flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-semibold text-purple-900 dark:text-purple-100">{{ $a->title }}</h4>
                                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $a->body }}</p>
                                    <p class="mt-2 text-xs text-purple-500/80">{{ optional($a->published_at)->diffForHumans() }}</p>
                                </div>
                            </div>
                            <button type="button" wire:click="dismissAnnouncement({{ $a->id }})"
                                    class="absolute top-3 end-3 p-1.5 rounded-lg text-purple-500 hover:text-purple-700 hover:bg-purple-100 dark:hover:bg-purple-900/40 transition"
                                    title="{{ __('Dismiss') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mb-6 p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Wallet Balance') }}</p>
                <p class="text-3xl font-bold {{ (float) $student->balanceFloat < 0 ? 'text-red-600' : 'text-purple-600' }} mt-1">
                    {{ number_format((float) $student->balanceFloat, 2) }} ₪
                </p>
            </div>

            @if ($activeTab === 'registrations')
                <div class="space-y-3">
                    @forelse ($registrations as $reg)
                        @php $section = $reg->section; @endphp
                        <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ $section?->getTranslation('name', app()->getLocale(), false) }}</h3>
                                    <p class="text-sm text-gray-500">{{ $section?->subject?->getTranslation('name', app()->getLocale(), false) }} · {{ $section?->trainer?->getTranslation('name', app()->getLocale(), false) }}</p>
                                </div>
                                <span class="text-sm px-3 py-1 rounded-full bg-purple-100 text-purple-700">{{ number_format((float) $reg->amount_paid, 2) }} ₪</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">{{ __('No records found') }}</p>
                    @endforelse
                </div>
            @endif

            @if ($activeTab === 'schedule')
                <div class="overflow-x-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <table class="min-w-[640px] w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('Day') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Sections') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($schedule as $day => $items)
                                <tr>
                                    <td class="px-4 py-3 font-medium capitalize">{{ __(ucfirst($day)) }}</td>
                                    <td class="px-4 py-3">
                                        @forelse ($items as $item)
                                            <div class="mb-2 last:mb-0">
                                                <span class="font-medium">{{ $item['start_time'] }} - {{ $item['end_time'] }}</span> ·
                                                {{ $item['section']?->getTranslation('name', app()->getLocale(), false) }}
                                                @if ($item['time']->room)
                                                    <span class="text-xs text-gray-500"> ({{ __('Room') }}: {{ $item['time']->room->number }})</span>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="text-gray-400">—</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($activeTab === 'transactions')
                <div class="overflow-x-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <table class="min-w-[640px] w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('Date') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Amount') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Description') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($transactions as $tx)
                                <tr>
                                    <td class="px-4 py-3">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-xs {{ $tx->type === 'deposit' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ __(ucfirst($tx->type)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ number_format((float) $tx->amountFloat, 2) }} ₪</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $tx->meta['description'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('No records found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($activeTab === 'complaints')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold mb-4">{{ __('Submit a Complaint') }}</h3>
                        <form wire:submit="submitComplaint" class="space-y-3">
                            <input wire:model="complaintSubject" type="text" placeholder="{{ __('Subject') }}"
                                   class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            @error('complaintSubject') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <textarea wire:model="complaintBody" rows="5" placeholder="{{ __('Describe your complaint') }}"
                                      class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700"></textarea>
                            @error('complaintBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <button class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm">{{ __('Send Complaint') }}</button>
                        </form>
                    </div>

                    <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold mb-4">{{ __('My Complaints') }}</h3>
                        <div class="space-y-3 max-h-[28rem] overflow-y-auto">
                            @forelse ($complaints as $complaint)
                                <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <span class="font-semibold text-sm truncate">{{ $complaint->subject }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap
                                            @class([
                                                'bg-amber-100 text-amber-700' => $complaint->status === 'open',
                                                'bg-blue-100 text-blue-700' => $complaint->status === 'in_progress',
                                                'bg-green-100 text-green-700' => $complaint->status === 'resolved',
                                            ])">
                                            {{ $complaint->status_label }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mb-2">{{ $complaint->created_at->diffForHumans() }}</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $complaint->body }}</p>
                                    @if ($complaint->admin_reply)
                                        <div class="mt-2 p-2 rounded bg-gray-50 dark:bg-gray-900/50 text-xs">
                                            <span class="font-semibold">{{ __('Admin Reply') }}:</span>
                                            <p class="mt-1">{{ $complaint->admin_reply }}</p>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-gray-500 text-sm">{{ __('No complaints yet') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            @if ($activeTab === 'profile')
                <div class="mb-6 p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold mb-1">{{ __('Recent Logins') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('Your last :count sign-in events.', ['count' => $loginActivities->count()]) }}</p>
                    @if ($loginActivities->isEmpty())
                        <p class="text-sm text-gray-500">{{ __('No records found') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-[600px] w-full text-sm">
                                <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="py-2 text-start">{{ __('When') }}</th>
                                        <th class="py-2 text-start">{{ __('IP') }}</th>
                                        <th class="py-2 text-start">{{ __('Browser') }}</th>
                                        <th class="py-2 text-start">{{ __('Platform') }}</th>
                                        <th class="py-2 text-start">{{ __('Device') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($loginActivities as $activity)
                                        <tr>
                                            <td class="py-2">{{ $activity->logged_in_at?->format('Y-m-d H:i') }}</td>
                                            <td class="py-2 font-mono text-xs">{{ $activity->ip ?? '—' }}</td>
                                            <td class="py-2">{{ $activity->browser ?? '—' }}</td>
                                            <td class="py-2">{{ $activity->platform ?? '—' }}</td>
                                            <td class="py-2">{{ $activity->device ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold mb-4">{{ __('Profile Picture') }}</h3>
                        <form wire:submit="updateProfile" class="space-y-3">
                            <input type="file" wire:model="newAvatar" accept="image/*" class="block w-full text-sm">
                            @error('newAvatar') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <button class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm">{{ __('Upload') }}</button>
                        </form>
                    </div>
                    <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold mb-4">{{ __('Change Password') }}</h3>
                        <form wire:submit="updatePassword" class="space-y-3">
                            <input wire:model="currentPassword" type="password" placeholder="{{ __('Current Password') }}" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            @error('currentPassword') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <input wire:model="newPassword" type="password" placeholder="{{ __('New Password') }}" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            @error('newPassword') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <input wire:model="newPasswordConfirmation" type="password" placeholder="{{ __('Confirm Password') }}" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            <button class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm">{{ __('Update') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </main>
    </div>
</div>
