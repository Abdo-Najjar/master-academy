<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="{ sidebarOpen: false, confirmBox: { open: false, message: '', action: null } }">
    <div class="md:hidden sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">{{ __('Student Portal') }}</h1>
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <div class="flex min-h-screen">
        <aside :class="sidebarOpen ? 'translate-x-0' : '{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }} md:translate-x-0'"
               class="fixed md:static top-0 bottom-0 start-0 z-50 w-60 md:w-64 bg-white dark:bg-gray-800 border-{{ app()->getLocale() === 'ar' ? 'l' : 'r' }} border-gray-200 dark:border-gray-700 transition-transform duration-300 ease-in-out shrink-0">
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
                @foreach (['registrations' => __('My Sections'), 'schedule' => __('Schedule'), 'materials' => __('Materials'), 'assignments' => __('Assignments'), 'grades' => __('Grades'), 'transactions' => __('Transactions'), 'certificates' => __('Certificates'), 'complaints' => __('Complaints'), 'profile' => __('Edit Profile')] as $tab => $label)
                    <button wire:click="setActiveTab('{{ $tab }}')" @click="sidebarOpen = false"
                            class="w-full text-start px-4 py-2.5 rounded-lg transition {{ $activeTab === $tab ? 'bg-purple-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <button type="button" @click="confirmBox = { open: true, message: '{{ __('Are you sure you want to logout?') }}', action: () => $wire.logout() }"
                        class="w-full text-start px-4 py-2.5 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                    {{ __('Logout') }}
                </button>
            </div>
        </aside>

        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="md:hidden fixed inset-0 bg-black/50 z-40" style="display: none;"></div>

        <main class="flex-1 min-w-0 p-4 md:p-6">
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

            @if ($activeTab === 'materials')
                <div class="space-y-3">
                    @forelse ($materials as $item)
                        @php
                            $media = $item['media'];
                            $section = $item['section'];
                            $ext = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        @endphp
                        <div wire:key="material-{{ $media->id }}" class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm flex items-center gap-4">
                            <div class="shrink-0 w-11 h-11 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium truncate">{{ $media->name }}</p>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ $section?->getTranslation('name', app()->getLocale(), false) }}
                                    · {{ strtoupper($ext) }} · {{ number_format($media->size / 1024, 0) }} KB
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ $media->getUrl() }}" target="_blank"
                                   class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs whitespace-nowrap">{{ __('Open') }}</a>
                                <a href="{{ $media->getUrl() }}" download="{{ $media->file_name }}"
                                   class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-xs whitespace-nowrap hover:bg-gray-100 dark:hover:bg-gray-700">{{ __('Download') }}</a>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">{{ __('No materials available yet') }}</div>
                    @endforelse
                </div>
            @endif

            @if ($activeTab === 'assignments')
                <div class="space-y-3">
                    @forelse ($assignments as $row)
                        @php
                            $a = $row['assignment'];
                            $sub = $row['submission'];
                            $isPastDue = $a->due_date && $a->due_date->isPast();
                        @endphp
                        <div wire:key="assignment-{{ $a->id }}" class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ $a->title }}</h3>
                                    <p class="text-sm text-gray-500">
                                        {{ $a->section?->getTranslation('name', app()->getLocale(), false) }}
                                        @if ($a->due_date)
                                            · {{ __('Due') }}: {{ $a->due_date->format('Y-m-d H:i') }}
                                            @if ($isPastDue) <span class="text-red-500">({{ __('Past due') }})</span> @endif
                                        @endif
                                    </p>
                                    @if ($a->description)
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 whitespace-pre-line">{{ $a->description }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    @if ($sub?->isGraded())
                                        <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-semibold whitespace-nowrap">
                                            {{ __('Grade') }}: {{ rtrim(rtrim((string) $sub->grade, '0'), '.') }}@if ($a->max_points) / {{ rtrim(rtrim((string) $a->max_points, '0'), '.') }} @endif
                                        </span>
                                    @elseif ($sub)
                                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-semibold whitespace-nowrap">{{ __('Submitted') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold whitespace-nowrap">{{ __('Not submitted') }}</span>
                                    @endif
                                    <a href="{{ route('student.assignments.show', $a) }}" wire:navigate
                                       class="px-3 py-1.5 text-sm rounded-lg bg-purple-600 hover:bg-purple-700 text-white whitespace-nowrap">
                                        {{ $sub ? __('Edit Submission') : __('Submit') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">{{ __('No records found') }}</div>
                    @endforelse
                </div>
            @endif

            @if ($activeTab === 'grades')
                <div class="overflow-x-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <table class="min-w-[640px] w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('Exam') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Section') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Date') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Score') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($grades as $g)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $g->exam?->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $g->exam?->section?->getTranslation('name', app()->getLocale(), false) }}</td>
                                    <td class="px-4 py-3">{{ optional($g->exam?->date)->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-semibold">
                                            {{ rtrim(rtrim((string) $g->score, '0'), '.') }} / {{ rtrim(rtrim((string) $g->exam?->max_score, '0'), '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('No records found') }}</td></tr>
                            @endforelse
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
                                <th class="px-4 py-3 text-start">{{ __('Receipt') }}</th>
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
                                    <td class="px-4 py-3">
                                        @php $rp = $tx->meta['receipt_path'] ?? null; @endphp
                                        @if ($rp)
                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($rp) }}" target="_blank"
                                               class="inline-flex items-center gap-1 px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs hover:bg-blue-200">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                {{ __('View') }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No records found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($activeTab === 'certificates')
                <div class="space-y-3">
                    @forelse ($certificates as $cert)
                        <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ $cert->template?->name ?? __('Certificate') }}</h3>
                                    @if ($cert->section)
                                        <p class="text-sm text-gray-500">{{ $cert->section->getTranslation('name', app()->getLocale(), false) }} · {{ $cert->section->subject?->getTranslation('name', app()->getLocale(), false) }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400 mt-1">{{ __('Serial') }}: {{ $cert->serial_number }} · {{ optional($cert->issued_at)->format('Y-m-d') }}</p>
                                </div>
                                <a href="{{ route('certificates.verify', $cert->verification_token) }}" target="_blank"
                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm whitespace-nowrap">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                    </svg>
                                    {{ __('Verify') }}
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-gray-500">{{ __('No certificates issued yet') }}</div>
                    @endforelse
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
                        <h3 class="text-lg font-semibold mb-4">{{ __('Admin Replies') }}</h3>
                        <div class="space-y-3 max-h-[28rem] overflow-y-auto">
                            @forelse ($complaints->filter(fn ($c) => filled($c->admin_reply)) as $complaint)
                                <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <span class="font-semibold text-sm truncate">{{ $complaint->subject }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap
                                            @class([
                                                'bg-amber-100 text-amber-700' => $complaint->status === 'open',
                                                'bg-blue-100 text-blue-700' => $complaint->status === 'in_progress',
                                                'bg-green-100 text-green-700' => $complaint->status === 'resolved',
                                            ])">{{ $complaint->status_label }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mb-2">{{ $complaint->created_at->diffForHumans() }}</p>
                                    <div class="mt-1 p-2 rounded bg-purple-50 dark:bg-purple-900/20 text-sm">
                                        <span class="font-semibold">{{ __('Admin Reply') }}:</span>
                                        <p class="mt-1">{{ $complaint->admin_reply }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500 text-sm">{{ __('No replies yet') }}</p>
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
                        <form wire:submit="updateProfile" class="space-y-4">
                            @php $avatarUrl = $student->getFirstMediaUrl('main'); @endphp
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <label style="position:relative; display:inline-block; cursor:pointer; flex:0 0 auto;">
                                    <span style="display:flex; align-items:center; justify-content:center; width:96px; height:96px; border-radius:9999px; overflow:hidden; border:2px solid #8b5cf6; background:#f3f4f6;">
                                        @if ($newAvatar)
                                            <img src="{{ $newAvatar->temporaryUrl() }}" style="width:96px; height:96px; object-fit:cover;" alt="">
                                        @elseif ($avatarUrl)
                                            <img src="{{ $avatarUrl }}" style="width:96px; height:96px; object-fit:cover;" alt="">
                                        @else
                                            <svg style="width:48px; height:48px; color:#9ca3af;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                        @endif
                                    </span>
                                    <span style="position:absolute; bottom:0; left:0; display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:9999px; background:#7c3aed; color:#fff; border:2px solid #fff;">
                                        <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </span>
                                    <input type="file" wire:model="newAvatar" accept="image/*" style="display:none;">
                                </label>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Click the photo to choose a new image') }}</p>
                            </div>
                            @error('newAvatar') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <div wire:loading wire:target="newAvatar" class="text-sm text-gray-500">{{ __('Uploading...') }}</div>
                            @if ($newAvatar)
                                <button class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm">{{ __('Upload') }}</button>
                            @endif
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

        @include('livewire.partials.confirm-modal')
    </div>
</div>
