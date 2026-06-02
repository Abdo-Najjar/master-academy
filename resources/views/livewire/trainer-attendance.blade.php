<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="{ sidebarOpen: false }">
    {{-- Top bar (mobile) --}}
    <div class="md:hidden sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">{{ __('Attendance') }}</h1>
        <a href="{{ route('trainer.dashboard') }}" wire:navigate
           class="text-sm px-3 py-1.5 rounded-lg bg-emerald-600 text-white">{{ __('Back to Dashboard') }}</a>
    </div>

    <div class="max-w-7xl mx-auto p-4 md:p-8 space-y-6">
        {{-- Header --}}
        <div class="hidden md:flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ __('Mark Attendance') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Quickly record who attended each session.') }}</p>
            </div>
            <a href="{{ route('trainer.dashboard') }}" wire:navigate
               class="text-sm px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">{{ __('Back to Dashboard') }}</a>
        </div>

        {{-- Flash --}}
        @if (session('message'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition
                 class="p-3 bg-green-100 border border-green-300 text-green-800 rounded-lg shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        {{-- Section + Date selectors --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Section') }}</label>
                <select wire:model.live="sectionId" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm">
                    @forelse ($sections as $s)
                        <option value="{{ $s->id }}">{{ $s->getTranslation('name', app()->getLocale(), false) }}{{ $s->subject ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false) : '' }}</option>
                    @empty
                        <option value="">{{ __('No records found') }}</option>
                    @endforelse
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Date') }}</label>
                <input type="date" wire:model.live="date"
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm" />
            </div>
            <div class="flex items-end gap-2">
                <button type="button" wire:click="markAll('present')"
                        class="flex-1 px-3 py-2 rounded-lg text-sm bg-green-600 hover:bg-green-700 text-white font-medium">
                    {{ __('Mark All Present') }}
                </button>
            </div>
        </div>

        {{-- Stats row --}}
        @if ($section)
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500">{{ __('Total Students') }}</p>
                    <p class="text-2xl font-bold mt-1">{{ count($statuses) }}</p>
                </div>
                <div class="p-4 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <p class="text-xs text-green-700 dark:text-green-300">{{ __('Present') }}</p>
                    <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-1">{{ $this->counts['present'] }}</p>
                </div>
                <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                    <p class="text-xs text-red-700 dark:text-red-300">{{ __('Absent') }}</p>
                    <p class="text-2xl font-bold text-red-700 dark:text-red-300 mt-1">{{ $this->counts['absent'] }}</p>
                </div>
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                    <p class="text-xs text-amber-700 dark:text-amber-300">{{ __('Late') }}</p>
                    <p class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ $this->counts['late'] }}</p>
                </div>
                <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <p class="text-xs text-blue-700 dark:text-blue-300">{{ __('Attendance Rate') }}</p>
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-300 mt-1">{{ $this->attendanceRate }}%</p>
                </div>
            </div>
        @endif

        {{-- Student list --}}
        @if ($section && $section->registrations->isNotEmpty())
            <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold">{{ __('Students') }} ({{ $section->registrations->count() }})</h3>
                    <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d M Y') }}</span>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($section->registrations as $registration)
                        @php
                            $student = $registration->student;
                            $sid = $student?->id;
                            $current = $statuses[$sid] ?? 'present';
                            $avatar = $student?->getFirstMediaUrl('main');
                        @endphp
                        @if ($student)
                            <div class="p-4 flex flex-col md:flex-row md:items-center gap-3 md:gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                {{-- Student info --}}
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    @if ($avatar)
                                        <img src="{{ $avatar }}" class="w-10 h-10 rounded-full object-cover" alt="">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-sm">
                                            {{ mb_substr($student->getTranslation('name', app()->getLocale(), false) ?? 'S', 0, 1) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-semibold truncate">{{ $student->getTranslation('name', app()->getLocale(), false) }}</p>
                                        <p class="text-xs text-gray-500">{{ $student->student_number }}</p>
                                    </div>
                                </div>

                                {{-- Status toggle buttons --}}
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ([
                                        'present' => ['label' => __('Present'), 'color' => 'green'],
                                        'absent' => ['label' => __('Absent'), 'color' => 'red'],
                                        'late' => ['label' => __('Late'), 'color' => 'amber'],
                                        'excused' => ['label' => __('Excused'), 'color' => 'blue'],
                                    ] as $key => $cfg)
                                        @php
                                            $active = $current === $key;
                                            $cls = match ($cfg['color']) {
                                                'green' => $active ? 'bg-green-600 text-white' : 'bg-green-50 text-green-700 hover:bg-green-100',
                                                'red' => $active ? 'bg-red-600 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100',
                                                'amber' => $active ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100',
                                                'blue' => $active ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100',
                                            };
                                        @endphp
                                        <button type="button" wire:click="setStatus({{ $sid }}, '{{ $key }}')"
                                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $cls }}">
                                            {{ $cfg['label'] }}
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Optional note --}}
                                <input type="text" wire:model="notes.{{ $sid }}"
                                       placeholder="{{ __('Optional note') }}"
                                       class="w-full md:w-48 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs" />
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Save bar --}}
                <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-700/30">
                    <span class="text-xs text-gray-500" wire:loading.remove wire:target="save">
                        {{ __('Changes are saved when you click "Save".') }}
                    </span>
                    <span class="text-xs text-emerald-600" wire:loading wire:target="save">{{ __('Saving...') }}</span>
                    <button type="button" wire:click="save" wire:loading.attr="disabled"
                            class="px-6 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-sm font-semibold">
                        {{ __('Save Attendance') }}
                    </button>
                </div>
            </div>

            {{-- Last 14 days summary --}}
            @if ($recentSummaries->isNotEmpty())
                <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base font-semibold">{{ __('Last 14 days') }}</h3>
                    </div>
                    <div class="p-5 overflow-x-auto">
                        <table class="min-w-[640px] w-full text-sm">
                            <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="py-2 text-start">{{ __('Date') }}</th>
                                    <th class="py-2 text-start">{{ __('Present') }}</th>
                                    <th class="py-2 text-start">{{ __('Absent') }}</th>
                                    <th class="py-2 text-start">{{ __('Late') }}</th>
                                    <th class="py-2 text-start">{{ __('Excused') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($recentSummaries as $day => $rows)
                                    @php
                                        $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
                                        foreach ($rows as $row) { $counts[$row->status] = (int) $row->total; }
                                    @endphp
                                    <tr>
                                        <td class="py-2 font-medium">{{ \Carbon\Carbon::parse($day)->translatedFormat('d M Y') }}</td>
                                        <td class="py-2 text-green-600">{{ $counts['present'] }}</td>
                                        <td class="py-2 text-red-600">{{ $counts['absent'] }}</td>
                                        <td class="py-2 text-amber-600">{{ $counts['late'] }}</td>
                                        <td class="py-2 text-blue-600">{{ $counts['excused'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @elseif ($section)
            <div class="p-8 text-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <p class="text-gray-500">{{ __('No students registered in this section yet.') }}</p>
            </div>
        @else
            <div class="p-8 text-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <p class="text-gray-500">{{ __('Select a section to begin.') }}</p>
            </div>
        @endif
    </div>
</div>
