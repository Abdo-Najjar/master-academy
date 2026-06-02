<?php

use App\Support\UserAgentParser;

it('detects Chrome on Windows desktop', function () {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    $parsed = UserAgentParser::parse($ua);

    expect($parsed['browser'])->toBe('Chrome');
    expect($parsed['platform'])->toBe('Windows 10/11');
    expect($parsed['device'])->toBe('Desktop');
});

it('detects Safari on iPhone', function () {
    $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
    $parsed = UserAgentParser::parse($ua);

    expect($parsed['browser'])->toBe('Safari');
    expect($parsed['platform'])->toBe('iOS');
    expect($parsed['device'])->toBe('Mobile');
});

it('detects Firefox on Linux', function () {
    $ua = 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0';
    $parsed = UserAgentParser::parse($ua);

    expect($parsed['browser'])->toBe('Firefox');
    expect($parsed['platform'])->toBe('Linux');
    expect($parsed['device'])->toBe('Desktop');
});

it('handles empty user agent gracefully', function () {
    $parsed = UserAgentParser::parse(null);

    expect($parsed['browser'])->toBe('Unknown');
    expect($parsed['platform'])->toBe('Unknown');
});

it('detects Edge over Chrome (Edg/ comes first)', function () {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
    $parsed = UserAgentParser::parse($ua);

    expect($parsed['browser'])->toBe('Edge');
});

it('detects Android tablet', function () {
    $ua = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    $parsed = UserAgentParser::parse($ua);

    expect($parsed['platform'])->toBe('Android');
    expect($parsed['device'])->toBe('Tablet');
});
