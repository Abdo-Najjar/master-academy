<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-4xl mx-auto p-4 md:p-6">
        <a href="{{ route('trainer.dashboard') }}" wire:navigate
           class="inline-flex items-center gap-1 text-sm text-emerald-600 hover:underline mb-4">
            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            {{ __('Back to Dashboard') }}
        </a>

        @if (session('message'))
            <div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('message') }}</div>
        @endif

        <div class="mb-6 p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h1 class="text-xl font-semibold">{{ $assignment->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $assignment->section?->getTranslation('name', app()->getLocale(), false) }}
                @if ($assignment->due_date) · {{ __('Due') }}: {{ $assignment->due_date->format('Y-m-d H:i') }} @endif
                @if ($assignment->max_points) · {{ __('Max Points') }}: {{ rtrim(rtrim((string) $assignment->max_points, '0'), '.') }} @endif
            </p>
            @if ($assignment->description)
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 whitespace-pre-line">{{ $assignment->description }}</p>
            @endif
        </div>

        <div class="mb-4 p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex flex-col md:flex-row gap-3">
            <select wire:model.live="statusFilter" class="px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700 text-sm">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="not_submitted">{{ __('Not submitted') }}</option>
                <option value="submitted">{{ __('Submitted') }}</option>
                <option value="graded">{{ __('Graded') }}</option>
            </select>
            <input type="text" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Search by student name or number') }}"
                   class="flex-1 px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700 text-sm">
        </div>

        <div class="space-y-3">
            @forelse ($rows as $row)
                @php
                    $student = $row['student'];
                    $submission = $row['submission'];
                    $status = $row['status'];
                @endphp
                <div wire:key="row-{{ $student->id }}" class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <div>
                            <p class="font-medium">{{ $student->getTranslation('name', app()->getLocale(), false) }}</p>
                            <p class="text-xs text-gray-500">{{ $student->student_number }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($status === 'graded')
                                <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-semibold">{{ __('Graded') }}</span>
                            @elseif ($status === 'submitted')
                                <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-semibold">{{ __('Submitted') }}</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">{{ __('Not submitted') }}</span>
                            @endif
                            @if ($submission?->submitted_at)
                                <span class="text-xs text-gray-400">{{ $submission->submitted_at->format('Y-m-d H:i') }}</span>
                            @endif
                        </div>
                    </div>

                    @if ($submission)
                        @if ($submission->content)
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line mb-2">{{ $submission->content }}</p>
                        @endif
                        @if ($submission->getFirstMedia('attachment'))
                            <a href="{{ $submission->getFirstMedia('attachment')->getUrl() }}" target="_blank"
                               class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 mb-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                {{ $submission->getFirstMedia('attachment')->file_name }}
                            </a>
                        @endif
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <input type="number" step="0.01" min="0" wire:model="gradeInputs.{{ $submission->id }}" placeholder="{{ __('Grade') }}"
                                   class="w-28 px-2 py-1.5 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            <input type="text" wire:model="feedbackInputs.{{ $submission->id }}" placeholder="{{ __('Feedback') }}"
                                   class="flex-1 min-w-[160px] px-2 py-1.5 text-sm border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            <button wire:click="saveGrade({{ $submission->id }})"
                                    class="px-3 py-1.5 text-sm rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">{{ __('Save') }}</button>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">{{ __('This student has not submitted yet.') }}</p>
                    @endif
                </div>
            @empty
                <div class="p-6 text-center text-gray-500 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">{{ __('No records found') }}</div>
            @endforelse
        </div>
    </div>
</div>
