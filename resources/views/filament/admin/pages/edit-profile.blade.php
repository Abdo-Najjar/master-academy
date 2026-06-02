<x-filament-panels::page>
    <div class="max-w-7xl mx-auto space-y-8">
        {{-- Profile Information Form --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <form wire:submit="updateProfile">
                <div class="p-6 mb-6">
                    {{ $this->profileForm }}
                </div>

                <div class="flex items-center justify-end gap-x-3 px-6 py-4 border-t border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-white/5">
                    <x-filament::button
                        type="submit"
                        size="lg"
                        wire:target="updateProfile"
                    >
                        <x-slot name="icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </x-slot>
                        {{ __('Save Changes') }}
                    </x-filament::button>
                </div>
            </form>
        </div>

        <div style="margin-top: 2rem;" class="divider"></div>

        {{-- Recent Logins --}}
        @php
            $loginActivities = auth()->user()
                ?->loginActivities()
                ->orderByDesc('logged_in_at')
                ->limit(10)
                ->get() ?? collect();
        @endphp
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-1">{{ __('Recent Logins') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ __('Your last :count sign-in events.', ['count' => $loginActivities->count()]) }}</p>

                @if ($loginActivities->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('No records found') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-[640px] w-full text-sm">
                            <thead class="text-start text-xs uppercase text-gray-500 border-b border-gray-200 dark:border-gray-700">
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
                                        <td class="py-2">{{ $activity->logged_in_at?->diffForHumans() }}<br>
                                            <span class="text-xs text-gray-500">{{ $activity->logged_in_at?->format('Y-m-d H:i') }}</span></td>
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

        <div style="margin-top: 2rem;" class="divider"></div>

        {{-- Update Password Form --}}
        <div class="rounded-xl bg-white mt-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <form wire:submit="updatePassword">
                <div class="p-6 mb-6">
                    {{ $this->passwordForm }}
                </div>

                <div class="flex items-center justify-end gap-x-3 px-6 py-4 border-t border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-white/5">
                    <x-filament::button
                        type="submit"
                        size="lg"
                        color="warning"
                        wire:target="updatePassword"
                    >
                        <x-slot name="icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </x-slot>
                        {{ __('Update Password') }}
                    </x-filament::button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
