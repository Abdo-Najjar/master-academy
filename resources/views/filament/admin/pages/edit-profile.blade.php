<x-filament-panels::page>
    <style>
        /* Scoped to this page so the shared panel styles stay untouched. */
        .ma-prof{display:flex;flex-direction:column;gap:2rem;max-width:80rem;margin-inline:auto;width:100%;}
        .ma-prof__actions{display:flex;justify-content:flex-end;margin-top:1rem;}

        .ma-prof-logins{width:100%;border-collapse:separate;border-spacing:0;font-size:.875rem;}
        .ma-prof-logins thead th{
            padding:.625rem .875rem;
            text-align:start;
            font-size:.75rem;
            font-weight:600;
            white-space:nowrap;
            color:rgb(100,116,139);
            border-bottom:1px solid rgba(148,163,184,.25);
        }
        .ma-prof-logins tbody td{
            padding:.75rem .875rem;
            vertical-align:middle;
            border-bottom:1px solid rgba(148,163,184,.12);
        }
        .ma-prof-logins tbody tr:last-child td{border-bottom:0;}
        .ma-prof-logins__when{font-weight:600;white-space:nowrap;}
        .ma-prof-logins__stamp{
            display:block;
            margin-top:.125rem;
            font-size:.75rem;
            font-weight:400;
            color:rgb(100,116,139);
            /* Timestamps read left-to-right even on an RTL page. */
            direction:ltr;
            unicode-bidi:isolate;
            text-align:start;
        }
        .ma-prof-logins__ip{
            font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
            font-size:.75rem;
            white-space:nowrap;
            direction:ltr;
            unicode-bidi:isolate;
            text-align:start;
        }
        .ma-prof-logins__cell{white-space:nowrap;color:rgb(100,116,139);}
        .ma-prof-scroll{overflow-x:auto;margin-inline:-.875rem;}
        .ma-prof-empty{font-size:.875rem;color:rgb(100,116,139);margin:0;}
    </style>

    @php
        $loginActivities = auth()->user()
            ?->loginActivities()
            ->orderByDesc('logged_in_at')
            ->limit(10)
            ->get() ?? collect();
    @endphp

    <div class="ma-prof">
        {{-- Profile information --}}
        <form wire:submit="updateProfile">
            {{ $this->profileForm }}

            <div class="ma-prof__actions">
                <x-filament::button
                    type="submit"
                    size="lg"
                    icon="heroicon-m-check"
                    wire:target="updateProfile"
                    wire:loading.attr="disabled"
                >
                    {{ __('Save Changes') }}
                </x-filament::button>
            </div>
        </form>

        {{-- Recent logins --}}
        <x-filament::section icon="heroicon-o-shield-check" :collapsible="true">
            <x-slot name="heading">{{ __('Recent Logins') }}</x-slot>
            <x-slot name="description">
                {{ __('Your last :count sign-in events.', ['count' => $loginActivities->count()]) }}
            </x-slot>

            @if ($loginActivities->isEmpty())
                <p class="ma-prof-empty">{{ __('No records found') }}</p>
            @else
                <div class="ma-prof-scroll">
                    <table class="ma-prof-logins">
                        <thead>
                            <tr>
                                <th>{{ __('When') }}</th>
                                <th>{{ __('IP') }}</th>
                                <th>{{ __('Browser') }}</th>
                                <th>{{ __('Platform') }}</th>
                                <th>{{ __('Device') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loginActivities as $activity)
                                <tr>
                                    <td class="ma-prof-logins__when">
                                        {{ $activity->logged_in_at?->diffForHumans() }}
                                        <span class="ma-prof-logins__stamp">
                                            {{ $activity->logged_in_at?->format('Y-m-d H:i') }}
                                        </span>
                                    </td>
                                    <td class="ma-prof-logins__ip">{{ $activity->ip ?? '—' }}</td>
                                    <td class="ma-prof-logins__cell">{{ $activity->browser ?? '—' }}</td>
                                    <td class="ma-prof-logins__cell">{{ $activity->platform ?? '—' }}</td>
                                    <td class="ma-prof-logins__cell">{{ $activity->device ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Password --}}
        <form wire:submit="updatePassword">
            {{ $this->passwordForm }}

            <div class="ma-prof__actions">
                <x-filament::button
                    type="submit"
                    size="lg"
                    color="warning"
                    icon="heroicon-m-key"
                    wire:target="updatePassword"
                    wire:loading.attr="disabled"
                >
                    {{ __('Update Password') }}
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
