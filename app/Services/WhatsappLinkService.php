<?php

namespace App\Services;

use App\Models\WhatsappSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Manages the WhatsApp account lifecycle for the admin panel.
 * Uses the Baileys CLI (whatsapp/cli.js) via exec().
 * The CLI writes status.json which is polled by the admin page.
 */
class WhatsappLinkService
{
    /**
     * Spawn the linking process in the background.
     * Returns immediately; caller must poll readStatus() for QR/ready.
     */
    public static function startLink(): WhatsappSession
    {
        // Ensure only one active session at a time
        WhatsappSession::whereNull('deleted_at')
            ->whereIn('status', [WhatsappSession::STATUS_INITIALIZING, WhatsappSession::STATUS_QR_READY])
            ->get()
            ->each(function (WhatsappSession $s) {
                static::logout($s);
                $s->forceDelete();
            });

        $session = WhatsappSession::create([
            'unique_id' => Str::uuid()->toString(),
            'status' => WhatsappSession::STATUS_INITIALIZING,
        ]);

        if (app()->runningUnitTests()) {
            return $session;
        }

        $command = self::cliCommand('link');
        $logFile = self::logFilePath();

        if (PHP_OS_FAMILY === 'Windows') {
            // "start /B" detaches without a console window; popen returns immediately.
            pclose(popen('start /B "" ' . $command . ' > "' . $logFile . '" 2>&1', 'r'));
        } else {
            exec($command . ' > ' . escapeshellarg($logFile) . ' 2>&1 &');
        }

        Log::info('WhatsApp link started', ['session_id' => $session->id]);

        return $session;
    }

    /**
     * Check the local environment for the pieces the Baileys CLI needs to run
     * (Node binary, cli.js, node_modules) and surface the last lines of its
     * stdout/stderr log — so a stuck "initializing" state on production can be
     * diagnosed without shell access.
     *
     * @return array{node_found: bool, node_version: ?string, cli_exists: bool, node_modules_exists: bool, log_tail: ?string}
     */
    public static function diagnose(): array
    {
        $nodeVersion = null;
        $nodeFound = false;
        exec('node --version 2>&1', $output, $exitCode);
        if ($exitCode === 0 && ! empty($output)) {
            $nodeFound = true;
            $nodeVersion = trim($output[0]);
        }

        return [
            'node_found' => $nodeFound,
            'node_version' => $nodeVersion,
            'cli_exists' => is_file(base_path('whatsapp/cli.js')),
            'node_modules_exists' => is_dir(base_path('whatsapp/node_modules')) && count(scandir(base_path('whatsapp/node_modules'))) > 2,
            'log_tail' => self::readLogTail(),
        ];
    }

    private static function logFilePath(): string
    {
        return base_path('whatsapp/link.log');
    }

    /** Last ~40 lines of the CLI's stdout/stderr log, if present. */
    private static function readLogTail(): ?string
    {
        $file = self::logFilePath();
        if (! is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($raw));

        return implode("\n", array_slice($lines, -40));
    }

    /**
     * Poll the CLI status.json and sync it to the DB row.
     * Returns the refreshed session.
     */
    public static function syncStatus(WhatsappSession $session): WhatsappSession
    {
        $data = self::readStatusFile();

        if (! $data || ! isset($data['status'])) {
            return $session;
        }

        $allowed = [
            WhatsappSession::STATUS_INITIALIZING,
            WhatsappSession::STATUS_QR_READY,
            WhatsappSession::STATUS_READY,
            WhatsappSession::STATUS_DISCONNECTED,
            WhatsappSession::STATUS_ERROR,
        ];

        $status = in_array($data['status'], $allowed, true) ? $data['status'] : WhatsappSession::STATUS_ERROR;
        $qrCode = $data['qr_code'] ?? null;

        // Stale-QR guard: Baileys rotates the QR every ~20-30s while the CLI is
        // alive and rewrites status.json each time. If the QR hasn't refreshed
        // in over 75s the CLI has died, so the displayed code is expired —
        // surface it as disconnected so the UI prompts a fresh re-link instead
        // of letting the admin scan a dead code.
        if ($status === WhatsappSession::STATUS_QR_READY && ! empty($data['updated_at'])) {
            try {
                $ageSeconds = \Carbon\Carbon::parse($data['updated_at'])->diffInSeconds(now(), true);
                if ($ageSeconds > 75) {
                    $status = WhatsappSession::STATUS_DISCONNECTED;
                    $qrCode = null;
                }
            } catch (\Throwable $e) {
                // Keep the reported status if the timestamp is unparseable.
            }
        }

        $session->update([
            'status'               => $status,
            'qr_code'              => $qrCode,
            'phone_number'         => isset($data['phone_number']) ? preg_replace('/\D/', '', (string) $data['phone_number']) : $session->phone_number,
            'name'                 => $data['name'] ?? $session->name,
            'profile_picture_path' => $data['profile_picture_path'] ?? $session->profile_picture_path,
            'connected_at'         => isset($data['connected_at']) ? \Carbon\Carbon::parse($data['connected_at']) : $session->connected_at,
        ]);

        return $session->fresh();
    }

    /**
     * Logout the linked account: call CLI logout and soft-delete the row.
     */
    public static function logout(WhatsappSession $session): bool
    {
        if (app()->runningUnitTests()) {
            $session->delete();
            return true;
        }

        $command = self::cliCommand('logout');
        exec($command . ' 2>&1', $output, $exitCode);

        Log::info('WhatsApp logout', ['exitCode' => $exitCode, 'output' => $output]);

        $session->delete();

        return $exitCode === 0;
    }

    /**
     * Return the single active (ready) session, or null.
     */
    public static function activeSession(): ?WhatsappSession
    {
        return WhatsappSession::linked()->latest()->first();
    }

    /** Read CLI status.json (platform account). */
    public static function readStatusFile(): ?array
    {
        $file = base_path('whatsapp/auth_info_baileys/status.json');

        if (! is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private static function cliCommand(string $sub): string
    {
        $cliPath = escapeshellarg(base_path('whatsapp/cli.js'));
        return 'node ' . $cliPath . ' ' . $sub;
    }
}
