<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateSystemBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $userId)
    {
    }

    public function handle(): void
    {
        /** @var User|null $user */
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning("CreateSystemBackupJob: user {$this->userId} not found, aborting.");
            return;
        }

        try {
            $exitCode = Artisan::call('backup:run');
            $output = Artisan::output();

            if ($exitCode !== 0) {
                throw new \RuntimeException("backup:run exited with code {$exitCode}: {$output}");
            }

            $filename = $this->findLatestBackupFile();

            if ($filename === null) {
                throw new \RuntimeException('Backup command succeeded but no .zip file was found.');
            }

            $disk = Storage::disk($this->backupDisk());
            $path = $this->backupName() . '/' . $filename;
            $sizeMb = round($disk->size($path) / 1024 / 1024, 2);

            Notification::make()
                ->success()
                ->icon('heroicon-o-archive-box')
                ->title(__('Backup completed successfully'))
                ->body(__('File: :name (:size MB)', [
                    'name' => $filename,
                    'size' => $sizeMb,
                ]))
                ->actions([
                    Action::make('download')
                        ->label(__('Download'))
                        ->button()
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(
                            route('admin.backup.download', ['filename' => $filename]),
                            shouldOpenInNewTab: true,
                        )
                        ->markAsRead(),
                ])
                ->sendToDatabase($user);
        } catch (Throwable $e) {
            Log::error('CreateSystemBackupJob failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->icon('heroicon-o-x-circle')
                ->title(__('Backup failed'))
                ->body($e->getMessage())
                ->sendToDatabase($user);
        }
    }

    protected function findLatestBackupFile(): ?string
    {
        $disk = Storage::disk($this->backupDisk());
        $folder = $this->backupName();

        if (! $disk->exists($folder)) {
            return null;
        }

        $latest = collect($disk->files($folder))
            ->filter(fn ($f) => str_ends_with(strtolower($f), '.zip'))
            ->sortByDesc(fn ($f) => $disk->lastModified($f))
            ->first();

        return $latest ? basename($latest) : null;
    }

    protected function backupDisk(): string
    {
        return config('backup.backup.destination.disks')[0] ?? 'local';
    }

    protected function backupName(): string
    {
        return config('backup.backup.name', config('app.name', 'laravel-backup'));
    }
}
