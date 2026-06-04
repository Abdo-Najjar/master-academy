<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\BackupStatsWidget;
use App\Jobs\CreateSystemBackupJob;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SystemBackup extends Page
{
    use HasHexaLite;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected string $view = 'filament.pages.system-backup';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('System Backup');
    }

    public function getTitle(): string
    {
        return __('System Backup');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('backup.run')
            || hexa()->can('backup.download')
            || hexa()->can('backup.delete');
    }

    public function defineGates(): array
    {
        return [
            'backup.run' => __('Create Backup'),
            'backup.download' => __('Download Backup'),
            'backup.delete' => __('Delete Backup'),
        ];
    }

    public function roleName(): string
    {
        return __('System Backup');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BackupStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBackup')
                ->label(__('Create Backup'))
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('Create System Backup'))
                ->modalDescription(__('This will create a full backup of the database and project files. You will receive a notification with a download link when it is ready.'))
                ->modalSubmitActionLabel(__('Start Backup'))
                ->visible(fn () => $this->canRun())
                ->action(function (): void {
                    CreateSystemBackupJob::dispatch(Auth::id());

                    Notification::make()
                        ->info()
                        ->icon('heroicon-o-clock')
                        ->title(__('Backup started'))
                        ->body(__('You will receive a notification with a download link when the backup is ready. This may take a few minutes.'))
                        ->send();
                }),
        ];
    }

    public function deleteBackupAction(): Action
    {
        return Action::make('deleteBackup')
            ->iconButton()
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->size('md')
            ->label(__('Delete'))
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-trash')
            ->modalIconColor('danger')
            ->modalHeading(__('Delete Backup'))
            ->modalDescription(__('Are you sure you want to delete this backup? This action cannot be undone.'))
            ->modalSubmitActionLabel(__('Delete'))
            ->visible(fn () => $this->canDelete())
            ->action(function (array $arguments): void {
                $filename = $arguments['filename'] ?? null;

                if (
                    ! is_string($filename)
                    || ! str_ends_with(strtolower($filename), '.zip')
                    || str_contains($filename, '/')
                    || str_contains($filename, '\\')
                    || str_contains($filename, '..')
                ) {
                    Notification::make()
                        ->danger()
                        ->title(__('Invalid filename'))
                        ->send();
                    return;
                }

                $disk = Storage::disk($this->diskName());
                $path = $this->folderName() . '/' . $filename;

                if ($disk->exists($path)) {
                    $disk->delete($path);
                }

                Notification::make()
                    ->success()
                    ->title(__('Backup deleted successfully'))
                    ->send();
            });
    }

    /**
     * @return array<int, array{name: string, size: string, size_raw: int, modified: string, ago: string}>
     */
    public function getBackups(): array
    {
        $disk = Storage::disk($this->diskName());
        $folder = $this->folderName();

        if (! $disk->exists($folder)) {
            return [];
        }

        return collect($disk->files($folder))
            ->filter(fn ($f) => str_ends_with(strtolower($f), '.zip'))
            ->sortByDesc(fn ($f) => $disk->lastModified($f))
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => $this->formatBytes($disk->size($f)),
                'size_raw' => $disk->size($f),
                'modified' => Carbon::createFromTimestamp($disk->lastModified($f))->format('Y-m-d H:i'),
                'ago' => Carbon::createFromTimestamp($disk->lastModified($f))->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{count: int, total_size: string, last_backup: ?string, last_backup_ago: ?string}
     */
    public function getStats(): array
    {
        $backups = $this->getBackups();

        return [
            'count' => count($backups),
            'total_size' => $this->formatBytes((int) array_sum(array_column($backups, 'size_raw'))),
            'last_backup' => $backups[0]['modified'] ?? null,
            'last_backup_ago' => $backups[0]['ago'] ?? null,
        ];
    }

    public function canRun(): bool
    {
        return hexa()->can('backup.run');
    }

    public function canDownload(): bool
    {
        return hexa()->can('backup.download');
    }

    public function canDelete(): bool
    {
        return hexa()->can('backup.delete');
    }

    protected function diskName(): string
    {
        return config('backup.backup.destination.disks')[0] ?? 'local';
    }

    protected function folderName(): string
    {
        return config('backup.backup.name', config('app.name', 'laravel-backup'));
    }

    protected function formatBytes(int $bytes): string
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
        return $bytes . ' B';
    }
}
