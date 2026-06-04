<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="{ sidebarOpen: false }">
    <div class="md:hidden sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold">{{ __('Trainer Portal') }}</h1>
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <div class="flex min-h-screen">
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
               class="fixed md:static top-0 bottom-0 z-50 w-60 md:w-64 bg-white dark:bg-gray-800 border-{{ app()->getLocale() === 'ar' ? 'l' : 'r' }} border-gray-200 dark:border-gray-700 transition-transform duration-300 ease-in-out shrink-0">
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
                @foreach (['sections' => __('My Sections'), 'attendance' => __('Attendance'), 'materials' => __('Materials'), 'transactions' => __('Transactions'), 'complaints' => __('Complaints'), 'profile' => __('Edit Profile')] as $tab => $label)
                    <button wire:click="setActiveTab('{{ $tab }}')" @click="sidebarOpen = false"
                            class="w-full text-start px-4 py-2.5 rounded-lg transition {{ $activeTab === $tab ? 'bg-emerald-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
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
                <div class="p-5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold mb-4">{{ __('Mark Attendance') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <select wire:model.live="attendanceSectionId" wire:change="loadAttendance" class="px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                            <option value="">{{ __('Select section') }}</option>
                            @foreach ($sections as $s)
                                <option value="{{ $s->id }}">{{ $s->getTranslation('name', app()->getLocale(), false) }}</option>
                            @endforeach
                        </select>
                        <input wire:model.live="attendanceDate" wire:change="loadAttendance" type="date" class="px-3 py-2 border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                    </div>

                    @if ($attendanceSection)
                        <div class="space-y-2">
                            @foreach ($attendanceSection->registrations as $reg)
                                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700">
                                    <span>{{ $reg->student?->getTranslation('name', app()->getLocale(), false) }}</span>
                                    <select wire:model="attendanceStatuses.{{ $reg->student_id }}" class="px-2 py-1 border rounded dark:bg-gray-900 dark:border-gray-600 text-sm">
                                        <option value="present">{{ __('Present') }}</option>
                                        <option value="absent">{{ __('Absent') }}</option>
                                        <option value="late">{{ __('Late') }}</option>
                                        <option value="excused">{{ __('Excused') }}</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                        <button wire:click="saveAttendance" class="mt-4 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">{{ __('Save Attendance') }}</button>
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
                            <input type="file" wire:model="newMaterials" multiple class="block w-full text-sm">
                            @error('newMaterials.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm">{{ __('Upload') }}</button>
                        </form>
                        <div class="space-y-2">
                            @forelse ($materialsSection->getMedia('materials') as $media)
                                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700">
                                    <a href="{{ $media->getFullUrl() }}" target="_blank" class="text-emerald-600 hover:underline">{{ $media->file_name }}</a>
                                    <button wire:click="removeMaterial({{ $media->id }})" wire:confirm="{{ __('Delete this file?') }}" class="text-sm text-red-600 hover:underline">{{ __('Delete') }}</button>
                                </div>
                            @empty
                                <p class="text-gray-500">{{ __('No materials uploaded yet') }}</p>
                            @endforelse
                        </div>
                    @endif
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
                <div class="max-w-2xl">
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
                            @php $avatarUrl = $trainer->getFirstMediaUrl('main'); @endphp
                            <div class="flex items-center gap-4">
                                <label class="relative cursor-pointer">
                                    <span class="flex items-center justify-center w-24 h-24 rounded-full overflow-hidden ring-2 ring-emerald-500 bg-gray-100 dark:bg-gray-700">
                                        @if ($newAvatar)
                                            <img src="{{ $newAvatar->temporaryUrl() }}" class="w-24 h-24 object-cover" alt="">
                                        @elseif ($avatarUrl)
                                            <img src="{{ $avatarUrl }}" class="w-24 h-24 object-cover" alt="">
                                        @else
                                            <svg class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                        @endif
                                    </span>
                                    <span class="absolute bottom-0 left-0 flex items-center justify-center w-7 h-7 rounded-full bg-emerald-600 text-white ring-2 ring-white dark:ring-gray-800">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </span>
                                    <input type="file" wire:model="newAvatar" accept="image/*" class="hidden">
                                </label>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Click the photo to choose a new image') }}</p>
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
    </div>
</div>
