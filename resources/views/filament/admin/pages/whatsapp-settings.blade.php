<x-filament-panels::page>
    @php
        $status = $session?->status;
    @endphp

    <style>
        .wa-wrap{max-width:560px;margin-inline:auto;width:100%;}
        .wa-card{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:2.5rem 2rem;width:100%;box-shadow:0 10px 30px -12px rgba(0,0,0,.12);}
        .dark .wa-card{background:#1e293b;border-color:#334155;box-shadow:0 10px 30px -12px rgba(0,0,0,.5);}
        .wa-status{display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .9rem;border-radius:9999px;font-size:.8rem;font-weight:600;}
        .wa-status--ready{background:#dcfce7;color:#15803d;}
        .wa-status--qr{background:#fef9c3;color:#92400e;}
        .wa-status--init{background:#dbeafe;color:#1d4ed8;}
        .wa-status--error{background:#fee2e2;color:#b91c1c;}
        .wa-qr{display:flex;justify-content:center;padding:1.5rem 0;}
        .wa-qr img{border-radius:.75rem;width:100%;max-width:300px;border:6px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,.1);}
        .dark .wa-qr img{border-color:#0f172a;}
        .wa-info-row{display:flex;align-items:center;gap:.75rem;padding:.5rem 0;font-size:.9rem;}
        .wa-info-label{color:#64748b;min-width:130px;}
        .wa-info-val{font-weight:600;}
        .wa-tip{font-size:.8rem;color:#94a3b8;margin-top:.5rem;}
        .wa-pulse{display:inline-block;width:10px;height:10px;border-radius:9999px;background:#22c55e;animation:wa-blink 1.2s infinite;}
        @keyframes wa-blink{0%,100%{opacity:1;}50%{opacity:.35;}}
    </style>

    <div class="wa-wrap">
    <div class="wa-card" wire:poll.3s="pollStatus">
        @if (! $session)
            {{-- No session yet --}}
            <div style="text-align:center;padding:2rem 0;">
                <svg style="margin:0 auto 1rem;width:56px;height:56px;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
                <p style="font-weight:600;font-size:1.1rem;margin-bottom:.25rem;">{{ __('No WhatsApp Account Linked') }}</p>
                <p class="wa-tip">{{ __('Click "Link WhatsApp Account" above to start.') }}</p>
            </div>

        @elseif ($status === \App\Models\WhatsappSession::STATUS_INITIALIZING)
            <div style="text-align:center;padding:1.5rem 0;">
                <span class="wa-status wa-status--init">
                    <svg style="width:14px;height:14px;animation:spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    {{ __('Initializing…') }}
                </span>
                <p class="wa-tip" style="margin-top:1rem;">{{ __('Please wait while the WhatsApp connection is being prepared.') }}</p>
            </div>

        @elseif ($status === \App\Models\WhatsappSession::STATUS_QR_READY)
            <div style="text-align:center;">
                <span class="wa-status wa-status--qr">{{ __('Scan QR Code') }}</span>
                <div class="wa-qr">
                    <img src="{{ $session->qr_code }}" alt="QR Code">
                </div>
                <p style="font-size:.875rem;font-weight:500;">{{ __('Open WhatsApp on your phone') }}</p>
                <p class="wa-tip">{{ __('Go to Settings → Linked Devices → Link a Device, then scan the code.') }}</p>
                <p class="wa-tip" style="margin-top:.5rem;">{{ __('Code refreshes automatically every 3 seconds.') }}</p>
            </div>

        @elseif ($status === \App\Models\WhatsappSession::STATUS_READY)
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                    <span class="wa-status wa-status--ready">
                        <span class="wa-pulse"></span>
                        {{ __('Connected') }}
                    </span>
                    <span style="font-size:.75rem;color:#94a3b8;">{{ __('Since') }}: {{ $session->connected_at?->format('Y-m-d H:i') }}</span>
                </div>

                @if ($session->profile_picture_path)
                    <div style="display:flex;justify-content:center;margin-bottom:1rem;">
                        <img src="{{ $session->profile_picture_path }}" style="width:72px;height:72px;border-radius:9999px;object-fit:cover;border:3px solid #22c55e;" alt="">
                    </div>
                @endif

                <div>
                    @if ($session->name)
                        <div class="wa-info-row">
                            <span class="wa-info-label">{{ __('Account Name') }}</span>
                            <span class="wa-info-val">{{ $session->name }}</span>
                        </div>
                    @endif
                    <div class="wa-info-row">
                        <span class="wa-info-label">{{ __('Phone Number') }}</span>
                        <span class="wa-info-val" dir="ltr">+{{ $session->phone_number }}</span>
                    </div>
                </div>

                <div style="margin-top:1rem;padding:.75rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.5rem;font-size:.8rem;color:#166534;">
                    ✅ {{ __('WhatsApp is active. Notifications and messages will be sent through this account.') }}
                </div>
            </div>

        @elseif (in_array($status, [\App\Models\WhatsappSession::STATUS_DISCONNECTED, \App\Models\WhatsappSession::STATUS_ERROR]))
            <div style="text-align:center;padding:1.5rem 0;">
                <span class="wa-status wa-status--error">{{ __('Disconnected') }}</span>
                <p style="margin-top:1rem;font-size:.875rem;color:#94a3b8;">
                    {{ __('The WhatsApp account has been disconnected. Please re-link your account.') }}
                </p>
            </div>
        @endif
    </div>

    @if ($diagnostics)
        <div class="wa-card" style="margin-top:1.5rem;">
            <h3 style="font-weight:600;margin-bottom:1rem;">{{ __('Connection Diagnostics') }}</h3>
            <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.85rem;">
                <div style="display:flex;justify-content:space-between;gap:1rem;">
                    <span>{{ __('Node.js available') }}</span>
                    <span style="font-weight:600;color:{{ $diagnostics['node_found'] ? '#16a34a' : '#dc2626' }};">
                        {{ $diagnostics['node_found'] ? ('✅ '.$diagnostics['node_version']) : '❌ '.__('Not found') }}
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;gap:1rem;">
                    <span>{{ __('CLI script (whatsapp/cli.js)') }}</span>
                    <span style="font-weight:600;color:{{ $diagnostics['cli_exists'] ? '#16a34a' : '#dc2626' }};">
                        {{ $diagnostics['cli_exists'] ? '✅ '.__('Found') : '❌ '.__('Missing') }}
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;gap:1rem;">
                    <span>{{ __('Dependencies (whatsapp/node_modules)') }}</span>
                    <span style="font-weight:600;color:{{ $diagnostics['node_modules_exists'] ? '#16a34a' : '#dc2626' }};">
                        {{ $diagnostics['node_modules_exists'] ? '✅ '.__('Installed') : '❌ '.__('Missing — run npm install inside whatsapp/') }}
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;gap:1rem;">
                    <span>{{ __('Link process currently running') }}</span>
                    <span style="font-weight:600;color:{{ $diagnostics['link_process_running'] ? '#d97706' : '#16a34a' }};">
                        {{ $diagnostics['link_process_running'] ? '⚠️ '.__('Yes') : '— '.__('No') }}
                    </span>
                </div>
            </div>
            @if ($diagnostics['log_tail'])
                <div style="margin-top:1rem;">
                    <p style="font-size:.8rem;font-weight:600;margin-bottom:.375rem;">{{ __('Last CLI output') }}</p>
                    <pre style="background:#0f172a;color:#e2e8f0;padding:.75rem;border-radius:.5rem;font-size:.7rem;overflow-x:auto;white-space:pre-wrap;max-height:220px;overflow-y:auto;">{{ $diagnostics['log_tail'] }}</pre>
                </div>
            @else
                <p style="margin-top:.75rem;font-size:.75rem;color:#94a3b8;">{{ __('No CLI output logged yet.') }}</p>
            @endif
        </div>
    @endif

    @if ($status === \App\Models\WhatsappSession::STATUS_READY)
        {{-- Quick send test --}}
        <div class="wa-card" style="margin-top:1.5rem;">
            <h3 style="font-weight:600;margin-bottom:1rem;">{{ __('Send Test Message') }}</h3>
            <form wire:submit.prevent="sendTest" style="display:flex;flex-direction:column;gap:.75rem;">
                <div>
                    <label style="font-size:.875rem;font-weight:500;">{{ __('Phone Number') }}</label>
                    <input wire:model="testPhone" type="text" placeholder="05XXXXXXXX"
                           class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm dark:bg-gray-800">
                </div>
                <div>
                    <label style="font-size:.875rem;font-weight:500;">{{ __('Message') }}</label>
                    <textarea wire:model="testMessage" rows="3" placeholder="{{ __('Test message from Manba center…') }}"
                              class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm dark:bg-gray-800"></textarea>
                </div>
                <div>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('Send') }}</span>
                        <span wire:loading>{{ __('Sending…') }}</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
    </div>

    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</x-filament-panels::page>
