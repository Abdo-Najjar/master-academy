<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="{ sidebarOpen: false, confirmBox: { open: false, message: '', action: null } }">
    {{-- Mobile header --}}
    <div class="md:hidden sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">{{ __('Parent Portal') }}</h1>
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }} md:translate-x-0'"
               class="fixed md:static top-0 bottom-0 start-0 z-50 w-64 bg-white dark:bg-gray-800 border-{{ app()->getLocale() === 'ar' ? 'l' : 'r' }} border-gray-200 dark:border-gray-700 transition-transform duration-300 ease-in-out shrink-0 overflow-y-auto">

            {{-- Parent info --}}
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-lg shrink-0">
                        {{ mb_substr($parent?->name ?? 'P', 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold truncate">{{ $parent?->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $parent?->phone }}</p>
                    </div>
                </div>
            </div>

            {{-- Children selector --}}
            @if ($students->count() > 1)
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 font-medium uppercase tracking-wide">{{ __('Select Child') }}</p>
                    <div class="space-y-1">
                        @foreach ($students as $s)
                            <button wire:click="selectStudent({{ $s->id }})" @click="sidebarOpen = false"
                                    class="w-full text-start px-3 py-2 rounded-lg text-sm transition {{ $selectedStudentId === $s->id ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                {{ $s->getTranslation('name', app()->getLocale(), false) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Navigation --}}
            <nav class="p-4 space-y-1">
                @foreach ([
                    'overview' => __('Overview'),
                    'attendance' => __('Attendance'),
                    'schedule' => __('Schedule'),
                    'payments' => __('Payments'),
                    'grades' => __('Grades'),
                    'certificates' => __('Certificates'),
                ] as $tab => $label)
                    <button wire:click="setActiveTab('{{ $tab }}')" @click="sidebarOpen = false"
                            class="w-full text-start px-4 py-2.5 rounded-lg transition {{ $activeTab === $tab ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
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

        {{-- Main content --}}
        <main class="flex-1 min-w-0 p-4 md:p-6">
            @if (session('message'))
                <div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('message') }}</div>
            @endif

            @if (! $student)
                <div class="text-center py-20 text-gray-400">
                    <svg class="mx-auto w-16 h-16 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p>{{ __('No children linked to this account.') }}</p>
                </div>
            @else

                {{-- Student header --}}
                <div class="mb-6 p-5 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 flex items-center justify-center font-bold text-xl shrink-0">
                            {{ mb_substr($student->getTranslation('name', app()->getLocale(), false) ?? 'S', 0, 1) }}
                        </div>
                        <div>
                            <h2 class="text-xl font-bold">{{ $student->getTranslation('name', app()->getLocale(), false) }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $student->student_number }} · {{ $student->grade_level }}</p>
                            <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full
                                {{ match($student->status ?? 'active') {
                                    'active' => 'bg-green-100 text-green-700',
                                    'suspended' => 'bg-yellow-100 text-yellow-700',
                                    'withdrawn' => 'bg-red-100 text-red-700',
                                    default => 'bg-gray-100 text-gray-600'
                                } }}">
                                {{ __($student->status ?? 'active') }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- OVERVIEW --}}
                @if ($activeTab === 'overview')
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm text-center">
                            <p class="text-2xl font-bold text-blue-600">{{ $registrations->count() }}</p>
                            <p class="text-sm text-gray-500 mt-1">{{ __('Sections') }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm text-center">
                            <p class="text-2xl font-bold text-green-600">{{ $attendances->where('status', 'present')->count() }}</p>
                            <p class="text-sm text-gray-500 mt-1">{{ __('Present') }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm text-center">
                            <p class="text-2xl font-bold text-red-500">{{ $attendances->whereIn('status', ['absent', 'absent_excused'])->count() }}</p>
                            <p class="text-sm text-gray-500 mt-1">{{ __('Absent') }}</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm text-center">
                            <p class="text-2xl font-bold text-purple-600">{{ $examGrades->count() }}</p>
                            <p class="text-sm text-gray-500 mt-1">{{ __('Exams') }}</p>
                        </div>
                    </div>

                    {{-- Sections summary --}}
                    <h3 class="text-lg font-semibold mb-3">{{ __('Enrolled Sections') }}</h3>
                    <div class="space-y-3">
                        @forelse ($registrations as $reg)
                            @php $section = $reg->section; @endphp
                            <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                    <div>
                                        <h4 class="font-semibold">{{ $section?->getTranslation('name', app()->getLocale(), false) }}</h4>
                                        <p class="text-sm text-gray-500">{{ $section?->subject?->getTranslation('name', app()->getLocale(), false) }} · {{ $section?->trainer?->getTranslation('name', app()->getLocale(), false) }}</p>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ __('Paid') }}: <span class="font-semibold text-green-600">{{ number_format((float) $reg->amount_paid, 2) }} ₪</span>
                                        / {{ __('Due') }}: <span class="font-semibold text-blue-600">{{ number_format((float) $reg->amount_due, 2) }} ₪</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm">{{ __('No sections enrolled.') }}</p>
                        @endforelse
                    </div>
                @endif

                {{-- ATTENDANCE --}}
                @if ($activeTab === 'attendance')
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold">{{ __('Attendance Record') }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-start">{{ __('Date') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Section') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Note') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse ($attendances as $att)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3">{{ $att->date?->format('Y-m-d') }}</td>
                                            <td class="px-4 py-3">{{ $att->section?->getTranslation('name', app()->getLocale(), false) }}</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2 py-0.5 rounded-full text-xs
                                                    {{ $att->status === 'present' ? 'bg-green-100 text-green-700' : ($att->status === 'absent_excused' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                                    {{ __($att->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-500">{{ $att->note ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">{{ __('No records found') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- SCHEDULE --}}
                @if ($activeTab === 'schedule')
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold">{{ __('Weekly Schedule') }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-start">{{ __('Day') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Time') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Section') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Room') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @php $hasAny = false; @endphp
                                    @foreach ($schedule as $day => $items)
                                        @foreach ($items as $item)
                                            @php $hasAny = true; @endphp
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-4 py-3 font-medium">{{ __(ucfirst($day)) }}</td>
                                                <td class="px-4 py-3">{{ $item['start_time'] }} – {{ $item['end_time'] }}</td>
                                                <td class="px-4 py-3">{{ $item['section']->getTranslation('name', app()->getLocale(), false) }}</td>
                                                <td class="px-4 py-3 text-gray-500">{{ $item['time']->room?->name ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                    @unless ($hasAny)
                                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">{{ __('No schedule available') }}</td></tr>
                                    @endunless
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- PAYMENTS --}}
                @if ($activeTab === 'payments')
                    <div class="space-y-4">
                        @forelse ($registrations as $reg)
                            @php $section = $reg->section; @endphp
                            <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                                <h4 class="font-semibold mb-3">{{ $section?->getTranslation('name', app()->getLocale(), false) }}</h4>
                                <div class="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">{{ __('Total Due') }}</p>
                                        <p class="text-lg font-bold text-blue-600">{{ number_format((float) $reg->amount_due, 2) }} ₪</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">{{ __('Total Paid') }}</p>
                                        <p class="text-lg font-bold text-green-600">{{ number_format((float) $reg->amount_paid, 2) }} ₪</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">{{ __('Remaining') }}</p>
                                        @php $remaining = (float) $reg->amount_due - (float) $reg->amount_paid - (float) $reg->exemption_amount; @endphp
                                        <p class="text-lg font-bold {{ $remaining > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($remaining, 2) }} ₪</p>
                                    </div>
                                </div>
                                @if ($reg->exemption_amount > 0)
                                    <p class="mt-2 text-xs text-gray-400">{{ __('Discount') }}: {{ number_format((float) $reg->exemption_amount, 2) }} ₪</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-gray-500">{{ __('No payment records found.') }}</p>
                        @endforelse
                    </div>
                @endif

                {{-- GRADES --}}
                @if ($activeTab === 'grades')
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold">{{ __('Exam Grades') }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-start">{{ __('Exam') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Section') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Date') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Score') }}</th>
                                        <th class="px-4 py-3 text-start">{{ __('Note') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse ($examGrades as $grade)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 font-medium">{{ $grade->exam?->name }}</td>
                                            <td class="px-4 py-3">{{ $grade->exam?->section?->getTranslation('name', app()->getLocale(), false) }}</td>
                                            <td class="px-4 py-3">{{ $grade->exam?->date?->format('Y-m-d') }}</td>
                                            <td class="px-4 py-3">
                                                @if ($grade->score !== null)
                                                    <span class="font-semibold {{ ($grade->score / $grade->exam?->max_score * 100) >= 50 ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ number_format((float) $grade->score, 1) }} / {{ number_format((float) $grade->exam?->max_score, 1) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-500">{{ $grade->note ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('No grades recorded yet') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- CERTIFICATES --}}
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
                                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm whitespace-nowrap">
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

            @endif
        </main>

        @include('livewire.partials.confirm-modal')
    </div>
</div>
