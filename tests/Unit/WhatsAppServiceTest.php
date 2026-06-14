<?php

use App\Services\WhatsAppService;

// ── normalizePhone ─────────────────────────────────────────────────────────

it('keeps a number that already starts with 972', function () {
    expect(WhatsAppService::normalizePhone('972501234567'))->toBe('972501234567');
});

it('strips leading 0 and prepends default country code', function () {
    expect(WhatsAppService::normalizePhone('0501234567'))->toBe('972501234567');
});

it('accepts a custom country code', function () {
    expect(WhatsAppService::normalizePhone('0501234567', '966'))->toBe('966501234567');
});

it('keeps a 966 prefix untouched', function () {
    expect(WhatsAppService::normalizePhone('966501234567'))->toBe('966501234567');
});

it('strips non-digit characters before normalizing', function () {
    expect(WhatsAppService::normalizePhone('+972501234567'))->toBe('972501234567');
});

it('returns empty string for null or empty input', function () {
    expect(WhatsAppService::normalizePhone(null))->toBe('');
    expect(WhatsAppService::normalizePhone(''))->toBe('');
});

// ── buildUrl ───────────────────────────────────────────────────────────────

it('builds a wa.me URL', function () {
    $url = WhatsAppService::buildUrl('0501234567', 'Hello!');
    expect($url)->toStartWith('https://wa.me/');
    expect($url)->toContain('?text=');
});

it('URL-encodes special characters in the message', function () {
    $url = WhatsAppService::buildUrl('0501234567', 'مرحبا & test');
    expect($url)->toContain(urlencode('مرحبا & test'));
});
