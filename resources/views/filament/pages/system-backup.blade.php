<x-filament-panels::page>
    @php
        $backups = $this->getBackups();
        $canDownload = $this->canDownload();
        $canDelete = $this->canDelete();
    @endphp

    {{-- Backups list section --}}
    <x-filament::section icon="heroicon-o-folder-open">
        <x-slot name="heading">
            {{ __('Available Backups') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Latest backups appear at the top. Use the button at the top of the page to create a new backup.') }}
        </x-slot>

        @if (empty($backups))
            {{-- Empty state --}}
            <div style="text-align:center; padding: 3rem 1rem;">
                <div style="display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:9999px; background:rgba(156,163,175,0.15); margin-bottom:1rem;">
                    <x-filament::icon
                        icon="heroicon-o-archive-box"
                        style="width:32px; height:32px; color:rgb(156,163,175);"
                    />
                </div>
                <h3 style="font-size:1rem; font-weight:600; color:var(--gray-700);">
                    {{ __('No backups yet. Click "Create Backup" above to create your first backup.') }}
                </h3>
            </div>
        @else
            {{-- Cards list --}}
            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                @foreach ($backups as $backup)
                    <div
                        style="
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            flex-wrap:wrap;
                            gap:1rem;
                            padding:1rem 1.25rem;
                            border:1px solid rgba(156,163,175,0.2);
                            border-radius:0.75rem;
                            background:rgba(249,250,251,0.5);
                            transition: background 0.15s;
                        "
                        onmouseover="this.style.background='rgba(243,244,246,0.8)'"
                        onmouseout="this.style.background='rgba(249,250,251,0.5)'"
                    >
                        {{-- Left side: icon + file info --}}
                        <div style="display:flex; align-items:center; gap:0.875rem; min-width:0; flex:1;">
                            <div style="
                                flex-shrink:0;
                                width:44px;
                                height:44px;
                                border-radius:0.625rem;
                                background:rgba(59,130,246,0.1);
                                display:flex;
                                align-items:center;
                                justify-content:center;
                            ">
                                <x-filament::icon
                                    icon="heroicon-o-archive-box"
                                    style="width:24px; height:24px; color:rgb(59,130,246);"
                                />
                            </div>

                            <div style="min-width:0; flex:1;">
                                <div style="
                                    font-family: ui-monospace, monospace;
                                    font-size:0.8125rem;
                                    font-weight:600;
                                    color:var(--gray-900);
                                    word-break: break-all;
                                ">
                                    {{ $backup['name'] }}
                                </div>
                                <div style="
                                    display:flex;
                                    gap:0.75rem;
                                    align-items:center;
                                    margin-top:0.25rem;
                                    font-size:0.75rem;
                                    color:var(--gray-500);
                                    flex-wrap:wrap;
                                ">
                                    <span style="display:inline-flex; align-items:center; gap:0.25rem;">
                                        <x-filament::icon
                                            icon="heroicon-m-circle-stack"
                                            style="width:14px; height:14px;"
                                        />
                                        {{ $backup['size'] }}
                                    </span>
                                    <span style="display:inline-flex; align-items:center; gap:0.25rem;">
                                        <x-filament::icon
                                            icon="heroicon-m-clock"
                                            style="width:14px; height:14px;"
                                        />
                                        {{ $backup['modified'] }}
                                        ·
                                        {{ $backup['ago'] }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Right side: action buttons --}}
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            @if ($canDownload)
                                <a
                                    href="{{ route('admin.backup.download', ['filename' => $backup['name']]) }}"
                                    target="_blank"
                                >
                                    <x-filament::button
                                        color="primary"
                                        icon="heroicon-m-arrow-down-tray"
                                        size="sm"
                                        tag="span"
                                    >
                                        {{ __('Download') }}
                                    </x-filament::button>
                                </a>
                            @endif

                            @if ($canDelete)
                                {{ ($this->deleteBackupAction)(['filename' => $backup['name']]) }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
