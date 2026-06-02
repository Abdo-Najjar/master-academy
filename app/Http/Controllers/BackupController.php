<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Download a backup zip file from storage.
     */
    public function download(Request $request, string $filename): StreamedResponse
    {
        abort_unless(hexa()->can('backup.download'), 403);

        $this->guardFilename($filename);

        $disk = Storage::disk($this->disk());
        $path = $this->folder() . '/' . $filename;

        abort_unless($disk->exists($path), 404);

        return $disk->download($path);
    }

    /**
     * Delete a backup zip file.
     */
    public function destroy(Request $request, string $filename)
    {
        abort_unless(hexa()->can('backup.delete'), 403);

        $this->guardFilename($filename);

        $disk = Storage::disk($this->disk());
        $path = $this->folder() . '/' . $filename;

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        return back();
    }

    /**
     * Reject anything that isn't a plain .zip filename in the backup folder.
     */
    protected function guardFilename(string $filename): void
    {
        if (
            ! str_ends_with(strtolower($filename), '.zip')
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, '..')
        ) {
            abort(404);
        }
    }

    protected function disk(): string
    {
        return config('backup.backup.destination.disks')[0] ?? 'local';
    }

    protected function folder(): string
    {
        return config('backup.backup.name', config('app.name', 'laravel-backup'));
    }
}
