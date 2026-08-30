<x-filament-panels::page>
    @php
        $backups = $this->getBackups();
        $canDownload = $this->canDownload();
        $canDelete = $this->canDelete();
    @endphp

    {{--
        Styled here rather than with utility classes: this panel serves
        Filament's own prebuilt stylesheet (no `viteTheme` on the panel), so a
        class the framework does not already use for itself would simply not
        exist and the page would render unstyled. Everything below leans on
        Filament's palette variables, which carry the light and dark values, and
        follows its own `:where(.dark, .dark *)` convention for the surfaces that
        have to flip.
    --}}
    <style>
        .ma-backup-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            background: var(--gray-50);
            transition: background 0.15s;
        }

        .ma-backup-card:hover {
            background: var(--gray-100);
        }

        .ma-backup-card:where(.dark, .dark *) {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
        }

        .ma-backup-card:where(.dark, .dark *):hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .ma-backup-name {
            font-family: ui-monospace, monospace;
            font-size: 0.8125rem;
            font-weight: 600;
            word-break: break-all;
            color: var(--gray-950);
        }

        .ma-backup-name:where(.dark, .dark *) {
            color: #fff;
        }

        .ma-backup-meta {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            flex-wrap: wrap;
            color: var(--gray-500);
        }

        .ma-backup-meta:where(.dark, .dark *) {
            color: var(--gray-400);
        }

        .ma-backup-tile {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in oklab, var(--primary-500) 12%, transparent);
            color: var(--primary-600);
        }

        .ma-backup-tile:where(.dark, .dark *) {
            color: var(--primary-400);
        }

        .ma-backup-empty-tile {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 9999px;
            margin-bottom: 1rem;
            background: var(--gray-100);
            color: var(--gray-400);
        }

        .ma-backup-empty-tile:where(.dark, .dark *) {
            background: rgba(255, 255, 255, 0.05);
            color: var(--gray-500);
        }

        .ma-backup-empty-text {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .ma-backup-empty-text:where(.dark, .dark *) {
            color: var(--gray-300);
        }
    </style>

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
                <div class="ma-backup-empty-tile">
                    <x-filament::icon
                        icon="heroicon-o-archive-box"
                        style="width:32px; height:32px;"
                    />
                </div>
                <h3 class="ma-backup-empty-text">
                    {{ __('No backups yet. Click "Create Backup" above to create your first backup.') }}
                </h3>
            </div>
        @else
            {{-- Cards list --}}
            <div style="display:flex; flex-direction:column; gap:0.75rem;">
                @foreach ($backups as $backup)
                    <div class="ma-backup-card">
                        {{-- Left side: icon + file info --}}
                        <div style="display:flex; align-items:center; gap:0.875rem; min-width:0; flex:1;">
                            <div class="ma-backup-tile">
                                <x-filament::icon
                                    icon="heroicon-o-archive-box"
                                    style="width:24px; height:24px;"
                                />
                            </div>

                            <div style="min-width:0; flex:1;">
                                <div class="ma-backup-name">
                                    {{ $backup['name'] }}
                                </div>
                                <div class="ma-backup-meta">
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
