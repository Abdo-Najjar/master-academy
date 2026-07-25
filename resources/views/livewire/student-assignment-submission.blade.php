<div class="min-h-screen bg-gray-50 dark:bg-gray-900" style="--portal-accent: #9333ea"
     x-data="{ sidebarOpen: false, confirmBox: { open: false, message: '', action: null } }">
    <div class="flex min-h-screen">
        @include('livewire.partials.student-sidebar', ['activeTab' => 'assignments', 'useWireClick' => false])

        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="md:hidden fixed inset-0 bg-black/50 z-40" style="display: none;"></div>

        <div class="flex min-w-0 flex-1 flex-col">
            @include('livewire.partials.portal-header', [
                'portalTitle' => $assignment->title,
                'portalUser' => $student,
                'portalSubtitle' => $student->student_number,
                'portalAvatarClass' => 'ring-purple-500',
                'portalFallbackClass' => 'bg-purple-500',
            ])

            <main class="flex-1 min-w-0 p-4 md:p-6">
                <div class="max-w-3xl mx-auto">
                    <a href="{{ route('student.dashboard') }}?tab=assignments" wire:navigate
                       class="inline-flex items-center gap-1 text-sm text-purple-600 hover:underline mb-4">
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        {{ __('Back') }}
                    </a>

                    @if (session('message'))
                        <div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('message') }}</div>
                    @endif

                    <div class="mb-6 p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <h1 class="text-xl font-semibold">{{ $assignment->title }}</h1>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $assignment->section?->name }}
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

                            <label class="flex flex-col items-center justify-center gap-2 w-full px-4 py-8 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-purple-500 dark:hover:border-purple-500 cursor-pointer text-center transition">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3-3m0 0l3 3m-3-3v9"/></svg>
                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Click to choose a file') }}</span>
                                <input type="file" wire:model="file" class="hidden">
                            </label>

                            <div wire:loading wire:target="file" class="text-sm text-gray-500">{{ __('Uploading...') }}</div>

                            @if ($file)
                                <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    {{ $file->getClientOriginalName() }}
                                </div>
                            @endif

                            <p class="text-xs text-gray-400">{{ __('Maximum file size: 20 MB') }}</p>
                            @error('file') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <button class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm">{{ __('Submit') }}</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>

        @include('livewire.partials.confirm-modal')
    </div>
</div>
