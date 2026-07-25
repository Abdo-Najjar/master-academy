<div class="min-h-screen bg-gray-50 dark:bg-gray-900" style="--portal-accent: #059669"
     x-data="{ sidebarOpen: false, confirmBox: { open: false, message: '', action: null } }">
    <div class="flex min-h-screen">
        @include('livewire.partials.trainer-sidebar', ['activeTab' => 'login-activities', 'useWireClick' => false])

        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="md:hidden fixed inset-0 bg-black/50 z-40" style="display: none;"></div>

        <div class="flex min-w-0 flex-1 flex-col">
            @include('livewire.partials.portal-header', [
                'portalTitle' => __('Recent Logins'),
                'portalUser' => $trainer,
                'portalSubtitle' => $trainer->trainer_number,
                'portalAvatarClass' => 'ring-emerald-500',
                'portalFallbackClass' => 'bg-emerald-500',
            ])

            <main class="flex-1 min-w-0 p-4 md:p-6">
                <div class="max-w-4xl mx-auto">
                    <a href="{{ route('trainer.dashboard') }}" wire:navigate
                       class="inline-flex items-center gap-1 text-sm text-emerald-600 hover:underline mb-4">
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        {{ __('Back') }}
                    </a>

                    <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                        <h1 class="text-lg font-semibold mb-1">{{ __('Recent Logins') }}</h1>
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
                </div>
            </main>
        </div>

        @include('livewire.partials.confirm-modal')
    </div>
</div>
