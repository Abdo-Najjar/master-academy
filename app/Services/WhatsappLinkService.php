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
        // Kill any process left over from a previous link attempt (a stuck
        // reconnect loop, or the admin clicking "Link" more than once) before
        // touching anything else. Two Baileys clients racing over the same
        // auth_info_baileys folder corrupt/invalidate each other's session —
        // the browser can end up polling a QR that no longer matches the
        // process actually holding the connection, so scanning it fails even
        // though the panel showed a code.
        if (! app()->runningUnitTests()) {
            self::killExistingLinkProcess();
            // Give the OS a moment to release the killed process's file handles
            // (log file, auth_info_baileys) before we reopen/rewrite them —
            // without this pause the fresh spawn can hit a sharing violation
            // (Windows) or a truncated write racing the dying process (Linux).
            usleep(500_000);
        }

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
            'link_process_running' => self::isLinkProcessRunning(),
            'log_tail' => self::readLogTail(),
        ];
    }

    /** Whether a `node cli.js link` process is currently alive on this host. */
    private static function isLinkProcessRunning(): bool
    {
        $cliPath = base_path('whatsapp/cli.js');

        if (PHP_OS_FAMILY === 'Windows') {
            exec(self::windowsFindLinkProcessCommand($cliPath), $output, $exitCode);

            return $exitCode === 0 && trim(implode('', $output)) !== '';
        }

        // cliCommand() wraps the path in escapeshellarg() quotes, so the raw
        // command line reads `node 'path/cli.js' link` — a `.*` between the
        // path and "link" absorbs that quote character instead of assuming
        // an exact space.
        exec('pgrep -f ' . escapeshellarg(preg_quote($cliPath, '/').'.*link') . ' 2>&1', $output, $exitCode);

        return $exitCode === 0 && ! empty($output);
    }

    /**
     * Kill any still-running `node cli.js link` process from a previous
     * attempt. Matched on the script's absolute path so this can't touch an
     * unrelated node process on the host.
     */
    private static function killExistingLinkProcess(): void
    {
        $cliPath = base_path('whatsapp/cli.js');

        if (PHP_OS_FAMILY === 'Windows') {
            exec(self::windowsKillLinkProcessCommand($cliPath) . ' 2>&1');
        } else {
            exec('pkill -f ' . escapeshellarg(preg_quote($cliPath, '/').'.*link') . ' 2>&1');
        }
    }

    /**
     * PowerShell command line to list matching PIDs. Uses -like (not WQL LIKE)
     * so a Windows path's backslashes need no special escaping — only the
     * single-quoted string's own quotes matter. The path and "link" are
     * matched as separate wildcard segments since escapeshellarg() quotes
     * the path in the real command line (`node "path\cli.js" link`), so
     * there's a `"` between them rather than a plain space.
     */
    private static function windowsFindLinkProcessCommand(string $cliPath): string
    {
        $pattern = '*'.str_replace("'", "''", $cliPath).'*link*';
        // Restrict to node.exe: the PowerShell command line invoking this very
        // search also contains the pattern text, so without a name filter the
        // search process would (falsely) match itself.
        $script = "(Get-CimInstance Win32_Process | Where-Object { \$_.Name -eq 'node.exe' -and \$_.CommandLine -like '{$pattern}' }).ProcessId";

        return 'powershell -NoProfile -Command "'.str_replace('"', '\\"', $script).'"';
    }

    private static function windowsKillLinkProcessCommand(string $cliPath): string
    {
        $pattern = '*'.str_replace("'", "''", $cliPath).'*link*';
        $script = "Get-CimInstance Win32_Process | Where-Object { \$_.Name -eq 'node.exe' -and \$_.CommandLine -like '{$pattern}' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }";

        return 'powershell -NoProfile -Command "'.str_replace('"', '\\"', $script).'"';
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
