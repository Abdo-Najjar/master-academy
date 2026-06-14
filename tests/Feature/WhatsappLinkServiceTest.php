<?php

use App\Models\WhatsappSession;
use App\Services\WhatsappLinkService;

it('startLink creates a WhatsappSession row', function () {
    $session = WhatsappLinkService::startLink();

    expect($session)->toBeInstanceOf(WhatsappSession::class);
    expect($session->status)->toBe(WhatsappSession::STATUS_INITIALIZING);
    expect($session->unique_id)->not->toBeEmpty();
});

it('startLink cleans up stale initializing sessions', function () {
    // Create two stale sessions
    WhatsappSession::create(['unique_id' => 'old-1', 'status' => WhatsappSession::STATUS_INITIALIZING]);
    WhatsappSession::create(['unique_id' => 'old-2', 'status' => WhatsappSession::STATUS_QR_READY]);

    WhatsappLinkService::startLink();

    // Old sessions should be gone (force-deleted)
    expect(WhatsappSession::withTrashed()->where('unique_id', 'old-1')->exists())->toBeFalse();
    expect(WhatsappSession::withTrashed()->where('unique_id', 'old-2')->exists())->toBeFalse();
    expect(WhatsappSession::count())->toBe(1);
});

it('syncStatus updates the session when status file exists', function () {
    // Write a status.json in the real auth_info_baileys directory
    $authDir = base_path('whatsapp/auth_info_baileys');
    @mkdir($authDir, 0777, true);
    $statusFile = $authDir . '/status.json';
    $originalContents = is_file($statusFile) ? file_get_contents($statusFile) : null;

    file_put_contents($statusFile, json_encode([
        'status'     => 'qr_ready',
        'qr_code'    => 'data:image/png;base64,abc',
        'updated_at' => now()->toISOString(),
    ]));

    $session = WhatsappSession::create([
        'unique_id' => 'test-' . uniqid(),
        'status'    => WhatsappSession::STATUS_INITIALIZING,
    ]);

    $updated = WhatsappLinkService::syncStatus($session);

    expect($updated->status)->toBe(WhatsappSession::STATUS_QR_READY);
    expect($updated->qr_code)->toStartWith('data:image');

    // Restore original file state
    if ($originalContents !== null) {
        file_put_contents($statusFile, $originalContents);
    } else {
        @unlink($statusFile);
    }
});

it('logout soft-deletes the session in unit test mode', function () {
    $session = WhatsappSession::create([
        'unique_id' => 'to-delete-' . uniqid(),
        'status'    => WhatsappSession::STATUS_READY,
    ]);

    // In test environment, logout() skips exec() and just deletes
    $result = WhatsappLinkService::logout($session);

    expect($result)->toBeTrue();
    expect(WhatsappSession::find($session->id))->toBeNull();
    expect(WhatsappSession::withTrashed()->find($session->id))->not->toBeNull();
});

it('activeSession returns the linked session', function () {
    WhatsappSession::create([
        'unique_id' => 'ready-' . uniqid(),
        'status'    => WhatsappSession::STATUS_READY,
    ]);

    expect(WhatsappLinkService::activeSession())->not->toBeNull();
    expect(WhatsappLinkService::activeSession()->status)->toBe(WhatsappSession::STATUS_READY);
});

it('activeSession returns null when no linked session', function () {
    expect(WhatsappLinkService::activeSession())->toBeNull();
});

it('readStatusFile returns null when file does not exist', function () {
    expect(WhatsappLinkService::readStatusFile())->toBeNull();
});
