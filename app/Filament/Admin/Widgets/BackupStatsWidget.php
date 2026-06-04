<?php

namespace App\Filament\Admin\Widgets;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Storage;

class BackupStatsWidget extends BaseWidget
{
    /**
     * Only render on the System Backup page, not on the dashboard.
     *
     * routeIs() matches the initial page load (GET). Livewire requests hit the
     * `/livewire/update` route, where routeIs() would be false and Filament
     * would abort 403 — so we also accept updates whose referer is the backup
     * page (this is what caused the page to 403 after a moment).
     */
    public static function canView(): bool
    {
        return str_contains((string) request()->header('referer'), '/admin/system-backup');
    }

    protected function getStats(): array
    {
        $files = $this->getBackupFiles();

        $count = $files->count();
        $totalSize = $files->sum('size');
        $latest = $files->first();

        return [
            Stat::make(__('Total Backups'), $count)
                ->description($count > 0 ? __('Available Backups') : __('No backups yet. Click "Create Backup" above to create your first backup.'))
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('primary'),

            Stat::make(__('Total Size'), $this->formatBytes($totalSize))
                ->description($this->formatBytes($totalSize / max($count, 1)) . ' / ' . __('Backup'))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('success'),

            Stat::make(
                __('Last Backup'),
                $latest ? Carbon::createFromTimestamp($latest['modified'])->diffForHumans() : __('Never')
            )
                ->description($latest ? Carbon::createFromTimestamp($latest['modified'])->format('Y-m-d H:i') : __('No backups yet. Click "Create Backup" above to create your first backup.'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($latest ? 'info' : 'gray'),
        ];
    }

    protected function getBackupFiles(): \Illuminate\Support\Collection
    {
        $diskName = config('backup.backup.destination.disks')[0] ?? 'local';
        $folder = config('backup.backup.name', config('app.name', 'laravel-backup'));

        $disk = Storage::disk($diskName);

        if (! $disk->exists($folder)) {
            return collect();
        }

        return collect($disk->files($folder))
            ->filter(fn ($f) => str_ends_with(strtolower($f), '.zip'))
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => $disk->size($f),
                'modified' => $disk->lastModified($f),
            ])
            ->sortByDesc('modified')
            ->values();
    }

    protected function formatBytes(float $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return round($bytes) . ' B';
    }
}
