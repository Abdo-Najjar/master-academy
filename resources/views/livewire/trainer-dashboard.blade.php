<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="{ sidebarOpen: false, confirmBox: { open: false, message: '', action: null } }">
    <x-notification-bell :notifications="$notifications" :unread-count="$unreadNotificationsCount" />

    <div class="md:hidden sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">{{ __('Trainer Portal') }}</h1>
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <div class="flex min-h-screen">
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
                @foreach (['sections' => __('My Sections'), 'attendance' => __('Attendance'), 'assignments' => __('Assignments'), 'transactions' => __('Transactions'), 'complaints' => __('Complaints'), 'profile' => __('Edit Profile')] as $tab => $label)
                    <button wire:click="setActiveTab('{{ $tab }}')" @click="sidebarOpen = false"
                            class="w-full text-start px-4 py-2.5 rounded-lg transition {{ $activeTab === $tab ? 'bg-emerald-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        {{ $label }}
                    </button>
                @endforeach
                <a href="{{ route('trainer.login-activities') }}" wire:navigate @click="sidebarOpen = false"
                   class="block w-full text-start px-4 py-2.5 rounded-lg transition hover:bg-gray-100 dark:hover:bg-gray-700">
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

        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="md:hidden fixed inset-0 bg-black/50 z-40" style="display: none;"></div>

        <main class="flex-1 min-w-0 p-4 md:p-6">
            @if (session('message'))
                <div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('message') }}</div>
            @endif

            <div class="mb-6 p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Wallet Balance') }}</p>
                <p class="text-3xl font-bold text-emerald-600 mt-1">
                    {{ number_format((float) $trainer->balanceFloat, 2) }} ₪
                </p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Default Rate') }}: {{ $trainer->default_rate }}%</p>
            </div>

            @if ($activeTab === 'sections')
                <div class="space-y-3">
                    @forelse ($sections as $section)
                        <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ $section->getTranslation('name', app()->getLocale(), false) }}</h3>
                                    <p class="text-sm text-gray-500">{{ $section->subject?->getTranslation('name', app()->getLocale(), false) }} · {{ $section->registrations->count() }} {{ __('students') }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button wire:click="openAttendance({{ $section->id }})"
                                            class="px-3 py-1.5 text-sm rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                                        {{ __('Attendance') }}
                                    </button>
                                    <button wire:click="openMaterials({{ $section->id }})"
                                            class="px-3 py-1.5 text-sm rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200">
                                        {{ __('Materials') }}
                                    </button>
                                </div>
                            </div>
                            @if ($section->times->isNotEmpty())
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                    @foreach ($section->times as $time)
                                        <div>{{ __(ucfirst($time->day)) }}: {{ \Carbon\Carbon::parse($time->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($time->end_time)->format('H:i') }}@if ($time->room) · {{ __('Room') }}: {{ $time->room->number }}@endif</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-gray-500">{{ __('No sections') }}</p>
                    @endforelse
                </div>
            @endif

            @if ($activeTab === 'attendance')
                <style>
                    .ta-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem;}
                    .ta-btn{padding:.5rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:600;color:#fff;border:none;cursor:pointer;}
                    .ta-btn--green{background:#16a34a;} .ta-btn--red{background:#dc2626;}
                    .ta-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.75rem;margin:1rem 0;}
                    .ta-stat{padding:1rem;border-radius:.75rem;border:1px solid;}
                    .ta-stat__label{font-size:.75rem;margin:0;} .ta-stat__value{font-size:1.5rem;font-weight:700;margin:.25rem 0 0;}
                    .ta-stat--gray{background:rgba(148,163,184,.10);border-color:rgba(148,163,184,.25);color:#64748b;}
                    .ta-stat--green{background:rgba(34,197,94,.10);border-color:rgba(34,197,94,.30);color:#166534;}
                    .ta-stat--red{background:rgba(239,68,68,.10);border-color:rgba(239,68,68,.30);color:#991b1b;}
                    .ta-stat--amber{background:rgba(245,158,11,.10);border-color:rgba(245,158,11,.30);color:#92400e;}
                    .ta-stat--blue{background:rgba(59,130,246,.10);border-color:rgba(59,130,246,.30);color:#1e40af;}
                    .ta-row{padding:.75rem 0;border-top:1px solid rgba(148,163,184,.15);display:flex;flex-wrap:wrap;align-items:center;gap:1rem;}
                    .ta-row__info{display:flex;align-items:center;gap:.75rem;flex:1;min-width:200px;}
                    .ta-avatar{width:40px;height:40px;border-radius:9999px;object-fit:cover;}
                    .ta-avatar--initials{display:flex;align-items:center;justify-content:center;background:#059669;color:#fff;font-weight:700;}
                    .ta-row__name{font-weight:600;margin:0;} .ta-row__id{font-size:.75rem;color:#64748b;margin:0;}
                    .ta-toggles{display:flex;flex-wrap:wrap;gap:.375rem;}
                    .ta-toggle{padding:.375rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:600;border:none;cursor:pointer;}
                    .ta-toggle--green{background:rgba(34,197,94,.15);color:#166534;} .ta-toggle--green.is-active{background:#16a34a;color:#fff;}
                    .ta-toggle--red{background:rgba(239,68,68,.15);color:#991b1b;} .ta-toggle--red.is-active{background:#dc2626;color:#fff;}
                    .ta-toggle--amber{background:rgba(245,158,11,.15);color:#92400e;} .ta-toggle--amber.is-active{background:#d97706;color:#fff;}
                    .ta-toggle--blue{background:rgba(59,130,246,.15);color:#1e40af;} .ta-toggle--blue.is-active{background:#2563eb;color:#fff;}
                    .ta-note{width:180px;padding:.375rem .75rem;border-radius:.5rem;border:1px solid rgba(148,163,184,.40);background:transparent;font-size:.75rem;}
                </style>
                <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Mark Attendance') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <select wire:model.live="attendanceSectionId" wire:change="loadAttendance" class="px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            <option value="">{{ __('Select section') }}</option>
                            @foreach ($sections as $s)
                                <option value="{{ $s->id }}">{{ $s->getTranslation('name', app()->getLocale(), false) }}</option>
                            @endforeach
                        </select>
                        <input wire:model.live="attendanceDate" wire:change="loadAttendance" type="date" class="px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                    </div>

                    @if ($attendanceSection)
                        <div class="ta-actions">
                            <button type="button" wire:click="markAll('present')" class="ta-btn ta-btn--green">{{ __('Mark All Present') }}</button>
                            <button type="button" wire:click="markAll('absent')" class="ta-btn ta-btn--red">{{ __('Mark All Absent') }}</button>
                        </div>

                        <div class="ta-stats">
                            <div class="ta-stat ta-stat--gray"><p class="ta-stat__label">{{ __('Total Students') }}</p><p class="ta-stat__value">{{ count($attendanceStatuses) }}</p></div>
                            <div class="ta-stat ta-stat--green"><p class="ta-stat__label">{{ __('Present') }}</p><p class="ta-stat__value">{{ $this->attendanceCounts['present'] }}</p></div>
                            <div class="ta-stat ta-stat--red"><p class="ta-stat__label">{{ __('Absent') }}</p><p class="ta-stat__value">{{ $this->attendanceCounts['absent'] }}</p></div>
                            <div class="ta-stat ta-stat--amber"><p class="ta-stat__label">{{ __('Late') }}</p><p class="ta-stat__value">{{ $this->attendanceCounts['late'] }}</p></div>
                            <div class="ta-stat ta-stat--blue"><p class="ta-stat__label">{{ __('Attendance Rate') }}</p><p class="ta-stat__value">{{ $this->attendanceRate }}%</p></div>
                        </div>

                        @if ($attendanceSection->registrations->isNotEmpty())
                            <p class="text-sm text-gray-500 mb-1">{{ \Carbon\Carbon::parse($attendanceDate)->translatedFormat('l, d M Y') }}</p>
                            <div>
                                @foreach ($attendanceSection->registrations as $reg)
                                    @php
                                        $student = $reg->student;
                                        $sid = $student?->id;
                                        $current = $attendanceStatuses[$sid] ?? 'present';
                                        $avatar = $student?->getFirstMediaUrl('main');
                                    @endphp
                                    @if ($student)
                                        <div class="ta-row">
                                            <div class="ta-row__info">
                                                @if ($avatar)
                                                    <img src="{{ $avatar }}" class="ta-avatar" alt="">
                                                @else
                                                    <div class="ta-avatar ta-avatar--initials">{{ mb_substr($student->getTranslation('name', app()->getLocale(), false) ?? 'S', 0, 1) }}</div>
                                                @endif
                                                <div>
                                                    <p class="ta-row__name">{{ $student->getTranslation('name', app()->getLocale(), false) }}</p>
                                                    <p class="ta-row__id">{{ $student->student_number }}</p>
                                                </div>
                                            </div>
                                            <div class="ta-toggles">
                                                @foreach (['present' => [__('Present'),'green'], 'absent' => [__('Absent'),'red'], 'late' => [__('Late'),'amber'], 'excused' => [__('Excused'),'blue']] as $key => [$label, $color])
                                                    <button type="button" wire:click="setStatus({{ $sid }}, '{{ $key }}')"
                                                            class="ta-toggle ta-toggle--{{ $color }} @if ($current === $key) is-active @endif">{{ $label }}</button>
                                                @endforeach
                                            </div>
                                            <input type="text" wire:model="attendanceNotes.{{ $sid }}" placeholder="{{ __('Optional note') }}" class="ta-note">
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <button wire:click="saveAttendance" class="mt-4 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">{{ __('Save Attendance') }}</button>
                        @else
                            <p class="text-gray-500 text-sm">{{ __('No students registered in this section yet.') }}</p>
                        @endif
                    @endif
                </div>
            @endif

            @if ($activeTab === 'materials')
                <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Section Materials') }}</h3>
                    <select wire:model.live="materialsSectionId" class="px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700 mb-4">
                        <option value="">{{ __('Select section') }}</option>
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}">{{ $s->getTranslation('name', app()->getLocale(), false) }}</option>
                        @endforeach
                    </select>

                    @if ($materialsSection)
                        <form wire:submit="uploadMaterials" class="mb-4 space-y-2">
                            <label class="flex flex-col items-center justify-center gap-2 w-full px-4 py-8 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-emerald-500 dark:hover:border-emerald-500 cursor-pointer text-center transition">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3-3m0 0l3 3m-3-3v9"/></svg>
                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Click to choose files') }}</span>
                                <input type="file" wire:model="newMaterials" multiple class="hidden">
                            </label>

                            <div wire:loading wire:target="newMaterials" class="text-sm text-gray-500">{{ __('Uploading...') }}</div>

                            @if ($newMaterials)
                                <ul class="space-y-1">
                                    @foreach ($newMaterials as $file)
                                        <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            {{ $file->getClientOriginalName() }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @error('newMaterials') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('newMaterials.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            @if ($newMaterials)
                                <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm">{{ __('Upload') }}</button>
                            @endif
                        </form>
                        <div class="space-y-2">
                            @forelse ($materialsSection->getMedia('materials') as $media)
                                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700">
                                    <a href="{{ $media->getFullUrl() }}" target="_blank" class="text-emerald-600 hover:underline">{{ $media->file_name }}</a>
                                    <button type="button" @click="confirmBox = { open: true, message: '{{ __('Delete this file?') }}', action: () => $wire.removeMaterial({{ $media->id }}) }" class="text-sm text-red-600 hover:underline">{{ __('Delete') }}</button>
                                </div>
                            @empty
                                <p class="text-gray-500">{{ __('No materials uploaded yet') }}</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif

            @if ($activeTab === 'assignments')
                <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 mb-4">
                    <h3 class="text-lg font-semibold mb-4">{{ __('New Assignment') }}</h3>
                    <form wire:submit="createAssignment" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('Section') }}</label>
                            <select wire:model="newAssignmentSectionId" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                                <option value="">{{ __('Select section') }}</option>
                                @foreach ($sections as $s)
                                    <option value="{{ $s->id }}">{{ $s->getTranslation('name', app()->getLocale(), false) }}</option>
                                @endforeach
                            </select>
                            @error('newAssignmentSectionId') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('Title') }}</label>
                            <input wire:model="newAssignmentTitle" type="text" placeholder="{{ __('Title') }}"
                                   class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            @error('newAssignmentTitle') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('Due Date') }}</label>
                            <x-date-input time="true" wire:model="newAssignmentDueDate"
                                   class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700" />
                            @error('newAssignmentDueDate') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('Max Points') }}</label>
                            <input wire:model="newAssignmentMaxPoints" type="number" step="0.01" min="0" placeholder="{{ __('Max Points') }}"
                                   class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            @error('newAssignmentMaxPoints') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">{{ __('Description') }}</label>
                            <textarea wire:model="newAssignmentDescription" rows="3" placeholder="{{ __('Description') }}"
                                      class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700"></textarea>
                            @error('newAssignmentDescription') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <button class="md:col-span-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm w-fit">{{ __('Create') }}</button>
                    </form>
                </div>

                <div class="space-y-3">
                    @forelse ($assignments as $a)
                        <div wire:key="assignment-{{ $a->id }}" class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ $a->title }}</h3>
                                    <p class="text-sm text-gray-500">
                                        {{ $a->section?->getTranslation('name', app()->getLocale(), false) }}
                                        @if ($a->due_date) · {{ __('Due') }}: {{ $a->due_date->format('Y-m-d H:i') }} @endif
                                        @if ($a->max_points) · {{ __('Max Points') }}: {{ rtrim(rtrim((string) $a->max_points, '0'), '.') }} @endif
                                    </p>
                                </div>
                                <a href="{{ route('trainer.assignments.show', $a) }}" wire:navigate
                                   class="px-3 py-1.5 text-sm rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200 whitespace-nowrap">
                                    {{ __('Submissions') }} ({{ $a->submissions_count }})
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500">{{ __('No records found') }}</p>
                    @endforelse
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
                            <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm">{{ __('Send Complaint') }}</button>
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
                                    <div class="mt-1 p-2 rounded bg-emerald-50 dark:bg-emerald-900/20 text-sm">
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold mb-4">{{ __('Profile Picture') }}</h3>
                        <form wire:submit="updateProfile" class="space-y-4">
                            @php $avatarUrl = $trainer->getFirstMediaUrl('main'); @endphp
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <label style="position:relative; display:inline-block; cursor:pointer; flex:0 0 auto;">
                                    <span style="display:flex; align-items:center; justify-content:center; width:96px; height:96px; border-radius:9999px; overflow:hidden; border:2px solid #10b981; background:#f3f4f6;">
                                        @if ($newAvatar)
                                            <img src="{{ $newAvatar->temporaryUrl() }}" style="width:96px; height:96px; object-fit:cover;" alt="">
                                        @elseif ($avatarUrl)
                                            <img src="{{ $avatarUrl }}" style="width:96px; height:96px; object-fit:cover;" alt="">
                                        @else
                                            <svg style="width:48px; height:48px; color:#9ca3af;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                        @endif
                                    </span>
                                    <span style="position:absolute; bottom:0; left:0; display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:9999px; background:#059669; color:#fff; border:2px solid #fff;">
                                        <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </span>
                                    <input type="file" wire:model="newAvatar" accept="image/*" style="display:none;">
                                </label>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Click the photo to choose a new image') }}</p>
                                    @if ($avatarUrl && ! $newAvatar)
                                        <button type="button"
                                                @click="confirmBox = { open: true, message: '{{ __('Delete profile picture?') }}', action: () => $wire.removeAvatar() }"
                                                class="mt-1 text-sm text-red-600 hover:underline">
                                            {{ __('Remove Photo') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                            @error('newAvatar') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <div wire:loading wire:target="newAvatar" class="text-sm text-gray-500">{{ __('Uploading...') }}</div>
                            @if ($newAvatar)
                                <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm">{{ __('Upload') }}</button>
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
                            <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm">{{ __('Update') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </main>

        @include('livewire.partials.confirm-modal')
    </div>
</div>
