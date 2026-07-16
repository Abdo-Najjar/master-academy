<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-3xl mx-auto p-4 md:p-6">
        <a href="{{ route('student.dashboard') }}" wire:navigate
           class="inline-flex items-center gap-1 text-sm text-purple-600 hover:underline mb-4">
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
                @if ($assignment->due_date)
                    · {{ __('Due') }}: {{ $assignment->due_date->format('Y-m-d H:i') }}
                    @if ($assignment->due_date->isPast()) <span class="text-red-500">({{ __('Past due') }})</span> @endif
                @endif
                @if ($assignment->max_points) · {{ __('Max Points') }}: {{ rtrim(rtrim((string) $assignment->max_points, '0'), '.') }} @endif
            </p>
            @if ($assignment->description)
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 whitespace-pre-line">{{ $assignment->description }}</p>
            @endif
        </div>

        @if ($submission?->isGraded())
            <div class="mb-6 p-5 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800">
                <h2 class="font-semibold mb-2">{{ __('Grade') }}</h2>
                <p class="text-2xl font-bold text-purple-700 dark:text-purple-300">
                    {{ rtrim(rtrim((string) $submission->grade, '0'), '.') }}
                    @if ($assignment->max_points) / {{ rtrim(rtrim((string) $assignment->max_points, '0'), '.') }} @endif
                </p>
                @if ($submission->feedback)
                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">{{ __('Feedback') }}: {{ $submission->feedback }}</p>
                @endif
            </div>
        @endif

        <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="font-semibold mb-4">{{ $submission ? __('Edit Submission') : __('Submit') }}</h2>
            <form wire:submit="submit" class="space-y-3">
                <textarea wire:model="content" rows="6" placeholder="{{ __('Write your answer here') }}"
                          class="w-full px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700"></textarea>
                @error('content') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($submission?->getFirstMedia('attachment'))
                    <p class="text-xs text-gray-500">
                        {{ __('Current file') }}:
                        <a href="{{ $submission->getFirstMedia('attachment')->getUrl() }}" target="_blank" class="text-purple-600 hover:underline">{{ $submission->getFirstMedia('attachment')->file_name }}</a>
                    </p>
                @endif

                <input type="file" wire:model="file" class="block w-full text-sm">
                <p class="text-xs text-gray-400">{{ __('Maximum file size: 20 MB') }}</p>
                @error('file') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div wire:loading wire:target="file" class="text-sm text-gray-500">{{ __('Uploading...') }}</div>

                <button class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm">{{ __('Submit') }}</button>
            </form>
        </div>
    </div>
</div>
